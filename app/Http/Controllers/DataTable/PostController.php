<?php

namespace App\Http\Controllers\DataTable;

use Auth;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Controllers\DataTable\DataTableController;

class PostController extends DataTableController
{
    public function builder()
    {
        return Post::query();
    }

    public function store(Request $request)
    {
        Auth::user()->posts()->create($request->only($this->getUpdatableColumns()));
    }

    public function getUpdatableColumns()
    {
        return  [
            'title',
            'body',
            'status'
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'body' => 'textarea',
            'status' => 'radio',
        ];
    }

//    public function update($id, PostRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
