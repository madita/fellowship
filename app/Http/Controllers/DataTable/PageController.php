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
        return Page::query();
    }

    public function store(Request $request)
    {
        //        dd($request);
        $page = auth()->user()->pages()->create($request->only($this->getUpdatableColumns()));

        if ($request->get('parent')) {
            $parent = $request->get('parent');

            $page->parent_id = $parent['id'];
            $page->save();
        }

        if ($request->get('taxonomy') && $request->get('categories')) {
            $taxonomy = $request->get('taxonomy');
            $taxonomy = $taxonomy['taxonomy'];
            //            dd('hm');
            $page->addCategories($request->get('categories'), $taxonomy);
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
            'sign_in_only',];
    }

    public function getCustomInputFields()
    {
        return [
            'content'      => 'wysiwyg',
            'published'    => 'checkbox',
            'sign_in_only' => 'checkbox',];
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
            'updated_at',];
    }


}
