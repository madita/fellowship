<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum\ForumPostLike;
use App\Models\Forum\ForumThread;
use App\Models\Forum\ForumThreadRead;
use App\Models\Tag\Taxonomy;
use App\Services\SpamDetectionService;
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
            ->with(['category.term', 'author'])
            ->firstOrFail();

        // Check access via category properties
        $category = $thread->category;
        if (($category->properties['is_private'] ?? false) && (!$user || !$user->isAdmin())) {
            abort(403, 'You do not have permission to access this thread.');
        }

        // Increment view count (throttle to once per session)
        $viewedKey = "thread_viewed_{$thread->id}";
        if (!session()->has($viewedKey)) {
            $thread->incrementViews();
            session()->put($viewedKey, true);
        }

        // Mark thread as read for authenticated user
        if ($user) {
            ForumThreadRead::updateOrCreate(
                ['user_id' => $user->id, 'thread_id' => $thread->id],
                ['read_at' => now()]
            );
        }

        // Get all posts flat, ordered by creation date
        $posts = $thread->posts()
            ->with(['author'])
            ->orderBy('created_at')
            ->paginate(20);

        // Batch query liked post IDs for the authenticated user
        $likedPostIds = [];
        if ($user) {
            $postIds = collect($posts->items())->pluck('id');
            $likedPostIds = ForumPostLike::where('user_id', $user->id)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->toArray();
        }

        // Add is_liked to each post
        $postsData = $posts->toArray();
        foreach ($postsData['data'] as &$postData) {
            $postData['is_liked'] = in_array($postData['id'], $likedPostIds);
        }

        return response()->json([
            'thread' => $thread,
            'posts' => $postsData,
            'can_reply' => $thread->canReply($user),
            'can_edit' => $thread->canEdit($user),
            'can_delete' => $thread->canDelete($user),
            'is_subscribed' => $thread->isSubscribedBy($user),
        ]);
    }

    /**
     * Create a new thread.
     */
    public function store(
        Request $request,
        int $id,
        SpamDetectionService $spamService
    ): JsonResponse {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'You must be logged in to create a thread.');
        }

        $category = Taxonomy::taxonomy('forum_cat')->findOrFail($id);

        // Check access
        if (($category->properties['is_private'] ?? false) && !$user->isAdmin()) {
            abort(403, 'You do not have permission to post in this forum.');
        }

        if (($category->properties['is_locked'] ?? false) && !$user->isAdmin()) {
            abort(403, 'This forum is locked.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        // Spam check
        $spamResult = $spamService->checkThreadCreation($user, $validated['body']);
        if ($spamResult) {
            abort($spamResult['status'], $spamResult['message']);
        }

        $thread = $category->forumThreads()->create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        // Auto-subscribe thread author
        $thread->subscribe($user);

        // Record activity
        activity('forum')
            ->performedOn($thread)
            ->causedBy($user)
            ->withProperties(['thread_title' => $thread->title, 'forum_name' => $category->term->title])
            ->event('thread_created')
            ->log('created a new thread');

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
