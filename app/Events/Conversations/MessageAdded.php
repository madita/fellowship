<?php

namespace App\Events\Conversations;

use App\Models\Conversation\ConversationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageAdded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(ConversationMessage $message)
    {
        $this->message = $message;
    }

//    public function broadcastWith()
//    {
//        return [
//            'message' => [
//                'id' => $this->message->id
//            ]
//        ];
//    }
    public function broadcastWith()
    {
        $this->message->load(['user', 'conversation']);

        return [
            'message' => array_merge($this->message->toArray(), [
                'selfOwned'        => false,
                'created_at_human' => $this->message->created_at->diffForHumans(),
                'conversation'     => [
                    'uuid' => $this->message->conversation->uuid,
                ],
            ]),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channels = [
            new PrivateChannel('conversations.'.$this->message->conversation->uuid),
        ];

        // Also broadcast to individual user channels for those not actively viewing the conversation
        $this->message->conversation->load('users');
        foreach ($this->message->conversation->users as $user) {
            if ($user->id !== $this->message->user_id) {
                $channels[] = new PrivateChannel('user.'.$user->id);
            }
        }

        return $channels;
    }

//    public function broadcastAs()
//    {
//        return 'message-added';
//    }
}
