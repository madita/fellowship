<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lecturize\Taxonomies\Models\Term;
use Stevebauman\Purify\Facades\Purify;
use Lecturize\Taxonomies\Models\Taxonomy;
use App\Helpers\TaxonomyHelper;

class WikiController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * view landing pages.
     *
     * @param $slug
     *
     * @return JsonResponse|\never
     */
    public function index()
    {

        $wiki = collect(Wiki::all())->map(function (Wiki $wiki) {
            $model = $wiki->wikiable_type;
            $data  = $model::where('id', $wiki->wikiable_id)->first();

            $taxonomies = $data->getCategories('wiki')->unique();

            return [
                'title'      => $wiki->title,
                'slug'       => $wiki->slug,
                'type'       => Str::lower(Str::afterLast($wiki->wikiable_type, '\\')),
                //                    'taxable_title' => $data->{$data->getTaxableTitle()},
                'data'       => $data,
                'taxonomies' => $taxonomies];

        });
        //        dd($wiki);
        //        $taxonomy = Taxonomy::where('taxonomy', 'wiki');
        //
        //        $taxables = Taxable::whereIn('taxonomy_id', $taxonomy->pluck('id'));
        //
        //        $taxableCollection = collect($taxables->orderBy('taxable_type')->orderBy('taxable_id')->get())->map(function (Taxable $taxable) {
        //            $model = $taxable->taxable_type;
        //            $data  = $model::where('id', $taxable->taxable_id)->first();
        //
        //            if ($data !== null) {
        //                $taxonomies = $data->getCategories('wiki')->unique();
        //                return [
        //                    'type'          => Str::lower(Str::afterLast($taxable->taxable_type, '\\')),
        //                    'taxable_title' => $data->{$data->getTaxableTitle()},
        //                    'data'          => $data,
        //                    'taxonomies'    => $taxonomies];
        //            }
        //        });

        return response()->json($wiki->groupBy('type'));
    }

    /**
     * view landing pages.
     *
     * @param $slug
     *
     * @return JsonResponse|\never
     */
    public function view($wikiable, $id)
    {

    }

    public function show($slug)
    {

        //        dd($slug);

        $wiki = Wiki::where('slug', '=', $slug)->first();

        if ($wiki === null) {
            return response()->json(['message' => 'Create Wiki page', 'slug' => $slug]);
        }

        $model = $wiki->wikiable_type;

        $data = $model::where('id', $wiki->wikiable_id)->first();

        $taxonomies = $data->getCategories('wiki')->unique();

        return response()->json(['page' => $data, 'parents' => $data->parents, 'taxonomies' => $taxonomies]);
    }


    public function history($wikiable, $id)
    {

    }

    public function update(Request $request, $wikiable)
    {

    }

}
