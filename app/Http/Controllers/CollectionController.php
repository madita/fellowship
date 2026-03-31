<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CollectionController extends Controller
{
    public function index()
    {
        // Get all collections with their related media
        $collections = Collection::with('media')->orderBy('created_at', 'desc')->get();

        // Format the media to include custom properties properly
        $collections->each(function ($collection) {
            $coverMedia = $collection->media->first(fn ($media) => $media->getCustomProperty('is_cover', false))
                ?? $collection->media->first(); // Fallback to the first media item

            $collection->coverImage = $coverMedia ? $coverMedia->getUrl() : null;

            $collection->media->each(function ($media) {
                $media->caption = $media->getCustomProperty('caption');
                $media->uploader = $media->getCustomProperty('uploader');
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
            $media->uploader = $media->getCustomProperty('uploader');
        });

        return response()->json($collection);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'        => 'required|string|max:255',
            'taxonomy_id' => 'required|integer|exists:taxonomies,id', // Validates that taxonomy exists
        ]);

        $user = auth()->user();
        if ($user->id) {
            $validatedData['user_id'] = $user->id;
        }

        $collection = Collection::create($validatedData);

        return response()->json($collection, 201);
    }

    // Upload media to a collection
    public function uploadMedia(Request $request, Collection $collection)
    {
        // Authorization check - only owner or admin can upload
        $this->authorize('uploadMedia', $collection);

        $request->validate([
            'files'      => 'required|array', // Ensure files is an array
            'files.*'    => 'file|mimes:jpg,jpeg,png,gif|max:2048', // Validate each file
            'captions'   => 'nullable|array', // Optional captions
            'captions.*' => 'nullable|string|max:255', // Validate each caption
        ]);

        $uploadedMedia = [];

        foreach ($request->file('files') as $index => $file) {
            // Add each media item to the collection

            $extension = $file->getClientOriginalExtension();
            $newFilename = Str::uuid().'.'.$extension;
            /** @var User $user */
            $user = auth()->user();

            $media = $collection->addMedia($file)
                ->usingFileName($newFilename)
                ->withCustomProperties(['album' => $collection->name, 'uploader' => $user->username]);

            $media = $collection->addMedia($file)->toMediaCollection('gallery');

            // Set the caption as a custom property if provided
            if ($request->has('captions') && isset($request->captions[$index])) {
                $media->setCustomProperty('caption', $request->captions[$index]);
                $media->save();
            }

            $uploadedMedia[] = [
                'id'      => $media->id,
                'url'     => $media->getUrl(),
                'caption' => $media->getCustomProperty('caption', null),
            ];
        }

        return response()->json([
            'message'        => __('messages.media.uploaded'),
            'uploaded_media' => $uploadedMedia,
        ]);
    }

    public function updateMediaCaption(Request $request, $mediaId)
    {
        $request->validate([
            'caption' => 'required|string|max:255',
        ]);

        // Find the media item
        $media = Media::findOrFail($mediaId);

        // Get the collection that owns this media and check authorization
        $collection = Collection::findOrFail($media->model_id);
        $this->authorize('update', $collection);

        $media->setCustomProperty('caption', $request->input('caption'));
        $media->save();

        return response()->json(['message' => __('messages.media.caption_updated')]);
    }

    public function setCoverImage(Request $request, $collectionId)
    {
        $collection = Collection::findOrFail($collectionId);

        $this->authorize('update', $collection);

        $request->validate([
            'media_id' => 'required|integer|exists:media,id',
        ]);

        $collection = Collection::findOrFail($collectionId);

        // Ensure only one media is marked as the cover
        $collection->media()->update(['custom_properties->is_cover' => false]);

        // Mark the selected media as the cover
        $media = $collection->media()->findOrFail($request->media_id);
        $media->setCustomProperty('is_cover', true)->save();

        return response()->json(['message' => __('messages.media.cover_updated')]);
    }
}
