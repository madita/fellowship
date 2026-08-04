<?php

namespace App\Http\Controllers;

use App\Helpers\RelateableHelper;
use App\Models\Collection;
use App\Models\Relateable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RelateableController extends Controller
{
    /**
     * Whitelist of allowed model types for relating.
     * Prevents arbitrary class instantiation attacks.
     */
    private const ALLOWED_RELATEABLE_TYPES = [
        'App\\Models\\Collection',
        'App\\Models\\Event\\Event',
        'App\\Models\\Page',
        'App\\Models\\Post',
        'App\\Models\\Wiki',
    ];

    /**
     * Display a list of models that use the Relateable trait.
     *
     * @return JsonResponse
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
        $request->validate([
            'model' => 'required|string',
        ]);

        $model = $request->input('model');

        return RelateableHelper::getModelItems($model);
    }

    public function relateModels(Request $request)
    {
        $request->validate([
            'data.sourceType' => 'required|string',
            'data.sourceId' => 'required|integer',
            'data.relatedType' => 'required|string',
            'data.relatedId' => 'required|integer',
        ]);

        $data = $request->get('data');

        $sourceType = $data['sourceType'];
        $relatedType = $data['relatedType'];

        // SECURITY: Validate model types against whitelist to prevent arbitrary class instantiation
        if (! in_array($sourceType, self::ALLOWED_RELATEABLE_TYPES, true)) {
            Log::warning('Relateable: Invalid source type attempted', [
                'sourceType' => $sourceType,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => __('messages.common.invalid_model_type')], 400);
        }

        if (! in_array($relatedType, self::ALLOWED_RELATEABLE_TYPES, true)) {
            Log::warning('Relateable: Invalid related type attempted', [
                'relatedType' => $relatedType,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => __('messages.common.invalid_model_type')], 400);
        }

        $sourceItem = $sourceType::find($data['sourceId']);
        $relatedItem = $relatedType::find($data['relatedId']);

        if (! $sourceItem || ! $relatedItem) {
            return response()->json(['error' => __('messages.common.item_not_found')], 404);
        }

        $sourceItem->relate($relatedItem);

        return response()->json(['message' => 'Item related successfully']);
    }

    /**
     * Unrelate two models (remove relationship).
     */
    public function unrelateModels(Request $request)
    {
        $request->validate([
            'data' => 'required|array',
            'data.sourceType' => 'required|string',
            'data.sourceId' => 'required|integer',
            'data.relatedType' => 'required|string',
            'data.relatedId' => 'required|integer',
        ]);

        $data = $request->get('data');

        $source = $data['sourceType'];
        $sourceItem = $source::where('id', $data['sourceId'])->first();

        $related = $data['relatedType'];
        $relatedItem = $related::where('id', $data['relatedId'])->first();

        if (! $sourceItem || ! $relatedItem) {
            return response()->json(['message' => 'Source or related item not found'], 404);
        }

        $sourceItem->unrelate($relatedItem);

        return response()->json(['message' => 'Item unrelated successfully']);

        return response()->json(['message' => __('messages.common.item_related')]);
    }

    public function getRelatedItems(Request $request)
    {
        $request->validate([
            'modelType' => 'required|string',
            'modelId' => 'required|integer',
        ]);

        $modelType = $request->get('modelType');
        $modelId = $request->get('modelId');

        // SECURITY: Validate model type against whitelist
        if (! in_array($modelType, self::ALLOWED_RELATEABLE_TYPES, true)) {
            Log::warning('Relateable: Invalid model type in getRelatedItems', [
                'modelType' => $modelType,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => __('messages.common.invalid_model_type')], 400);
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
            if ($relatedModel instanceof Collection) {
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
