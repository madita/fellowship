<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->only(['update']);
    }

    /**
     * List published posts.
     */
    public function index(Request $request): JsonResponse
    {
        $posts = Post::with('translations')
            ->where('status', 'published')
            ->latest()
            ->paginate($request->get('per_page', 12));

        return response()->json($posts);
    }

    /**
     * View a single post by slug.
     */
    public function view($slug): JsonResponse
    {
        $post = Post::with('translations')
            ->where('slug', '=', $slug)
            ->first();

        if (! $post || $post->status !== 'published') {
            return abort(404);
        }

        $taxonomies = $post->taxonomies()->get()->groupBy('taxonomy')->map(function ($items) {
            return $items->filter(fn ($t) => $t->term)->map(function ($t) {
                return [
                    'id' => $t->term->id,
                    'name' => $t->term->title,
                    'slug' => $t->term->slug,
                    'color' => $t->color,
                ];
            })->values();
        });

        return response()->json(['data' => $post, 'taxonomies' => $taxonomies]);
    }

    /**
     * Show post for editing.
     */
    public function show(Post $post): JsonResponse
    {
        if (! $post) {
            return abort(404);
        }

        $post->load('translations');

        return response()->json(['data' => $post]);
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->all();
        $post->fill($data);

        $post->save();

        return response()->json([
            'message' => __('messages.success.updated', ['item' => 'Post']),
        ]);
    }
}
