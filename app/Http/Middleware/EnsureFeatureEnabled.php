<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request for a feature an admin has switched off.
 *
 * The toggles under Admin → Settings → Features hide a feature from the
 * menus and block its pages in the router, but a hidden route is not a
 * closed door: anyone can still call the API by hand. This is the door.
 *
 * Used as `feature.enabled:podcast`, matching the
 * `feature_<key>_enabled` settings the toggle screen writes. A missing
 * setting means on, the same reading the client uses, so an install that
 * predates a feature keeps it.
 *
 * The generic form of EnsureSandboxEnabled, which predates the registry
 * and keeps its own setting key.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $enabled = Setting::get("feature_{$feature}_enabled", true);

        if ( ! filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'message' => __('messages.error.feature_disabled'),
            ], 403);
        }

        return $next($request);
    }
}
