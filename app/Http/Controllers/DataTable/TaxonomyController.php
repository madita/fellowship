<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Tag\Taxonomy;
use Illuminate\Http\Request;

class TaxonomyController extends DataTableController
{
    public function builder()
    {
        return Taxonomy::query();
    }

    public function store(Request $request)
    {
        $taxonomy = Taxonomy::create($request->only($this->getUpdatableColumns()));

        return response()->json(['message' => 'success', 'taxonomy' => $taxonomy], 200);
    }

    public function update($id, Request $request)
    {

        $taxonomy = Taxonomy::find($id);

        $taxonomy->update($request->only($this->getUpdatableColumns()));

        return response()->json(['message' => 'success', 'taxonomy' => $taxonomy], 200);
    }

    public function getUpdatableColumns()
    {
        return [
            'taxonomy',
            'parent_id',
            'description',
            'content',
            'lead',
            'meta_desc',
            'color',
            'properties',
            'sort',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'content' => 'wysiwyg',
            'description' => 'textarea',
            'color' => 'color',
            'parent_id' => 'parent', ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'taxonomy',
            'description',
            'content',
            'lead',
            'meta_desc',
            'color',
            'parent_id',
            'properties',
            'sort',
        ];
    }

    //    public function update($id, TaxonomyRequest $request)
    //    {
    //        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
    //    }
}
