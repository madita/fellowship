<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Post;
use Auth;
use Illuminate\Http\Request;

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
            'status',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'body'   => 'wysiwyg',
            'status' => ['select'=> ['draft', 'published']],
        ];
    }

//    public function update($id, PostRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
