<?php

namespace App\Http\Controllers\DataTable;

use App\Helpers\TaxonomyHelper;
use Illuminate\Http\Request;
use Lecturize\Taxonomies\Models\Term;

class TermController extends DataTableController
{
    public function builder()
    {
        return Term::query();
    }

    public function store(Request $request)
    {
//        dd($request);
        $parent = $request->get('tag_taxonomy_id') ?? 0;

        $name = $request->get('name');
        $taxonomy = $request->get('taxonomy');

        if (!$taxonomy || !$name) {
            return response()->json(['message' => 'error']);
        }

        $createdItem = TaxonomyHelper::createTaxables($name, $taxonomy, $parent);

        return response()->json(['message' => 'success', 'term' => $createdItem], 200);
    }

    public function getUpdatableColumns()
    {
        return  [
            'name',
            'taxonomy',
            'tag_taxonomy_id',
            'slug',
            'desc',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'name'            => 'input',
            'desc'            => 'textarea',
            'taxonomy'        => 'model',
            'tag_taxonomy_id' => 'model',
        ];
    }

    public function getCustomColumnsNames()
    {
        return [];
    }

    public function getFilterFields()
    {
        return [
            'tag_taxonomy_id' => 'taxonomy',
        ];
    }

//    public function update($id, TaxonomyRequest $request)
//    {
//        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
//    }
}
