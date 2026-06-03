<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Forum\ForumThreadRead;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
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
            ->orderBy('sort')
            ->get()
            ->each(function ($cat) {
                // Query the actual latest ForumPost across all threads in this category
                $latestPost = ForumPost::whereIn(
                        'thread_id',
                        $cat->forumThreads()->select('id')
                    )
                    ->with('author')
                    ->orderByDesc('created_at')
                    ->first();

                $cat->setAttribute('latestPost', $latestPost);

                // Fall back to thread author if no replies exist yet
                if (!$latestPost) {
                    $latestThread = $cat->forumThreads()
                        ->with('author')
                        ->orderByDesc('created_at')
                        ->first();
                    $cat->setRelation('latestThread', $latestThread);
                }
            })
            ->filter(fn($cat) => $this->canAccessForum($cat, $user))
            ->values();

        // Compute has_unread per category for authenticated users
        $categoryUnread = [];
        if ($user) {
            $catIds = $categories->pluck('id')->all();
            $childIds = $categories->flatMap(fn($cat) => $cat->children->pluck('id'))->all();
            $allCatIds = array_merge($catIds, $childIds);

            // Get threads with activity since previous_login_at (new threads OR new replies)
            // Exclude threads where the current user made the last post
            $threadsQuery = ForumThread::whereIn('taxonomy_id', $allCatIds)
                ->where('last_post_user_id', '!=', $user->id);
            if ($user->previous_login_at) {
                $threadsQuery->where('last_post_at', '>', $user->previous_login_at);
            }
            $threadRows = $threadsQuery->select('id', 'taxonomy_id', 'last_post_at')->get();

            // Get user's read records for these threads (keyed by thread_id)
            $readRecords = ForumThreadRead::where('user_id', $user->id)
                ->whereIn('thread_id', $threadRows->pluck('id'))
                ->pluck('read_at', 'thread_id');

            // Build unread set per parent category
            foreach ($categories as $cat) {
                $ownAndChildIds = collect([$cat->id])
                    ->merge($cat->children->pluck('id'))
                    ->all();

                $unread = $threadRows
                    ->whereIn('taxonomy_id', $ownAndChildIds)
                    ->contains(function ($thread) use ($readRecords) {
                        $readAt = $readRecords[$thread->id] ?? null;
                        // Unread if never opened, or if new posts since last read
                        return !$readAt || $thread->last_post_at > $readAt;
                    });

                $categoryUnread[$cat->id] = $unread;
            }
        }

        $result = $categories->map(function ($cat) use ($categoryUnread) {
            $data = $this->transformCategory($cat);
            $data['has_unread'] = $categoryUnread[$cat->id] ?? false;
            return $data;
        });

        return response()->json($result);
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

        if (!$this->canAccessForum($taxonomy, $user)) {
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

        // Add is_read flag for authenticated users
        if ($user) {
            $threadIds = $threads->pluck('id');
            $readRecords = ForumThreadRead::where('user_id', $user->id)
                ->whereIn('thread_id', $threadIds)
                ->pluck('read_at', 'thread_id');

            $threads->getCollection()->transform(function ($thread) use ($readRecords, $user) {
                // If the user made the last post, they already know about it
                if ($thread->last_post_user_id === $user->id) {
                    $thread->is_read = true;
                    return $thread;
                }

                $readAt = $readRecords[$thread->id] ?? null;
                if ($readAt) {
                    $thread->is_read = $readAt >= $thread->last_post_at;
                } else {
                    $thread->is_read = $user->previous_login_at && $thread->last_post_at <= $user->previous_login_at;
                }
                return $thread;
            });
        }

        return response()->json([
            'forum' => $this->transformCategory($taxonomy),
            'threads' => $threads,
            'can_create_thread' => $this->canPostInForum($taxonomy, $user) && ($user !== null),
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
            'allowed_roles' => 'nullable|array',
            'allowed_roles.*' => 'string',
            'post_roles' => 'nullable|array',
            'post_roles.*' => 'string',
            'moderate_roles' => 'nullable|array',
            'moderate_roles.*' => 'string',
            'delete_roles' => 'nullable|array',
            'delete_roles.*' => 'string',
        ]);

        // Resolve parent: if parent_id is given, find the taxonomy
        $parentTaxonomyId = null;
        if (!empty($validated['parent_id'])) {
            $parent = Taxonomy::taxonomy('forum_cat')->findOrFail($validated['parent_id']);
            $parentTaxonomyId = $parent->id;
        }

        $term = Term::firstOrCreateByTitle($validated['name']);

        $taxonomy = Taxonomy::create([
            'term_id'    => $term->id,
            'taxonomy'   => 'forum_cat',
            'parent_id'  => $parentTaxonomyId,
            'sort'       => $validated['position'] ?? 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => [
                'is_private'    => $validated['is_private'] ?? false,
                'is_locked'     => $validated['is_locked'] ?? false,
                'allowed_roles'  => $validated['allowed_roles'] ?? [],
                'post_roles'     => $validated['post_roles'] ?? [],
                'moderate_roles' => $validated['moderate_roles'] ?? [],
                'delete_roles'   => $validated['delete_roles'] ?? [],
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
            'allowed_roles' => 'nullable|array',
            'allowed_roles.*' => 'string',
            'post_roles' => 'nullable|array',
            'post_roles.*' => 'string',
            'moderate_roles' => 'nullable|array',
            'moderate_roles.*' => 'string',
            'delete_roles' => 'nullable|array',
            'delete_roles.*' => 'string',
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
        if (array_key_exists('allowed_roles', $validated)) {
            $properties['allowed_roles'] = $validated['allowed_roles'] ?? [];
        }
        if (array_key_exists('post_roles', $validated)) {
            $properties['post_roles'] = $validated['post_roles'] ?? [];
        }
        if (array_key_exists('moderate_roles', $validated)) {
            $properties['moderate_roles'] = $validated['moderate_roles'] ?? [];
        }
        if (array_key_exists('delete_roles', $validated)) {
            $properties['delete_roles'] = $validated['delete_roles'] ?? [];
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
     * Get the newest threads across all categories (including child categories).
     * Supports ?filter=popular|unanswered|mine
     */
    public function recentThreads(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Exclude private categories the user can't access (and their children)
        $excludeIds = [];
        $privateCats = Taxonomy::taxonomy('forum_cat')
            ->get()
            ->filter(fn($cat) => ($cat->properties['is_private'] ?? false) && !$this->canAccessForum($cat, $user));

        foreach ($privateCats as $cat) {
            $excludeIds[] = $cat->id;
            // Also exclude child categories of private parents
            $childIds = Taxonomy::taxonomy('forum_cat')
                ->where('parent_id', $cat->id)
                ->pluck('id')
                ->all();
            $excludeIds = array_merge($excludeIds, $childIds);
        }

        $query = ForumThread::with(['author', 'category.term', 'lastPostUser'])
            ->withCount('posts');

        if (!empty($excludeIds)) {
            $query->whereNotIn('taxonomy_id', $excludeIds);
        }

        // Apply filter
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
        }

        // Default sort by newest unless popular filter already sorts
        if ($filter !== 'popular') {
            $query->orderByDesc('created_at');
        }

        $threads = $query->paginate(15);

        // Build read records for authenticated user
        $readRecords = collect();
        if ($user) {
            $threadIds = $threads->pluck('id');
            $readRecords = ForumThreadRead::where('user_id', $user->id)
                ->whereIn('thread_id', $threadIds)
                ->pluck('read_at', 'thread_id');
        }

        // Transform to include category color and is_read flag
        $threads->getCollection()->transform(function ($thread) use ($user, $readRecords) {
            $thread->category_name = $thread->category?->term?->title;
            $thread->category_slug = $thread->category?->term?->slug;
            $thread->category_color = $thread->category?->color;

            if ($user) {
                if ($thread->last_post_user_id === $user->id) {
                    $thread->is_read = true;
                    return $thread;
                }

                $readAt = $readRecords[$thread->id] ?? null;
                if ($readAt) {
                    $thread->is_read = $readAt >= $thread->last_post_at;
                } else {
                    $thread->is_read = $user->previous_login_at && $thread->last_post_at <= $user->previous_login_at;
                }
            }

            return $thread;
        });

        return response()->json($threads);
    }

    /**
     * Check if a user can access (view) a forum category.
     */
    private function canAccessForum(Taxonomy $cat, ?User $user): bool
    {
        if (!($cat->properties['is_private'] ?? false)) {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $allowedRoles = $cat->properties['allowed_roles'] ?? [];

        if (empty($allowedRoles)) {
            return false;
        }

        return $user->hasAnyRole($allowedRoles);
    }

    /**
     * Check if a user can create threads in a forum category.
     */
    private function canPostInForum(Taxonomy $cat, ?User $user): bool
    {
        if (!($cat->properties['is_locked'] ?? false)) {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $postRoles = $cat->properties['post_roles'] ?? [];

        if (empty($postRoles)) {
            return false;
        }

        return $user->hasAnyRole($postRoles);
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
            'color'         => $cat->color,
            'is_private'    => $cat->properties['is_private'] ?? false,
            'is_locked'     => $cat->properties['is_locked'] ?? false,
            'allowed_roles'  => $cat->properties['allowed_roles'] ?? [],
            'post_roles'     => $cat->properties['post_roles'] ?? [],
            'moderate_roles' => $cat->properties['moderate_roles'] ?? [],
            'delete_roles'   => $cat->properties['delete_roles'] ?? [],
            'threads_count' => $cat->forum_threads_count ?? 0,
            'posts_count'   => $cat->forum_posts_count ?? 0,
            'last_post_at'  => $cat->latestPost
                ? $cat->latestPost->created_at
                : ($cat->relationLoaded('latestThread') && $cat->latestThread
                    ? $cat->latestThread->created_at
                    : null),
            'lastPost'      => $cat->latestPost
                ? [
                    'author' => $cat->latestPost->author ? [
                        'id'       => $cat->latestPost->author->id,
                        'username' => $cat->latestPost->author->username,
                        'avatar'   => $cat->latestPost->author->avatar ?? null,
                    ] : null,
                ]
                : ($cat->relationLoaded('latestThread') && $cat->latestThread
                    ? [
                        'author' => $cat->latestThread->author ? [
                            'id'       => $cat->latestThread->author->id,
                            'username' => $cat->latestThread->author->username,
                            'avatar'   => $cat->latestThread->author->avatar ?? null,
                        ] : null,
                    ]
                    : null),
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
