<?php

namespace App\Http\Controllers\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxComment;
use App\Models\Sandbox\SandboxThread;
use App\Models\User;
use App\Notifications\SandboxNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SandboxCommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all threads for a sandbox.
     */
    public function index(Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $threads = $sandbox->threads()
            ->with(['user:id,username', 'comments.user:id,username'])
            ->get();

        return response()->json(['threads' => $threads]);
    }

    /**
     * Create a new comment thread.
     */
    public function storeThread(Request $request, Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'quote' => 'nullable|string|max:5000',
            'content' => 'required|string|max:5000',
        ]);

        $thread = $sandbox->threads()->create([
            'user_id' => $user->id,
            'quote' => $validated['quote'] ?? null,
        ]);

        $thread->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
        ]);

        $thread->load(['user:id,username', 'comments.user:id,username']);

        // Notify sandbox owner + collaborators (except the commenter)
        $this->notifySandboxParticipants($sandbox, $user, 'comment', [
            'thread_id' => $thread->id,
            'quote' => Str::limit($validated['quote'] ?? '', 80),
            'excerpt' => Str::limit($validated['content'], 100),
        ]);

        return response()->json(['thread' => $thread], 201);
    }

    /**
     * Resolve or unresolve a thread.
     */
    public function updateThread(Request $request, Sandbox $sandbox, SandboxThread $thread): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($thread->sandbox_id !== $sandbox->id) {
            return response()->json(['error' => 'Thread does not belong to this sandbox'], 404);
        }

        $validated = $request->validate([
            'resolved' => 'sometimes|boolean',
        ]);

        if (isset($validated['resolved'])) {
            $thread->update([
                'resolved_at' => $validated['resolved'] ? now() : null,
            ]);

            // Notify thread creator when resolved by someone else
            if ($validated['resolved'] && $thread->user_id !== $user->id) {
                $threadCreator = User::find($thread->user_id);
                if ($threadCreator) {
                    $threadCreator->notify(new SandboxNotification($sandbox, 'resolved', $user, [
                        'thread_id' => $thread->id,
                    ]));
                }
            }
        }

        $thread->load(['user:id,username', 'comments.user:id,username']);

        return response()->json(['thread' => $thread]);
    }

    /**
     * Delete a thread.
     */
    public function destroyThread(Sandbox $sandbox, SandboxThread $thread): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canEdit($user) && $thread->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($thread->sandbox_id !== $sandbox->id) {
            return response()->json(['error' => 'Thread does not belong to this sandbox'], 404);
        }

        $thread->delete();

        return response()->json(['message' => 'Thread deleted']);
    }

    /**
     * Add a comment (reply) to a thread.
     */
    public function storeComment(Request $request, Sandbox $sandbox, SandboxThread $thread): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($thread->sandbox_id !== $sandbox->id) {
            return response()->json(['error' => 'Thread does not belong to this sandbox'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment = $thread->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
        ]);

        $comment->load('user:id,username');

        // Notify thread creator + other commenters (except the replier)
        $this->notifyThreadParticipants($sandbox, $thread, $user, [
            'thread_id' => $thread->id,
            'excerpt' => Str::limit($validated['content'], 100),
        ]);

        return response()->json(['comment' => $comment], 201);
    }

    /**
     * Update a comment.
     */
    public function updateComment(Request $request, Sandbox $sandbox, SandboxThread $thread, SandboxComment $comment): JsonResponse
    {
        $user = auth()->user();

        if ($comment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($thread->sandbox_id !== $sandbox->id || $comment->thread_id !== $thread->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment->update(['content' => $validated['content']]);
        $comment->load('user:id,username');

        return response()->json(['comment' => $comment]);
    }

    /**
     * Delete a comment.
     */
    public function destroyComment(Sandbox $sandbox, SandboxThread $thread, SandboxComment $comment): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canEdit($user) && $comment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($thread->sandbox_id !== $sandbox->id || $comment->thread_id !== $thread->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted']);
    }

    /**
     * Notify sandbox owner + accepted collaborators (excluding the actor).
     */
    protected function notifySandboxParticipants(Sandbox $sandbox, User $actor, string $action, array $extra = []): void
    {
        $recipientIds = $sandbox->collaborators()
            ->whereNotNull('accepted_at')
            ->pluck('users.id')
            ->toArray();

        // Include sandbox owner
        if (!in_array($sandbox->user_id, $recipientIds)) {
            $recipientIds[] = $sandbox->user_id;
        }

        // Exclude the actor
        $recipientIds = array_filter($recipientIds, fn($id) => $id !== $actor->id);

        $recipients = User::whereIn('id', $recipientIds)->get();
        foreach ($recipients as $recipient) {
            $recipient->notify(new SandboxNotification($sandbox, $action, $actor, $extra));
        }
    }

    /**
     * Notify thread creator + other commenters on reply (excluding the replier).
     */
    protected function notifyThreadParticipants(Sandbox $sandbox, SandboxThread $thread, User $actor, array $extra = []): void
    {
        // Collect unique user IDs from thread creator + all commenters
        $recipientIds = $thread->comments()
            ->pluck('user_id')
            ->push($thread->user_id)
            ->unique()
            ->reject(fn($id) => $id === $actor->id)
            ->values()
            ->toArray();

        $recipients = User::whereIn('id', $recipientIds)->get();
        foreach ($recipients as $recipient) {
            $recipient->notify(new SandboxNotification($sandbox, 'reply', $actor, $extra));
        }
    }
}
