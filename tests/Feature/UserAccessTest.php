<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Handing a member a role or a permission, from the members table or
 * from their profile - and the rules that stop it being a way to hand
 * yourself more than you were given.
 */
class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsTableSeeder::class);
        $this->seed(RolesTableSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->member = User::factory()->create();
    }

    public function test_an_admin_sees_every_role_and_permission_there_is(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users/access/catalogue')
            ->assertOk();

        $this->assertTrue(collect($response->json('roles'))->pluck('name')->contains('admin'));
        $this->assertTrue(collect($response->json('permissions'))->pluck('name')->contains('manage-forum'));

        // Grouped, because a list of nearly thirty is unreadable flat
        $this->assertArrayHasKey('community', $response->json('categories'));

        // Each row says what it is for
        $forum = collect($response->json('permissions'))->firstWhere('name', 'manage-forum');
        $this->assertSame('community', $forum['category']);
        $this->assertNotEmpty($forum['description']);

        $this->assertTrue($response->json('may_grant_admin'));
    }

    public function test_handing_over_a_permission_lets_the_member_in(): void
    {
        $this->actingAs($this->member, 'sanctum')->getJson('/api/admin/ranks')->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => [],
                'permissions' => ['manage-rank'],
            ])
            ->assertOk()
            ->assertJsonPath('permissions', ['manage-rank']);

        $this->actingAs($this->member->fresh(), 'sanctum')->getJson('/api/admin/ranks')->assertOk();
    }

    public function test_a_role_brings_its_permissions_and_says_where_they_came_from(): void
    {
        $role = Role::create(['name' => 'moderator', 'guard_name' => 'api', 'display_name' => 'Moderator']);
        $role->givePermissionTo(['manage-forum', 'manage-ticket']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => ['moderator'],
                'permissions' => [],
            ])
            ->assertOk();

        // Nothing was handed over directly…
        $this->assertSame([], $response->json('permissions'));
        // …and they can do both things anyway
        $this->assertEqualsCanonicalizing(['manage-forum', 'manage-ticket'], $response->json('effective'));
        // …which the screen can say out loud, so a tick nobody put there
        // by hand is not offered to be unticked
        $this->assertSame(['Moderator'], $response->json('from_roles.manage-forum'));

        $this->actingAs($this->member->fresh(), 'sanctum')->getJson('/api/admin/tickets/stats')->assertOk();
    }

    public function test_taking_it_away_closes_the_door_again(): void
    {
        $this->member->givePermissionTo('manage-rank');
        $this->actingAs($this->member->fresh(), 'sanctum')->getJson('/api/admin/ranks')->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => [],
                'permissions' => [],
            ])
            ->assertOk();

        $this->actingAs($this->member->fresh(), 'sanctum')->getJson('/api/admin/ranks')->assertForbidden();
    }

    public function test_nobody_edits_their_own_access(): void
    {
        // Not even an admin. Without the rule, the one admin who can be
        // talked into it is the way in - and an admin loses nothing by
        // asking another admin.
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->admin->id}/access", [
                'roles'       => ['admin'],
                'permissions' => ['manage-forum'],
            ])
            ->assertForbidden();

        $this->assertSame([], $this->admin->fresh()->permissions->pluck('name')->all());
    }

    public function test_only_an_admin_hands_out_the_admin_role(): void
    {
        $delegate = User::factory()->create();
        $delegate->givePermissionTo('manage-user');

        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => ['admin'],
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertFalse($this->member->fresh()->hasRole('admin'));

        // …nor take it off somebody
        $other = User::factory()->create();
        $other->assignRole('admin');

        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$other->id}/access", [
                'roles'       => [],
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertTrue($other->fresh()->hasRole('admin'));
    }

    public function test_nobody_hands_out_what_they_do_not_hold(): void
    {
        // Whoever looks after the members can hand out the forum if they
        // look after the forum too - and cannot make somebody a
        // translator, which is the point of having been given only these
        $delegate = User::factory()->create();
        $delegate->givePermissionTo(['manage-user', 'manage-forum']);

        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => [],
                'permissions' => ['manage-forum'],
            ])
            ->assertOk();

        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => [],
                'permissions' => ['manage-forum', 'manage-translation'],
            ])
            ->assertForbidden();

        $this->assertSame(['manage-forum'], $this->member->fresh()->permissions->pluck('name')->all());
    }

    public function test_nor_takes_away_what_they_do_not_hold(): void
    {
        $delegate = User::factory()->create();
        $delegate->givePermissionTo(['manage-user', 'manage-forum']);

        $this->member->givePermissionTo(['manage-forum', 'manage-translation']);

        // Taking away counts: otherwise the forum's delegate could
        // quietly strip the translator
        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => [],
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertEqualsCanonicalizing(
            ['manage-forum', 'manage-translation'],
            $this->member->fresh()->permissions->pluck('name')->all()
        );
    }

    public function test_a_role_cannot_be_used_to_smuggle_a_permission_out(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'api', 'display_name' => 'Editor']);
        $role->givePermissionTo(['manage-forum', 'manage-translation']);

        $delegate = User::factory()->create();
        $delegate->givePermissionTo(['manage-user', 'manage-forum']);

        // Handing out a role hands out everything in it, so the rule has
        // to look inside it
        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => ['editor'],
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertFalse($this->member->fresh()->hasRole('editor'));
    }

    public function test_the_last_admin_keeps_the_role(): void
    {
        $other = User::factory()->create();
        $other->assignRole('admin');

        // Two admins: one of them may be stood down
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$other->id}/access", ['roles' => [], 'permissions' => []])
            ->assertOk();

        // One left, and a site nobody can administer cannot be put right
        // from inside it
        $this->actingAs($other->fresh(), 'sanctum')
            ->putJson("/api/admin/users/{$this->admin->id}/access", ['roles' => [], 'permissions' => []])
            ->assertForbidden();

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_a_member_cannot_hand_anything_to_anybody(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson("/api/admin/users/{$this->admin->id}/access")
            ->assertForbidden();

        $this->actingAs($this->member, 'sanctum')
            ->putJson("/api/admin/users/{$this->admin->id}/access", ['roles' => [], 'permissions' => []])
            ->assertForbidden();

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/admin/users/access/catalogue')
            ->assertForbidden();
    }

    public function test_an_unknown_role_or_permission_is_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => ['sorcerer'],
                'permissions' => [],
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->member->id}/access", [
                'roles'       => [],
                'permissions' => ['manage-everything'],
            ])
            ->assertStatus(422);
    }

    public function test_the_catalogue_offers_a_delegate_only_what_they_hold(): void
    {
        $delegate = User::factory()->create();
        $delegate->givePermissionTo(['manage-user', 'manage-forum']);

        $response = $this->actingAs($delegate, 'sanctum')
            ->getJson("/api/admin/users/{$this->member->id}/access")
            ->assertOk();

        // The two they hold, and the parts of the forum that holding it
        // whole answers for: somebody given manage-forum can do all of
        // this themselves, so being unable to pass any of it on would
        // leave the split with nobody able to use it
        $this->assertEqualsCanonicalizing(
            ['manage-user', 'manage-forum', 'view-forum', 'create-forum', 'edit-forum', 'delete-forum'],
            $response->json('grantable')
        );

        // …and not another feature's parts
        $this->assertNotContains('create-wiki', $response->json('grantable'));
        $this->assertFalse($response->json('may_grant_admin'));

        // The whole list is still shown - what cannot be handed out is
        // shown as something they cannot hand out, not hidden, so it is
        // clear who to ask
        $catalogue = $this->actingAs($delegate, 'sanctum')
            ->getJson('/api/admin/users/access/catalogue')
            ->assertOk();

        $this->assertTrue(collect($catalogue->json('permissions'))->pluck('name')->contains('manage-translation'));
    }
}
