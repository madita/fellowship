<?php

namespace App\Events\Conversations;

use App\Models\Conversation\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
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
        return [
            'conversation' => [
                'uuid' => $this->conversation->uuid,
            ],
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        if (! $this->conversation->relationLoaded('users')) {
            $this->conversation->load('users');
        }

        return $this->conversation->users->map(function ($user) {
            return new PrivateChannel('user.'.$user->id);
        })
            ->toArray();
    }
}
