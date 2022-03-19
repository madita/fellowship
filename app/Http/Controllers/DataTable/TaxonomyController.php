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

    public function getUpdatableColumns()
    {
        return  [
            'taxonomy',
            'desc',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'body'   => 'textarea',
            'status' => 'radio',
        ];
    }

    public function getCustomColumnsNames()
    {
        return ['sort'=>'sort'];
    }

//    public function update($id, TaxonomyRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
