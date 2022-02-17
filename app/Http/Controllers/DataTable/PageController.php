<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Page;
use Auth;
use Illuminate\Http\Request;

class PageController extends DataTableController
{
    public function builder()
    {
        return Page::query();
    }

    public function store(Request $request)
    {
        Auth::user()->pages()->create($request->only($this->getUpdatableColumns()));
    }

    public function getUpdatableColumns()
    {
        return  [
            'title',
            'body',
            'published',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'body'      => 'textarea',
            'published' => 'checkbox',
        ];
    }

//    public function update($id, PageRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
