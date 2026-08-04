<?php

namespace App\Helpers;

use App\Traits\HasRelateableContent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RelateableHelper
{
    public static function getRelateableModels()
    {
        $modelsPath = app_path('Models');
        $modelFiles = File::allFiles($modelsPath);
        //        dd($modelFiles);
        $relateableModels = [];

        foreach ($modelFiles as $file) {
            //            dd($file->getRelativePathname());
            // Get the relative path from the models directory
            //            $relativePath = Str::replaceFirst($modelsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());

            //            dd($relativePath);

            // Convert the relative path to a namespace
            $namespace = 'App\Models\\'.str_replace('/', '\\', Str::before($file->getRelativePathname(), '.php'));

            //            dd(in_array(HasRelateableContent::class, class_uses_recursive($namespace)));

            if (class_exists($namespace) && in_array(HasRelateableContent::class, class_uses_recursive($namespace))) {
                $relateableModels[] = $namespace;
            }
        }

        return $relateableModels;
    }

    public static function getModels()
    {
        $modelsPath = app_path('Models');
        $modelFiles = File::allFiles($modelsPath);
        //        dd($modelFiles);
        $models = [];

        foreach ($modelFiles as $file) {
            $namespace = 'App\Models\\'.str_replace('/', '\\', Str::before($file->getRelativePathname(), '.php'));

            if (class_exists($namespace)) {
                $models[] = $namespace;
            }
        }

        return $models;
    }

    public static function getModelItems($model)
    {
        // Validate that the model class exists
        if (! class_exists($model)) {
            return response()->json(['error' => __('messages.common.model_not_found')], 404);
        }

        // Ensure the model is an instance of Eloquent or has the necessary traits
        if (! in_array('Illuminate\Database\Eloquent\Model', class_parents($model))) {
            return response()->json(['error' => __('messages.common.invalid_model_type')], 400);
        }

        // Fetch all items from the model
        $items = $model::all();

        // Return items in JSON format
        return response()->json(['items' => $items], 200);
    }
}
