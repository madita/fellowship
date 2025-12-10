<?php

namespace App\Http\Controllers\Conversation;

use App\Events\Conversations\ConversationUpdated;
use App\Events\Conversations\MessageAdded;
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
        try {
            $message = $conversation->messages()->create([
                'user_id' => auth()->id(),
                'body' => $request->get('body'),
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            // Update sender's read_at to mark they've read their own message
            auth()->user()->conversations()->updateExistingPivot($conversation->id, [
                'read_at' => now(),
            ]);

            // Don't reset read_at for other users - new messages will naturally appear as unread
            // since their created_at is after the existing read_at timestamp

            broadcast(new MessageAdded($message))->toOthers();
            broadcast(new ConversationUpdated($message->conversation));

            // Simple array response (matching your frontend expectations)
            $responseData = [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at,
                'created_at_human' => $message->created_at->diffForHumans(),
                'self_owned' => true,
                'user' => $message->user,
            ];

            return response()->json($responseData, 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create conversation reply', [
                'conversation_id' => $conversation->id,
                'user_id'         => $request->user()->id,
                'error'           => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create reply',
                'errors'  => ['body' => ['Unable to save reply. Please try again.']],
            ], 422);
        }
    }

}
