<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Notifications\ForumMentionNotification;
use App\Notifications\ForumReplyNotification;
use App\Services\MentionService;
use App\Services\SpamDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Stevebauman\Purify\Facades\Purify;

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

        if ( ! $user) {
            abort(401, 'You must be logged in to reply.');
        }

        if ( ! $thread->canReply($user)) {
            abort(403, 'You do not have permission to reply to this thread.');
        }

        $validated = $request->validate([
            'body'      => 'required|string',
            'parent_id' => 'nullable|exists:forum_posts,id,deleted_at,NULL',
        ]);

        // Verify parent_id belongs to this thread if provided
        if ( ! empty($validated['parent_id'])) {
            $parentPost = ForumPost::find($validated['parent_id']);
            if ( ! $parentPost) {
                abort(400, 'Parent post not found.');
            }
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
            // The 'sandbox' config matches the TipTap editor's output
            // (tables, code blocks, blockquotes, …) — the default config
            // would silently strip those elements.
            'body'      => Purify::config('sandbox')->clean($validated['body']),
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        // Replying starts you watching the thread
        $thread->watch($user);

        // Tell the watchers, bar whoever just posted. The trait also drops
        // anyone who can no longer see the thread.
        $watchers = $thread->watcherRecipients($user->id);

        NotificationFacade::send($watchers, new ForumReplyNotification($thread, $post));

        $alreadyTold = $watchers->pluck('id');

        // Parse @mentions and notify
        $mentionedUsers = $mentionService->parseMentions($validated['body']);
        foreach ($mentionedUsers as $mentionedUser) {
            // One notice each: not the author, and not anyone the reply
            // notification has just reached
            if ($mentionedUser->id !== $user->id && ! $alreadyTold->contains($mentionedUser->id)) {
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

        if ( ! $post->canEdit($user)) {
            abort(403, 'You do not have permission to edit this post.');
        }

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $previous          = $post->body;
        $validated['body'] = Purify::config('sandbox')->clean($validated['body']);
        $post->update($validated);

        // Someone added to the post is notified; the ones already in it are not
        $mentioned = app(MentionService::class)->newMentions($post->body, $previous, $user);
        foreach ($mentioned as $mentionedUser) {
            $mentionedUser->notify(new ForumMentionNotification($post->thread, $post));
        }

        return response()->json($post->load('author'));
    }

    /**
     * Delete a post.
     */
    public function destroy(ForumPost $post): JsonResponse
    {
        $user = Auth::user();

        if ( ! $post->canDelete($user)) {
            abort(403, 'You do not have permission to delete this post.');
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    /**
     * Mark the reply that answered the thread, or take the mark away.
     *
     * One request does both, because a thread has one answer at a time.
     * It used to only ever set the flag, which is why the button that
     * said "Unmark Helpful" did nothing at all.
     *
     * Who may decide is the post's own question - it knows its thread,
     * and the thread knows its category's moderators.
     */
    public function toggleHelpful(ForumPost $post): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user) {
            abort(401, 'You must be logged in.');
        }

        if ( ! $post->canBeMarkedHelpfulBy($user)) {
            abort(403, 'Only the thread author or a moderator can mark an answer helpful.');
        }

        $helpful = $post->toggleHelpful($user);

        return response()->json([
            'helpful' => $helpful,
            'post'    => $post->fresh(),
        ]);
    }
}
