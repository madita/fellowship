<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Page;
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
        if($request->get('taxonomieValue')) {
            $taxonomy = $request->get('taxonomieValue');
        }

        if($request->get('termValue')) {
            $page->addTerms($request->get('termValue'), $taxonomy['taxonomy']);
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
