<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Page;
use App\Models\Tag\Taxonomy;
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
        $page = auth()->user()->pages()->create($request->only($this->getUpdatableColumns()));

        $taxonomy = null;
        if($request->get('taxonomy')) {
            $taxonomy = $request->get('taxonomy');
            $taxonomy = $taxonomy['taxonomy'];

            if($request->get('categories')) {
                $page->addTerms($request->get('categories'), $taxonomy);
            }
        }

        if($request->get('terms')) {
            $page->addTerms($request->get('terms'),'tags');
        }

    }

    public function getUpdatableColumns()
    {
        return  [
            'title',
            'body',
            'published',
            'sign_in_only',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'body'         => 'wysiwyg',
            'published'    => 'checkbox',
            'sign_in_only' => 'checkbox',
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
            'updated_at',
        ];
    }

//    public function update($id, PageRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
