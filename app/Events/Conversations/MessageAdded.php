<?php

namespace App\Events\Conversations;

use App\Models\Conversation\ConversationMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

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
        $this->message->load(['user']);

        return [
            'message' => array_merge($this->message->toArray(), [
                'selfOwned' => false,
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
        return new PrivateChannel('conversations.' . $this->message->conversation->uuid);
    }

//    public function broadcastAs()
//    {
//        return 'message-added';
//    }
}
