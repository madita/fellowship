<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcServer;
use App\Services\Irc\IrcConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class IrcAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    // --- Servers ---

    public function getServers(): JsonResponse
    {
        $servers = IrcServer::withCount('connections')
            ->orderBy('order')
            ->get()
            ->map(function ($server) {
                // Probing inline would block the request up to 5s per dead
                // server — reuse the last checkServer() result instead.
                // null means "not checked yet".
                $server->is_reachable = Cache::get($this->reachableCacheKey($server));
                return $server;
            });

        return response()->json($servers);
    }

    public function storeServer(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'use_ssl' => 'boolean',
            'password' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        $server = IrcServer::create($request->all());

        return response()->json($server, 201);
    }

    public function updateServer(Request $request, IrcServer $server): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'host' => 'string|max:255',
            'port' => 'integer|min:1|max:65535',
            'use_ssl' => 'boolean',
            'password' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        $server->update($request->all());

        return response()->json($server);
    }

    public function deleteServer(IrcServer $server): JsonResponse
    {
        if ($server->connections()->where('status', 'connected')->exists()) {
            return response()->json([
                'message' => 'Cannot delete server with active connections',
            ], 422);
        }

        $server->delete();

        return response()->json(['message' => 'Server deleted']);
    }

    public function checkServer(IrcServer $server): JsonResponse
    {
        $reachable = IrcConnectionManager::isReachable($server);
        Cache::put($this->reachableCacheKey($server), $reachable, now()->addMinutes(10));

        return response()->json([
            'is_reachable' => $reachable,
            'host' => $server->host,
            'port' => $server->port,
        ]);
    }

    private function reachableCacheKey(IrcServer $server): string
    {
        return "irc:server_reachable:{$server->id}";
    }

    // --- Connections ---

    public function getConnections(): JsonResponse
    {
        $connections = IrcConnection::with(['server', 'user:id,name,username'])
            ->withCount('channels')
            ->latest()
            ->get();

        return response()->json($connections);
    }

    public function disconnectConnection(IrcConnection $connection): JsonResponse
    {
        Redis::rpush('irc:commands', json_encode([
            'type' => 'disconnect',
            'connection_id' => $connection->id,
            'message' => 'Disconnected by admin',
        ]));

        return response()->json(['message' => 'Disconnect command sent']);
    }

    public function deleteConnection(IrcConnection $connection): JsonResponse
    {
        if ($connection->status === 'connected') {
            Redis::rpush('irc:commands', json_encode([
                'type' => 'disconnect',
                'connection_id' => $connection->id,
                'message' => 'Deleted by admin',
            ]));
        }

        $connection->delete();

        return response()->json(['message' => 'Connection deleted']);
    }

    // --- Daemon Status ---

    public function getDaemonStatus(): JsonResponse
    {
        $activeConnections = IrcConnection::where('status', 'connected')->count();
        $connectingCount = IrcConnection::where('status', 'connecting')->count();
        $totalConnections = IrcConnection::count();
        $totalChannels = IrcChannel::where('is_joined', true)->count();
        $totalServers = IrcServer::where('is_active', true)->count();

        // Check if daemon is responsive by looking at Redis command queue length
        $pendingCommands = Redis::llen('irc:commands') ?: 0;

        // Check daemon heartbeat (we'll set this in the daemon)
        $lastHeartbeat = Redis::get('irc:daemon:heartbeat');
        $daemonRunning = $lastHeartbeat && (time() - (int) $lastHeartbeat) < 15;

        return response()->json([
            'daemon_running' => $daemonRunning,
            'last_heartbeat' => $lastHeartbeat ? date('Y-m-d H:i:s', (int) $lastHeartbeat) : null,
            'active_connections' => $activeConnections,
            'connecting_count' => $connectingCount,
            'total_connections' => $totalConnections,
            'joined_channels' => $totalChannels,
            'active_servers' => $totalServers,
            'pending_commands' => $pendingCommands,
        ]);
    }

    // --- Stats ---

    public function getStats(): JsonResponse
    {
        $totalMessages = \App\Models\Irc\IrcMessage::count();
        $todayMessages = \App\Models\Irc\IrcMessage::whereDate('sent_at', today())->count();
        $uniqueUsers = IrcConnection::distinct('user_id')->count();
        $serverStats = IrcServer::withCount(['connections' => function ($q) {
            $q->where('status', 'connected');
        }])->where('is_active', true)->get();

        return response()->json([
            'total_messages' => $totalMessages,
            'today_messages' => $todayMessages,
            'unique_users' => $uniqueUsers,
            'server_stats' => $serverStats,
        ]);
    }

}
