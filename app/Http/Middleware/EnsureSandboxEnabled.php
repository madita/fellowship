<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSandboxEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = Setting::get('sandbox_enabled', true);

        if (! filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'message' => 'The sandbox feature is currently disabled.',
            ], 403);
        }

        return $next($request);
    }
}
