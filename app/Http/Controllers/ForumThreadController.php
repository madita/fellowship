<?php

namespace App\Http\Controllers;

use App\Models\Forum\Forum;
use App\Models\Forum\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumThreadController extends Controller
{
    /**
     * Get a specific thread with its posts.
     */
    public function show(string $forumSlug, string $threadSlug): JsonResponse
    {
        $user = Auth::user();

        $thread = ForumThread::where('slug', $threadSlug)
            ->with(['forum', 'author'])
            ->firstOrFail();

        if (!$thread->forum->canAccess($user)) {
            abort(403, 'You do not have permission to access this thread.');
        }

        // Increment view count (throttle to once per session)
        $viewedKey = "thread_viewed_{$thread->id}";
        if (!session()->has($viewedKey)) {
            $thread->incrementViews();
            session()->put($viewedKey, true);
        }

        // Get posts with pagination
        $posts = $thread->posts()
            ->with(['author', 'replies.author'])
            ->whereNull('parent_id') // Only top-level posts
            ->paginate(20);

        return response()->json([
            'thread' => $thread,
            'posts' => $posts,
            'can_reply' => $thread->canReply($user),
            'can_edit' => $thread->canEdit($user),
            'can_delete' => $thread->canDelete($user),
        ]);
    }

    /**
     * Create a new thread.
     */
    public function store(Request $request, Forum $forum): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'You must be logged in to create a thread.');
        }

        if (!$forum->canAccess($user)) {
            abort(403, 'You do not have permission to post in this forum.');
        }

        if ($forum->is_locked && !$user->isAdmin()) {
            abort(403, 'This forum is locked.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $thread = $forum->threads()->create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return response()->json($thread->load('author'), 201);
    }

    /**
     * Update a thread.
     */
    public function update(Request $request, ForumThread $thread): JsonResponse
    {
        $user = Auth::user();

        if (!$thread->canEdit($user)) {
            abort(403, 'You do not have permission to edit this thread.');
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'body' => 'string',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
        ]);

        // Only admins can pin/lock
        if (isset($validated['is_pinned']) || isset($validated['is_locked'])) {
            $this->authorize('admin');
        }

        $thread->update($validated);

        return response()->json($thread->load('author'));
    }

    /**
     * Delete a thread.
     */
    public function destroy(ForumThread $thread): JsonResponse
    {
        $user = Auth::user();

        if (!$thread->canDelete($user)) {
            abort(403, 'You do not have permission to delete this thread.');
        }

        $thread->delete();

        return response()->json(['message' => 'Thread deleted successfully']);
    }
}
