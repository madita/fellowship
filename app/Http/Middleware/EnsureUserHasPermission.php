<?php

namespace App\Http\Middleware;

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
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        $held = $user ? $user->getAllPermissions()->pluck('name') : collect();

        if ($held->intersect($permissions)->isEmpty()) {
            return response()->json([
                'message' => __('messages.error.permission_required'),
            ], 403);
        }

        return $next($request);
    }
}
