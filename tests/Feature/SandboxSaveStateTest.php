<?php

namespace Tests\Feature;

use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxVersion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SandboxSaveStateTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $owner;
    protected Sandbox $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->sandbox = Sandbox::factory()->ownedBy($this->owner)->create([
            'content' => '<p>Original content</p>',
        ]);
    }

    // ── Content Persistence ─────────────────────────────

    public function test_save_state_persists_simple_html()
    {
        $this->actingAs($this->owner, 'sanctum');
        $content = '<p>Hello <strong>world</strong></p>';

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => $content,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sandboxes', [
            'id' => $this->sandbox->id,
            'content' => $content,
        ]);
    }

    public function test_save_state_preserves_tiptap_elements()
    {
        $this->actingAs($this->owner, 'sanctum');
        $content = '<h1>Title</h1><h2>Subtitle</h2><p>Text with <strong>bold</strong> and <em>italic</em> and <s>strike</s>.</p><blockquote><p>A quote</p></blockquote><pre><code>code block</code></pre><hr><ul><li>item 1</li><li>item 2</li></ul><ol><li>first</li></ol>';

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => $content,
        ]);

        $response->assertOk();
        $saved = Sandbox::find($this->sandbox->id)->content;

        // Key TipTap elements should be preserved
        $this->assertStringContainsString('<h1>', $saved);
        $this->assertStringContainsString('<h2>', $saved);
        $this->assertStringContainsString('<blockquote>', $saved);
        $this->assertStringContainsString('<pre>', $saved);
        $this->assertStringContainsString('<code>', $saved);
        $this->assertStringContainsString('<hr', $saved);
        $this->assertStringContainsString('<s>', $saved);
        $this->assertStringContainsString('<strong>', $saved);
        $this->assertStringContainsString('<em>', $saved);
        $this->assertStringContainsString('<ul>', $saved);
        $this->assertStringContainsString('<ol>', $saved);
    }

    public function test_save_state_preserves_comment_mark_spans()
    {
        $this->actingAs($this->owner, 'sanctum');
        $content = '<p>Some <span class="comment-highlight" data-thread-id="42">highlighted text</span> here</p>';

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => $content,
        ]);

        $response->assertOk();
        $saved = Sandbox::find($this->sandbox->id)->content;

        $this->assertStringContainsString('data-thread-id', $saved);
        $this->assertStringContainsString('comment-highlight', $saved);
    }

    public function test_save_state_strips_script_tags()
    {
        $this->actingAs($this->owner, 'sanctum');
        $content = '<p>Hello</p><script>alert("xss")</script><p>World</p>';

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => $content,
        ]);

        $response->assertOk();
        $saved = Sandbox::find($this->sandbox->id)->content;

        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('alert(', $saved);
        $this->assertStringContainsString('<p>Hello</p>', $saved);
    }

    public function test_save_state_strips_event_handlers()
    {
        $this->actingAs($this->owner, 'sanctum');
        $content = '<p onclick="alert(1)">Click me</p><img src="x" onerror="alert(2)">';

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => $content,
        ]);

        $response->assertOk();
        $saved = Sandbox::find($this->sandbox->id)->content;

        $this->assertStringNotContainsString('onclick', $saved);
        $this->assertStringNotContainsString('onerror', $saved);
    }

    public function test_save_state_strips_iframe_tags()
    {
        $this->actingAs($this->owner, 'sanctum');
        $content = '<p>Before</p><iframe src="https://evil.com"></iframe><p>After</p>';

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => $content,
        ]);

        $response->assertOk();
        $saved = Sandbox::find($this->sandbox->id)->content;

        $this->assertStringNotContainsString('<iframe', $saved);
    }

    // ── Version Snapshots ───────────────────────────────

    public function test_version_snapshot_preserves_tiptap_content()
    {
        $this->actingAs($this->owner, 'sanctum');
        $oldContent = '<h2>Old Title</h2><blockquote><p>Old quote</p></blockquote>';
        $this->sandbox->update(['content' => $oldContent]);

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => '<p>New content</p>',
            'createVersion' => true,
            'versionTitle' => 'Snapshot test',
        ]);

        $response->assertOk();
        $version = SandboxVersion::where('sandbox_id', $this->sandbox->id)
            ->where('title', 'Snapshot test')
            ->first();

        $this->assertNotNull($version);
        $this->assertStringContainsString('<h2>', $version->content);
        $this->assertStringContainsString('<blockquote>', $version->content);
    }

    public function test_version_snapshot_sanitizes_content()
    {
        $this->actingAs($this->owner, 'sanctum');
        $this->sandbox->update(['content' => '<p>Good</p><script>bad()</script>']);

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => '<p>New</p>',
            'createVersion' => true,
        ]);

        $response->assertOk();
        $version = SandboxVersion::where('sandbox_id', $this->sandbox->id)->latest()->first();

        $this->assertStringNotContainsString('<script', $version->content);
    }

    // ── Restore Version ─────────────────────────────────

    public function test_restore_version_preserves_tiptap_content()
    {
        $version = SandboxVersion::factory()->create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
            'content' => '<h1>Restored</h1><pre><code>let x = 1;</code></pre><blockquote><p>Quote</p></blockquote>',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/versions/{$version->id}/restore");

        $response->assertOk();
        $saved = Sandbox::find($this->sandbox->id)->content;

        $this->assertStringContainsString('<h1>Restored</h1>', $saved);
        $this->assertStringContainsString('<pre>', $saved);
        $this->assertStringContainsString('<blockquote>', $saved);
    }

    public function test_restore_version_sanitizes_content()
    {
        $version = SandboxVersion::factory()->create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
            'content' => '<p>Safe</p><script>evil()</script>',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/versions/{$version->id}/restore");

        $response->assertOk();
        $saved = Sandbox::find($this->sandbox->id)->content;

        $this->assertStringContainsString('<p>Safe</p>', $saved);
        $this->assertStringNotContainsString('<script', $saved);
    }

    // ── Role-based Limits ───────────────────────────────

    public function test_max_sandboxes_limit_enforced()
    {
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api'], ['display_name' => 'User']);
        $this->owner->assignRole($role);

        Setting::set('sandbox_role_limits', json_encode([
            'user' => ['max_sandboxes' => 1, 'max_collaborators' => 0, 'max_versions' => 0],
        ]), 'string');

        // Owner already has 1 sandbox from setUp
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/sandbox', [
            'title' => 'Second Sandbox',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => __('messages.sandbox.limit_reached', ['limit' => 1])]);
    }

    public function test_max_sandboxes_zero_means_unlimited()
    {
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api'], ['display_name' => 'User']);
        $this->owner->assignRole($role);

        Setting::set('sandbox_role_limits', json_encode([
            'user' => ['max_sandboxes' => 0, 'max_collaborators' => 0, 'max_versions' => 0],
        ]), 'string');

        // Create several sandboxes
        Sandbox::factory()->count(5)->ownedBy($this->owner)->create();

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/sandbox', [
            'title' => 'Another Sandbox',
        ]);

        $response->assertStatus(201);
    }

    public function test_max_versions_limit_enforced()
    {
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api'], ['display_name' => 'User']);
        $this->owner->assignRole($role);

        Setting::set('sandbox_role_limits', json_encode([
            'user' => ['max_sandboxes' => 0, 'max_collaborators' => 0, 'max_versions' => 2],
        ]), 'string');

        // Create 2 versions already at the limit
        SandboxVersion::factory()->count(2)->create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => '<p>New content</p>',
            'createVersion' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_max_collaborators_limit_enforced()
    {
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api'], ['display_name' => 'User']);
        $this->owner->assignRole($role);

        Setting::set('sandbox_role_limits', json_encode([
            'user' => ['max_sandboxes' => 0, 'max_collaborators' => 1, 'max_versions' => 0],
        ]), 'string');

        // Add one collaborator to reach the limit
        $collab1 = User::factory()->create();
        $this->sandbox->collaborators()->attach($collab1->id, [
            'role' => 'editor',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->actingAs($this->owner, 'sanctum');
        $collab2 = User::factory()->create();

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/collaborators", [
            'user_id' => $collab2->id,
            'role' => 'editor',
        ]);

        $response->assertStatus(403);
    }

    public function test_most_permissive_role_wins()
    {
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api'], ['display_name' => 'User']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api'], ['display_name' => 'Admin']);
        $this->owner->assignRole([$userRole, $adminRole]);

        Setting::set('sandbox_role_limits', json_encode([
            'user' => ['max_sandboxes' => 2, 'max_collaborators' => 0, 'max_versions' => 0],
            'admin' => ['max_sandboxes' => 0, 'max_collaborators' => 0, 'max_versions' => 0],
        ]), 'string');

        // Create many sandboxes — admin role has unlimited, so should pass
        Sandbox::factory()->count(5)->ownedBy($this->owner)->create();

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/sandbox', [
            'title' => 'Should work - admin unlimited',
        ]);

        $response->assertStatus(201);
    }

    // ── Collaborator Removal ────────────────────────────

    public function test_remove_non_collaborator_returns_404()
    {
        $this->actingAs($this->owner, 'sanctum');
        $stranger = User::factory()->create();

        $response = $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/collaborators/{$stranger->id}");

        $response->assertStatus(404);
    }
}
