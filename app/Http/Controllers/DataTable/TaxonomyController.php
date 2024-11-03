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
        Taxonomy::create($request->only($this->getUpdatableColumns()));
    }



    public function update($id, Request $request)
    {
        //            dd($id, $request);

    }



    public function getUpdatableColumns()
    {
        return [
            'taxonomy',
            'description',
            'content',
            'lead',
            'meta_desc',
            'color',
            'parent',
            'properties',
            'sort',
            ];
    }

    public function getCustomInputFields()
    {
        return [
            'content'      => 'wysiwyg',
            'description'    => 'wysiwyg',
            'color' => 'color', ];
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
            'parent',
            'properties',
            'sort',
        ];
    }



//    public function update($id, TaxonomyRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
