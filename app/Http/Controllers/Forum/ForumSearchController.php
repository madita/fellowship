<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:200',
            'type' => 'nullable|string|in:threads,posts,all',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = $request->input('q');
        $type = $request->input('type', 'all');
        $page = $request->input('page', 1);
        $perPage = 10;

        // Build filter to exclude private categories for non-admin users
        $filter = $this->buildPrivateCategoryFilter();

        $threads = ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];
        $posts = ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];

        if ($type === 'all' || $type === 'threads') {
            $threads = $this->searchThreads($query, $filter, $page, $perPage);
        }

        if ($type === 'all' || $type === 'posts') {
            $posts = $this->searchPosts($query, $filter, $page, $perPage);
        }

        return response()->json([
            'query' => $query,
            'threads' => $threads,
            'posts' => $posts,
        ]);
    }

    private function searchThreads(string $query, ?string $filter, int $page, int $perPage): array
    {
        $scoutBuilder = ForumThread::search($query);

        if ($filter) {
            $scoutBuilder->options(['filter' => $filter]);
        }

        $results = $scoutBuilder->paginate($perPage, 'page', $page);

        // Re-fetch with eager-loaded relations
        $threadIds = collect($results->items())->pluck('id')->all();
        $threads = ForumThread::whereIn('id', $threadIds)
            ->with(['author', 'category.term', 'lastPostUser'])
            ->get()
            ->sortBy(function ($thread) use ($threadIds) {
                return array_search($thread->id, $threadIds);
            })
            ->values()
            ->map(fn($thread) => $this->transformThread($thread));

        return [
            'data' => $threads,
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
        ];
    }

    private function searchPosts(string $query, ?string $filter, int $page, int $perPage): array
    {
        $scoutBuilder = ForumPost::search($query);

        if ($filter) {
            $scoutBuilder->options(['filter' => $filter]);
        }

        $results = $scoutBuilder->paginate($perPage, 'page', $page);

        // Re-fetch with eager-loaded relations
        $postIds = collect($results->items())->pluck('id')->all();
        $posts = ForumPost::whereIn('id', $postIds)
            ->with(['author', 'thread.category.term'])
            ->get()
            ->sortBy(function ($post) use ($postIds) {
                return array_search($post->id, $postIds);
            })
            ->values()
            ->map(fn($post) => $this->transformPost($post));

        return [
            'data' => $posts,
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
        ];
    }

    private function buildPrivateCategoryFilter(): ?string
    {
        $user = Auth::user();

        if ($user && $user->isAdmin()) {
            return null;
        }

        $privateIds = Taxonomy::taxonomy('forum_cat')
            ->get()
            ->filter(fn($cat) => $cat->properties['is_private'] ?? false)
            ->pluck('id')
            ->all();

        if (empty($privateIds)) {
            return null;
        }

        // Meilisearch filter syntax: exclude each private taxonomy_id
        $filters = array_map(fn($id) => "taxonomy_id != {$id}", $privateIds);

        return implode(' AND ', $filters);
    }

    private function transformThread(ForumThread $thread): array
    {
        return [
            'id' => $thread->id,
            'title' => $thread->title,
            'body' => $thread->body,
            'slug' => $thread->slug,
            'author' => $thread->author ? [
                'id' => $thread->author->id,
                'username' => $thread->author->username,
                'avatar' => $thread->author->avatar ?? null,
            ] : null,
            'category_name' => $thread->category?->term?->title,
            'category_slug' => $thread->category?->term?->slug,
            'is_pinned' => $thread->is_pinned,
            'is_locked' => $thread->is_locked,
            'reply_count' => $thread->reply_count ?? 0,
            'view_count' => $thread->view_count ?? 0,
            'created_at' => $thread->created_at,
            'last_post_at' => $thread->last_post_at,
        ];
    }

    private function transformPost(ForumPost $post): array
    {
        return [
            'id' => $post->id,
            'body' => $post->body,
            'author' => $post->author ? [
                'id' => $post->author->id,
                'username' => $post->author->username,
                'avatar' => $post->author->avatar ?? null,
            ] : null,
            'thread_id' => $post->thread_id,
            'thread_title' => $post->thread?->title,
            'thread_slug' => $post->thread?->slug,
            'category_name' => $post->thread?->category?->term?->title,
            'category_slug' => $post->thread?->category?->term?->slug,
            'is_solution' => $post->is_solution,
            'created_at' => $post->created_at,
        ];
    }
}
