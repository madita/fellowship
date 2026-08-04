<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WikiController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {}

    public function getUpdatableColumns($type)
    {
        switch ($type) {
            case 'page':
                return [
                    'title',
                    'content',
                    'published',
                    'sign_in_only', ];
        }
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
        $query = $request->get('q');
        $user = Auth::user();
        $isAdmin = $user && $user->isAdmin();

        $page = request()->input('page', 1); // Current page number, default to 1

        $wikiQuery = Wiki::where('status', null);

        // Non-admins only see approved pages
        if (! $isAdmin) {
            $wikiQuery->approved();
        }

        if ($query !== null && $query !== '') {
            $wikiQuery->whereTranslationLike('title', '%'.$query.'%');
        }

        $wikidata = $wikiQuery->with('approval')->orderBy('created_at', 'desc')->paginate($perPage);

        $total = $wikidata->total();

        $wiki = $wikidata->getCollection()->map(function (Wiki $wiki) {
            $model = $wiki->wikiable_type;
            $data = $model::where('id', $wiki->wikiable_id)->first();

            $taxonomies = $data->getCategories('wiki')->unique();
            $tags = $data->getCategories('tags')->unique();

            return [
                'title' => $wiki->title,
                'slug' => $wiki->slug,
                'type' => Str::lower(Str::afterLast($wiki->wikiable_type, '\\')),
                'model' => $wiki->wikiable_type,
                'data' => $data,
                'taxonomies' => $taxonomies,
                'tags' => $tags,
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
                'label' => $cnt,
                'url' => request()->url()."?page{$cnt}&q={$query}",
            ];
        }

        return response()->json([
            'data' => $paginator->values(),
            'total' => $total,
            'to' => $perPage * $page,
            'per_page' => $perPage,
            'current_page' => $page,
            'first_page' => $paginator->url(1),
            'last_page' => $paginator->lastPage(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'path' => request()->url(),
            'links' => $links,
        ]);

        //        $wiki->total = $wikidata->total;
        //        $wiki->to = $wiki->per_page*$wiki->current_page;

        //        return response()->json($paginator);
    }

    public function getPages()
    {
        $user = Auth::user();
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
    public function view($wikiable, $id) {}

    public function show($slug)
    {

        $wiki = Wiki::where('slug', '=', $slug)->first();

        if ($wiki === null) {
            $data = ['slug' => $slug, 'title' => Str::ucfirst($slug), 'content' => ''];

            return response(['status' => 404, 'message' => __('messages.wiki.create_page'), 'page' => $data], 404);
        }

        // Block unapproved pages for non-admins
        $currentUser = Auth::user();
        $isAdmin = $currentUser && $currentUser->isAdmin();
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
            $title = explode('#', $matches[1][$key]);

            $title = isset($matches[3][$key]) && trim($matches[3][$key]) != '' ? $matches[3][$key] : $title[0];
            $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != '' ? $matches[3][$key] : null;

            $page = Page::firstOrNew(['title' => $title, 'slug' => Str::slug($title)]);

            $replace =
                "<a data-wiki-id=\"0\" class=\"new\" data-title=\"{$page->title}\" data-linked-resource-type=\"wikiable\" data-alternative=\"{$alternative}\" href=\"/wiki/{$page->slug}\" contenteditable=\"false\">{$title}</a>";
            $content = Str::replace($item, $replace, $content);
        }
        $data->content = $content;
        $taxonomies = $data->getCategories('wiki')->unique();
        $terms = $data->getCategories('tags')->unique();
        $user = $data->user;

        $approval = $wiki->approval;

        return response()->json([
            'page' => $data,
            'user' => $user,
            'wiki' => $wiki,
            'parent' => $wiki->parent,
            'children' => $wiki->children,
            'terms' => $taxonomies,
            'tags' => $terms,
            'is_approved' => $approval !== null,
            'approved_at' => $approval?->approved_at,
            'approved_by' => $approval?->approver?->name,
        ]);
    }

    public function history($wikiable, $id) {}

    public function store(Request $request)
    {
        // Authorization check
        if (! auth()->check()) {
            abort(401, 'Authentication required to create wiki pages');
        }

        // Input validation
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:255|unique:wikiables,slug',
            'parent_id' => 'nullable|array',
            'categories' => 'nullable|array',
            'terms' => 'nullable|array',
        ]);

        $parent = $request->get('parent_id');
        $parent_id = $parent['id'] ?? 0;

        // Sanitize content (strip potentially dangerous tags/attributes)
        $content = strip_tags($validated['content'], '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><img><table><thead><tbody><tr><td><th>');

        $page = auth()->user()->pages()->create([
            'title' => $validated['title'],
            'content' => $content,
            'sign_in_only' => 0,
            'published' => 1]);

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
            'title' => $page->title,
            'slug' => $request->get('slug') ?: Str::slug($validated['title']),
            'parent_id' => $parent_id,
        ]);

        $page->wikiable()->save($wiki);

        return response()->json(['message' => __('messages.wiki.created'), 'page' => $page]);
    }

    public function update(Request $request, $slug)
    {
        // Authorization check
        if (! auth()->check()) {
            abort(401, 'Authentication required to update wiki pages');
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();

        $model = $wiki->wikiable_type;
        $data = $model::where('id', $wiki->wikiable_id)->firstOrFail();

        // Check if user is the owner or admin
        if ($data->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to edit this wiki page');
        }

        // Input validation
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'string',
            'parent_id' => 'nullable|array',
            'categories' => 'nullable|array',
            'terms' => 'nullable|array',
        ]);

        $parent = $request->get('parent_id');
        $parent_id = $parent['id'] ?? 0;

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
        if (! $user || ! $user->isAdmin()) {
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
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can unapprove wiki pages.');
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();
        $wiki->unapprove();

        return response()->json(['message' => 'Wiki page approval revoked.', 'wiki' => $wiki->fresh()]);
    }

    public function storeCategory(Request $request)
    {
        // Authorization check - only admins can create wiki categories
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Only administrators can create wiki categories');
        }

        // Input validation
        $validated = $request->validate([
            'term' => 'required|string|max:255',
            'content' => 'nullable|string',
            'parent' => 'nullable|array',
        ]);

        $term = Term::firstOrCreateByTitle($validated['term']);

        $taxonomy = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);
        $parent = $request->get('parent');

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
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Only administrators can update wiki categories');
        }

        // Input validation
        $validated = $request->validate([
            'category' => 'required|array',
            'old' => 'required|array',
            'parent' => 'nullable|array',
            'term' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        $termNew = $validated['category'];
        $termOld = $validated['old'];
        $parent = $request->get('parent');
        $title = $request->get('term');

        $term = Term::find($termNew['term']['id']);
        if ($term->title != $termOld['term']['title']) {
            $term->title = $termNew['term']['title'];
            $term->slug = Str::slug($termNew['term']['title']);
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
        if (! auth()->check()) {
            abort(401, 'Authentication required to delete wiki pages');
        }

        $wiki = Wiki::where('slug', '=', $slug)->firstOrFail();

        $model = $wiki->wikiable_type;
        $data = $model::where('id', $wiki->wikiable_id)->firstOrFail();

        // Check if user is the owner or admin
        if ($data->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to delete this wiki page');
        }

        // Delete the wiki and associated page
        $data->delete();
        $wiki->delete();

        return response()->json(['message' => __('messages.wiki.deleted')]);
    }
}
