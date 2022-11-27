<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends DataTableController
{
    public function builder()
    {
        return Post::query();
    }

    public function store(Request $request)
    {
        $post = auth()->user()->posts()->create($request->only($this->getUpdatableColumns()));

        if($request->get('termValue')) {
            $post->addCategories($request->get('termValue'), 'blog');
        }
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
