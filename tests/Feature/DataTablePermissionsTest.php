<?php

namespace Tests\Feature;

use App\Models\Event\EventProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The data table endpoints require the permission their admin screen
 * requires (see resources/js/router/middleware/permission.js). Admins pass
 * everything. Two reads stay open on purpose: the role and permission name
 * lists that several admin screens show, and the event profile form a
 * member loads when answering an event.
 */
class DataTablePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        foreach (['manage-user', 'manage-role', 'manage-page', 'manage-post'] as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api'],
                ['display_name' => ucfirst(str_replace('-', ' ', $permission))]
            );
        }
    }

    private function member(?string $permission = null): User
    {
        $user = User::factory()->create();
        if ($permission !== null) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/datatable/users')->assertUnauthorized();
        $this->getJson('/api/datatable/pages')->assertUnauthorized();
        $this->patchJson('/api/account/profile', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_a_member_without_permissions_reaches_no_table(): void
    {
        $this->actingAs($this->member(), 'sanctum');

        foreach (['users', 'roles', 'permissions', 'pages', 'taxonomies', 'terms', 'posts', 'events', 'event-types', 'event-profiles'] as $table) {
            $this->getJson("/api/datatable/{$table}")->assertForbidden();
        }
    }

    public function test_each_permission_opens_its_own_tables_only(): void
    {
        $cases = [
            'manage-user' => [['users'], ['pages', 'posts', 'permissions']],
            'manage-page' => [['pages', 'taxonomies', 'terms'], ['users', 'posts', 'permissions']],
            'manage-post' => [['posts', 'events', 'event-types', 'event-profiles'], ['users', 'pages', 'permissions']],
            'manage-role' => [['roles', 'permissions'], ['users', 'pages', 'posts']],
        ];

        foreach ($cases as $permission => [$allowed, $denied]) {
            $this->actingAs($this->member($permission), 'sanctum');

            foreach ($allowed as $table) {
                $this->getJson("/api/datatable/{$table}")->assertOk();
            }
            foreach ($denied as $table) {
                $this->getJson("/api/datatable/{$table}")->assertForbidden();
            }
        }
    }

    public function test_an_admin_reaches_every_table(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        foreach (['users', 'roles', 'permissions', 'pages', 'taxonomies', 'terms', 'posts', 'events', 'event-types', 'event-profiles'] as $table) {
            $this->getJson("/api/datatable/{$table}")->assertOk();
        }
    }

    public function test_role_and_permission_names_stay_readable_for_other_admin_screens(): void
    {
        // Moderation, sandbox limits and the forum categories screen list role names
        $this->actingAs($this->member('manage-post'), 'sanctum');
        $this->getJson('/api/datatable/roles')->assertOk();
        $this->getJson('/api/datatable/permissions/roles')->assertOk();
        $this->getJson('/api/datatable/permissions/permissions')->assertOk();

        // Changing them still needs manage-role
        $this->postJson('/api/datatable/permissions/roles', [])->assertForbidden();
        $this->deleteJson('/api/datatable/roles/1')->assertForbidden();
    }

    public function test_members_can_load_the_event_profile_form_but_not_the_table(): void
    {
        $profile = EventProfile::create(['name' => 'Potluck', 'options' => '{"form":[]}']);

        $this->actingAs($this->member(), 'sanctum');
        $this->getJson('/api/datatable/event-profiles/' . $profile->id)->assertOk();
        $this->getJson('/api/datatable/event-profiles')->assertForbidden();
    }

    public function test_members_edit_their_own_profile_without_the_users_table(): void
    {
        $member = $this->member();
        $other  = $this->member();

        $this->actingAs($member, 'sanctum')
            ->patchJson('/api/account/profile', [
                'name'     => 'New Name',
                'username' => 'new-name',
                'email'    => 'new@member.test',
            ])
            ->assertOk();

        $this->assertSame('New Name', $member->fresh()->name);
        $this->assertSame('new@member.test', $member->fresh()->email);

        // Someone else's account is still out of reach
        $this->actingAs($member, 'sanctum')
            ->patchJson('/api/datatable/users/' . $other->id, ['name' => 'Hacked'])
            ->assertForbidden();
        $this->assertNotSame('Hacked', $other->fresh()->name);
    }

    public function test_the_profile_endpoint_validates_the_e_mail(): void
    {
        $taken  = $this->member();
        $member = $this->member();

        $this->actingAs($member, 'sanctum')
            ->patchJson('/api/account/profile', [
                'name'     => 'Someone',
                'username' => 'someone',
                'email'    => $taken->email,
            ])
            ->assertStatus(422);
    }
}
