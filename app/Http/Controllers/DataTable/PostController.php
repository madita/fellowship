<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
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

        if ($request->get('categories')) {
            $post->addCategories($request->get('categories'), 'category');
        }

        if ($request->get('terms')) {
            $post->addCategories($request->get('terms'), 'tags');
        }
    }

    public function update($id, Request $request)
    {
        $post = Post::find($id);
        $post->update($request->only($this->getUpdatableColumns()));

        $post->detachCategories();

        if ($request->get('categories')) {
            $post->addCategories($request->get('categories'), 'category');
        }

        if ($request->get('terms')) {
            $post->addCategories($request->get('terms'), 'tags');
        }
    }

    public function show($id, Request $request): JsonResponse
    {
        $post = Post::find($id);
        $data = $post->toArray();

        $data['categories'] = $post->getCategories('category')->pluck('title')->toArray();
        $data['terms'] = $post->getCategories('tags')->pluck('title')->toArray();

        return response()->json($data);
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

    public function getUpdatableColumns()
    {
        return [
            'title',
            'body',
            'status',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'body' => 'wysiwyg',
            'status' => ['select' => ['draft', 'published']],
        ];
    }
}
