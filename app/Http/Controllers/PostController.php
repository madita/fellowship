<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
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
     * view posts.
     * @param $slug
     * @return JsonResponse|\never
     */
    public function view($slug)
    {
        $post = Post::where('slug', '=', $slug)->first();
        $posts = Post::all();

        if (!$post || $post->status !== 'published') {
            return abort(404);
        }

        return response()
            ->json(['data' => $post]);
    }

    public function show(Post $post)
    {
        //$post = Post::where('slug', '=', $slug)->first();
        $posts = Post::all();

        if (!$post) {
            return abort(404);
        }

//        if ($post->sign_in_only && !Auth::check())
//            return redirect('/')->withErrors(config('constants.NA'));

        return response()
            ->json(['data' => $post]);
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->all();
        $post->fill($data);

        $post->save();

        return response()->json([
            'data' => 'succes'
        ]);
    }
}
