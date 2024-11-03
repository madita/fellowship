<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CollectionController extends Controller
{
    public function index()
    {
        // Get all collections with their related media
        $collections = Collection::with('media')->get();

        // Format the media to include custom properties properly
        $collections->each(function ($collection) {
            $collection->media->each(function ($media) {
                $media->caption = $media->getCustomProperty('caption');
                $media->photographer = $media->getCustomProperty('photographer');
                $media->url = $media->getUrl();
            });
        });

        return response()->json($collections);
    }

    public function show($slug)
    {
        $collection = Collection::where('slug', '=', $slug)->first();


        $collection->load('media');

        // Add custom properties to each media item
        $collection->media->each(function ($media) {
            $media->caption = $media->getCustomProperty('caption');
            $media->photographer = $media->getCustomProperty('photographer');
        });

        return response()->json($collection);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'taxonomy_id' => 'required|integer|exists:taxonomies,id', // Validates that taxonomy exists
        ]);


        $user = auth()->user();
        $validatedData['user_id'] = $user->id;

        $collection = Collection::create($validatedData);

        return response()->json($collection, 201);
    }

    // Upload media to a collection
    public function uploadMedia(Request $request, Collection $collection)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif|max:2048',
            'caption' => 'nullable|string|max:255',
        ]);

        // Add the media item to the collection
        $media = $collection->addMedia($request->file('file'))->toMediaCollection('gallery');

        // Set the caption as a custom property if provided
        if ($request->has('caption')) {
            $media->setCustomProperty('caption', $request->input('caption'));
            $media->save();
        }

        return response()->json(['message' => 'Media uploaded successfully']);
    }

    public function updateMediaCaption(Request $request, $mediaId)
    {
        $request->validate([
            'caption' => 'required|string|max:255',
        ]);

        // Find the media item and update its caption
        $media = Media::findOrFail($mediaId);
        $media->setCustomProperty('caption', $request->input('caption'));
        $media->save();

        return response()->json(['message' => 'Caption updated successfully']);
    }
}
