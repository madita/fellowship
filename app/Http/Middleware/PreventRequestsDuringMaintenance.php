<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array
     */
    protected $except = [
        'api/login',
        'api/logout',
        'api/admin/*',
        'sanctum/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     */
    public function handle($request, Closure $next): Response
    {
        // First check database-based maintenance mode setting
        try {
            $maintenanceMode = Setting::get('maintenance_mode', false);

            if ($maintenanceMode) {
                // Allow admin users to bypass maintenance mode
                if ($request->user() && $request->user()->hasRole('admin')) {
                    return $next($request);
                }

                // Check if current URI should be excluded
                foreach ($this->except as $except) {
                    if ($request->is($except)) {
                        return $next($request);
                    }
                }

                // Return maintenance mode response
                return response()->json([
                    'message' => Setting::get('maintenance_message', 'Application is currently under maintenance. Please check back soon.'),
                ], 503);
            }
        } catch (\Exception $e) {
            // If database is not available or settings table doesn't exist yet,
            // fall through to check file-based maintenance mode
        }

        // Also check Laravel's file-based maintenance mode as fallback
        return parent::handle($request, $next);
    }
}
