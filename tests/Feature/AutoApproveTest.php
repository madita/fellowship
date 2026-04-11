<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Setting;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutoApproveTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        Role::create(['name' => 'user', 'guard_name' => 'api', 'display_name' => 'User']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('user');

        // Ensure the wiki_approval ticket type exists (may already be seeded by migration)
        TicketType::firstOrCreate(
            ['slug' => 'wiki_approval'],
            [
                'name' => 'Wiki Approval',
                'description' => 'Auto-created when new wiki pages are submitted',
                'is_active' => true,
                'auto_create' => true,
                'position' => 1,
            ]
        );
    }

    // ─── Approvable Trait: getAutoApproveSettingKey ───

    public function test_get_auto_approve_setting_key_returns_correct_key()
    {
        $this->assertEquals('auto_approve_roles_wiki', Wiki::getAutoApproveSettingKey());
    }

    // ─── Approvable Trait: shouldAutoApprove ───

    public function test_should_auto_approve_returns_false_when_no_setting_exists()
    {
        $wiki = $this->createWikiWithoutEvents();

        $this->actingAs($this->admin, 'sanctum');
        $this->assertFalse($wiki->shouldAutoApprove());
    }

    public function test_should_auto_approve_returns_false_when_roles_array_is_empty()
    {
        Setting::set('auto_approve_roles_wiki', json_encode([]), 'json');

        $wiki = $this->createWikiWithoutEvents();

        $this->actingAs($this->admin, 'sanctum');
        $this->assertFalse($wiki->shouldAutoApprove());
    }

    public function test_should_auto_approve_returns_true_for_matching_role()
    {
        Setting::set('auto_approve_roles_wiki', json_encode(['admin']), 'json');

        $wiki = $this->createWikiWithoutEvents();

        $this->actingAs($this->admin, 'sanctum');
        $this->assertTrue($wiki->shouldAutoApprove());
    }

    public function test_should_auto_approve_returns_false_for_non_matching_role()
    {
        Setting::set('auto_approve_roles_wiki', json_encode(['admin']), 'json');

        $wiki = $this->createWikiWithoutEvents();

        $this->actingAs($this->regularUser, 'sanctum');
        $this->assertFalse($wiki->shouldAutoApprove());
    }

    public function test_should_auto_approve_returns_false_when_no_user_is_authenticated()
    {
        Setting::set('auto_approve_roles_wiki', json_encode(['admin']), 'json');

        $wiki = $this->createWikiWithoutEvents();

        $this->assertFalse($wiki->shouldAutoApprove());
    }

    public function test_should_auto_approve_accepts_explicit_user_parameter()
    {
        Setting::set('auto_approve_roles_wiki', json_encode(['admin']), 'json');

        $wiki = $this->createWikiWithoutEvents();

        // Not authenticated, but pass user explicitly
        $this->assertTrue($wiki->shouldAutoApprove($this->admin));
        $this->assertFalse($wiki->shouldAutoApprove($this->regularUser));
    }

    public function test_should_auto_approve_works_with_multiple_roles()
    {
        Role::create(['name' => 'editor', 'guard_name' => 'api', 'display_name' => 'Editor']);
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        Setting::set('auto_approve_roles_wiki', json_encode(['admin', 'editor']), 'json');

        $wiki = $this->createWikiWithoutEvents();

        $this->assertTrue($wiki->shouldAutoApprove($this->admin));
        $this->assertTrue($wiki->shouldAutoApprove($editor));
        $this->assertFalse($wiki->shouldAutoApprove($this->regularUser));
    }

    // ─── Wiki Boot Hook: auto-approve integration ───

    public function test_wiki_created_by_admin_is_auto_approved_when_setting_enabled()
    {
        Setting::set('auto_approve_roles_wiki', json_encode(['admin']), 'json');

        $this->actingAs($this->admin, 'sanctum');

        $wiki = Wiki::create([
            'title' => 'Admin Wiki Page',
            'slug' => 'admin-wiki-page',
            'status' => 'published',
            'wikiable_type' => 'App\\Models\\User',
            'wikiable_id' => $this->admin->id,
        ]);

        // Should be approved
        $this->assertTrue($wiki->isApproved());

        // Should NOT have an approval ticket
        $this->assertEquals(0, $wiki->tickets()->count());
    }

    public function test_wiki_created_by_regular_user_creates_ticket_when_setting_enabled()
    {
        Setting::set('auto_approve_roles_wiki', json_encode(['admin']), 'json');

        $this->actingAs($this->regularUser, 'sanctum');

        $wiki = Wiki::create([
            'title' => 'User Wiki Page',
            'slug' => 'user-wiki-page',
            'status' => 'published',
            'wikiable_type' => 'App\\Models\\User',
            'wikiable_id' => $this->regularUser->id,
        ]);

        // Should NOT be approved
        $this->assertFalse($wiki->isApproved());

        // Should have an approval ticket
        $this->assertEquals(1, $wiki->tickets()->count());
        $this->assertEquals('wiki_approval', $wiki->tickets->first()->ticketType->slug);
    }

    public function test_wiki_created_by_admin_creates_ticket_when_no_setting()
    {
        // No auto_approve_roles_wiki setting → all creations create tickets
        $this->actingAs($this->admin, 'sanctum');

        $wiki = Wiki::create([
            'title' => 'Admin Wiki No Setting',
            'slug' => 'admin-wiki-no-setting',
            'status' => 'published',
            'wikiable_type' => 'App\\Models\\User',
            'wikiable_id' => $this->admin->id,
        ]);

        $this->assertFalse($wiki->isApproved());
        $this->assertEquals(1, $wiki->tickets()->count());
    }

    public function test_wiki_created_by_admin_creates_ticket_when_roles_empty()
    {
        Setting::set('auto_approve_roles_wiki', json_encode([]), 'json');

        $this->actingAs($this->admin, 'sanctum');

        $wiki = Wiki::create([
            'title' => 'Admin Wiki Empty Roles',
            'slug' => 'admin-wiki-empty-roles',
            'status' => 'published',
            'wikiable_type' => 'App\\Models\\User',
            'wikiable_id' => $this->admin->id,
        ]);

        $this->assertFalse($wiki->isApproved());
        $this->assertEquals(1, $wiki->tickets()->count());
    }

    // ─── Settings API: saving/reading auto-approve roles ───

    public function test_admin_can_save_auto_approve_roles_via_api()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/settings', [
            'settings' => [
                [
                    'key' => 'auto_approve_roles_wiki',
                    'value' => json_encode(['admin']),
                    'type' => 'json',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $roles = Setting::get('auto_approve_roles_wiki', []);
        $this->assertEquals(['admin'], $roles);
    }

    public function test_auto_approve_roles_setting_returns_array()
    {
        Setting::set('auto_approve_roles_wiki', json_encode(['admin', 'editor']), 'json');

        $result = Setting::get('auto_approve_roles_wiki');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('admin', $result);
        $this->assertContains('editor', $result);
    }

    public function test_auto_approve_roles_setting_returns_default_when_not_set()
    {
        $result = Setting::get('auto_approve_roles_wiki', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ─── Helpers ───

    /**
     * Create a wiki without triggering boot events (for unit-testing shouldAutoApprove in isolation).
     */
    private function createWikiWithoutEvents(): Wiki
    {
        Wiki::withoutEvents(function () use (&$wiki) {
            $wiki = Wiki::create([
                'title' => 'Test Wiki',
                'slug' => 'test-wiki-' . uniqid(),
                'status' => 'published',
                'wikiable_type' => 'App\\Models\\User',
                'wikiable_id' => 1,
            ]);
        });

        return $wiki;
    }
}
