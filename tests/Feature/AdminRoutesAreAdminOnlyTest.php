<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Everything under /api/admin has to ask for an admin.
 *
 * The group itself is declared with auth:sanctum only, and each route is
 * expected to add the admin middleware for itself — which is easy to forget,
 * and a forgotten one lets any signed-in member run it. This walks the route
 * table so a new route cannot quietly miss the guard.
 */
class AdminRoutesAreAdminOnlyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Middleware that establishes the caller is more than a signed-in
     * member — by class, and by the alias a route may name it with.
     */
    private const GUARDS = [
        'App\Http\Middleware\EnsureUserIsAdmin',
        'App\Http\Middleware\EnsureUserHasPermission',
        'Spatie\Permission\Middleware\RoleMiddleware',
        'Spatie\Permission\Middleware\PermissionMiddleware',
        'Spatie\Permission\Middleware\RoleOrPermissionMiddleware',
        'admin',
        'role',
        'permission',
        'role_or_permission',
        'permission.any',
    ];

    /**
     * Routes that are deliberately open to any signed-in member, with why.
     */
    private const ALLOWED = [
        // none yet
    ];

    public function test_every_admin_route_asks_for_more_than_a_signed_in_member(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/admin')) {
                continue;
            }

            if (in_array($route->uri(), self::ALLOWED, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $guarded = collect($middleware)->contains(
                fn ($name) => collect(self::GUARDS)->contains(fn ($guard) => str_starts_with((string) $name, $guard))
            );

            if (! $guarded) {
                $unguarded[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        $this->assertSame([], array_values(array_unique($unguarded)), implode("\n", [
            'These /api/admin routes are reachable by any signed-in member:',
            ...array_unique($unguarded),
        ]));
    }

    public function test_a_plain_member_cannot_rewrite_the_homepage_or_announcements(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/admin/homepage/sections')->assertForbidden();
        $this->actingAs($member, 'sanctum')->getJson('/api/admin/footer/widgets')->assertForbidden();
        $this->actingAs($member, 'sanctum')->getJson('/api/admin/announcement')->assertForbidden();
        $this->actingAs($member, 'sanctum')->getJson('/api/admin/model-translations/stats')->assertForbidden();
    }
}
