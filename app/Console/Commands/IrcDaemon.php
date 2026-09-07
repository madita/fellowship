<?php

namespace App\Console\Commands;

use App\Models\Irc\IrcConnection;
use App\Services\Irc\IrcConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class IrcDaemon extends Command
{
    protected $signature = 'irc:daemon';
    protected $description = 'Run the IRC connection daemon that manages real IRC server connections';

    private IrcConnectionManager $manager;
    private bool $running = true;

    public function handle(): int
    {
        $this->info('IRC Daemon starting...');
        Log::info('[IRC Daemon] Starting');

        $this->manager = new IrcConnectionManager();
        $this->manager->setBroadcastCallback(function (string $type, int $connectionId, array $data) {
            $this->broadcastEvent($type, $connectionId, $data);
        });
        $this->manager->setOutputCallback(function (string $message) {
            $this->line("[" . date('H:i:s') . "] {$message}");
        });

        // Handle graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, fn () => $this->running = false);
            pcntl_signal(SIGINT, fn () => $this->running = false);
        }

        // Auto-connect any connections that were marked as connected (recover from crash)
        $this->recoverConnections();

        $this->info('IRC Daemon running. Press Ctrl+C to stop.');

        $lastHeartbeat = 0;

        while ($this->running) {
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            // Send heartbeat every 5 seconds
            $now = time();
            if ($now - $lastHeartbeat >= 5) {
                Redis::set('irc:daemon:heartbeat', $now);
                Redis::expire('irc:daemon:heartbeat', 30);
                $lastHeartbeat = $now;
            }

            // Process Redis commands (non-blocking)
            $this->processRedisCommands();

            // Read from all active sockets
            $allLines = $this->manager->readAll(100000); // 100ms timeout

            foreach ($allLines as $connectionId => $lines) {
                foreach ($lines as $line) {
                    $this->manager->handleLine($connectionId, $line);
                }
            }

            // Small sleep if no active connections to prevent CPU spin
            if (!$this->manager->hasActiveConnections()) {
                usleep(200000); // 200ms
            }
        }

        // Clear heartbeat on shutdown
        Redis::del('irc:daemon:heartbeat');

        $this->info('IRC Daemon shutting down...');
        foreach ($this->manager->getActiveConnectionIds() as $connectionId) {
            $this->manager->disconnect($connectionId, 'Daemon shutting down');
        }

        Log::info('[IRC Daemon] Stopped');
        return self::SUCCESS;
    }

    private function processRedisCommands(): void
    {
        // Use LPOP to non-blocking pop from command queue
        $maxCommands = 10; // Process up to 10 commands per loop
        for ($i = 0; $i < $maxCommands; $i++) {
            $raw = Redis::lpop('irc:commands');
            if (!$raw) {
                break;
            }

            $command = json_decode($raw, true);
            if (!$command || !isset($command['type'])) {
                Log::warning('[IRC Daemon] Invalid command received: ' . $raw);
                continue;
            }

            $this->handleCommand($command);
        }
    }

    private function handleCommand(array $command): void
    {
        $type = $command['type'];
        $connectionId = $command['connection_id'] ?? null;

        $this->line("[" . date('H:i:s') . "] Command: {$type}" . ($connectionId ? " (conn {$connectionId})" : '') . " " . json_encode(array_diff_key($command, ['type' => 1, 'connection_id' => 1])));

        switch ($type) {
            case 'connect':
                $connection = IrcConnection::with('server')->find($connectionId);
                if ($connection) {
                    $this->manager->connect($connection);
                } else {
                    Log::error("[IRC Daemon] Connection {$connectionId} not found");
                }
                break;

            case 'disconnect':
                $this->manager->disconnect(
                    $connectionId,
                    $command['message'] ?? 'Leaving'
                );
                break;

            case 'join':
                $channel = $command['channel'] ?? '';
                if ($channel) {
                    $this->manager->sendJoin($connectionId, $channel);
                }
                break;

            case 'part':
                $channel = $command['channel'] ?? '';
                if ($channel) {
                    $this->manager->sendPart($connectionId, $channel, $command['reason'] ?? null);
                }
                break;

            case 'send':
                $target = $command['target'] ?? '';
                $message = $command['message'] ?? '';
                if ($target && $message) {
                    // Handle /me actions
                    if (($command['msg_type'] ?? 'message') === 'action') {
                        $this->manager->write(
                            $connectionId,
                            "PRIVMSG {$target} :\x01ACTION {$message}\x01\r\n"
                        );
                    } else {
                        $this->manager->sendPrivmsg($connectionId, $target, $message);
                    }
                }
                break;

            case 'nick':
                $nick = $command['nickname'] ?? '';
                if ($nick) {
                    $this->manager->sendNick($connectionId, $nick);
                }
                break;

            case 'names':
                $channel = $command['channel'] ?? '';
                if ($channel) {
                    $this->manager->write(
                        $connectionId,
                        "NAMES {$channel}\r\n"
                    );
                }
                break;

            default:
                Log::warning("[IRC Daemon] Unknown command type: {$type}");
        }
    }

    private function broadcastEvent(string $type, int $connectionId, array $data): void
    {
        $connection = IrcConnection::find($connectionId);
        $userId = $connection?->user_id;

        if (!$userId) {
            return;
        }

        $payload = json_encode([
            'type' => $type,
            'connection_id' => $connectionId,
            'user_id' => $userId,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);

        // Push to a per-user Redis list that the frontend can poll
        Redis::rpush("irc:events:{$userId}", $payload);
        // Keep only last 100 events per user
        Redis::ltrim("irc:events:{$userId}", -100, -1);
        // Auto-expire after 1 hour
        Redis::expire("irc:events:{$userId}", 3600);
    }

    private function recoverConnections(): void
    {
        // Find connections that were left in 'connected' or 'connecting' state
        $staleConnections = IrcConnection::with('server')
            ->whereIn('status', ['connected', 'connecting'])
            ->get();

        foreach ($staleConnections as $connection) {
            $this->info("Recovering connection {$connection->id} to {$connection->server->name}");
            $connection->update(['status' => 'disconnected']);
            // If auto_connect is on, reconnect
            if ($connection->auto_connect) {
                $this->manager->connect($connection);
            }
        }
    }
}
