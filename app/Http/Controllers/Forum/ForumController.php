<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumController extends Controller
{
    /**
     * Get all forum categories with statistics.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $categories = Taxonomy::taxonomy('forum_cat')
            ->whereNull('parent_id')
            ->with([
                'term',
                'children' => fn($q) => $q->with('term')->withCount('forumThreads')->orderBy('sort'),
            ])
            ->withCount(['forumThreads', 'forumPosts'])
            ->addSelect(['latest_post_at' => ForumThread::select('last_post_at')
                ->whereColumn('taxonomy_id', 'taxonomies.id')
                ->orderByDesc('last_post_at')
                ->limit(1),
            ])
            ->orderBy('sort')
            ->get()
            ->each(function ($cat) {
                $latestThread = $cat->forumThreads()
                    ->orderByDesc('last_post_at')
                    ->with('lastPostUser')
                    ->first();
                $cat->setRelation('latestThread', $latestThread);
            })
            ->filter(function ($cat) use ($user) {
                if ($cat->properties['is_private'] ?? false) {
                    return $user && $user->isAdmin();
                }
                return true;
            })
            ->values()
            ->map(fn($cat) => $this->transformCategory($cat));

        return response()->json($categories);
    }

    /**
     * Get a specific forum category with its threads.
     * Supports ?filter=popular|unanswered|mine|solved and ?sort=latest|oldest|most_views
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $user = Auth::user();

        $taxonomy = Taxonomy::taxonomy('forum_cat')
            ->whereHas('term', fn($q) => $q->where('slug', $slug))
            ->with(['term', 'parent.term', 'children.term'])
            ->withCount('forumThreads')
            ->firstOrFail();

        if (($taxonomy->properties['is_private'] ?? false) && (!$user || !$user->isAdmin())) {
            abort(403, 'You do not have permission to access this forum.');
        }

        // Build threads query with filter/sort
        $query = ForumThread::where('taxonomy_id', $taxonomy->id)
            ->with(['category.term', 'author', 'lastPostUser']);

        $filter = $request->query('filter');
        switch ($filter) {
            case 'popular':
                $query->orderByDesc('reply_count');
                break;
            case 'unanswered':
                $query->where('reply_count', 0);
                break;
            case 'mine':
                if ($user) {
                    $query->where('user_id', $user->id);
                }
                break;
            case 'solved':
                $query->whereHas('posts', function ($q) {
                    $q->where('is_solution', true);
                });
                break;
        }

        $sort = $request->query('sort', 'latest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at');
                break;
            case 'most_views':
                $query->orderByDesc('view_count');
                break;
            default: // 'latest'
                if ($filter !== 'popular') {
                    $query->orderByDesc('is_pinned')->orderByDesc('last_post_at');
                }
                break;
        }

        $threads = $query->paginate(20);

        return response()->json([
            'forum' => $this->transformCategory($taxonomy),
            'threads' => $threads,
        ]);
    }

    /**
     * Create a new forum category (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer',
            'position' => 'nullable|integer',
            'is_private' => 'boolean',
            'is_locked' => 'boolean',
        ]);

        // Resolve parent: if parent_id is given, find the taxonomy
        $parentTaxonomyId = null;
        if (!empty($validated['parent_id'])) {
            $parent = Taxonomy::taxonomy('forum_cat')->findOrFail($validated['parent_id']);
            $parentTaxonomyId = $parent->id;
        }

        $term = Term::firstOrCreate(['title' => $validated['name']]);

        $taxonomy = Taxonomy::create([
            'term_id'    => $term->id,
            'taxonomy'   => 'forum_cat',
            'parent_id'  => $parentTaxonomyId,
            'sort'       => $validated['position'] ?? 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => [
                'is_private' => $validated['is_private'] ?? false,
                'is_locked'  => $validated['is_locked'] ?? false,
            ],
        ]);

        if (!empty($validated['description'])) {
            $taxonomy->description = $validated['description'];
            $taxonomy->save();
        }

        $taxonomy->load(['term', 'children.term']);
        $taxonomy->loadCount('forumThreads');

        return response()->json($this->transformCategory($taxonomy), 201);
    }

    /**
     * Update a forum category (admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('admin');

        $taxonomy = Taxonomy::taxonomy('forum_cat')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer',
            'position' => 'integer',
            'is_private' => 'boolean',
            'is_locked' => 'boolean',
        ]);

        // Update term title if name changed
        if (isset($validated['name'])) {
            $term = $taxonomy->term;
            $term->title = $validated['name'];
            $term->save();
        }

        // Update description (translatable)
        if (array_key_exists('description', $validated)) {
            $taxonomy->description = $validated['description'];
        }

        // Update parent
        if (array_key_exists('parent_id', $validated)) {
            if ($validated['parent_id']) {
                $parent = Taxonomy::taxonomy('forum_cat')->findOrFail($validated['parent_id']);
                $taxonomy->parent_id = $parent->id;
            } else {
                $taxonomy->parent_id = null;
            }
        }

        if (isset($validated['position'])) {
            $taxonomy->sort = $validated['position'];
        }

        // Merge properties
        $properties = $taxonomy->properties ?? [];
        if (isset($validated['is_private'])) {
            $properties['is_private'] = $validated['is_private'];
        }
        if (isset($validated['is_locked'])) {
            $properties['is_locked'] = $validated['is_locked'];
        }
        $taxonomy->properties = $properties;

        $taxonomy->save();

        $taxonomy->load(['term', 'children.term']);
        $taxonomy->loadCount('forumThreads');

        return response()->json($this->transformCategory($taxonomy));
    }

    /**
     * Delete a forum category (admin only).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('admin');

        $taxonomy = Taxonomy::taxonomy('forum_cat')->findOrFail($id);
        $taxonomy->delete();

        return response()->json(['message' => 'Forum deleted successfully']);
    }

    /**
     * Transform a Taxonomy into the forum category API shape.
     */
    private function transformCategory(Taxonomy $cat): array
    {
        return [
            'id'            => $cat->id,
            'name'          => $cat->term->title,
            'slug'          => $cat->term->slug,
            'description'   => $cat->description,
            'parent_id'     => $cat->parent_id,
            'position'      => $cat->sort,
            'is_private'    => $cat->properties['is_private'] ?? false,
            'is_locked'     => $cat->properties['is_locked'] ?? false,
            'threads_count' => $cat->forum_threads_count ?? 0,
            'posts_count'   => $cat->forum_posts_count ?? 0,
            'last_post_at'  => $cat->latest_post_at ?? null,
            'lastPost'      => $cat->relationLoaded('latestThread') && $cat->latestThread
                ? [
                    'author' => $cat->latestThread->lastPostUser ? [
                        'id'       => $cat->latestThread->lastPostUser->id,
                        'username' => $cat->latestThread->lastPostUser->username,
                        'avatar'   => $cat->latestThread->lastPostUser->avatar ?? null,
                    ] : null,
                ]
                : null,
            'children'      => $cat->relationLoaded('children')
                ? $cat->children->map(fn($c) => $this->transformCategory($c))->values()
                : [],
            'parent'        => $cat->relationLoaded('parent') && $cat->parent
                ? [
                    'id'   => $cat->parent->id,
                    'name' => $cat->parent->term->title,
                    'slug' => $cat->parent->term->slug,
                ]
                : null,
        ];
    }
}
