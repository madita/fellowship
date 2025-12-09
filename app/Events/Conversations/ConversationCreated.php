<?php

namespace App\Events\Conversations;

use App\Models\Conversation\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

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
                'uuid'    => $this->conversation->uuid,
                'creator' => $creator ? [
                    'id'       => $creator->id,
                    'username' => $creator->username ?? $creator->email,
                    'avatar'   => $creator->avatar ?? null,
                ] : null,
                'users' => $this->conversation->users->map(function ($user) {
                    return [
                        'id'       => $user->id,
                        'username' => $user->username ?? $user->email,
                        'avatar'   => $user->avatar ?? null,
                    ];
                })->toArray(),
            ],
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
//    public function broadcastOn()
//    {
//       // dd('test');
//        return $this->conversation->others->map(function ($user) {
//            return new PrivateChannel('users.' . $user->id);
//        })
//            ->toArray();
//    }

    public function broadcastOn()
    {
        $channels = [
            new PrivateChannel('conversations.'.$this->conversation->uuid),
        ];

        // Also broadcast to individual user channels for those not actively viewing the conversation
        $this->conversation->load('users');
        foreach ($this->conversation->users as $user) {
            // Don't send to the creator of the conversation
            if ($user->id !== $this->conversation->creator_id) {
                $channels[] = new PrivateChannel('user.'.$user->id);
            }
        }

        return $channels;
    }
}
