<?php

namespace App\Http\Controllers;

use App\Helpers\RelateableHelper;
use App\Models\Collection;
use App\Models\Event\Event;
use Illuminate\Http\Request;
use App\Models\Relateable;

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

    public function getRelatedItems(Request $request)
    {
        $modelType = $request->get('modelType');
        $modelId = $request->get('modelId');

        if (!$modelId || !$modelType) {
            return response()->json(['error' => 'Model ID and type are required'], 400);
        }

        $relatedItems = Relateable::with(['source', 'related'])
            ->where('source_id', $modelId)
            ->where('source_type', $modelType)
            ->get();

        // Map the related items with both source and related models
        $items = $relatedItems->map(function ($item) {
            $relatedModel = $item->related;

            // Determine the coverImage if the related model is a Collection
            $coverImage = null;
            if ($relatedModel instanceof \App\Models\Collection) {
                $coverMedia = $relatedModel->media->first(fn ($media) => $media->getCustomProperty('is_cover', false))
                    ?? $relatedModel->media->first();
                $coverImage = $coverMedia ? $coverMedia->getUrl() : null;
            }

            return [
                'source' => [
                    'id' => $item->source->id,
                    'type' => $item->source_type,
                    'title' => $this->getModelLabel($item->source),
                    'slug' => $item->source->slug,
                ],
                'related' => [
                    'id' => $relatedModel->id,
                    'type' => $item->related_type,
                    'title' => $this->getModelLabel($relatedModel),
                    'slug' => $relatedModel->slug,
                    'coverImage' => $coverImage, // Include the coverImage for related items
                ],
            ];
        });

        return response()->json(['items' => $items]);
    }


    protected function getModelLabel($model)
    {
        return $model->name ?? $model->title ?? 'Unknown';
    }
}
