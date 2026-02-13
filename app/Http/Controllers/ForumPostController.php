<?php

namespace App\Http\Controllers;

use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Notifications\ForumMentionNotification;
use App\Notifications\ForumReplyNotification;
use App\Services\MentionService;
use App\Services\SpamDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumPostController extends Controller
{
    /**
     * Create a new post (reply to thread).
     */
    public function store(
        Request $request,
        ForumThread $thread,
        SpamDetectionService $spamService,
        MentionService $mentionService
    ): JsonResponse {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'You must be logged in to reply.');
        }

        if (!$thread->canReply($user)) {
            abort(403, 'You do not have permission to reply to this thread.');
        }

        $validated = $request->validate([
            'body' => 'required|string',
            'parent_id' => 'nullable|exists:forum_posts,id',
        ]);

        // Verify parent_id belongs to this thread if provided
        if (!empty($validated['parent_id'])) {
            $parentPost = ForumPost::find($validated['parent_id']);
            if ($parentPost->thread_id !== $thread->id) {
                abort(400, 'Invalid parent post.');
            }
        }

        // Spam check
        $spamResult = $spamService->checkPostCreation($user, $validated['body']);
        if ($spamResult) {
            abort($spamResult['status'], $spamResult['message']);
        }

        $post = $thread->posts()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        // Auto-subscribe the replier
        $thread->subscribe($user);

        // Record activity
        activity('forum')
            ->performedOn($post)
            ->causedBy($user)
            ->withProperties(['thread_title' => $thread->title])
            ->event('post_created')
            ->log('replied in a thread');

        // Notify all subscribers except the post author
        $thread->load('subscriptions');
        $subscriberIds = $thread->subscriptions
            ->pluck('user_id')
            ->filter(fn ($id) => $id !== $user->id);

        $subscribers = \App\Models\User::whereIn('id', $subscriberIds)->get();
        foreach ($subscribers as $subscriber) {
            $subscriber->notify(new ForumReplyNotification($thread, $post));
        }

        // Parse @mentions and notify
        $mentionedUsers = $mentionService->parseMentions($validated['body']);
        foreach ($mentionedUsers as $mentionedUser) {
            // Don't notify the post author or already-notified subscribers
            if ($mentionedUser->id !== $user->id && !$subscriberIds->contains($mentionedUser->id)) {
                $mentionedUser->notify(new ForumMentionNotification($thread, $post));
            }
        }

        return response()->json($post->load('author'), 201);
    }

    /**
     * Update a post.
     */
    public function update(Request $request, ForumPost $post): JsonResponse
    {
        $user = Auth::user();

        if (!$post->canEdit($user)) {
            abort(403, 'You do not have permission to edit this post.');
        }

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $post->update($validated);

        return response()->json($post->load('author'));
    }

    /**
     * Delete a post.
     */
    public function destroy(ForumPost $post): JsonResponse
    {
        $user = Auth::user();

        if (!$post->canDelete($user)) {
            abort(403, 'You do not have permission to delete this post.');
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    /**
     * Mark a post as the solution (thread author or admin only).
     */
    public function markAsSolution(ForumPost $post): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'You must be logged in.');
        }

        $thread = $post->thread;

        // Only thread author or admin can mark solutions
        if ($user->id !== $thread->user_id && !$user->isAdmin()) {
            abort(403, 'Only the thread author or an admin can mark a solution.');
        }

        $post->markAsSolution();

        // Record activity
        activity('forum')
            ->performedOn($post)
            ->causedBy($user)
            ->withProperties(['thread_title' => $thread->title])
            ->event('solution_marked')
            ->log('marked a post as helpful');

        return response()->json([
            'message' => 'Post marked as solution',
            'post' => $post->fresh(),
        ]);
    }
}
