<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Revision;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
        $isAdmin = $user && $user->isAdmin();

        $page = request()->input('page', 1); // Current page number, default to 1

        $wikiQuery = Wiki::where('status', null);

        // Non-admins only see approved pages
        if ( ! $isAdmin) {
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
        $limit   = max(1, min((int) $request->get('limit', 5), 20));
        $user    = Auth::user();
        $isAdmin = $user && $user->isAdmin();

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
            ->when( ! $isAdmin, fn ($q) => $q->approved())
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
        $user    = Auth::user();
        $isAdmin = $user && $user->isAdmin();

        $wikidata = $isAdmin ? Wiki::all() : Wiki::approved()->get();

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

        // Block unapproved pages for non-admins
        $currentUser = Auth::user();
        $isAdmin     = $currentUser && $currentUser->isAdmin();
        if ($wiki->isPending() && ! $isAdmin) {
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
            'page'        => $data,
            'user'        => $user,
            'wiki'        => $wiki,
            'parent'      => $wiki->parent,
            'children'    => $wiki->children,
            'terms'       => $taxonomies,
            'tags'        => $terms,
            'is_approved' => $approval !== null,
            'approved_at' => $approval?->approved_at,
            'approved_by' => $approval?->approver?->name,
        ]);
    }

    public function store(Request $request)
    {
        // Authorization check
        if ( ! auth()->check()) {
            abort(401, 'Authentication required to create wiki pages');
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

        // Check if user is the owner or admin
        if ($data->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
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
        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can approve wiki pages.');
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
        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can unapprove wiki pages.');
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();
        $wiki->unapprove();

        return response()->json(['message' => 'Wiki page approval revoked.', 'wiki' => $wiki->fresh()]);
    }

    public function storeCategory(Request $request)
    {
        // Authorization check - only admins can create wiki categories
        if ( ! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Only administrators can create wiki categories');
        }

        // Input validation
        $validated = $request->validate([
            'term'    => 'required|string|max:255',
            'content' => 'nullable|string',
            'parent'  => 'nullable|array',
        ]);

        $term = Term::firstOrCreateByTitle($validated['term']);

        $taxonomy = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);
        $parent   = $request->get('parent');

        if ($parent['parent_id']) {
            $taxonomy->parent_id = $parent['parent_id'];
        }

        if ($request->get('content')) {
            $taxonomy->description = $request->get('content');
        }
        $taxonomy->save();

        return response()->json(['message' => __('messages.wiki.category_created'), 'taxonomy' => $taxonomy]);
    }

    public function updateCategory(Request $request, $slug)
    {
        // Authorization check - only admins can update wiki categories
        if ( ! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Only administrators can update wiki categories');
        }

        // Input validation
        $validated = $request->validate([
            'category' => 'required|array',
            'old'      => 'required|array',
            'parent'   => 'nullable|array',
            'term'     => 'nullable|string',
            'content'  => 'nullable|string',
        ]);

        $termNew = $validated['category'];
        $termOld = $validated['old'];
        $parent  = $request->get('parent');
        $title   = $request->get('term');

        $term = Term::find($termNew['term']['id']);
        if ($term->title != $termOld['term']['title']) {
            $term->title = $termNew['term']['title'];
            $term->slug  = Str::slug($termNew['term']['title']);
            $term->update();
        }

        $taxonomy = Taxonomy::where('term_id', $term->id)->where('taxonomy', 'wiki')->first();
        //        $parent = $termNew['parent'];

        if ($parent['parent_id']) {
            $taxonomy->parent_id = $parent['parent_id'];
        }

        if ($request->get('content')) {
            $taxonomy->description = $request->get('content');
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

        // Check if user is the owner or admin
        if ($data->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to delete this wiki page');
        }

        // Delete the wiki and associated page
        $data->delete();
        $wiki->delete();

        return response()->json(['message' => __('messages.wiki.deleted')]);
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

        $user = Auth::user();
        if ($wiki->isPending() && ! ($user && $user->isAdmin())) {
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
