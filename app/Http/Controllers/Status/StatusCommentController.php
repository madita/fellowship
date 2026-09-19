<?php

namespace App\Http\Controllers\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusComment;
use App\Notifications\StatusMentionNotification;
use App\Services\MentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stevebauman\Purify\Facades\Purify;

class StatusCommentController extends Controller
{
    /**
     * Update a comment.
     */
    public function update(Request $request, StatusComment $comment): JsonResponse
    {
        $user = Auth::user();

        if ( ! $comment->canEdit($user)) {
            abort(403, 'You do not have permission to edit this comment.');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $previous             = $comment->content;
        $validated['content'] = Purify::config('sandbox')->clean($validated['content']);
        $comment->update($validated);

        // Someone added to the comment is notified; the ones already in it are not
        $mentioned = app(MentionService::class)->newMentions($comment->content, $previous, $user);
        foreach ($mentioned as $mentionedUser) {
            $mentionedUser->notify(new StatusMentionNotification($comment->status, $comment));
        }

        return response()->json($comment->load('user'));
    }

    /**
     * Delete a comment.
     */
    public function destroy(StatusComment $comment): JsonResponse
    {
        $user = Auth::user();

        if ( ! $comment->canDelete($user)) {
            abort(403, 'You do not have permission to delete this comment.');
        }

        // Decrement the status comment count
        $comment->status->decrement('comments_count');

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
