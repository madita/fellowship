<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\FeaturePermissions;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A permission per feature: that the set is complete, that the admin
 * routes are actually behind them, and that handing one out does what it
 * says - without being a way to hand yourself more.
 */
class FeaturePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsTableSeeder::class);
        $this->seed(RolesTableSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_every_feature_has_a_permission_in_the_database(): void
    {
        $seeded = Permission::where('guard_name', 'api')->pluck('name');

        foreach (FeaturePermissions::names() as $name) {
            $this->assertTrue($seeded->contains($name), "{$name} was not seeded");
        }

        // Each of them says what it is and what it is for: the screen
        // that hands them out is a list of nearly thirty rows, and a row
        // with no words on it cannot be handed out by anybody
        foreach (Permission::where('guard_name', 'api')->get() as $permission) {
            $this->assertNotEmpty($permission->display_name, "{$permission->name} has no name");
            $this->assertNotEmpty($permission->category, "{$permission->name} has no category");
        }
    }

    public function test_every_admin_route_is_behind_a_feature_permission(): void
    {
        $ungated = [];

        foreach (Route::getRoutes() as $route) {
            if ( ! str_starts_with($route->uri(), 'api/admin')) {
                continue;
            }

            $gated = collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission.any'));

            if ( ! $gated) {
                $ungated[] = $route->uri();
            }
        }

        // An admin route with no permission on it is a door only the
        // admin role opens, which is the thing this set exists to undo
        $this->assertSame([], array_values(array_unique($ungated)));
    }

    public function test_every_gate_names_a_permission_that_exists(): void
    {
        $known = Permission::where('guard_name', 'api')->pluck('name')->all();
        $unknown = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if ( ! is_string($middleware) || ! str_starts_with($middleware, 'permission.any:')) {
                    continue;
                }

                foreach (explode(',', substr($middleware, strlen('permission.any:'))) as $name) {
                    if ( ! in_array($name, $known, true)) {
                        $unknown[] = $route->uri() . ' => ' . $name;
                    }
                }
            }
        }

        // A gate on a name nothing seeded refuses everybody but an admin,
        // quietly and for ever
        $this->assertSame([], array_values(array_unique($unknown)));
    }

    public function test_a_feature_permission_opens_only_its_own_feature(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo('manage-translation');

        // What they were given
        $this->actingAs($editor, 'sanctum')
            ->getJson('/api/admin/translations/locales')
            ->assertOk();

        // …and nothing else
        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/ranks')->assertForbidden();
        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/media')->assertForbidden();
        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/settings')->assertForbidden();
    }

    public function test_an_admin_still_opens_everything(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/ranks')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/translations/locales')->assertOk();
    }

    public function test_a_member_with_nothing_opens_nothing(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/admin/ranks')->assertForbidden();
        $this->actingAs($member, 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_a_feature_permission_is_an_ability_too(): void
    {
        $moderator = User::factory()->create();
        $moderator->givePermissionTo('manage-forum');

        // So a controller can ask for it with authorize(), which is how
        // the forum asks before it lets a category be made
        $this->assertTrue($moderator->can('manage-forum'));
        $this->assertFalse($moderator->can('manage-translation'));

        // And an admin holds all of them whatever the row says
        $this->assertTrue($this->admin->can('manage-forum'));
        $this->assertTrue($this->admin->can('manage-translation'));
    }

    public function test_the_coarse_permissions_no_longer_stand_for_everything(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo('manage-page');

        // manage-page used to be the key to the migration tool, the
        // translations, the menus and the media library
        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/media')->assertForbidden();
        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/translations/locales')->assertForbidden();
        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/menus')->assertForbidden();
    }

    public function test_a_role_that_held_the_coarse_permission_keeps_what_it_could_reach(): void
    {
        // The upgrade path: a moderator role made before the split held
        // manage-page, and reached the forum and the wiki with it. The
        // migration hands it the permissions carved out of that one, so
        // nothing is lost on the day this lands.
        $role = Role::create(['name' => 'moderator', 'guard_name' => 'api', 'display_name' => 'Moderator']);
        $role->givePermissionTo('manage-page');

        // Run the migration itself: the suite's database was built
        // before this role existed, so migrating again is a no-op
        (require database_path('migrations/2026_11_05_000001_add_feature_permissions.php'))->up();

        $role->refresh();
        $held = $role->permissions->pluck('name');

        foreach (FeaturePermissions::inherited() as $name => $inherits) {
            if ($inherits !== 'manage-page') {
                continue;
            }

            $this->assertTrue($held->contains($name), "moderator did not inherit {$name}");
        }

        // And not the ones that were never manage-page's to give
        $this->assertFalse($held->contains('manage-rank'));
        $this->assertFalse($held->contains('manage-user'));
    }
}
