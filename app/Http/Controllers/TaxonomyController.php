<?php

namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use App\Models\Tag\Taxable;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
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

    public function getTaxables(Request $request)
    {
        $params = $request->all();
        $term = Term::where('slug', $params['term'])->first();

        $taxonomy = Taxonomy::where('term_id', $term->id);

        if($params['taxonomy']!=null) {
            $taxonomy = $taxonomy->where('taxonomy', $params['taxonomy']);
        }

        $taxables = Taxable::whereIn('taxonomy_id', $taxonomy->pluck('id'));

        if($params['model']!=null) {
            $taxables = $taxables->where('taxable_type', 'like', '%'.$params['model']);
        }


        $taxableCollection = collect($taxables->orderBy('taxable_type')->orderBy('taxable_id')->get())->map(function (Taxable $taxable){
            $model = app($taxable->taxable_type);
            $data = $model::where('id',$taxable->taxable_id)->first();

            $taxonomies = $data->getTaxonomies('taxonomy')->unique();
            $tax = collect($taxonomies)->mapWithKeys(function ($taxonomy, $key) use($data)  {
                return  [$taxonomy => $data->getTerms($taxonomy)];
            });

                return [
                'type' => Str::lower(Str::afterLast($taxable->taxable_type, '\\')),
                'taxable_title' => $data->{$data->getTaxableTitle()},
                'data'=> $data,
                'taxonomies'=> $tax
            ];
        });

//        dd($taxableCollection->groupBy('type'));

        return response()->json($taxableCollection->unique('data')->groupBy('type'));
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
