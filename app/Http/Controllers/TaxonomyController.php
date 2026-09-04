<?php

namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use App\Models\Tag\Taxable;
//use App\Models\Tag\Taxonomy;

use App\Models\Tag\Taxonomy;
//use App\Models\Tag\Term;
use App\Models\Tag\Term;
//use Lecturize\Taxonomies\Models\Taxonomy;
//use Lecturize\Taxonomies\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            $tax = collect(Taxonomy::where('taxonomy', $taxonomy)->with('term')->get())->each(function (Taxonomy $taxonomy) use ($terms) {
                $term = $taxonomy->term;

                if ($term) {
                    $terms->add([
                        'id'        => $term->id,
                        'title'     => $term->title,
                        'slug'      => $term->slug,
                        'parent_id' => $taxonomy->id,
                    ]);
                }
            });
        } else {
            $terms = Term::all();
        }

        $capital = $terms->sortBy(['slug'])->groupBy(function ($item, $key) {
            return $item['slug'][0];
        });

        $data = [
            'terms'   => $terms,
            'total'   => $terms->count(),
            'capital' => $capital,
        ];

        return response()->json($data);
    }

    public function getTermInfo($term)
    {
        return response()->json($term);
    }

    public function getTaxables(Request $request)
    {
        $params = $request->all();
        $term = Term::where('slug', $params['term'])->first();

        if (!isset($term->id)) {
            return response()->json(['message' => __('messages.common.no_data'), 'data' => null, 'category' => null]);
        }

        $taxonomy = Taxonomy::where('term_id', $term->id);

//        $taxonomy = Taxonomy::where('taxonomy', 'tags');

        //how to get only wiki items???
//        if ($params['taxonomy'] != null) {
//            $taxonomy = $taxonomy->where('taxonomy', $params['taxonomy']);
//        }

        $taxables = Taxable::whereIn('taxonomy_id', $taxonomy->pluck('id'));

        if ($params['model'] != null) {
            $taxables = $taxables->where('taxable_type', 'like', '%'.$params['model']);
        }

        $taxableCollection = collect($taxables->orderBy('taxable_type')->orderBy('taxable_id')->get())->map(function (Taxable $taxable) use ($taxonomy) {
            $model = app($taxable->taxable_type);
            $data = $model::where('id', $taxable->taxable_id)->first();

            return [
                'type'              => Str::lower(Str::afterLast($taxable->taxable_type, '\\')),
                'taxable_title'     => $data->{$data->getTaxableTitle()},
                'category'          => $taxonomy,
                'data'              => $data,
                // description lives in taxonomy_translations — the translated
                // attribute is appended on serialization, don't select it.
                // values() so the filtered collection serialises as a JSON
                // array (the frontend reads taxonomy[0]).
                'taxonomy'          => collect($taxonomy->get(['id', 'taxonomy', 'parent_id', 'term_id']))->where('id', $taxable->taxonomy_id)->values(),
            ];
        });

        $capital = $taxableCollection->unique('data')->groupBy(function ($item, $key) {
            return $item['data']['slug'][0];     //treats the name string as an array
        });

        $data = [
            'category' => $taxonomy->with('children')->with('parent')->first(),
            'data'     => [
                'total'   => $taxableCollection->unique('data')->count(),
                'type'    => $taxableCollection->unique('data')->groupBy('type'),
                'capital' => $capital,
            ],
        ];


        return response()->json($data);
    }

    public function saveTerms(Request $request)
    {

        $term = $request->get('term');
        $taxonomy = $request->get('taxonomy');
        $parent = $request->get('parent');
        $parent_id = 0;

        if ($parent !== null) {
            $parent_id = isset($parent['parent_id']) ? $parent['parent_id'] : 0;
        }

        $tax = isset($taxonomy['taxonomy']) ? $taxonomy['taxonomy'] : $taxonomy;

        TaxonomyHelper::createTaxables($term, $tax, $parent_id);

        return response()->json(['success' => true, 'term' => $term]);
    }
}
