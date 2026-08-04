<?php

namespace App\Http\Controllers;

use App\Models\Status\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StatusController extends Controller
{
    /**
     * Get timeline/feed (all statuses or user's feed).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Status::with(['user', 'likes', 'comments.user', 'comments.replies.user'])
            ->recent();

        // Filter by user if specified
        if ($request->has('user_id')) {
            $query->fromUser($request->user_id);
        }

        $statuses = $query->paginate($request->get('per_page', 10));

        return response()->json($statuses);
    }

    /**
     * Get a specific status with full details.
     */
    public function show(Status $status): JsonResponse
    {
        $status->load(['user', 'likes.user', 'allComments.user', 'allComments.replies.user']);

        return response()->json($status);
    }

    /**
     * Create a new status.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'You must be logged in to post a status.');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'images' => 'array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'feeling' => 'nullable|string|max:50',
        ]);

        $status = Status::create([
            'user_id' => $user->id,
            'content' => $validated['content'],
            'feeling' => $validated['feeling'] ?? null,
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $filename = Str::uuid().'.'.$image->getClientOriginalExtension();

                $status->addMedia($image)
                    ->usingFileName($filename)
                    ->toMediaCollection('images');
            }
        }

        return response()->json($status->load('user', 'media'), 201);
    }

    /**
     * Update a status.
     */
    public function update(Request $request, Status $status): JsonResponse
    {
        $user = Auth::user();

        if (! $status->canEdit($user)) {
            abort(403, 'You do not have permission to edit this status.');
        }

        $validated = $request->validate([
            'content' => 'sometimes|string|max:5000',
            'remove_media_ids' => 'array',
            'remove_media_ids.*' => 'integer',
            'images' => 'array',
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        if ($request->has('content')) {
            $status->update(['content' => $validated['content']]);
        }

        // Remove images the user deleted (scoped to this status only)
        if (! empty($validated['remove_media_ids'])) {
            $status->media()
                ->whereIn('id', $validated['remove_media_ids'])
                ->get()
                ->each
                ->delete();
        }

        // Add newly attached images, keeping the 10-image cap
        if ($request->hasFile('images')) {
            $remaining = 10 - $status->media()->where('collection_name', 'images')->count();

            foreach ($request->file('images') as $image) {
                if ($remaining <= 0) {
                    break;
                }

                $filename = Str::uuid().'.'.$image->getClientOriginalExtension();

                $status->addMedia($image)
                    ->usingFileName($filename)
                    ->toMediaCollection('images');

                $remaining--;
            }
        }

        return response()->json($status->fresh());
    }

    /**
     * Delete a status.
     */
    public function destroy(Status $status): JsonResponse
    {
        $user = Auth::user();

        if (! $status->canDelete($user)) {
            abort(403, 'You do not have permission to delete this status.');
        }

        $status->delete();

        return response()->json(['message' => 'Status deleted successfully']);
    }

    /**
     * Toggle like on a status.
     */
    public function toggleLike(Status $status): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'You must be logged in to like a status.');
        }

        $liked = $status->toggleLike($user);

        return response()->json([
            'liked' => $liked,
            'likes_count' => $status->fresh()->likes_count,
        ]);
    }

    /**
     * Get likes for a status.
     */
    public function likes(Status $status): JsonResponse
    {
        $likes = $status->likes()->with('user')->paginate(20);

        return response()->json($likes);
    }

    /**
     * Add a comment to a status.
     */
    public function addComment(Request $request, Status $status): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'You must be logged in to comment.');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:status_comments,id',
        ]);

        $comment = $status->addComment(
            $user,
            $validated['content'],
            $validated['parent_id'] ?? null
        );

        return response()->json($comment->load('user', 'replies.user'), 201);
    }
}
