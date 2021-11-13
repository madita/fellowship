<?php

namespace App\Http\Controllers\DataTable;

use Auth;
use App\Models\Page;
use Illuminate\Http\Request;
use App\Http\Controllers\DataTable\DataTableController;

class PageController extends DataTableController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

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
            'published'
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'body' => 'textarea',
            'published' => 'checkbox',
        ];
    }

//    public function update($id, PageRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
