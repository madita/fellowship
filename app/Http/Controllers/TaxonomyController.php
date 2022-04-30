<?php

namespace App\Http\Controllers;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;

class TaxonomyController extends Controller
{
    public function getTaxonomies()
    {
        $taxonomies = Taxonomy::all();

        return response()->json($taxonomies);
    }

    public function getTerms()
    {
        $terms = Term::all();

        return response()->json($terms);
    }
}
