<?php

namespace App\Http\Controllers;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcMessage;
use App\Models\Irc\IrcServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

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
     * Connect to IRC server via the IRC daemon.
     */
    public function connect(IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $connection->update(['status' => 'connecting']);

        Redis::rpush('irc:commands', json_encode([
            'type' => 'connect',
            'connection_id' => $connection->id,
        ]));

        return response()->json([
            'message' => 'Connecting to IRC server...',
            'connection' => $connection->fresh('server'),
        ]);
    }

    /**
     * Disconnect from IRC server via the IRC daemon.
     */
    public function disconnect(IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        Redis::rpush('irc:commands', json_encode([
            'type' => 'disconnect',
            'connection_id' => $connection->id,
            'message' => 'Leaving',
        ]));

        return response()->json([
            'message' => 'Disconnecting from IRC server...',
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
        if (! str_starts_with($channelName, '#')) {
            $channelName = '#'.$channelName;
        }

        $channel = IrcChannel::firstOrCreate(
            [
                'irc_connection_id' => $connection->id,
                'name' => $channelName,
            ],
            [
                'is_joined' => false,
                'joined_at' => now(),
            ]
        );

        // Send JOIN command to IRC daemon
        Redis::rpush('irc:commands', json_encode([
            'type' => 'join',
            'connection_id' => $connection->id,
            'channel' => $channelName,
        ]));

        return response()->json([
            'message' => "Joining {$channelName}...",
            'channel' => $channel,
        ]);
    }

    /**
     * Part (leave) a channel.
     */
    public function partChannel(IrcChannel $channel): JsonResponse
    {
        $this->authorize('update', $channel->connection);

        Redis::rpush('irc:commands', json_encode([
            'type' => 'part',
            'connection_id' => $channel->irc_connection_id,
            'channel' => $channel->name,
        ]));

        return response()->json([
            'message' => "Leaving {$channel->name}...",
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

        // Get the latest N messages, then sort chronologically
        $messages = $channel->messages()
            ->orderBy('sent_at', 'desc')
            ->orderBy('id', 'desc')
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->limit($limit)
            ->get()
            ->sortBy(['sent_at', 'id'])
            ->values();

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
            'type' => 'nullable|string|in:message,action',
            'emotion' => 'nullable|string',
            'gesture' => 'nullable|string',
            'bubble_type' => 'nullable|string',
        ]);

        $msgType = $request->type ?? 'message';

        $message = IrcMessage::create([
            'irc_channel_id' => $channel->id,
            'irc_connection_id' => $channel->irc_connection_id,
            'type' => $msgType,
            'from_nick' => $channel->connection->nickname,
            'message' => $request->message,
            'emotion' => $request->emotion ?? 'normal',
            'gesture' => $request->gesture ?? 'none',
            'bubble_type' => $request->bubble_type ?? 'speech',
            'sent_at' => now(),
        ]);

        // Send to actual IRC server via daemon
        Redis::rpush('irc:commands', json_encode([
            'type' => 'send',
            'connection_id' => $channel->irc_connection_id,
            'target' => $channel->name,
            'message' => $request->message,
            'msg_type' => $msgType,
        ]));

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
            'is_favorite' => ! $channel->is_favorite,
        ]);

        return response()->json([
            'is_favorite' => $channel->is_favorite,
        ]);
    }

    /**
     * Get users in a channel.
     */
    public function getChannelUsers(IrcChannel $channel): JsonResponse
    {
        $this->authorize('view', $channel->connection);

        // Read from Redis (populated by the IRC daemon)
        $redisKey = "irc:channel_users:{$channel->id}";
        $cached = Redis::get($redisKey);

        if ($cached) {
            return response()->json(json_decode($cached, true));
        }

        // Fallback: show current connection's nick
        $connection = $channel->connection;

        return response()->json([
            [
                'nickname' => $connection->nickname,
                'isOnline' => $connection->status === 'connected',
                'isOp' => false,
                'isVoice' => false,
                'prefix' => '',
            ],
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

    /**
     * Poll for IRC events (status changes, messages, user updates).
     */
    public function pollEvents(): JsonResponse
    {
        $userId = Auth::id();
        $key = "irc:events:{$userId}";

        // Get all pending events and clear them
        $events = Redis::lrange($key, 0, -1);
        Redis::del($key);

        $decoded = array_map(fn ($e) => json_decode($e, true), $events);

        return response()->json($decoded);
    }

    /**
     * Send a NICK change via the IRC daemon.
     */
    public function changeNick(Request $request, IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $request->validate([
            'nickname' => 'required|string|max:30',
        ]);

        Redis::rpush('irc:commands', json_encode([
            'type' => 'nick',
            'connection_id' => $connection->id,
            'nickname' => $request->nickname,
        ]));

        return response()->json([
            'message' => 'Changing nickname...',
        ]);
    }

    /**
     * Send a private message or open a PM channel.
     */
    public function sendPrivateMessage(Request $request, IrcConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $request->validate([
            'nick' => 'required|string|max:30',
            'message' => 'nullable|string',
        ]);

        $nick = $request->nick;

        // Create or find the PM channel
        $channel = IrcChannel::firstOrCreate(
            [
                'irc_connection_id' => $connection->id,
                'name' => $nick,
            ],
            [
                'is_joined' => true,
                'is_private' => true,
                'joined_at' => now(),
            ]
        );

        if ($request->message) {
            // Store the outgoing message
            IrcMessage::create([
                'irc_channel_id' => $channel->id,
                'irc_connection_id' => $connection->id,
                'type' => 'message',
                'from_nick' => $connection->nickname,
                'message' => $request->message,
                'is_private' => true,
                'sent_at' => now(),
            ]);

            // Send via IRC daemon
            Redis::rpush('irc:commands', json_encode([
                'type' => 'send',
                'connection_id' => $connection->id,
                'target' => $nick,
                'message' => $request->message,
                'msg_type' => 'message',
            ]));
        }

        return response()->json([
            'message' => $request->message ? "Message sent to {$nick}" : "Opened chat with {$nick}",
            'channel' => $channel,
        ]);
    }
}
