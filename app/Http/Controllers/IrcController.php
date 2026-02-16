<?php

namespace App\Http\Controllers;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcMessage;
use App\Models\Irc\IrcServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IrcController extends Controller
{
    /**
     * Get all available IRC servers.
     */
    public function getServers(): JsonResponse
    {
        $servers = IrcServer::active()->get();

        return response()->json($servers);
    }

    /**
     * Get user's IRC connections.
     */
    public function getConnections(): JsonResponse
    {
        $connections = IrcConnection::with(['server', 'channels'])
            ->forUser(Auth::id())
            ->get();

        return response()->json($connections);
    }

    /**
     * Create a new IRC connection.
     */
    public function createConnection(Request $request): JsonResponse
    {
        $request->validate([
            'irc_server_id' => 'required|exists:irc_servers,id',
            'nickname' => 'required|string|max:30',
            'username' => 'nullable|string|max:30',
            'realname' => 'nullable|string|max:100',
            'auto_connect' => 'boolean',
            'auto_join_channels' => 'nullable|array',
        ]);

        $connection = IrcConnection::create([
            'user_id' => Auth::id(),
            'irc_server_id' => $request->irc_server_id,
            'nickname' => $request->nickname,
            'username' => $request->username ?? $request->nickname,
            'realname' => $request->realname ?? $request->nickname,
            'auto_connect' => $request->auto_connect ?? false,
            'auto_join_channels' => $request->auto_join_channels ?? [],
            'status' => 'disconnected',
        ]);

        return response()->json([
            'message' => 'IRC connection created',
            'connection' => $connection->load('server'),
        ], 201);
    }

    /**
     * Update IRC connection.
     */
    public function updateConnection(Request $request, IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $request->validate([
            'nickname' => 'string|max:30',
            'username' => 'nullable|string|max:30',
            'realname' => 'nullable|string|max:100',
            'auto_connect' => 'boolean',
            'auto_join_channels' => 'nullable|array',
        ]);

        $connection->update($request->only([
            'nickname',
            'username',
            'realname',
            'auto_connect',
            'auto_join_channels',
        ]));

        return response()->json([
            'message' => 'Connection updated',
            'connection' => $connection,
        ]);
    }

    /**
     * Delete IRC connection.
     */
    public function deleteConnection(IrcConnection $connection): JsonResponse
    {
        $this->authorize('delete', $connection);

        $connection->delete();

        return response()->json([
            'message' => 'Connection deleted',
        ]);
    }

    /**
     * Connect to IRC server (simulated - would be handled by worker).
     */
    public function connect(IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        // In a real implementation, this would dispatch a job
        // For now, we'll just update the status
        $connection->update([
            'status' => 'connecting',
        ]);

        // Dispatch job to actually connect
        // dispatch(new ConnectToIrc($connection));

        return response()->json([
            'message' => 'Connecting to IRC server...',
            'connection' => $connection,
        ]);
    }

    /**
     * Disconnect from IRC server.
     */
    public function disconnect(IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $connection->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
        ]);

        $connection->channels()->update(['is_joined' => false]);

        return response()->json([
            'message' => 'Disconnected from IRC server',
        ]);
    }

    /**
     * Join a channel.
     */
    public function joinChannel(Request $request, IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $request->validate([
            'channel' => 'required|string|max:50',
        ]);

        $channelName = $request->channel;
        if (!str_starts_with($channelName, '#')) {
            $channelName = '#' . $channelName;
        }

        $channel = IrcChannel::firstOrCreate(
            [
                'irc_connection_id' => $connection->id,
                'name' => $channelName,
            ],
            [
                'is_joined' => true,
                'joined_at' => now(),
            ]
        );

        if (!$channel->is_joined) {
            $channel->update([
                'is_joined' => true,
                'joined_at' => now(),
            ]);
        }

        return response()->json([
            'message' => "Joined {$channelName}",
            'channel' => $channel,
        ]);
    }

    /**
     * Part (leave) a channel.
     */
    public function partChannel(IrcChannel $channel): JsonResponse
    {
        $this->authorize('update', $channel->connection);

        $channel->update([
            'is_joined' => false,
        ]);

        return response()->json([
            'message' => "Left {$channel->name}",
        ]);
    }

    /**
     * Get messages for a channel.
     */
    public function getChannelMessages(IrcChannel $channel, Request $request): JsonResponse
    {
        $this->authorize('view', $channel->connection);

        $limit = $request->get('limit', 100);
        $before = $request->get('before'); // Message ID for pagination

        $query = $channel->messages()->latest('sent_at');

        if ($before) {
            $query->where('id', '<', $before);
        }

        $messages = $query->limit($limit)->get()->reverse()->values();

        // Mark as read
        $channel->markAsRead();

        return response()->json($messages);
    }

    /**
     * Send a message to a channel.
     */
    public function sendMessage(Request $request, IrcChannel $channel): JsonResponse
    {
        $this->authorize('update', $channel->connection);

        $request->validate([
            'message' => 'required|string',
            'emotion' => 'nullable|string',
            'gesture' => 'nullable|string',
            'bubble_type' => 'nullable|string',
        ]);

        $message = IrcMessage::create([
            'irc_channel_id' => $channel->id,
            'irc_connection_id' => $channel->irc_connection_id,
            'type' => 'message',
            'from_nick' => $channel->connection->nickname,
            'message' => $request->message,
            'emotion' => $request->emotion ?? 'normal',
            'gesture' => $request->gesture ?? 'none',
            'bubble_type' => $request->bubble_type ?? 'speech',
            'sent_at' => now(),
        ]);

        // In real implementation, this would send to actual IRC server
        // For now, it's stored and broadcast via WebSocket

        return response()->json([
            'message' => 'Message sent',
            'data' => $message,
        ], 201);
    }

    /**
     * Get channel list for a server (simulated).
     */
    public function getServerChannels(IrcConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        // In real implementation, this would query the IRC server
        // For now, return user's channels
        $channels = $connection->channels;

        return response()->json($channels);
    }

    /**
     * Toggle favorite channel.
     */
    public function toggleFavorite(IrcChannel $channel): JsonResponse
    {
        $this->authorize('update', $channel->connection);

        $channel->update([
            'is_favorite' => !$channel->is_favorite,
        ]);

        return response()->json([
            'is_favorite' => $channel->is_favorite,
        ]);
    }

    /**
     * Get unread count across all channels.
     */
    public function getUnreadCount(IrcConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        $unreadCount = $connection->channels()->sum('unread_count');

        return response()->json([
            'unread_count' => $unreadCount,
        ]);
    }
}
