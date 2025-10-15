<?php

namespace App\Events\Conversations;

use App\Models\Conversation\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Conversation $conversation)
    {
        $this->conversation = $conversation;
    }

    public function broadcastWith()
    {
        // Load relationships if not already loaded
        if (!$this->conversation->relationLoaded('users')) {
            $this->conversation->load('users');
        }

        // Load creator relationship - may be null for old conversations
        if (!$this->conversation->relationLoaded('creator')) {
            $this->conversation->load('creator');
        }

        // If no creator is set, use the first message's user or authenticated user
        $creator = $this->conversation->creator;
        if (!$creator && auth()->check()) {
            $creator = auth()->user();
        }

        return [
            'conversation' => [
                'uuid' => $this->conversation->uuid,
                'creator' => $creator ? [
                    'id' => $creator->id,
                    'username' => $creator->username ?? $creator->email,
                    'avatar' => $creator->avatar ?? null,
                ] : null,
                'users' => $this->conversation->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'username' => $user->username ?? $user->email,
                        'avatar' => $user->avatar ?? null,
                    ];
                })->toArray(),
            ]
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
       // dd('test');
        return $this->conversation->others->map(function ($user) {
            return new PrivateChannel('users.' . $user->id);
        })
            ->toArray();
    }
}
