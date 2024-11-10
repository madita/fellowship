<?php

namespace App\Http\Controllers;

use App\Helpers\RelateableHelper;
use App\Models\Collection;
use App\Models\Event\Event;
use Illuminate\Http\Request;

class RelateableController extends Controller
{
    /**
     * Display a list of models that use the Relateable trait.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSourceModels()
    {
        $relateableModels = RelateableHelper::getRelateableModels();

        return response()->json([
            'source_models' => $relateableModels,
        ]);
    }

    public function getModels()
    {
        $models = RelateableHelper::getModels();

        return response()->json([
            'models' => $models,
        ]);
    }

    public function getModelItems(Request $request)
    {
        $post = Event::find(2);

        $collection = Collection::find(4);


        $post->relate($collection);

        $model = $request->input('model');
        return RelateableHelper::getModelItems($model);
    }

    public function relateModels(Request $request)
    {
//        dd($request->get('data'));

        $data = $request->get('data');

        $source = $data['sourceType'];
        $sourceItem = $source::where('id', $data['sourceId'])->first();

        $related = $data['relatedType'];
        $relatedItem = $related::where('id', $data['relatedId'])->first();

        $sourceItem->relate($relatedItem);

        return response()->json(['message' => 'Item related']);
    }
}
