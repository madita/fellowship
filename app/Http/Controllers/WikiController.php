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

    public function getUpdatableColumns($type)
    {
        switch ($type) {
            case 'page': return  [
                'title',
                'content',
                'published',
                'sign_in_only',
            ];
        }
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
                'model'       => $wiki->wikiable_type,
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
            $data = ['slug' => $slug, 'title' => Str::ucfirst($slug), 'content' => ""];
//            return response()->json(['status' => 404, 'message' => 'Create Wiki page', 'page' => $data]);
            return response(['status' => 404, 'message' => 'Create Wiki page', 'page' => $data], 404);
        }

        $model = $wiki->wikiable_type;

        $data = $model::where('id', $wiki->wikiable_id)->first();

        $taxonomies = $data->getCategories('wiki')->unique();
        $terms = $data->getCategories('tags')->unique();

        return response()->json(['page' => $data, 'parents' => $data->parents, 'terms' => $taxonomies, 'tags' => $terms]);
    }


    public function history($wikiable, $id)
    {

    }

    public function store(Request $request)
    {
//        dd($request->all());
        $page = auth()->user()->pages()->create(
            [
                'title'      => $request->get('title'),
                'content'    => $request->get('content'),
                'sign_in_only' => 0,
                'published' => 1
            ]
        );

        if($request->get('categories')) {
//            $taxonomy = $request->get('taxonomy');
//            $taxonomy = $taxonomy['taxonomy'];
//            //            dd('hm');
//            $page->addCategories($request->get('categories'), $taxonomy);

            foreach ($request->get('categories') as $term) {
                if(isset($term['title'])) {
                    $page->addCategory($term['title'], 'wiki');
                } else {
                    $page->addCategory($term, 'wiki');
                }
            }
        }


        if($request->get('terms')) {

            foreach ($request->get('terms') as $term) {
                if(isset($term['title'])) {
                    $page->addCategory($term['title'], 'tags');
                } else {
                    $page->addCategory($term, 'tags');
                }
            }
        }
        $wiki = new Wiki(['title' => $page->title, 'slug' => $request->get('slug')]);

        $page->wikiable()->save($wiki);
    }

    public function update(Request $request, $slug)
    {
//        dd('update', $request->all(), $slug);

//
//        $wiki = $request->all();
//
//        $model = $wiki['type'];
//        $data = $model::where('id', $wiki['id'])->first();



        $wiki = Wiki::where('slug', '=', $slug)->first();
        $model = $wiki->wikiable_type;

        $data = $model::where('id', $wiki->wikiable_id)->first();



        $data->update($request->only($this->getUpdatableColumns($request->get('type'))));

        //
        if($request->get('parent')) {
            $parent = $request->get('parent');


            $data->parent_id = $parent['id'];
            $data->update();
        }

        $data->detachCategories();

        if($request->get('taxonomy') && $request->get('categories')) {
            $taxonomy = $request->get('taxonomy');
            if(!is_string($taxonomy)) {
                $taxonomy = $taxonomy['taxonomy'];
            }

//            $data->addCategories($request->get('categories'), $taxonomy);
            if($request->get('categories')) {

                foreach ($request->get('categories') as $term) {
                    if(isset($term['title'])) {
                        $data->addCategory($term['title'], 'wiki');
                    } else {
                        $data->addCategory($term, 'wiki');
                    }
                }
            }
        }

        if($request->get('terms')) {

            foreach ($request->get('terms') as $term) {
                if(isset($term['title'])) {
                    $data->addCategory($term['title'], 'tags');
                } else {
                    $data->addCategory($term, 'tags');
                }
            }
        }


//        if($request->get('terms')) {
//            $data->addCategories($request->get('terms'),'tags');
//        }


    }



}
