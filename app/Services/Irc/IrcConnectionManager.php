<?php

namespace App\Services\Irc;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcMessage;
use App\Models\Irc\IrcServer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class IrcConnectionManager
{
    /** @var array<int, resource> Active socket connections keyed by connection ID */
    private array $sockets = [];

    /** @var array<int, string> Read buffers for partial line reads */
    private array $buffers = [];

    /** @var array<int, IrcConnection> Connection models */
    private array $connections = [];

    /** @var array<int, array<string, array>> Channel user lists: [connectionId => [channelName => [nick, ...]]] */
    private array $channelUsers = [];

    /** @var array<int, array<string, array>> Channel user modes: [connectionId => [channelName => [nick => prefix]]] */
    private array $channelUserModes = [];

    /** @var callable|null Callback for broadcasting events */
    private $broadcastCallback = null;

    /** @var callable|null Callback for console output */
    private $outputCallback = null;

    public function setBroadcastCallback(callable $callback): void
    {
        $this->broadcastCallback = $callback;
    }

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    private function info(string $message): void
    {
        Log::info("[IRC] {$message}");
        if ($this->outputCallback) {
            ($this->outputCallback)($message);
        }
    }

    /**
     * Whether the IRC daemon (irc:daemon) is alive, based on its Redis
     * heartbeat. Commands queued while it is down are consumed only after a
     * restart — callers should fall back to direct state changes instead of
     * queueing into the void.
     */
    public static function isDaemonRunning(): bool
    {
        try {
            $lastHeartbeat = Redis::get('irc:daemon:heartbeat');

            return $lastHeartbeat && (time() - (int) $lastHeartbeat) < 15;
        } catch (\Throwable $e) {
            // Redis unavailable (or the extension missing) means no daemon
            // can be running either.
            return false;
        }
    }

    /**
     * Create a stream context with the same SSL options connect() uses.
     *
     * @return resource
     */
    private static function createStreamContext(IrcServer $server)
    {
        $context = stream_context_create();
        if ($server->use_ssl) {
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
        }

        return $context;
    }

    /**
     * Probe whether an IRC server accepts connections, using the same
     * protocol and SSL options as connect(). Blocks up to $timeoutSeconds.
     */
    public static function isReachable(IrcServer $server, int $timeoutSeconds = 5): bool
    {
        $protocol = $server->use_ssl ? 'ssl' : 'tcp';

        $socket = @stream_socket_client(
            "{$protocol}://{$server->host}:{$server->port}",
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            self::createStreamContext($server)
        );

        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * Open a TCP connection to an IRC server and send registration.
     */
    public function connect(IrcConnection $connection): bool
    {
        $server = $connection->server;
        if (!$server) {
            Log::error("[IRC] Connection {$connection->id}: No server configured");
            return false;
        }

        $protocol = $server->use_ssl ? 'ssl' : 'tcp';
        $address = "{$protocol}://{$server->host}:{$server->port}";

        Log::info("[IRC] Connection {$connection->id}: Connecting to {$address}");

        $connection->update(['status' => 'connecting']);
        $this->broadcast('status', $connection->id, ['status' => 'connecting']);

        $context = self::createStreamContext($server);

        $this->info("Connecting to {$address}...");

        $socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $errorMsg = $errstr ?: 'Connection failed (check host/port/SSL settings)';
            Log::error("[IRC] Connection {$connection->id}: Failed to connect - {$errorMsg} ({$errno})");
            $this->info("FAILED: {$errorMsg}");
            $connection->update(['status' => 'disconnected']);
            $this->broadcast('status', $connection->id, [
                'status' => 'disconnected',
                'error' => $errorMsg,
            ]);
            return false;
        }

        $this->info("TCP connection established to {$server->host}:{$server->port}");

        stream_set_blocking($socket, false);

        $this->sockets[$connection->id] = $socket;
        $this->buffers[$connection->id] = '';
        $this->connections[$connection->id] = $connection;
        $this->channelUsers[$connection->id] = [];

        // Send IRC registration
        $this->info("Sending registration as {$connection->nickname}...");
        if ($server->password) {
            $this->write($connection->id, IrcProtocolParser::build('PASS', [], $server->password));
        }
        $this->write($connection->id, IrcProtocolParser::build('NICK', [$connection->nickname]));
        $this->write($connection->id, IrcProtocolParser::build('USER', [
            $connection->username ?? $connection->nickname,
            '0',
            '*',
        ], $connection->realname ?? $connection->nickname));
        $this->info("Registration sent, waiting for server response...");

        return true;
    }

    /**
     * Disconnect from an IRC server.
     */
    public function disconnect(int $connectionId, string $message = 'Leaving'): void
    {
        if (isset($this->sockets[$connectionId])) {
            $this->write($connectionId, IrcProtocolParser::build('QUIT', [], $message));
            usleep(100000); // Give server time to process
            fclose($this->sockets[$connectionId]);
        }

        $this->cleanup($connectionId);

        $connection = IrcConnection::find($connectionId);
        if ($connection) {
            $connection->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
            ]);
            $connection->channels()->update(['is_joined' => false]);
        }

        $this->broadcast('status', $connectionId, ['status' => 'disconnected']);
        Log::info("[IRC] Connection {$connectionId}: Disconnected");
    }

    /**
     * Write raw data to a connection's socket.
     */
    public function write(int $connectionId, string $data): bool
    {
        if (!isset($this->sockets[$connectionId])) {
            $this->info("Write failed: no socket for connection {$connectionId}");
            return false;
        }

        $result = @fwrite($this->sockets[$connectionId], $data);
        if ($result === false) {
            $this->info("Write failed: fwrite returned false for connection {$connectionId}");
            $this->handleDisconnect($connectionId);
            return false;
        }

        if ($result === 0) {
            $this->info("Write warning: 0 bytes written for connection {$connectionId}");
        }

        $this->info(">> " . trim($data));
        return true;
    }

    /**
     * Send a PRIVMSG to a channel or user.
     */
    public function sendPrivmsg(int $connectionId, string $target, string $message): void
    {
        $this->write($connectionId, IrcProtocolParser::build('PRIVMSG', [$target], $message));
    }

    /**
     * Send a JOIN command.
     */
    public function sendJoin(int $connectionId, string $channel): void
    {
        $this->write($connectionId, IrcProtocolParser::build('JOIN', [$channel]));
    }

    /**
     * Send a PART command.
     */
    public function sendPart(int $connectionId, string $channel, ?string $reason = null): void
    {
        $this->write($connectionId, IrcProtocolParser::build('PART', [$channel], $reason ?? 'Leaving'));
    }

    /**
     * Send a NICK change command.
     */
    public function sendNick(int $connectionId, string $newNick): void
    {
        $this->write($connectionId, IrcProtocolParser::build('NICK', [$newNick]));
    }

    /**
     * Read from all active sockets using stream_select.
     * Returns parsed lines grouped by connection ID.
     */
    public function readAll(int $timeoutMicroseconds = 100000): array
    {
        if (empty($this->sockets)) {
            return [];
        }

        $read = array_values($this->sockets);
        $write = null;
        $except = null;

        $changed = @stream_select($read, $write, $except, 0, $timeoutMicroseconds);

        if ($changed === false || $changed === 0) {
            return [];
        }

        $allLines = [];

        foreach ($read as $socket) {
            $connectionId = array_search($socket, $this->sockets, true);
            if ($connectionId === false) {
                continue;
            }

            $data = @fread($socket, 4096);

            if ($data === false || $data === '') {
                if (feof($socket)) {
                    Log::info("[IRC] Connection {$connectionId}: Server closed connection");
                    $this->handleDisconnect($connectionId);
                }
                continue;
            }

            $this->buffers[$connectionId] .= $data;

            // Split buffer into complete lines
            while (($newlinePos = strpos($this->buffers[$connectionId], "\n")) !== false) {
                $line = substr($this->buffers[$connectionId], 0, $newlinePos);
                $this->buffers[$connectionId] = substr($this->buffers[$connectionId], $newlinePos + 1);
                $line = rtrim($line, "\r");

                if ($line === '') {
                    continue;
                }

                $this->info("<< {$line}");
                $allLines[$connectionId][] = $line;
            }
        }

        return $allLines;
    }

    /**
     * Process a parsed IRC line and take appropriate actions.
     */
    public function handleLine(int $connectionId, string $rawLine): void
    {
        $parsed = IrcProtocolParser::parse($rawLine);
        $command = $parsed['command'];

        switch ($command) {
            case 'PING':
                $this->write($connectionId, IrcProtocolParser::build('PONG', [], $parsed['params'][0] ?? ''));
                break;

            case '001': // RPL_WELCOME - Successfully registered
                $this->onRegistered($connectionId);
                $this->storeServerMessage($connectionId, $parsed);
                break;

            case '002': // RPL_YOURHOST
            case '003': // RPL_CREATED
            case '004': // RPL_MYINFO
            case '005': // RPL_ISUPPORT
                $this->storeServerMessage($connectionId, $parsed);
                break;

            case '251': // RPL_LUSERCLIENT
            case '252': // RPL_LUSEROP
            case '253': // RPL_LUSERUNKNOWN
            case '254': // RPL_LUSERCHANNELS
            case '255': // RPL_LUSERME
            case '265': // RPL_LOCALUSERS
            case '266': // RPL_GLOBALUSERS
                $this->storeServerMessage($connectionId, $parsed);
                break;

            case '332': // RPL_TOPIC
                $this->onTopic($connectionId, $parsed);
                break;

            case '353': // RPL_NAMREPLY
                $this->onNames($connectionId, $parsed);
                break;

            case '366': // RPL_ENDOFNAMES
                $this->onEndOfNames($connectionId, $parsed);
                break;

            case '372': // RPL_MOTD
            case '375': // RPL_MOTDSTART
            case '376': // RPL_ENDOFMOTD
                $this->storeServerMessage($connectionId, $parsed);
                break;

            case 'PRIVMSG':
                $this->onPrivmsg($connectionId, $parsed);
                break;

            case 'NOTICE':
                $this->onNotice($connectionId, $parsed);
                break;

            case 'JOIN':
                $this->onJoin($connectionId, $parsed);
                break;

            case 'PART':
                $this->onPart($connectionId, $parsed);
                break;

            case 'QUIT':
                $this->onQuit($connectionId, $parsed);
                break;

            case 'NICK':
                $this->onNickChange($connectionId, $parsed);
                break;

            case 'KICK':
                $this->onKick($connectionId, $parsed);
                break;

            case 'TOPIC':
                $this->onTopicChange($connectionId, $parsed);
                break;

            case 'ERROR':
                Log::error("[IRC] Connection {$connectionId}: Server error - " . ($parsed['params'][0] ?? 'Unknown'));
                $this->handleDisconnect($connectionId);
                break;

            default:
                if (IrcProtocolParser::isNumeric($command)) {
                    $this->storeServerMessage($connectionId, $parsed);
                }
                break;
        }
    }

    /**
     * Get all active socket resources (for external stream_select).
     */
    public function getActiveSockets(): array
    {
        return $this->sockets;
    }

    public function hasActiveConnections(): bool
    {
        return !empty($this->sockets);
    }

    public function getActiveConnectionIds(): array
    {
        return array_keys($this->sockets);
    }

    // --- IRC Event Handlers ---

    private function onRegistered(int $connectionId): void
    {
        $connection = $this->connections[$connectionId] ?? IrcConnection::find($connectionId);
        if (!$connection) return;

        $connection->update([
            'status' => 'connected',
            'connected_at' => now(),
        ]);
        $this->connections[$connectionId] = $connection->fresh();

        $this->broadcast('status', $connectionId, ['status' => 'connected']);
        Log::info("[IRC] Connection {$connectionId}: Registered successfully as {$connection->nickname}");

        // Rejoin all known channels for this connection (non-private)
        $channels = IrcChannel::where('irc_connection_id', $connectionId)
            ->where('is_private', false)
            ->get();
        foreach ($channels as $channel) {
            $this->info("Auto-rejoining {$channel->name}");
            $this->sendJoin($connectionId, $channel->name);
        }

        // Also join any from auto_join_channels that aren't already in DB
        if (!empty($connection->auto_join_channels)) {
            $existingNames = $channels->pluck('name')->map(fn ($n) => strtolower($n))->toArray();
            foreach ($connection->auto_join_channels as $ch) {
                if (!in_array(strtolower($ch), $existingNames)) {
                    $this->info("Auto-joining {$ch} (from config)");
                    $this->sendJoin($connectionId, $ch);
                }
            }
        }
    }

    private function onPrivmsg(int $connectionId, array $parsed): void
    {
        $nick = $parsed['nick'] ?? $parsed['prefix'] ?? 'unknown';
        $target = $parsed['params'][0] ?? '';
        $message = $parsed['params'][1] ?? '';

        // Check for CTCP ACTION (/me)
        $type = 'message';
        if (str_starts_with($message, "\x01ACTION ") && str_ends_with($message, "\x01")) {
            $message = substr($message, 8, -1);
            $type = 'action';
        }

        // For private messages, target is our nick — use sender's nick as the channel
        $isPrivate = !str_starts_with($target, '#') && !str_starts_with($target, '&');
        $channelName = $isPrivate ? $nick : $target;

        $channel = $this->findOrCreateChannel($connectionId, $channelName, $isPrivate);
        if (!$channel) return;

        $connection = $this->connections[$connectionId] ?? null;
        $isMention = !$isPrivate && $connection && str_contains(
            strtolower($message),
            strtolower($connection->nickname)
        );

        $ircMessage = IrcMessage::create([
            'irc_channel_id' => $channel->id,
            'irc_connection_id' => $connectionId,
            'type' => $type,
            'from_nick' => $nick,
            'message' => $message,
            'is_private' => $isPrivate,
            'is_mention' => $isMention,
            'sent_at' => now(),
        ]);

        $channel->incrementUnread();

        $this->broadcast('message', $connectionId, [
            'channel_id' => $channel->id,
            'message' => $ircMessage->toArray(),
        ]);
    }

    private function onNotice(int $connectionId, array $parsed): void
    {
        $nick = $parsed['nick'] ?? $parsed['prefix'] ?? 'server';
        $message = $parsed['params'][1] ?? $parsed['params'][0] ?? '';

        $this->broadcast('notice', $connectionId, [
            'from' => $nick,
            'message' => $message,
        ]);
    }

    private function onJoin(int $connectionId, array $parsed): void
    {
        $nick = $parsed['nick'] ?? '';
        $channelName = $parsed['params'][0] ?? '';
        $connection = $this->connections[$connectionId] ?? null;

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if (!$channel) return;

        if ($connection && strtolower($nick) === strtolower($connection->nickname)) {
            // We joined the channel
            $channel->update(['is_joined' => true, 'joined_at' => now()]);
            Log::info("[IRC] Connection {$connectionId}: Joined {$channelName}");
            // Request NAMES list
            $this->write($connectionId, IrcProtocolParser::build('NAMES', [$channelName]));
        } else {
            // Someone else joined
            $this->addUserToChannel($connectionId, $channelName, $nick);
        }

        $this->storeChannelEvent($connectionId, $channel, 'join', $nick, "{$nick} has joined {$channelName}");
        $this->broadcast('channel_update', $connectionId, ['channel' => $channel->fresh()->toArray()]);
    }

    private function onPart(int $connectionId, array $parsed): void
    {
        $nick = $parsed['nick'] ?? '';
        $channelName = $parsed['params'][0] ?? '';
        $reason = $parsed['params'][1] ?? '';
        $connection = $this->connections[$connectionId] ?? null;

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if (!$channel) return;

        if ($connection && strtolower($nick) === strtolower($connection->nickname)) {
            $channel->update(['is_joined' => false]);
        } else {
            $this->removeUserFromChannel($connectionId, $channelName, $nick);
        }

        $msg = $reason ? "{$nick} has left {$channelName} ({$reason})" : "{$nick} has left {$channelName}";
        $this->storeChannelEvent($connectionId, $channel, 'part', $nick, $msg);
        $this->broadcast('channel_update', $connectionId, ['channel' => $channel->fresh()->toArray()]);
    }

    private function onQuit(int $connectionId, array $parsed): void
    {
        $nick = $parsed['nick'] ?? '';
        $reason = $parsed['params'][0] ?? '';

        // Remove from all channels and sync Redis
        $channels = IrcChannel::where('irc_connection_id', $connectionId)
            ->where('is_joined', true)
            ->get();

        if (isset($this->channelUsers[$connectionId])) {
            foreach ($this->channelUsers[$connectionId] as $channelName => &$users) {
                $hadUser = count($users);
                $users = array_values(array_filter($users, fn ($u) => strtolower($u) !== strtolower($nick)));
                unset($this->channelUserModes[$connectionId][$channelName][$nick]);

                if ($hadUser !== count($users)) {
                    $channel = $channels->firstWhere('name', $channelName);
                    if ($channel) {
                        $this->syncChannelUsersToRedis($connectionId, $channelName, $channel->id);
                    }
                }
            }
        }

        $msg = $reason ? "{$nick} has quit ({$reason})" : "{$nick} has quit";
        foreach ($channels as $channel) {
            $this->storeChannelEvent($connectionId, $channel, 'quit', $nick, $msg);
        }
    }

    private function onNickChange(int $connectionId, array $parsed): void
    {
        $oldNick = $parsed['nick'] ?? '';
        $newNick = $parsed['params'][0] ?? '';
        $connection = $this->connections[$connectionId] ?? null;

        // Update in channel user lists
        $channels = IrcChannel::where('irc_connection_id', $connectionId)
            ->where('is_joined', true)
            ->get();

        if (isset($this->channelUsers[$connectionId])) {
            foreach ($this->channelUsers[$connectionId] as $channelName => &$users) {
                $users = array_map(fn ($u) => strtolower($u) === strtolower($oldNick) ? $newNick : $u, $users);
                // Update mode prefix mapping
                if (isset($this->channelUserModes[$connectionId][$channelName][$oldNick])) {
                    $this->channelUserModes[$connectionId][$channelName][$newNick] = $this->channelUserModes[$connectionId][$channelName][$oldNick];
                    unset($this->channelUserModes[$connectionId][$channelName][$oldNick]);
                }
                $channel = $channels->firstWhere('name', $channelName);
                if ($channel) {
                    $this->syncChannelUsersToRedis($connectionId, $channelName, $channel->id);
                }
            }
        }

        // If it's our nick change
        if ($connection && strtolower($oldNick) === strtolower($connection->nickname)) {
            $connection->update(['nickname' => $newNick]);
            $this->connections[$connectionId] = $connection->fresh();
        }

        foreach ($channels as $channel) {
            $this->storeChannelEvent($connectionId, $channel, 'nick', $oldNick, "{$oldNick} is now known as {$newNick}");
        }
    }

    private function onKick(int $connectionId, array $parsed): void
    {
        $channelName = $parsed['params'][0] ?? '';
        $kickedNick = $parsed['params'][1] ?? '';
        $reason = $parsed['params'][2] ?? '';
        $kicker = $parsed['nick'] ?? '';
        $connection = $this->connections[$connectionId] ?? null;

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if (!$channel) return;

        if ($connection && strtolower($kickedNick) === strtolower($connection->nickname)) {
            $channel->update(['is_joined' => false]);
        } else {
            $this->removeUserFromChannel($connectionId, $channelName, $kickedNick);
        }

        $msg = "{$kicker} has kicked {$kickedNick} from {$channelName}" . ($reason ? " ({$reason})" : '');
        $this->storeChannelEvent($connectionId, $channel, 'kick', $kicker, $msg);
    }

    private function onTopic(int $connectionId, array $parsed): void
    {
        $channelName = $parsed['params'][1] ?? '';
        $topic = $parsed['params'][2] ?? '';

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if ($channel) {
            $channel->update(['topic' => $topic]);
            $this->broadcast('channel_update', $connectionId, ['channel' => $channel->fresh()->toArray()]);
        }
    }

    private function onTopicChange(int $connectionId, array $parsed): void
    {
        $channelName = $parsed['params'][0] ?? '';
        $topic = $parsed['params'][1] ?? '';
        $nick = $parsed['nick'] ?? '';

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if ($channel) {
            $channel->update(['topic' => $topic]);
            $this->storeChannelEvent($connectionId, $channel, 'topic', $nick, "{$nick} changed the topic to: {$topic}");
            $this->broadcast('channel_update', $connectionId, ['channel' => $channel->fresh()->toArray()]);
        }
    }

    private function onNames(int $connectionId, array $parsed): void
    {
        // 353: <nick> = <channel> :<names>
        $channelName = $parsed['params'][2] ?? '';
        $names = $parsed['params'][3] ?? '';

        if (!isset($this->channelUsers[$connectionId][$channelName])) {
            $this->channelUsers[$connectionId][$channelName] = [];
            $this->channelUserModes[$connectionId][$channelName] = [];
        }

        $nicks = preg_split('/\s+/', trim($names));
        foreach ($nicks as $nick) {
            // Extract mode prefix (@=op, +=voice, %=halfop, ~=owner, &=admin)
            $prefix = '';
            $cleanNick = $nick;
            if (preg_match('/^([@+%~&]+)(.+)$/', $nick, $m)) {
                $prefix = $m[1];
                $cleanNick = $m[2];
            }
            if ($cleanNick !== '') {
                $this->channelUsers[$connectionId][$channelName][] = $cleanNick;
                $this->channelUserModes[$connectionId][$channelName][$cleanNick] = $prefix;
            }
        }
    }

    private function onEndOfNames(int $connectionId, array $parsed): void
    {
        $channelName = $parsed['params'][1] ?? '';

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if ($channel) {
            $this->syncChannelUsersToRedis($connectionId, $channelName, $channel->id);
        }
    }

    // --- Helpers ---

    private function findOrCreateChannel(int $connectionId, string $channelName, bool $isPrivate = false): ?IrcChannel
    {
        if ($channelName === '') {
            return null;
        }

        return IrcChannel::firstOrCreate(
            [
                'irc_connection_id' => $connectionId,
                'name' => $channelName,
            ],
            [
                'is_joined' => true,
                'joined_at' => now(),
                'is_private' => $isPrivate,
            ]
        );
    }

    private function storeChannelEvent(int $connectionId, IrcChannel $channel, string $type, string $nick, string $message): void
    {
        $ircMessage = IrcMessage::create([
            'irc_channel_id' => $channel->id,
            'irc_connection_id' => $connectionId,
            'type' => $type,
            'from_nick' => $nick,
            'message' => $message,
            'sent_at' => now(),
        ]);

        $this->broadcast('message', $connectionId, [
            'channel_id' => $channel->id,
            'message' => $ircMessage->toArray(),
        ]);
    }

    private function storeServerMessage(int $connectionId, array $parsed): void
    {
        $message = end($parsed['params']) ?: '';

        $this->broadcast('server_message', $connectionId, [
            'command' => $parsed['command'],
            'message' => $message,
        ]);
    }

    private function addUserToChannel(int $connectionId, string $channelName, string $nick): void
    {
        if (!isset($this->channelUsers[$connectionId][$channelName])) {
            $this->channelUsers[$connectionId][$channelName] = [];
            $this->channelUserModes[$connectionId][$channelName] = [];
        }
        $this->channelUsers[$connectionId][$channelName][] = $nick;
        $this->channelUserModes[$connectionId][$channelName][$nick] = '';

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if ($channel) {
            $this->syncChannelUsersToRedis($connectionId, $channelName, $channel->id);
        }
    }

    private function removeUserFromChannel(int $connectionId, string $channelName, string $nick): void
    {
        if (isset($this->channelUsers[$connectionId][$channelName])) {
            $this->channelUsers[$connectionId][$channelName] = array_values(
                array_filter($this->channelUsers[$connectionId][$channelName], fn ($u) => strtolower($u) !== strtolower($nick))
            );
            unset($this->channelUserModes[$connectionId][$channelName][$nick]);
        }

        $channel = $this->findOrCreateChannel($connectionId, $channelName);
        if ($channel) {
            $this->syncChannelUsersToRedis($connectionId, $channelName, $channel->id);
        }
    }

    private function handleDisconnect(int $connectionId): void
    {
        $connection = IrcConnection::find($connectionId);
        if ($connection) {
            $connection->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
            ]);
            $connection->channels()->update(['is_joined' => false]);
        }

        if (isset($this->sockets[$connectionId])) {
            @fclose($this->sockets[$connectionId]);
        }

        $this->cleanup($connectionId);
        $this->broadcast('status', $connectionId, ['status' => 'disconnected']);
    }

    private function cleanup(int $connectionId): void
    {
        // Clean up Redis channel user keys
        $channels = IrcChannel::where('irc_connection_id', $connectionId)->get();
        foreach ($channels as $channel) {
            Redis::del("irc:channel_users:{$channel->id}");
        }

        unset($this->sockets[$connectionId]);
        unset($this->buffers[$connectionId]);
        unset($this->connections[$connectionId]);
        unset($this->channelUsers[$connectionId]);
        unset($this->channelUserModes[$connectionId]);
    }

    private function broadcast(string $type, int $connectionId, array $data): void
    {
        if ($this->broadcastCallback) {
            ($this->broadcastCallback)($type, $connectionId, $data);
        }
    }

    /**
     * Get channel users for a specific channel.
     */
    public function getChannelUsers(int $connectionId, string $channelName): array
    {
        return $this->channelUsers[$connectionId][$channelName] ?? [];
    }

    /**
     * Sync channel user list to Redis and broadcast update.
     */
    private function syncChannelUsersToRedis(int $connectionId, string $channelName, int $channelId): void
    {
        $nicks = array_unique($this->channelUsers[$connectionId][$channelName] ?? []);
        $modes = $this->channelUserModes[$connectionId][$channelName] ?? [];

        // Sort: ops first, then voiced, then regular, alphabetically within each group
        usort($nicks, function ($a, $b) use ($modes) {
            $prefixA = $modes[$a] ?? '';
            $prefixB = $modes[$b] ?? '';
            $orderA = $this->prefixOrder($prefixA);
            $orderB = $this->prefixOrder($prefixB);
            if ($orderA !== $orderB) return $orderA - $orderB;
            return strcasecmp($a, $b);
        });

        $users = array_map(fn ($n) => [
            'nickname' => $n,
            'isOnline' => true,
            'isOp' => str_contains($modes[$n] ?? '', '@'),
            'isVoice' => str_contains($modes[$n] ?? '', '+'),
            'prefix' => $modes[$n] ?? '',
        ], $nicks);

        // Store in Redis so the API can read it
        $redisKey = "irc:channel_users:{$channelId}";
        Redis::set($redisKey, json_encode($users));
        Redis::expire($redisKey, 3600);

        // Broadcast to frontend
        $this->broadcast('users_updated', $connectionId, [
            'channel_id' => $channelId,
            'channel_name' => $channelName,
            'users' => $users,
        ]);
    }

    private function prefixOrder(string $prefix): int
    {
        if (str_contains($prefix, '~')) return 0; // owner
        if (str_contains($prefix, '&')) return 1; // admin
        if (str_contains($prefix, '@')) return 2; // op
        if (str_contains($prefix, '%')) return 3; // halfop
        if (str_contains($prefix, '+')) return 4; // voice
        return 5; // regular
    }
}
