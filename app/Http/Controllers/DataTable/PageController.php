<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends DataTableController
{
    protected $hasForm = true;

    public function builder()
    {
        $query = Page::query();

        // Filter out pages that have a wiki entry
        if (request()->boolean('exclude_wiki')) {
            $query->whereDoesntHave('wikiable');
        }

        return $query;
    }

    public function store(Request $request)
    {
        //        dd($request);

        $data = $request->only($this->getUpdatableColumns());
        $data['published'] = !empty($data['published']) ? 1 : 0;
        $data['sign_in_only'] = !empty($data['sign_in_only']) ? 1 : 0;


        $page = auth()->user()->pages()->create($data);

        if ($request->get('parent')) {
            $parent = $request->get('parent');

            $page->parent_id = $parent['id'];
            $page->save();
        }

        if ($request->get('categories')) {
            $taxonomy = $request->get('taxonomy');
            $taxonomyName = is_array($taxonomy) ? ($taxonomy['taxonomy'] ?? 'category') : ($taxonomy ?? 'category');
            $page->addCategories($request->get('categories'), $taxonomyName);
        }

        if ($request->get('terms')) {
            $page->addCategories($request->get('terms'), 'tags');
        }
    }

    public function update($id, Request $request)
    {
        //            dd($id, $request);
        $page = Page::find($id);
        $page->update($request->only($this->getUpdatableColumns()));

        //
        if ($request->get('parent')) {
            $parent = $request->get('parent');

            $page->parent_id = $parent['id'];
            $page->update();
        }

        $page->detachCategories();

        if ($request->get('categories')) {
            $taxonomy = $request->get('taxonomy');
            $taxonomyName = is_array($taxonomy) ? ($taxonomy['taxonomy'] ?? 'category') : ($taxonomy ?? 'category');
            $page->addCategories($request->get('categories'), $taxonomyName);
        }

        if ($request->get('terms')) {
            $page->addCategories($request->get('terms'), 'tags');
        }
    }

    public function getTaxonomyFields()
    {
        return [
            'categories' => [
                'taxonomy' => 'category',
                'label' => 'Categories',
                'multiple' => true,
                'endpoint' => '/api/tag/terms/category',
            ],
            'terms' => [
                'taxonomy' => 'tags',
                'label' => 'Tags',
                'multiple' => true,
                'endpoint' => '/api/tag/terms/tags',
            ],
        ];
    }

    public function show($id, Request $request): JsonResponse
    {
        $page = Page::find($id);
        $data = $page->toArray();

        $data['categories'] = $page->getCategories('category')->pluck('title')->toArray();
        $data['terms'] = $page->getCategories('tags')->pluck('title')->toArray();

        return response()->json($data);
    }

    public function getUpdatableColumns()
    {
        return [
            'title',
            'content',
            'published',
            'sign_in_only', ];
    }

    public function getCustomInputFields()
    {
        return [
            'content'      => 'wysiwyg',
            'published'    => 'checkbox',
            'sign_in_only' => 'checkbox', ];
    }

    public function getToggleFilters()
    {
        return [
            ['key' => 'exclude_wiki', 'label' => 'Exclude Wiki Pages', 'icon' => 'mdi-book-remove-outline'],
        ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'published',
            'sign_in_only',
            'slug',
            'title',
            'type',
            'user_id',
            'parent_id',
            'created_at',
            'updated_at', ];
    }
}
