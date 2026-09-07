<?php

namespace App\Http\Controllers\Sandbox;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SandboxStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Check Yjs WebSocket server status.
     */
    public function websocketStatus(): JsonResponse
    {
        $wsHost = config('sandbox.yjs_host', '127.0.0.1');
        $wsPort = (int) config('sandbox.yjs_port', 1234);
        $timeout = 2; // seconds

        $status = [
            'host' => $wsHost,
            'port' => $wsPort,
            'connected' => false,
            'latency_ms' => null,
            'error' => null,
        ];

        $start = microtime(true);

        try {
            $socket = @fsockopen($wsHost, $wsPort, $errno, $errstr, $timeout);

            if ($socket) {
                $status['connected'] = true;
                $status['latency_ms'] = round((microtime(true) - $start) * 1000, 1);
                fclose($socket);
            } else {
                $status['error'] = $errstr ?: 'Connection refused';
            }
        } catch (\Throwable $e) {
            $status['error'] = $e->getMessage();
        }

        return response()->json($status, $status['connected'] ? 200 : 503);
    }

    /**
     * Overall sandbox system status.
     */
    public function status(): JsonResponse
    {
        $wsHost = config('sandbox.yjs_host', '127.0.0.1');
        $wsPort = (int) config('sandbox.yjs_port', 1234);

        // Check WebSocket server
        $wsConnected = false;
        $wsLatency = null;
        $wsError = null;

        $start = microtime(true);
        try {
            $socket = @fsockopen($wsHost, $wsPort, $errno, $errstr, 2);
            if ($socket) {
                $wsConnected = true;
                $wsLatency = round((microtime(true) - $start) * 1000, 1);
                fclose($socket);
            } else {
                $wsError = $errstr ?: 'Connection refused';
            }
        } catch (\Throwable $e) {
            $wsError = $e->getMessage();
        }

        // Database stats
        $sandboxCount = \App\Models\Sandbox\Sandbox::count();
        $activeSandboxes = \App\Models\Sandbox\Sandbox::where('last_edited_at', '>=', now()->subDay())->count();
        $versionCount = \App\Models\Sandbox\SandboxVersion::count();
        $collaboratorCount = \Illuminate\Support\Facades\DB::table('sandbox_collaborators')->count();

        return response()->json([
            'websocket' => [
                'host' => $wsHost,
                'port' => $wsPort,
                'connected' => $wsConnected,
                'latency_ms' => $wsLatency,
                'error' => $wsError,
            ],
            'stats' => [
                'total_sandboxes' => $sandboxCount,
                'active_last_24h' => $activeSandboxes,
                'total_versions' => $versionCount,
                'total_collaborators' => $collaboratorCount,
            ],
        ]);
    }
}
