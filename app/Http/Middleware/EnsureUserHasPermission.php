<?php

namespace App\Http\Middleware;

use App\Support\FeaturePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets admins through, and anyone holding one of the given permissions:
 *
 *   Route::middleware('permission.any:manage-page,manage-post')
 *
 * Permissions are matched by name, the way /api/user reports them and the
 * admin screens check them, so the API mirrors what the UI already allows.
 *
 * Holding a feature whole counts as holding each action it is split
 * into, so a route asking for `create-podcast` lets `manage-podcast`
 * through. That is the one place the umbrella is applied for the whole
 * API - without it, every gate would have to name both, and the day
 * somebody forgot the second name an admin's delegate would quietly
 * lose a screen they used to have.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        $held = $user ? $user->getAllPermissions()->pluck('name')->all() : [];

        foreach ($permissions as $wanted) {
            if (FeaturePermissions::satisfies($wanted, $held)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => __('messages.error.permission_required'),
        ], 403);
    }
}
