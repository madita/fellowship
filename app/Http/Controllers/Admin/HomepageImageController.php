<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomepageImageController extends Controller
{
    /**
     * Upload an image for homepage widgets.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image'      => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // 2MB max
            'collection' => 'nullable|string|in:home',
        ]);

        try {
            $file = $request->file('image');
            $collection = $request->input('collection', 'home');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file upload',
                ], 422);
            }

            // Generate filename
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('widget_').'_'.Str::random(10).'.'.$extension;
            $path = $collection.'/'.$filename;

            // Store file (Windows-compatible method)
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath() ?: $file->getPathname()));

            return response()->json([
                'success' => true,
                'path'    => $path,
                'url'     => Storage::url($path),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an image.
     */
    public function delete(Request $request)
    {
        $path = $request->input('path');

        // Don't try to delete if path is empty or invalid
        if (empty($path) || !is_string($path) || trim($path) === '') {
            return response()->json([
                'success' => true,
                'message' => 'No image to delete',
            ]);
        }

        try {
            // Additional safety check before file operations
            $path = trim($path);

            if (strlen($path) > 0 && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: '.$e->getMessage(),
            ], 500);
        }
    }
}
