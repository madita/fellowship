<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Page;

class ImageController extends Controller
{
    public function upload(Request $request)
    {
//    dd($request->all());
        $request->validate([
            'file' => 'required|image',
        ]);

//        $path = $request->file('file')->store('images', 'public');

        $model = 'Page';
//        $data  = Page::where('slug', $wiki->slug)->first();
        $data  = Page::where('slug', 'abcd')->first();

        $path = $data->addMedia($request->file('file'))->toMediaCollection('images');
        //
//        $replace      = "<img src=\"{$image->getUrl()}\" alt=\"{$altText}\">";


        return response()->json(['path' => asset($path->getURL())]);
    }
}
