<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Revision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * view landing pages.
     *
     *
     * @return JsonResponse|never
     */
    public function view($slug)
    {
        $page = Page::where('slug', '=', $slug)->with(['children', 'translations'])->first();

        $parent = null;
        // $pages = Page::all();

        if (! $page || ! $page->published) {
            return abort(404);
        }

        if ($page->sign_in_only && ! auth()->check()) {
            return abort(403);
        }

        $tax = $page->taxonomies()->get()->groupBy('taxonomy')->map(function ($items) {
            return $items->filter(fn ($t) => $t->term)->map(function ($t) {
                return [
                    'id' => $t->term->id,
                    'name' => $t->term->title,
                    'slug' => $t->term->slug,
                    'color' => $t->color,
                ];
            })->values();
        });

        return response()
            ->json(['page' => $page, 'parents' => $page->parents, 'taxonomies' => $tax]);
    }

    public function show(Page $page)
    {
        if (! $page) {
            return abort(404);
        }

        $tax = $page->taxonomies()->get()->groupBy('taxonomy')->map(function ($items) {
            return $items->filter(fn ($t) => $t->term)->map(function ($t) {
                return [
                    'id' => $t->term->id,
                    'title' => $t->term->title,
                    'slug' => $t->term->slug,
                    'color' => $t->color,
                ];
            })->values();
        });

        return response()
            ->json(['page' => $page, 'parent' => $page->parent, 'taxonomies' => $tax]);
    }

    //    public function showWithCategory($taxonomy, $category)
    //    {
    //
    //        $pages = Page::withTerm($category, 'tags')->where('published', true)->get();
    //
    //        return response()
    //            ->json(['pages' => $pages]);
    //    }

    public function history(Page $page)
    {
        //        $page = Page::where('slug', '=', $slug)->first();

        if (! $page || ! $page->published) {
            return abort(404);
        }

        if ($page->sign_in_only && ! auth()->check()) {
            return abort(403);
        }

        $history = collect($page->revisions)->map(function (Revision $revision) {
            $revision['user'] = $revision->executor()->first();
            $revision['diff'] = $revision->getDiff();

            return $revision;
        });

        return response()
            ->json($history->toArray());
    }

    public function update(Request $request, Page $page)
    {
        $data = $request->all();
        $page->fill($data);

        $page->save();

        return response()->json([
            'data' => 'succes',
        ]);
    }
}
