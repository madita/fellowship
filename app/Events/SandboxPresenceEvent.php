<?php

namespace App\Events;

use App\Models\Sandbox\Sandbox;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SandboxPresenceEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Sandbox $sandbox,
        public User $user,
        public string $action, // 'joined', 'left', 'cursor', 'selection'
        public ?array $data = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('sandbox.' . $this->sandbox->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'presence.' . $this->action;
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
            ],
            'action' => $this->action,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
