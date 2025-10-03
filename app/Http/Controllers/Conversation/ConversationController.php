<?php

namespace App\Http\Controllers\Conversation;

use App\Events\Conversations\ConversationCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = auth()->user()
            ->conversations()
            ->with(['users', 'messages'])
            ->get();

        return response()->json(
            $conversations->map(fn($conversation) => $this->transformConversation($conversation))
        );
    }

    public function show(Conversation $conversation, Request $request): JsonResponse
    {
        // TODO: Add authorization
        // $this->authorize('show', $conversation);

        $conversation->load(['users', 'messages', 'messages.user']);
        $conversation->loadCount('messages');

        // Add computed properties to messages
        $conversation->messages->each(function ($message) {
            $message->self_owned = $message->user_id === auth()->id();
            $message->created_at_human = $message->created_at->diffForHumans();
        });

        return response()->json($this->transformConversation($conversation, true));
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $recipientsIds = collect($request->get('recipients'))
            ->merge([auth()->id()])
            ->unique();

        $conversation = new Conversation([
            'uuid' => Str::uuid(),
            'last_message_at' => now(),
        ]);

        $conversation->save();

        // Create the initial message
        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->get('body'),
        ]);

        // Sync users to conversation
        $conversation->users()->sync($recipientsIds);

        // Load relationships for response
        $conversation->load(['users', 'messages']);

        broadcast(new ConversationCreated($conversation))->toOthers();

        return response()->json(
            $this->transformConversation($conversation),
            201
        );
    }

    /**
     * Transform conversation data for API response
     */
    private function transformConversation(Conversation $conversation, bool $includeMessages = false): array
    {
        $firstMessage = $conversation->messages->first();

        $data = [
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            'body' => $firstMessage?->body,
            'messages_count' => $conversation->messages->count(),
            'created_at_human' => $conversation->created_at->diffForHumans(),
//            'last_message_at_human' => $conversation->last_message_at
//                ? $conversation->last_message_at->diffForHumans()
//                : $conversation->created_at->diffForHumans(),
            'users' => $conversation->users->map(fn($user) => $this->transformUser($user)),
            'participant_count' => $conversation->users->count() - 1,
        ];

        // Include full messages if requested (for show method)
        if ($includeMessages && $conversation->relationLoaded('messages')) {
            $data['messages'] = $conversation->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'body' => $message->body,
                    'user_id' => $message->user_id,
                    'user' => $this->transformUser($message->user),
                    'self_owned' => $message->self_owned ?? ($message->user_id === auth()->id()),
                    //'created_at' => $message->created_at->toISOString(),
                    'created_at_human' => $message->created_at_human ?? $message->created_at->diffForHumans(),
                ];
            });
        }

        return $data;
    }

    /**
     * Transform user data for API response
     */
    private function transformUser($user): array
    {
        if (!$user) {
            return [];
        }

        return [
            'id' => $user->id,
            //'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'initials' => $user->initials,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
