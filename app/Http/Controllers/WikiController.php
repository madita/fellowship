<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Revision;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Models\Wiki;
use App\Notifications\WikiUpdatedNotification;
use App\Support\TaxonomyTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WikiController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function getUpdatableColumns($type)
    {
        return [
            'title',
            'content',
            'published_at',
            'sign_in_only',
        ];
    }

    /**
     * view landing pages.
     *
     * @param  $slug
     * @return JsonResponse|never
     */
    public function index(Request $request)
    {
        $perPage = 9;
        $query   = $request->get('q');
        $user    = Auth::user();

        $page = request()->input('page', 1); // Current page number, default to 1

        $wikiQuery = Wiki::where('status', null);

        // Everybody else only sees approved pages
        if ( ! $this->seesPending($user)) {
            $wikiQuery->approved();
        }

        if ($query !== null && $query !== '') {
            $wikiQuery->whereTranslationLike('title', '%' . $query . '%');
        }

        $wikidata = $wikiQuery->with('approval')->orderBy('created_at', 'desc')->paginate($perPage);

        $total = $wikidata->total();

        $wiki = $wikidata->getCollection()->map(function (Wiki $wiki) {
            $model = $wiki->wikiable_type;
            $data  = $model::where('id', $wiki->wikiable_id)->first();

            $taxonomies = $data->getCategories('wiki')->unique();
            $tags       = $data->getCategories('tags')->unique();

            return [
                'title'       => $wiki->title,
                'slug'        => $wiki->slug,
                'type'        => Str::lower(Str::afterLast($wiki->wikiable_type, '\\')),
                'model'       => $wiki->wikiable_type,
                'data'        => $data,
                'taxonomies'  => $taxonomies,
                'tags'        => $tags,
                'is_approved' => $wiki->isApproved(),
                'approved_at' => $wiki->approval?->approved_at,
            ];
        });

        $paginator = new LengthAwarePaginator(
            $wiki,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        //        $wikis = $paginator->values();

        $links = [];

        for ($cnt = $page; $cnt <= $page + 5; $cnt++) {
            $links[] = [
                'active' => $cnt === $page ? true : false,
                'label'  => $cnt,
                'url'    => request()->url() . "?page{$cnt}&q={$query}",
            ];
        }

        return response()->json([
            'data'          => $paginator->values(),
            'total'         => $total,
            'to'            => $perPage * $page,
            'per_page'      => $perPage,
            'current_page'  => $page,
            'first_page'    => $paginator->url(1),
            'last_page'     => $paginator->lastPage(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'path'          => request()->url(),
            'links'         => $links,
        ]);

        //        $wiki->total = $wikidata->total;
        //        $wiki->to = $wiki->per_page*$wiki->current_page;

        //        return response()->json($paginator);
    }

    /**
     * Recently created or edited wiki pages, newest change first — one entry
     * per page, drawn from the revision log. Non-admins only see approved
     * pages. Query: limit (default 5, max 20).
     */
    public function recentChanges(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->get('limit', 5), 20));
        $user  = Auth::user();

        // Latest revision per page. The morph relation stores the class name;
        // older rows may carry the table name the listener passes.
        $revisions = Revision::with('executor')
            ->whereIn('revisionable_type', [(new Page)->getMorphClass(), (new Page)->getTable()])
            ->whereIn('action', ['created', 'updated'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit * 10)
            ->get()
            ->unique('revisionable_id')
            ->take($limit * 2);

        $pageIds = $revisions->pluck('revisionable_id');

        $wikis = Wiki::where('wikiable_type', Page::class)
            ->whereIn('wikiable_id', $pageIds)
            ->whereNull('status')
            ->when( ! $this->seesPending($user), fn ($q) => $q->approved())
            ->get()
            ->keyBy('wikiable_id');

        $pages = Page::whereIn('id', $pageIds)->get()->keyBy('id');

        $changes = $revisions
            ->filter(fn (Revision $revision) => $wikis->has($revision->revisionable_id) && $pages->has($revision->revisionable_id))
            ->take($limit)
            ->map(function (Revision $revision) use ($wikis, $pages) {
                $page = $pages->get($revision->revisionable_id);
                $wiki = $wikis->get($revision->revisionable_id);

                return [
                    'id'     => $revision->id,
                    'action' => $revision->action,
                    'title'  => $page->title,
                    'slug'   => $wiki->slug,
                    'url'    => '/wiki/' . $wiki->slug,
                    'author' => $revision->executor?->only(['id', 'username']),
                    'date'   => $revision->created_at,
                ];
            })
            ->values();

        return response()->json(['data' => $changes]);
    }

    /**
     * Every recorded change to one wiki page, newest first.
     *
     * Metadata only: which fields changed, by whom and when. The text of a
     * version is fetched one at a time through historyVersion, because a wiki
     * page can be long and a history list does not need all of it at once.
     */
    public function history(string $slug): JsonResponse
    {
        $wiki = $this->wikiForHistory($slug);
        $page = $this->pageBehind($wiki);

        $history = $this->revisionsOf($page)
            ->map(function (Revision $revision) {
                $diff = $revision->getDiff();

                return [
                    'id'      => $revision->id,
                    'action'  => $revision->action,
                    'author'  => $revision->executor?->only(['id', 'username']),
                    'date'    => $revision->created_at,
                    'fields'  => array_keys($diff),
                    'title'   => $diff['title']['new_value'] ?? null,
                    // Enough to recognise the change without shipping the page.
                    'excerpt' => $this->excerpt($diff['content']['new_value'] ?? null),
                ];
            })
            ->values();

        return response()->json(['data' => $history]);
    }

    /**
     * One version of a wiki page, as it stood when that revision was written.
     */
    public function historyVersion(string $slug, int $revision): JsonResponse
    {
        $wiki = $this->wikiForHistory($slug);
        $page = $this->pageBehind($wiki);

        $revisions = $this->revisionsOf($page);
        $target    = $revisions->firstWhere('id', $revision);

        if ( ! $target) {
            abort(404);
        }

        // Older to newer, up to and including the one asked for: a revision
        // only records what changed, so the text at that point is the newest
        // value written at or before it. Without this a revision that only
        // renamed the page would come back with no text at all.
        $upTo = $revisions->filter(fn (Revision $item) => $item->id <= $target->id)->sortBy('id');

        return response()->json([
            'data' => [
                'id'      => $target->id,
                'action'  => $target->action,
                'author'  => $target->executor?->only(['id', 'username']),
                'date'    => $target->created_at,
                'title'   => $this->valueAsOf($upTo, 'title') ?? $page->title,
                'content' => $this->valueAsOf($upTo, 'content') ?? '',
                'diff'    => $target->getDiff(),
                'current' => $target->id === $revisions->max('id'),
            ],
        ]);
    }

    public function getPages()
    {
        $user = Auth::user();

        $wikidata = $this->seesPending($user) ? Wiki::all() : Wiki::approved()->get();

        return response()->json($wikidata);
    }

    /**
     * view landing pages.
     *
     * @param  $slug
     * @return JsonResponse|never
     */
    public function view($wikiable, $id)
    {
    }

    public function show($slug)
    {
        $wiki = Wiki::where('slug', '=', $slug)->first();

        if ($wiki === null) {
            $data = ['slug' => $slug, 'title' => Str::ucfirst($slug), 'content' => ''];

            return response(['status' => 404, 'message' => __('messages.wiki.create_page'), 'page' => $data], 404);
        }

        // The author's own page, and whoever will decide on it
        $currentUser = Auth::user();

        if ( ! $wiki->isVisibleTo($currentUser)) {
            abort(403, 'This page is pending approval.');
        }

        $model = $wiki->wikiable_type;

        $data = $model::where('id', $wiki->wikiable_id)->first();

        $content = $data->content;

        preg_match_all(
            "/\[\[(.*?)(\|(.*?))?\]\]/",
            $content,
            $matches
        );

        foreach ($matches[0] as $key => $item) {
            // [[Target#anchor|Label]] — the link points at Target; the label
            // is only what gets displayed.
            $target      = explode('#', $matches[1][$key])[0];
            $label       = isset($matches[3][$key]) && trim($matches[3][$key]) != '' ? $matches[3][$key] : $target;
            $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != '' ? $matches[3][$key] : null;

            // title lives in page_translations — match via translation or slug,
            // falling back to an unsaved page for the "create this page" link.
            $page = Page::whereTranslation('title', $target)->first()
                ?? Page::where('slug', Str::slug($target))->first()
                ?? new Page(['title' => $target, 'slug' => Str::slug($target)]);

            $replace =
                "<a data-wiki-id=\"0\" class=\"new\" data-title=\"{$page->title}\" data-linked-resource-type=\"wikiable\" data-alternative=\"{$alternative}\" href=\"/wiki/{$page->slug}\" contenteditable=\"false\">{$label}</a>";
            $content = Str::replace($item, $replace, $content);
        }
        $data->content = $content;
        $taxonomies    = $data->getCategories('wiki')->unique();
        $terms         = $data->getCategories('tags')->unique();
        $user          = $data->user;

        $approval = $wiki->approval;

        return response()->json([
            'page'           => $data,
            'user'           => $user,
            'wiki'           => $wiki,
            'parent'         => $wiki->parent,
            // The whole way up, for the trail above the title. Nearest
            // first, the way the model walks it, and reduced to what a
            // link needs — the page has always read `parents` and only
            // `parent` was ever sent, so there was nothing to build a
            // trail from. PageController@view does the same for pages.
            'parents'        => $wiki->parents->map(fn (Wiki $ancestor) => [
                'id'    => $ancestor->id,
                'title' => $ancestor->title,
                'slug'  => $ancestor->slug,
            ])->values(),
            'children'       => $wiki->children,
            'terms'          => $taxonomies,
            'tags'           => $terms,
            'is_approved'    => $approval !== null,
            'approved_at'    => $approval?->approved_at,
            'approved_by'    => $approval?->approver?->name,
            'is_watching'    => $wiki->isWatchedBy($currentUser),
            'watchers_count' => $wiki->watches()->count(),
        ]);
    }

    public function store(Request $request)
    {
        // Authorization check
        if ( ! auth()->check()) {
            abort(401, 'Authentication required to create wiki pages');
        }

        // Every member has always been able to write a page and have it
        // wait for approval, so the `user` role is given create-wiki by
        // the migration rather than this becoming a new door. Saying it
        // out loud is what lets it be taken away from one person.
        if ( ! auth()->user()->can('create-wiki')) {
            abort(403, __('messages.error.permission_required'));
        }

        // Input validation
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'slug'       => 'nullable|string|max:255|unique:wikiables,slug',
            'parent'     => 'nullable|array',
            'parent_id'  => 'nullable',
            'categories' => 'nullable|array',
            'terms'      => 'nullable|array',
        ]);

        $parent_id = $this->resolveParentId($request);

        // Sanitize content (strip potentially dangerous tags/attributes)
        $content = strip_tags($validated['content'], '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><img><table><thead><tbody><tr><td><th>');

        $page = auth()->user()->pages()->create([
            'title'        => $validated['title'],
            'content'      => $content,
            'sign_in_only' => 0,
            'published_at' => now()]);

        if ($request->get('categories')) {
            //            $taxonomy = $request->get('taxonomy');
            //            $taxonomy = $taxonomy['taxonomy'];
            //            $page->addCategories($request->get('categories'), $taxonomy);

            foreach ($request->get('categories') as $term) {
                if (isset($term['title'])) {
                    $page->addCategory($term['title'], 'wiki');
                } else {
                    $page->addCategory($term, 'wiki');
                }
            }
        }

        if ($request->get('terms')) {
            foreach ($request->get('terms') as $term) {
                if (isset($term['title'])) {
                    $page->addCategory($term['title'], 'tags');
                } else {
                    $page->addCategory($term, 'tags');
                }
            }
        }
        $wiki = new Wiki([
            'title'     => $page->title,
            'slug'      => $request->get('slug') ?: Str::slug($validated['title']),
            'parent_id' => $parent_id,
        ]);

        $page->wikiable()->save($wiki);

        // The page only has its wiki address now; an approved page can tell
        // whoever it mentions (a pending one does so once it is approved).
        $page->notifyMentionedMembers();
        $wiki->isApproved() ? $wiki->afterApproved() : $wiki->announceSubmission();

        return response()->json(['message' => __('messages.wiki.created'), 'page' => $page]);
    }

    public function update(Request $request, $slug)
    {
        // Authorization check
        if ( ! auth()->check()) {
            abort(401, 'Authentication required to update wiki pages');
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();

        $model = $wiki->wikiable_type;
        $data  = $model::where('id', $wiki->wikiable_id)->firstOrFail();

        // Their own page, or somebody trusted to change other people's
        if ($data->user_id !== auth()->id() && ! $this->mayEditOthers(auth()->user())) {
            abort(403, 'You do not have permission to edit this wiki page');
        }

        // Input validation
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'string',
            'parent'     => 'nullable|array',
            'parent_id'  => 'nullable',
            'categories' => 'nullable|array',
            'terms'      => 'nullable|array',
        ]);

        $parent_id = $this->resolveParentId($request);

        $wiki->update(['title' => $validated['title'], 'parent_id' => $parent_id]);

        $model = $wiki->wikiable_type;

        $data = $model::where('id', $wiki->wikiable_id)->first();

        $data->update($request->only($this->getUpdatableColumns($request->get('type'))));

        // Tell everyone watching the page, bar whoever just edited it, and
        // start the editor watching what they have worked on
        $wiki->notifyWatchers(
            new WikiUpdatedNotification($wiki, auth()->user()),
            auth()->id()
        );

        $wiki->watch(auth()->user());

        //
        //        if ($request->get('parent')) {
        //            $parent = $request->get('parent');
        //
        //            $data->parent_id = $parent['id'];
        //            $data->update();
        //        }

        $data->detachCategories();

        if ($request->get('categories')) {
            foreach ($request->get('categories') as $term) {
                if (isset($term['title'])) {
                    $data->addCategory($term['title'], 'wiki');
                } else {
                    $data->addCategory($term, 'wiki');
                }
            }
        }

        //        if ($request->get('taxonomy') && $request->get('categories')) {
        //            $taxonomy = $request->get('taxonomy');
        //            if (!is_string($taxonomy)) {
        //                $taxonomy = $taxonomy['taxonomy'];
        //            }
        //
        //            //            $data->addCategories($request->get('categories'), $taxonomy);
        //            if ($request->get('categories')) {
        //
        //                foreach ($request->get('categories') as $term) {
        //                    if (isset($term['title'])) {
        //                        $data->addCategory($term['title'], 'wiki');
        //                    } else {
        //                        $data->addCategory($term, 'wiki');
        //                    }
        //                }
        //            }
        //        }

        if ($request->get('terms')) {
            foreach ($request->get('terms') as $term) {
                if (isset($term['title'])) {
                    $data->addCategory($term['title'], 'tags');
                } else {
                    $data->addCategory($term, 'tags');
                }
            }
        }

        //        if($request->get('terms')) {
        //            $data->addCategories($request->get('terms'),'tags');
        //        }

        return response()->json(['message' => __('messages.wiki.updated'), $model => $data]);
    }

    public function approve($slug): JsonResponse
    {
        $user = Auth::user();
        if ( ! $user || ! $user->can('approve-wiki')) {
            abort(403, __('messages.error.permission_required'));
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();
        $wiki->approve($user);

        // Also resolve any open wiki_approval ticket for this wiki
        $wiki->tickets()
            ->open()
            ->ofType('wiki_approval')
            ->each(function (Ticket $ticket) {
                $ticket->resolve();
            });

        return response()->json(['message' => 'Wiki page approved successfully.', 'wiki' => $wiki->fresh()]);
    }

    public function unapprove($slug): JsonResponse
    {
        $user = Auth::user();
        if ( ! $user || ! $user->can('approve-wiki')) {
            abort(403, __('messages.error.permission_required'));
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();
        $wiki->unapprove();

        return response()->json(['message' => 'Wiki page approval revoked.', 'wiki' => $wiki->fresh()]);
    }

    /**
     * The wiki's categories, in the order a reader of the tree would meet
     * them, each with its depth and the names of the categories above it.
     *
     * Picking a parent used to mean picking from a flat alphabetical list
     * of names, which says nothing about where a category would land - and
     * two branches may well hold the same word. The whole vocabulary is
     * small enough to send at once and arrange in memory.
     */
    public function categoryOptions(): JsonResponse
    {
        // The tree itself is the same list every other picker reads; what
        // the wiki adds is how many pages sit in each category, which the
        // editor sorts its suggestions by.
        $pages = Taxonomy::where('taxonomy', 'wiki')
            ->withCount('pages')
            ->pluck('pages_count', 'id');

        $options = array_map(
            fn (array $option) => $option + ['pages_count' => (int) ($pages[$option['id']] ?? 0)],
            TaxonomyTree::options('wiki')
        );

        return response()->json(['data' => $options]);
    }

    public function storeCategory(Request $request)
    {
        // The shape of the wiki rather than an article in it, so it asks
        // for the feature as a whole and not create-wiki: a member
        // trusted to write a page is not thereby trusted to invent the
        // categories everything else is filed under
        if ( ! auth()->check() || ! auth()->user()->can('manage-wiki')) {
            abort(403, __('messages.error.permission_required'));
        }

        // Input validation. The parent arrives either as a plain id from
        // the page editor or, from the older category screens, as the
        // whole row that was picked - resolveCategoryParent() reads both.
        $validated = $request->validate([
            'term'      => 'required|string|max:255',
            'content'   => 'nullable|string',
            'parent'    => 'nullable',
            'parent_id' => 'nullable',
        ]);

        $parentId = $this->resolveCategoryParent($request);
        $term     = Term::firstOrCreateByTitle(trim($validated['term']));

        // One word names one category. This used to be firstOrNew(), so
        // re-entering a name quietly rewrote the category already there,
        // moving everything filed under it to another parent.
        if (Taxonomy::where('taxonomy', 'wiki')->where('term_id', $term->id)->exists()) {
            throw ValidationException::withMessages(['term' => __('messages.wiki.category_exists')]);
        }

        $taxonomy            = new Taxonomy;
        $taxonomy->taxonomy  = 'wiki';
        $taxonomy->term_id   = $term->id;
        $taxonomy->parent_id = $parentId;

        if (filled($validated['content'] ?? null)) {
            $taxonomy->description = $validated['content'];
        }

        $taxonomy->save();

        return response()->json([
            'message'  => __('messages.wiki.category_created'),
            'taxonomy' => $taxonomy,
            // The shape the picker speaks, so a category just made can be
            // selected without reloading the list.
            'category' => $this->categoryPayload($taxonomy->load('term')),
        ], 201);
    }

    public function updateCategory(Request $request, $slug)
    {
        // As with creating one: the wiki's shape, not its articles
        if ( ! auth()->check() || ! auth()->user()->can('manage-wiki')) {
            abort(403, __('messages.error.permission_required'));
        }

        // Input validation
        $validated = $request->validate([
            'category' => 'required|array',
            'old'      => 'required|array',
            'parent'   => 'nullable',
            'term'     => 'nullable|string',
            'content'  => 'nullable|string',
        ]);

        $termNew = $validated['category'];
        $termOld = $validated['old'];

        $term = Term::find($termNew['term']['id']);
        if ($term->title != $termOld['term']['title']) {
            $term->title = $termNew['term']['title'];
            $term->slug  = Str::slug($termNew['term']['title']);
            $term->update();
        }

        $taxonomy = Taxonomy::where('term_id', $term->id)->where('taxonomy', 'wiki')->firstOrFail();

        // Only touched when the request actually names a parent, so a
        // screen that does not send one leaves the category where it is;
        // sending an empty one now moves it back to the root, which no
        // amount of clearing the field used to achieve.
        if ($request->has('parent_id') || $request->has('parent')) {
            $taxonomy->parent_id = $this->resolveCategoryParent($request, $taxonomy);
        }

        if (filled($request->input('content'))) {
            $taxonomy->description = $request->input('content');
        }
        $taxonomy->update();

        return response()->json(['message' => __('messages.wiki.category_updated'), 'slugchange' => $term->title != $termOld['term']['title'], 'term' => $term, 'taxonomy' => $taxonomy]);
    }

    /**
     * Delete a wiki page.
     */
    public function destroy($slug)
    {
        // Authorization check
        if ( ! auth()->check()) {
            abort(401, 'Authentication required to delete wiki pages');
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();

        $model = $wiki->wikiable_type;
        $data  = $model::where('id', $wiki->wikiable_id)->firstOrFail();

        // Their own page, or somebody trusted to remove other people's
        if ($data->user_id !== auth()->id() && ! $this->mayEditOthers(auth()->user(), 'delete')) {
            abort(403, 'You do not have permission to delete this wiki page');
        }

        // Delete the wiki and associated page
        $data->delete();
        $wiki->delete();

        return response()->json(['message' => __('messages.wiki.deleted')]);
    }

    /**
     * A single category in the shape the picker reads, with the one thing
     * the wiki counts differently: pages, not everything filed under it.
     */
    private function categoryPayload(Taxonomy $taxonomy): array
    {
        return TaxonomyTree::payload($taxonomy) + ['pages_count' => $taxonomy->pages()->count()];
    }

    /**
     * The category a category sits under.
     *
     * Every screen now sends a plain parent_id. The older shape - the
     * whole picked row under "parent", whose "parent_id" key was in fact
     * the id of the category it named - is still read, because a client
     * that has not reloaded would otherwise move a category to the root
     * without being asked. Its "id" key deliberately is not read: in that
     * shape it was the id of the term, not of the category.
     *
     * An empty choice means the root, and a parent that would put a branch
     * inside itself is refused rather than written - everything under it
     * would then be unreachable from anywhere.
     *
     * input() rather than get(): get() reads the query string and the form
     * body only, so a JSON body - which is all the editor sends - is
     * invisible to it unless the request happens to have been built by the
     * kernel's own factory.
     */
    private function resolveCategoryParent(Request $request, ?Taxonomy $self = null): ?int
    {
        $raw = $request->has('parent_id') ? $request->input('parent_id') : $request->input('parent');

        if (is_array($raw)) {
            $raw = $raw['parent_id'] ?? null;
        }

        $parentId = is_numeric($raw) ? (int) $raw : 0;

        if ($parentId <= 0) {
            return null;
        }

        $parent = Taxonomy::find($parentId);

        if ($parent === null || $parent->taxonomy !== 'wiki') {
            throw ValidationException::withMessages([
                'parent_id' => __('messages.wiki.parent_not_a_category'),
            ]);
        }

        if ($self !== null && ($parentId === $self->id || in_array($parentId, $self->descendantIds(), true))) {
            throw ValidationException::withMessages([
                'parent_id' => __('messages.taxonomy.parent_descendant'),
            ]);
        }

        return $parentId;
    }

    /**
     * Whether this member may see wiki pages still waiting to be
     * approved.
     *
     * It used to be admins and nobody else, which left the approver -
     * the whole point of approve-wiki - unable to read the page they
     * were being asked to judge. Both permissions answer yes: one is
     * for whoever looks after the wiki, the other for whoever works
     * through its queue.
     */
    private function seesPending(?User $user): bool
    {
        return $user !== null && ($user->can('view-wiki') || $user->can('approve-wiki'));
    }

    /**
     * Whether this member may change or remove somebody else's page.
     *
     * A member's own page has always been theirs to edit, and still is -
     * this is the permission to touch a page you did not write.
     */
    private function mayEditOthers(?User $user, string $action = 'edit'): bool
    {
        return $user !== null && $user->can("{$action}-wiki");
    }

    /**
     * The page a wiki entry hangs under.
     *
     * The editor sends the chosen parent as an object under "parent", while
     * the page row carries its own scalar parent_id that gets echoed straight
     * back on save. Either is accepted: demanding an array rejected every
     * ordinary edit, and reading the wrong key silently dropped the choice.
     */
    private function resolveParentId(Request $request): int
    {
        $parent = $request->get('parent') ?? $request->get('parent_id');

        if (is_array($parent)) {
            return (int) ($parent['id'] ?? 0);
        }

        return (int) ($parent ?? 0);
    }

    /**
     * The wiki page a history is asked for, with the same gate as show():
     * a page still waiting for approval is not public.
     */
    private function wikiForHistory(string $slug): Wiki
    {
        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();

        if ( ! $wiki->isVisibleTo(Auth::user())) {
            abort(403, 'This page is pending approval.');
        }

        return $wiki;
    }

    private function pageBehind(Wiki $wiki): Page
    {
        $model = $wiki->wikiable_type ?: Page::class;

        return $model::findOrFail($wiki->wikiable_id);
    }

    /**
     * @return Collection<int,Revision>
     */
    private function revisionsOf(Page $page)
    {
        // Older rows carry the table name the listener writes; newer ones may
        // carry the morph class. Both mean the same page.
        return Revision::with('executor')
            ->whereIn('revisionable_type', [(new Page)->getMorphClass(), (new Page)->getTable()])
            ->where('revisionable_id', $page->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The newest recorded value of a field within the given revisions.
     */
    private function valueAsOf($revisions, string $key): ?string
    {
        $value = null;

        foreach ($revisions as $revision) {
            $diff = $revision->getDiff();
            if (array_key_exists($key, $diff)) {
                $value = $diff[$key]['new_value'];
            }
        }

        return $value;
    }

    private function excerpt(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)));

        return mb_strlen($plain) > 140 ? mb_substr($plain, 0, 140) . '…' : $plain;
    }
}
