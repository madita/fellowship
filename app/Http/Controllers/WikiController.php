<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lecturize\Taxonomies\Models\Taxonomy;
use Lecturize\Taxonomies\Models\Term;
use function PHPUnit\Framework\isNull;
use Illuminate\Pagination\LengthAwarePaginator;

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
            case 'page':
                return [
                    'title',
                    'content',
                    'published',
                    'sign_in_only',];
        }
    }

    /**
     * view landing pages.
     *
     * @param $slug
     *
     * @return JsonResponse|\never
     */
    public function index(Request $request)
    {
        $perPage = 9;
        $query = $request->get('q');


        $page = request()->input('page', 1); // Current page number, default to 1

        if($query === null || $query === "") {
            $wikidata = Wiki::paginate($perPage);
        } else {
            $wikidata = Wiki::where('title', 'LIKE', '%' . $query . '%')->paginate(9);
        }

        $total = $wikidata->total();
        $offset = ($page - 1) * $perPage;

        //dd($wikidata->toSql());

        $wiki = $wikidata->getCollection()->map(function (Wiki $wiki) {

            $model = $wiki->wikiable_type;
            $data  = $model::where('id', $wiki->wikiable_id)->first();

            $taxonomies = $data->getCategories('wiki')->unique();

            return [
                'title'      => $wiki->title,
                'slug'       => $wiki->slug,
                'type'       => Str::lower(Str::afterLast($wiki->wikiable_type, '\\')),
                'model'      => $wiki->wikiable_type,
                //                    'taxable_title' => $data->{$data->getTaxableTitle()},
                'data'       => $data,
                'taxonomies' => $taxonomies];

        });

        $paginator = new LengthAwarePaginator(
            $wiki,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

//        $wikis = $paginator->values();

        $links = [];

        for($cnt = $page; $cnt <= $page+5; $cnt++) {

            $links[] = [
                'active' => $cnt===$page?true:false,
                'label' => $cnt,
                'url'=> request()->url()."?page{$cnt}&q={$query}"
            ];
        }

        return response()->json([
                                    'data' => $paginator->values(),
                                    'total' => $total,
                                    'to' => $perPage * $page,
                                    'per_page' => $perPage,
                                    'current_page' => $page,
                                    'first_page' => $paginator->url(1),
                                    'last_page' => $paginator->lastPage(),
                                    'next_page_url' => $paginator->nextPageUrl(),
                                    'prev_page_url' => $paginator->previousPageUrl(),
                                    'path' => request()->url(),
                                    'links' => $links
                                ]);

//        $wiki->total = $wikidata->total;
//        $wiki->to = $wiki->per_page*$wiki->current_page;


//        return response()->json($paginator);
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

        $content = $data->content;

        preg_match_all(
            "/\[\[(.*?)(\|(.*?))?\]\]/", $content, $matches
        );

        foreach ($matches[0] as $key => $item) {

            //get anker
            $title = explode("#", $matches[1][$key]);

//            $page  = Page::new(['title'=> $title[0], 'slug' => Str::slug($title[0])]);


                $title       = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $title[0];
                $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;

                $page = Page::firstOrNew(['title' => $title, 'slug' => Str::slug($title)]);


                $replace =
                    "<a data-wiki-id=\"0\" class=\"new\" data-title=\"{$page->title}\" data-linked-resource-type=\"wikiable\" data-alternative=\"{$alternative}\" href=\"/wiki/{$page->slug}\" contenteditable=\"false\">{$title}</a>";
            $content = Str::replace($item, $replace, $content);


        }
        $data->content = $content;
        $taxonomies = $data->getCategories('wiki')->unique();
        $terms      = $data->getCategories('tags')->unique();

        //        $data->content = Str::replace()

        return response()->json(['page' => $data, 'parents' => $data->parents, 'terms' => $taxonomies, 'tags' => $terms]);
    }


    public function history($wikiable, $id)
    {

    }

    public function store(Request $request)
    {
        //        dd($request->all());
        $page = auth()->user()->pages()->create([
                                                    'title'        => $request->get('title'),
                                                    'content'      => $request->get('content'),
                                                    'sign_in_only' => 0,
                                                    'published'    => 1]);

        if ($request->get('categories')) {
            //            $taxonomy = $request->get('taxonomy');
            //            $taxonomy = $taxonomy['taxonomy'];
            //            //            dd('hm');
            //            $page->addCategories($request->get('categories'), $taxonomy);

            foreach ($request->get('categories') as $term) {
                if (isset($term['title'])) {
                    $page->addCategory($term['title'], 'wiki');
                } else {
                    $page->addCategory($term, 'wiki');
                }
            }
        }


        if ($request->get('terms')) {

            foreach ($request->get('terms') as $term) {
                if (isset($term['title'])) {
                    $page->addCategory($term['title'], 'tags');
                } else {
                    $page->addCategory($term, 'tags');
                }
            }
        }
        $wiki = new Wiki(['title' => $page->title, 'slug' => $request->get('slug')]);

        $page->wikiable()->save($wiki);

        return response()->json(['message' => 'success', 'page' => $page]);

    }

    public function update(Request $request, $slug)
    {
        //        dd('update', $request->all(), $slug);

        //
        //        $wiki = $request->all();
        //
        //        $model = $wiki['type'];
        //        $data = $model::where('id', $wiki['id'])->first();


        $wiki  = Wiki::where('slug', '=', $slug)->first();
        $model = $wiki->wikiable_type;

        $data = $model::where('id', $wiki->wikiable_id)->first();


        $data->update($request->only($this->getUpdatableColumns($request->get('type'))));

        //
        if ($request->get('parent')) {
            $parent = $request->get('parent');


            $data->parent_id = $parent['id'];
            $data->update();
        }

        $data->detachCategories();

        if ($request->get('taxonomy') && $request->get('categories')) {
            $taxonomy = $request->get('taxonomy');
            if (!is_string($taxonomy)) {
                $taxonomy = $taxonomy['taxonomy'];
            }

            //            $data->addCategories($request->get('categories'), $taxonomy);
            if ($request->get('categories')) {

                foreach ($request->get('categories') as $term) {
                    if (isset($term['title'])) {
                        $data->addCategory($term['title'], 'wiki');
                    } else {
                        $data->addCategory($term, 'wiki');
                    }
                }
            }
        }

        if ($request->get('terms')) {

            foreach ($request->get('terms') as $term) {
                if (isset($term['title'])) {
                    $data->addCategory($term['title'], 'tags');
                } else {
                    $data->addCategory($term, 'tags');
                }
            }
        }


        //        if($request->get('terms')) {
        //            $data->addCategories($request->get('terms'),'tags');
        //        }

        return response()->json(['message' => 'success', $model => $data]);
    }

    public function storeCategory(Request $request)
    {
       // dd($request);

        $term = Term::firstOrCreate(['title' => $request->get('term')]);

        $taxonomy              = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);
        $parent = $request->get('parent');
//        dd($parent);

        if($parent['parent_id']) {
            $taxonomy->parent_id = $parent['parent_id'];
        }

        if($request->get('content')) {
            $taxonomy->description = $request->get('content');
        }
        $taxonomy->save();

        return response()->json(['message' => 'success', 'taxonomy' => $taxonomy]);

    }

    public function updateCategory(Request $request, $slug)
    {
//         dd($request);

        $termNew = $request->get('category');
        $termOld = $request->get('old');
        $parent = $request->get('parent');
        $title = $request->get('term');

//        dd($parent);

        $term = Term::find($termNew['term']['id']);
        if($term->title != $termOld['term']['title']) {
            $term->title = $termNew['term']['title'];
            $term->slug = Str::slug($termNew['term']['title']);
            $term->update();
        }

        $taxonomy  = Taxonomy::where('term_id',$term->id)->where('taxonomy', 'wiki')->first();
//        $parent = $termNew['parent'];
        //        dd($parent);

        if($parent['parent_id']) {
            $taxonomy->parent_id = $parent['parent_id'];
        }

        if($request->get('content')) {
            $taxonomy->description = $request->get('content');
        }
        $taxonomy->update();

//        dd($taxonomy);
        return response()->json(['message' => 'success', 'slugchange' => $term->title != $termOld['term']['title'] ,'term'=> $term, 'taxonomy' => $taxonomy]);

    }


}
