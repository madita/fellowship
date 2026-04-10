<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Page;
//use App\Models\Tag\Taxonomy;
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
        $data['published'] = $data['published'] === null ? 0 : 1;
        $data['sign_in_only'] = $data['sign_in_only'] === null ? 0 : 1;


        $page = auth()->user()->pages()->create($data);

        if ($request->get('parent')) {
            $parent = $request->get('parent');

            $page->parent_id = $parent['id'];
            $page->save();
        }

        if ($request->get('taxonomy') && $request->get('categories')) {
            $taxonomy = $request->get('taxonomy');
            $taxonomy = $taxonomy['taxonomy'];
            $page->addCategories($request->get('categories'), $taxonomy);
        }

        if ($request->get('terms')) {
            $page->addCategories($request->get('terms'), 'tags');
        }
    }

    public function update($id, Request $request)
    {
        $page = Page::find($id);
        $page->update($request->only($this->getUpdatableColumns()));

        //
        if ($request->get('parent')) {
            $parent = $request->get('parent');

            $page->parent_id = $parent['id'];
            $page->update();
        }

        $page->detachCategories();

        if ($request->get('taxonomy') && $request->get('categories')) {
            $taxonomy = $request->get('taxonomy');
            if (!is_string($taxonomy)) {
                $taxonomy = $taxonomy['taxonomy'];
            }

            $page->addCategories($request->get('categories'), $taxonomy);
        }

        if ($request->get('terms')) {
            $page->addCategories($request->get('terms'), 'tags');
        }
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
