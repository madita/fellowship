<?php

namespace App\Http\Controllers\Conversation;

use App\Events\Conversations\MessageAdded;
use App\Events\Conversations\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationReplyRequest;
use App\Models\Conversation\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationReplyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    public function store(StoreConversationReplyRequest $request, Conversation $conversation): JsonResponse
    {
        // Authorization check
        // $this->authorize('reply', $conversation);

        //dd($request);

        try {
            // Create the reply
           /* $reply = new Conversation();
            $reply->body = $request->validated()['body'];
            $reply->user()->associate($request->user());

            $conversation->replies()->save($reply);
            $conversation->touchLastReply();

            // Load relationships
            $reply->load(['user']);*/

           /* $this->validate([
                'body' => 'required'
            ]);*/

            $message = $conversation->messages()->create([
                'user_id' => auth()->id(),
                'body' => $request->get('body')
            ]);

            $conversation->update([
                'last_message_at' => now()
            ]);

            foreach ($conversation->others as $user) {
                $user->conversations()->updateExistingPivot($conversation, [
                    'read_at' => null
                ]);
            }

            broadcast(new MessageAdded($message))->toOthers();
            broadcast(new ConversationUpdated($message->conversation));



            // Simple array response (matching your frontend expectations)
            $responseData = [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at,
                'created_at_human' => $message->created_at->diffForHumans(),
                'self_owned' => true,
                'user' => $message->user
            ];

            // Broadcast event
//            broadcast(new ConversationReplyCreated($reply))->toOthers();

            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            \Log::error('Failed to create conversation reply', [
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to create reply',
                'errors' => ['body' => ['Unable to save reply. Please try again.']]
            ], 422);
        }
    }

    private function generateDefaultAvatar($user): string
    {
        $name = $user->name ?? $user->username;
        return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=667eea&color=fff&size=128";
    }
}
