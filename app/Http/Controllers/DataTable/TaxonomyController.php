<?php

namespace App\Http\Controllers\DataTable;

use App\Helpers\TaxonomyHelper;
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

//        dd($request);
        $taxonomy = Taxonomy::create($request->only($this->getUpdatableColumns()));

//        $data = $request->only($this->getUpdatableColumns());

//        dd($data);
//
//
////        $parent = $request->get('tag_taxonomy_id')??0;
//
////        $name = $request->get('name');
////        $taxonomy = $request->get('taxonomy');
//
////        if(!$taxonomy || !$name) {
////            return response()->json(['message' => 'error']);
////        }
//
//        $taxonomy = TaxonomyHelper::createTaxables($name, $taxonomy, $parent);

        return response()->json(['message' => 'success', 'taxonomy' => $taxonomy], 200);
    }



    public function update($id, Request $request)
    {
        //            dd($id, $request);

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
            'content'      => 'wysiwyg',
            'description'    => 'textarea',
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
