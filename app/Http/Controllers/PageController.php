<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * view landing pages.
     *
     * @param $slug
     *
     * @return JsonResponse|\never
     */
    public function view($slug)
    {
        $page = Page::where('slug', '=', $slug)->first();
        //$pages = Page::all();

        if (!$page || !$page->published) {
            return abort(404);
        }

        if ($page->sign_in_only && !auth()->check()) {
            return abort(403);
        }

        return response()
            ->json($page);
    }

    public function show(Page $page)
    {
        //$page = Page::where('slug', '=', $slug)->first();
        $pages = Page::all();

        if (!$page) {
            return abort(404);
        }

//        if ($page->sign_in_only && !Auth::check())
//            return redirect('/')->withErrors(config('constants.NA'));

        return response()
            ->json(['data' => $page]);
    }

    public function history(Page $page)
    {
//        $page = Page::where('slug', '=', $slug)->first();

        if (!$page || !$page->published) {
            return abort(404);
        }

        if ($page->sign_in_only && !auth()->check()) {
            return abort(403);
        }

        $history = collect($page->revisions)->map(function (\App\Models\Revision $revision) {
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
