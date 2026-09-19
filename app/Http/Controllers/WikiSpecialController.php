<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Revision;
use App\Models\Tag\Taxonomy;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The wiki's special pages, in the spirit of MediaWiki: every page at a
 * glance, the categories, what changed lately, and the housekeeping lists
 * (wanted, orphaned, dead-end, uncategorised, longest and shortest).
 */
class WikiSpecialController extends Controller
{
    /** How long the link graph of the whole wiki is kept. */
    private const GRAPH_TTL = 300;

    /**
     * Every page, alphabetical. `letter` narrows to one initial.
     */
    public function allPages(Request $request): JsonResponse
    {
        $letter = Str::upper((string) $request->get('letter'));

        $pages = $this->pages()
            ->map(fn (array $page) => [
                'title'   => $page['title'],
                'slug'    => $page['slug'],
                'initial' => $page['initial'],
                'pending' => $page['pending'],
            ])
            ->when($letter !== '', fn ($pages) => $pages->where('initial', $letter))
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'data'     => $pages,
            'initials' => $this->pages()->pluck('initial')->unique()->sort()->values(),
        ]);
    }

    /**
     * The categories of the wiki, with how many pages each holds.
     */
    public function categories(): JsonResponse
    {
        $categories = Taxonomy::where('taxonomy', 'wiki')
            ->with('term')
            ->withCount('pages')
            ->get()
            ->map(fn (Taxonomy $taxonomy) => [
                'title'       => $taxonomy->term?->title,
                'slug'        => $taxonomy->term?->slug,
                'description' => $taxonomy->description,
                'pages_count' => (int) $taxonomy->pages_count,
            ])
            ->filter(fn (array $category) => filled($category['slug']))
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['data' => $categories]);
    }

    /**
     * Pages linked with [[…]] that nobody has written yet, most wanted first.
     */
    public function wantedPages(): JsonResponse
    {
        $graph   = $this->linkGraph();
        $titles  = $this->pages()->pluck('title')->map(fn ($title) => Str::lower($title))->all();
        $slugs   = $this->pages()->pluck('slug')->all();

        $wanted = collect($graph['targets'])
            ->reject(fn (array $target) => in_array(Str::lower($target['title']), $titles, true)
                || in_array($target['slug'], $slugs, true))
            ->sortByDesc('count')
            ->values();

        return response()->json(['data' => $wanted]);
    }

    /**
     * Pages no other page links to.
     */
    public function orphanedPages(): JsonResponse
    {
        $linkedSlugs = collect($this->linkGraph()['targets'])->pluck('slug')->all();

        $orphans = $this->pages()
            ->reject(fn (array $page) => in_array($page['slug'], $linkedSlugs, true))
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['data' => $orphans]);
    }

    /**
     * Pages that link nowhere themselves.
     */
    public function deadEndPages(): JsonResponse
    {
        $withLinks = $this->linkGraph()['sources'];

        $deadEnds = $this->pages()
            ->reject(fn (array $page) => in_array($page['slug'], $withLinks, true))
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['data' => $deadEnds]);
    }

    /**
     * Pages that are in no category yet.
     */
    public function uncategorisedPages(): JsonResponse
    {
        $uncategorised = $this->pages()
            ->filter(fn (array $page) => $page['categories'] === 0)
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['data' => $uncategorised]);
    }

    /**
     * Pages by how much text they hold; `order=short` for the other end.
     */
    public function pagesByLength(Request $request): JsonResponse
    {
        $pages = $this->pages();

        $sorted = $request->get('order') === 'short'
            ? $pages->sortBy('length')
            : $pages->sortByDesc('length');

        return response()->json(['data' => $sorted->take(100)->values()]);
    }

    /**
     * What the wiki adds up to.
     */
    public function statistics(): JsonResponse
    {
        $pages = $this->pages();

        // Revisions are stored against the model class, not the table
        $revisions = Revision::where('revisionable_type', Page::class);

        return response()->json([
            'data' => [
                'pages'         => $pages->count(),
                'categories'    => Taxonomy::where('taxonomy', 'wiki')->count(),
                'edits'         => (clone $revisions)->count(),
                'contributors'  => (clone $revisions)->whereNotNull('user_id')->distinct()->count('user_id'),
                'pending'       => Wiki::whereNull('status')->pending()->count(),
                'words'         => $pages->sum('words'),
                'uncategorised' => $pages->where('categories', 0)->count(),
            ],
        ]);
    }

    /**
     * One page at random, to read something else for a change.
     */
    public function randomPage(): JsonResponse
    {
        $page = $this->pages()->random(1)->first();

        return response()->json(['data' => $page]);
    }

    /**
     * Every wiki page the member may see, with what the lists need of it.
     */
    private function pages()
    {
        $isAdmin = Auth::user()?->isAdmin() ?? false;

        return Cache::remember('wiki.special.pages.' . ($isAdmin ? 'admin' : 'member'), self::GRAPH_TTL, function () use ($isAdmin) {
            $query = Wiki::whereNull('status')->with('approval');

            if ( ! $isAdmin) {
                $query->approved();
            }

            return $query->get()->map(function (Wiki $wiki) {
                $model = $wiki->wikiable_type;
                $page  = $model ? $model::find($wiki->wikiable_id) : null;
                $text  = trim(html_entity_decode(strip_tags((string) $page?->content)));

                return [
                    'title'      => (string) $wiki->title,
                    'slug'       => $wiki->slug,
                    'initial'    => Str::upper(Str::substr((string) $wiki->title, 0, 1)),
                    'length'     => Str::length($text),
                    'words'      => $text === '' ? 0 : str_word_count($text),
                    'categories' => $page ? $page->getCategories('wiki')->count() : 0,
                    'updated_at' => $page?->updated_at,
                    'pending'    => $wiki->isPending(),
                ];
            })->values();
        });
    }

    /**
     * Which page links where. Targets are counted by how often they are
     * linked, so the wanted list can lead with what is missed most.
     *
     * @return array{targets: array<int,array>, sources: string[]}
     */
    private function linkGraph(): array
    {
        return Cache::remember('wiki.special.link-graph', self::GRAPH_TTL, function () {
            $targets = [];
            $sources = [];

            $pages = Wiki::whereNull('status')->get();

            foreach ($pages as $wiki) {
                $model   = $wiki->wikiable_type;
                $page    = $model ? $model::find($wiki->wikiable_id) : null;
                $content = (string) $page?->content;

                preg_match_all('/\[\[(.*?)(\|(.*?))?\]\]/', $content, $matches);

                if ($matches[1]) {
                    $sources[] = $wiki->slug;
                }

                foreach ($matches[1] as $target) {
                    // [[Target#anchor]] points at Target
                    $title = trim(explode('#', $target)[0]);

                    if ($title === '') {
                        continue;
                    }

                    $slug = Str::slug($title);
                    $targets[$slug] ??= ['title' => $title, 'slug' => $slug, 'count' => 0];
                    $targets[$slug]['count']++;
                }
            }

            return ['targets' => array_values($targets), 'sources' => array_values(array_unique($sources))];
        });
    }
}
