<?php

namespace App\Http\Controllers;

use App\Models\Status\StatusComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatusCommentController extends Controller
{
    /**
     * Update a comment.
     */
    public function update(Request $request, StatusComment $comment): JsonResponse
    {
        $user = Auth::user();

        if (!$comment->canEdit($user)) {
            abort(403, 'You do not have permission to edit this comment.');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $comment->update($validated);

        return response()->json($comment->load('user'));
    }

    /**
     * Delete a comment.
     */
    public function destroy(StatusComment $comment): JsonResponse
    {
        $user = Auth::user();

        if (!$comment->canDelete($user)) {
            abort(403, 'You do not have permission to delete this comment.');
        }

        // Decrement the status comment count
        $comment->status->decrement('comments_count');

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
