<?php

namespace App\Http\Controllers\Conversation;

use App\Events\ConversationUsersCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationUserRequest;
use App\Models\Conversation\Conversation;
use App\Transformers\ConversationTransformer;

class ConversationUserController extends Controller
{
    public function store(StoreConversationUserRequest $request, Conversation $conversation)
    {
        // $this->authorize('affect', $conversation);

        $conversation->users()->syncWithoutDetaching($request->recipients);
        // $conversation->users()->sync($recipientsIds);

        $conversation->load(['users']);

        // broadcast(new ConversationUsersCreated($conversation))->toOthers();

        /* return fractal()
             ->item($conversation)
             ->parseIncludes(['user', 'users'])
             ->transformWith(new ConversationTransformer)
             ->toArray();*/
        return response()->json([
            // 'data' => $this->transformConversation($conversation, [ 'users', 'replies', 'replies.user'])
            'data' => $conversation,
        ], 201);
    }
}
