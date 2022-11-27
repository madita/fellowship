<?php

namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use App\Models\Tag\Taxable;
use App\Models\Tag\Taxonomy;
//use App\Models\Tag\Term;
use Lecturize\Taxonomies\Models\Term;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use function PHPUnit\Framework\isNull;

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
            $tax   = collect(Taxonomy::where('taxonomy', $taxonomy)->with('term')->get())->each(function (Taxonomy $taxonomy) use ($terms) {

                    $term = $taxonomy->term()->get(['id', 'title', 'slug'])->flatten()->toArray();

                    if (isset($term[0])) {
                        $tempArr = array_merge($term[0], ['parent_id' => $taxonomy->id]);
                        $terms->add($tempArr);
                    }
                });

        } else {
            $terms = Term::all();
        }

        return response()->json($terms);
    }

    public function getTaxables(Request $request)
    {

        $params = $request->all();
        //        dd($params);
        $term = Term::where('slug', $params['term'])->first();


        if (!isset($term->id)) {
            return response()->json(['message' => 'No data found']);
        }

        $taxonomy = Taxonomy::where('term_id', $term->id);
//        dd($taxonomy->get());

//        $taxonomy = Taxonomy::where('taxonomy', 'tags');

//how to get only wiki items???
//        if ($params['taxonomy'] != null) {
//            $taxonomy = $taxonomy->where('taxonomy', $params['taxonomy']);
//        }


        $taxables = Taxable::whereIn('taxonomy_id', $taxonomy->pluck('id'));
//        dd($taxonomy->pluck('id'));
//        dd($taxables->get());

        if ($params['model'] != null) {
            $taxables = $taxables->where('taxable_type', 'like', '%' . $params['model']);
        }


        $taxableCollection = collect($taxables->orderBy('taxable_type')->orderBy('taxable_id')->get())->map(function (Taxable $taxable) use($taxonomy) {
            $model = app($taxable->taxable_type);
            $data  = $model::where('id', $taxable->taxable_id)->first();

                return [
                    'type'          => Str::lower(Str::afterLast($taxable->taxable_type, '\\')),
                    'taxable_title' => $data->{$data->getTaxableTitle()},
                    'category' => $taxonomy,
                    'data'          => $data,
                    'taxonomy'          => collect($taxonomy->get(['id', 'taxonomy', 'parent_id', 'term_id', 'description']))->where('id', $taxable->taxonomy_id),
                    ];

        });

        $capital = $taxableCollection->unique('data')->groupBy(function($item,$key) {
            return $item['data']['slug'][0];     //treats the name string as an array
        });

        $data = [
            'category' => $taxonomy->get(),
            'data' => [
                'total' => $taxableCollection->unique('data')->count(),
                'type' => $taxableCollection->unique('data')->groupBy('type'),
                'capital' => $capital
            ]
        ];


        //        dd($taxableCollection->groupBy('type'));

        return response()->json($data);
    }

    public function saveTerms(Request $request)
    {

//        dd($request);

        $term     = $request->get('term');
        $taxonomy = $request->get('taxonomy');
        $parent   = $request->get('parent');

        $parent_id = $parent === null ? 0:$parent['parent_id'];

//        dd($parent_id);

        TaxonomyHelper::createTaxables($term, $taxonomy['taxonomy'], $parent_id);

        return response()->json(['success' => true, 'term' => $term]);
    }
}
