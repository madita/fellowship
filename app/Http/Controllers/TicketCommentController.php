<?php

namespace App\Http\Controllers;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketCommentController extends Controller
{
    /**
     * Add a comment to a ticket.
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'You must be logged in to comment.');
        }

        // Check permission
        $isAdmin = $user->isAdmin();
        $isCreator = $ticket->created_by_user_id === $user->id;

        if (! $isAdmin && ! $isCreator) {
            abort(403, 'You do not have permission to comment on this ticket.');
        }

        $validated = $request->validate([
            'comment' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        // Only admins can post internal comments
        if (isset($validated['is_internal']) && $validated['is_internal'] && ! $isAdmin) {
            abort(403, 'Only admins can post internal comments.');
        }

        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'comment' => $validated['comment'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        return response()->json($comment->load('user'), 201);
    }

    /**
     * Update a comment.
     */
    public function update(Request $request, TicketComment $comment): JsonResponse
    {
        $user = Auth::user();

        if (! $comment->canEdit($user)) {
            abort(403, 'You do not have permission to edit this comment.');
        }

        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        $comment->update($validated);

        return response()->json($comment->load('user'));
    }

    /**
     * Delete a comment.
     */
    public function destroy(TicketComment $comment): JsonResponse
    {
        $user = Auth::user();

        if (! $comment->canDelete($user)) {
            abort(403, 'You do not have permission to delete this comment.');
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
