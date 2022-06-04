<?php

namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Http\Request;

class TaxonomyController extends Controller
{
    public function getTaxonomies()
    {
        $taxonomies = Taxonomy::all();

        return response()->json($taxonomies);
    }

    public function getTerms($taxonomy = null)
    {

        if ($taxonomy) {
            $terms = collect();
            $tax = collect(Taxonomy::where('taxonomy', $taxonomy)->with('term')->get())
                ->each(function (Taxonomy $taxonomy) use($terms) {

                    $term = $taxonomy->term()->get(['id','name', 'color'])->flatten()->toArray();

                    if(isset($term[0])) {
                        $tempArr = array_merge($term[0], ['parent_id' => $taxonomy->id]);
                        $terms->add($tempArr);
                    }
                });

        } else {
            $terms = Term::all();
        }

        return response()->json($terms);
    }

    public function saveTerms(Request $request)
    {

        $term = $request->get('term');
        $taxonomy = $request->get('taxonomy');
        $parent = $request->get('parent');

        TaxonomyHelper::createTaxables($term, $taxonomy['taxonomy'], $parent['parent_id']);

        return response()->json(['success' => true, 'term' => $term]);
    }
}
