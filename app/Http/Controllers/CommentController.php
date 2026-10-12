<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Status\Status;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\StatusMentionNotification;
use App\Services\MentionService;
use App\Support\Commentables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stevebauman\Purify\Facades\Purify;

/**
 * Comments on anything registered in App\Support\Commentables: a wiki
 * page's discussion, a timeline post.
 *
 * Replaces the timeline's own comment endpoints. Ticket comments keep
 * theirs — see the create_commentable_table migration.
 */
class CommentController extends Controller
{
    private const MAX_LENGTH = 2000;

    public function __construct()
    {
        $this->middleware('auth')->except('index');
    }

    /**
     * A discussion, as topics each carrying their replies.
     */
    public function index(Request $request, string $kind, int $id): JsonResponse
    {
        $subject = $this->findCommentable($kind, $id, $request->user());

        if ( ! $subject) {
            return response()->json(['message' => __('messages.comments.not_found')], 404);
        }

        return response()->json([
            'data'  => $subject->comments()->with('replies')->get(),
            'count' => $subject->allComments()->count(),
        ]);
    }

    public function store(Request $request, string $kind, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $subject = $this->findCommentable($kind, $id, $user);

        if ( ! $subject) {
            return response()->json(['message' => __('messages.comments.not_found')], 404);
        }

        $validated = $request->validate([
            'content'   => 'required|string|max:' . self::MAX_LENGTH,
            'parent_id' => 'nullable|integer|exists:commentable,id',
        ]);

        $parent = isset($validated['parent_id'])
            ? Comment::find($validated['parent_id'])
            : null;

        // A reply has to be to something said in this same discussion, or
        // a client could graft one discussion's reply onto another
        if ($parent && ! $subject->owns($parent)) {
            return response()->json(['message' => __('messages.comments.bad_parent')], 422);
        }

        $comment = $subject->addComment(
            $user,
            Purify::config('sandbox')->clean($validated['content']),
            $parent?->id
        );

        $this->notifyMentions($subject, $comment, $user);

        return response()->json($comment->load('replies'), 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ( ! $comment->canEdit($user)) {
            abort(403, __('messages.comments.cannot_edit'));
        }

        $validated = $request->validate([
            'content' => 'required|string|max:' . self::MAX_LENGTH,
        ]);

        $previous = $comment->content;

        $comment->update(['content' => Purify::config('sandbox')->clean($validated['content'])]);

        // Only whoever was newly added to the text hears about it
        $this->notifyMentions($comment->commentable, $comment, $user, $previous);

        return response()->json($comment->fresh());
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        if ( ! $comment->canDelete($request->user())) {
            abort(403, __('messages.comments.cannot_delete'));
        }

        // The count is kept by the Comment model, so deleting is all there
        // is to do here
        $comment->delete();

        return response()->json(['message' => __('messages.comments.deleted')]);
    }

    /**
     * Tell whoever was named in the text.
     *
     * A timeline comment keeps the notification the timeline already sent,
     * payload and all, because the frontend and its tests match on those
     * exact type strings. Everything else gets the general one.
     */
    private function notifyMentions(
        $subject,
        Comment $comment,
        User $author,
        ?string $previous = null
    ): void {
        $mentioned = app(MentionService::class)
            ->newMentions($comment->content, $previous, $author)
            ->filter(fn (User $user) => $subject->isCommentableBy($user));

        foreach ($mentioned as $user) {
            $user->notify($subject instanceof Status
                ? new StatusMentionNotification($subject, $comment)
                : new MentionNotification(
                    Commentables::kindForType($comment->commentable_type) ?? 'comment',
                    $this->titleOf($subject),
                    $this->urlOf($subject),
                    $author,
                    $comment->content
                ));
        }
    }

    private function titleOf($subject): string
    {
        return (string) (method_exists($subject, 'mentionTitle')
            ? $subject->mentionTitle()
            : ($subject->title ?? ''));
    }

    private function urlOf($subject): string
    {
        if (method_exists($subject, 'commentUrl')) {
            return $subject->commentUrl();
        }

        return (string) (method_exists($subject, 'mentionUrl') ? $subject->mentionUrl() : '');
    }

    /**
     * The model behind a kind and an id, or null when the kind is not
     * commentable, the row is gone, or the member cannot see it.
     */
    private function findCommentable(string $kind, int $id, ?User $user)
    {
        $type = Commentables::typeForKind($kind);

        if ( ! $type) {
            return null;
        }

        $model = $type::find($id);

        if ( ! $model || ! $model->isCommentableBy($user)) {
            return null;
        }

        return $model;
    }
}
