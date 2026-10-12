<?php

namespace App\Http\Controllers\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\Status;
use App\Notifications\StatusMentionNotification;
use App\Services\MentionService;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

class StatusController extends Controller
{
    /**
     * Get timeline/feed (all statuses or user's feed).
     */
    public function index(Request $request): JsonResponse
    {
        // The like rows themselves are not loaded: nothing in the client
        // reads them, and each one dragged a full user object along with
        // it. The count is the likes_count column and "did I like it" is
        // the is_liked_by_me attribute.
        $query = Status::with(array_merge(
            ['user', 'comments.user', 'comments.replies.user'],
            Status::POLL_RELATIONS
        ))->recent();

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
        $status->load(array_merge(
            ['user', 'allComments.user', 'allComments.replies.user'],
            Status::POLL_RELATIONS
        ));

        return response()->json($status);
    }

    /**
     * Create a new status.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user) {
            abort(401, 'You must be logged in to post a status.');
        }

        $validated = $request->validate([
            'content'  => 'required|string|max:5000',
            'images'   => 'array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'feeling'  => 'nullable|string|max:50',
            'poll'     => 'nullable',
        ]);

        // Multipart bodies carry the poll as a JSON string; validate it up front
        $pollData = PollService::fromRequestValue($request->input('poll'));
        if ($pollData !== null) {
            $pollData = PollService::validate($pollData);
        }

        $status = DB::transaction(function () use ($request, $user, $validated, $pollData) {
            $status = Status::create([
                'user_id' => $user->id,
                'content' => Purify::config('sandbox')->clean($validated['content']),
                'feeling' => $validated['feeling'] ?? null,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();

                    $status->addMedia($image)
                        ->usingFileName($filename)
                        ->toMediaCollection('images');
                }
            }

            if ($pollData !== null) {
                PollService::create($status, $user, $pollData);
            }

            return $status;
        });

        $this->notifyMentions($status, $status->content);

        $status->load(array_merge(['user', 'media'], Status::POLL_RELATIONS));

        return response()->json($status, 201);
    }

    /**
     * Update a status.
     */
    public function update(Request $request, Status $status): JsonResponse
    {
        $user = Auth::user();

        if ( ! $status->canEdit($user)) {
            abort(403, 'You do not have permission to edit this status.');
        }

        $validated = $request->validate([
            'content'            => 'sometimes|string|max:5000',
            'remove_media_ids'   => 'array',
            'remove_media_ids.*' => 'integer',
            'images'             => 'array',
            'images.*'           => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        if ($request->has('content')) {
            $previous = $status->content;
            $status->update(['content' => Purify::config('sandbox')->clean($validated['content'])]);
            $this->notifyMentions($status, $status->content, $previous);
        }

        // Remove images the user deleted (scoped to this status only)
        if ( ! empty($validated['remove_media_ids'])) {
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

                $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();

                $status->addMedia($image)
                    ->usingFileName($filename)
                    ->toMediaCollection('images');

                $remaining--;
            }
        }

        return response()->json($status->fresh()->load(Status::POLL_RELATIONS));
    }

    /**
     * Delete a status.
     */
    public function destroy(Status $status): JsonResponse
    {
        $user = Auth::user();

        if ( ! $status->canDelete($user)) {
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

        if ( ! $user) {
            abort(401, 'You must be logged in to like a status.');
        }

        $liked = $status->toggleLike($user);

        return response()->json([
            'liked'       => $liked,
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
     * Commenting moved to CommentController, which serves every
     * commentable kind: POST /api/comments/status/{id}.
     */

    /**
     * Notify every member mentioned as @username in the text, except the
     * author and anyone who was already mentioned in the previous version.
     */
    protected function notifyMentions(Status $status, string $content, ?string $previous = null): void
    {
        $service = app(MentionService::class);
        $already = $previous ? $service->parseMentions($previous)->pluck('id') : collect();

        foreach ($service->parseMentions($content) as $mentioned) {
            if ($mentioned->id === $status->user_id || $already->contains($mentioned->id)) {
                continue;
            }
            // Mentions in a comment are CommentController's business now
            $mentioned->notify(new StatusMentionNotification($status));
        }
    }
}
