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

        // Mark conversation as read for current user
        auth()->user()->conversations()->updateExistingPivot($conversation->id, [
            'read_at' => now(),
        ]);

        return response()->json($this->transformConversation($conversation, true));
    }

    /**
     * Mark conversation as read for the authenticated user
     */
    public function markAsRead(Conversation $conversation): JsonResponse
    {
        auth()->user()->conversations()->updateExistingPivot($conversation->id, [
            'read_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $recipientsIds = collect($request->get('recipients'))
            ->merge([auth()->id()])
            ->unique();

        $conversation = new Conversation([
            'uuid' => Str::uuid(),
            'last_message_at' => now(),
            'creator_id' => auth()->id(),
        ]);

        $conversation->save();

        // Create the initial message
        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->get('body'),
        ]);

        // Sync users to conversation with read_at timestamp for creator
        $syncData = [];
        foreach ($recipientsIds as $userId) {
            $syncData[$userId] = [
                'read_at' => $userId === auth()->id() ? now() : null,
            ];
        }
        $conversation->users()->sync($syncData);

        // Load relationships for response
        $conversation->load(['users', 'messages']);

        broadcast(new ConversationCreated($conversation))->toOthers();

        return response()->json(
            $this->transformConversation($conversation),
            201
        );
    }

    /**
     * Transform conversation data for API response.
     */
    private function transformConversation(Conversation $conversation, bool $includeMessages = false): array
    {
        $firstMessage = $conversation->messages->first();

        // Get user's read_at timestamp from pivot table
        $userConversation = auth()->user()->conversations()->where('conversation_id', $conversation->id)->first();
        $readAt = $userConversation?->pivot?->read_at;

        // Calculate unread count using loaded messages collection
        $unreadCount = 0;
        if ($conversation->relationLoaded('messages')) {
            if ($readAt) {
                // Count messages created after read_at timestamp
                $unreadCount = $conversation->messages->filter(function ($message) use ($readAt) {
                    return $message->user_id !== auth()->id() && $message->created_at > $readAt;
                })->count();
            } else {
                // If never read, all messages (except own) are unread
                $unreadCount = $conversation->messages->filter(function ($message) {
                    return $message->user_id !== auth()->id();
                })->count();
            }
        }

        $data = [
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            'body' => $firstMessage?->body,
            'messages_count' => $conversation->messages->count(),
            'unread_count' => $unreadCount,
            'is_unread' => $unreadCount > 0,
            'read_at' => $readAt ? ($readAt instanceof \Carbon\Carbon ? $readAt->toISOString() : $readAt) : null,
            'created_at' => $conversation->created_at->toISOString(),
            'created_at_human' => $conversation->created_at->diffForHumans(),
            'last_message_at' => $conversation->last_message_at
                ? ($conversation->last_message_at instanceof \Carbon\Carbon
                    ? $conversation->last_message_at->toISOString()
                    : $conversation->last_message_at)
                : null,
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
                    'created_at_human' => $message->created_at_human ?? $message->created_at->diffForHumans(),
                ];
            });
        }

        return $data;
    }

    /**
     * Transform user data for API response.
     */
    private function transformUser($user): array
    {
        if (!$user) {
            return [];
        }

        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'initials' => $user->initials,
            'avatar' => $user->avatar ?? null,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
