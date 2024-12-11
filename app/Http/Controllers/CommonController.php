<?php
namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommonController extends Controller
{
    public function getItems(Request $request)
    {
//        dd($request);
        // Get the foreign key field and its value from the request
        $foreignKeyField = $request->get('foreign_key');
//        $foreignKeyValue = $request->input('value');

//        dd($foreignKeyField);

        if (!$foreignKeyField) {
            return response()->json(['error' => 'Invalid parameters provided'], 400);
        }

        if($foreignKeyField === 'taxonomy') {
//            dd('taxonmoy');
//            dd(TaxonomyHelper::getTaxonomy());

            $items = TaxonomyHelper::getTaxonomy();

            return response()->json($items);
        }

        // Extract the related model name from the foreign key (e.g., 'user_id' -> 'User')
        if (str_ends_with($foreignKeyField, '_id')) {

            //$relatedModelName = Str::studly(str_replace('_id', '', $foreignKeyField));
            $fieldWithoutId = str_replace('_id', '', $foreignKeyField);

            // Split the field based on underscores to determine namespace and model
            $fieldParts = explode('_', $fieldWithoutId);
            if (count($fieldParts) === 1) {
                // Single part (e.g., user_id -> User)
                $relatedModelName = Str::studly($fieldParts[0]);
                $relatedModelClass = "App\\Models\\$relatedModelName";
            } else {
                // Multiple parts (e.g., event_type_id -> App\Models\Event\Type)
                $namespacePart = ucfirst($fieldParts[0]); // First part, used for namespace
                $relatedModelName = Str::studly(str_replace('_id', '', $foreignKeyField));
                if($namespacePart === 'Tag') {
                    $relatedModelName = Str::replaceFirst($namespacePart, '', $relatedModelName);
                }
//                $modelNamePart = Str::studly(implode('_', array_slice($fieldParts, 1))); // Remaining parts combined, used for model name
                $relatedModelClass = "App\\Models\\$namespacePart\\$relatedModelName";
            }
        } else {
            return response()->json(['error' => 'Invalid foreign key field'], 400);
        }

//        dd($relatedModelClass);

        // Construct the related model class name dynamically
//        $relatedModelClass = "App\\Models\\" . $relatedModelName;

        // Check if the related model class exists
        if (!class_exists($relatedModelClass)) {
//            dd($relatedModelClass);
            return response()->json(['error' => 'Related model not found'], 404);
        }

        try {
            // Query the related model for the given ID value
            $items = $relatedModelClass::all();

            return response()->json($items);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching the items: ' . $e->getMessage()], 500);
        }
    }
}

