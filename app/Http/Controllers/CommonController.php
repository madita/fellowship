<?php

namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommonController extends Controller
{
    public function getItems(Request $request)
    {
        $foreignKeyField = $request->get('foreign_key');

        if (!$foreignKeyField) {
            return response()->json(['error' => __('messages.common.invalid_params')], 400);
        }

        if ($foreignKeyField === 'taxonomy') {
            $items = TaxonomyHelper::getTaxonomy();

            return response()->json($items);
        }

        // Extract the related model name from the foreign key (e.g., 'user_id' -> 'User')
        if (str_ends_with($foreignKeyField, '_id')) {
            $fieldWithoutId = str_replace('_id', '', $foreignKeyField);

            // Split the field based on underscores to determine namespace and model
            $fieldParts = explode('_', $fieldWithoutId);
            if (count($fieldParts) === 1) {
                // Single part (e.g., user_id -> User)
                $relatedModelName = Str::studly($fieldParts[0]);
                $relatedModelClass = "App\\Models\\$relatedModelName";
            } else {
                // Multiple parts (e.g., event_type_id -> App\Models\Event\Type)
                $namespacePart = ucfirst($fieldParts[0]);
                $relatedModelName = Str::studly(str_replace('_id', '', $foreignKeyField));
                if ($namespacePart === 'Tag') {
                    $relatedModelName = Str::replaceFirst($namespacePart, '', $relatedModelName);
                }
                $relatedModelClass = "App\\Models\\$namespacePart\\$relatedModelName";
            }
        } else {
            return response()->json(['error' => __('messages.common.invalid_foreign_key')], 400);
        }

        // Check if the related model class exists
        if (!class_exists($relatedModelClass)) {
            return response()->json(['error' => __('messages.common.model_not_found')], 404);
        }

        try {
            $items = $relatedModelClass::all();

            return response()->json($items);
        } catch (Exception $e) {
            return response()->json(['error' => __('messages.common.fetch_error', ['error' => $e->getMessage()])], 500);
        }
    }
}
