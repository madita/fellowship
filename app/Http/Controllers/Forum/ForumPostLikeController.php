<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum\ForumPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ForumPostLikeController extends Controller
{
    /**
     * Like a post.
     */
    public function store(ForumPost $post): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $post->like($user);

        activity('forum')
            ->performedOn($post)
            ->causedBy($user)
            ->withProperties(['thread_title' => $post->thread->title])
            ->event('post_liked')
            ->log('liked a post');

        return response()->json([
            'is_liked' => true,
            'like_count' => $post->fresh()->like_count,
        ]);
    }

    /**
     * Unlike a post.
     */
    public function destroy(ForumPost $post): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $post->unlike($user);

        return response()->json([
            'is_liked' => false,
            'like_count' => $post->fresh()->like_count,
        ]);
    }
}
