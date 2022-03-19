<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Tag\Term;
use Illuminate\Http\Request;
use App\Http\Controllers\DataTable\DataTableController;

class TermController extends DataTableController
{
    public function builder()
    {
        return Term::query();
    }

    public function store(Request $request)
    {
        Term::create($request->only($this->getUpdatableColumns()));
    }

    public function getUpdatableColumns()
    {
        return  [
            'name',
            'slug',
            'desc'
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'name' => 'input',
            'desc' => 'textarea',
        ];
    }

    public function getCustomColumnsNames()
    {
        return [];
    }

//    public function update($id, TaxonomyRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
