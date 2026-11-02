<?php

namespace App\Traits;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Lets members comment on this model, and reply to each other.
 *
 * Used by Wiki, where it is the page's discussion, and by Status, where it
 * replaces the timeline's own comment table.
 *
 * A model that limits who may see it should also define
 * `isVisibleTo(?User $user): bool` — the comment endpoints honour it, so a
 * wiki page still awaiting approval has no discussion a stranger can read
 * or post to.
 */
trait Commentable
{
    /**
     * A morph has no foreign key to cascade through, so the rows go here.
     * Only on a real delete, matching the rest of the morph traits.
     */
    public static function bootCommentable(): void
    {
        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->allComments()->forceDelete();
        });
    }

    /**
     * The topics: comments that are not replies to another comment.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->whereNull('parent_id')
            ->orderBy('created_at');
    }

    /**
     * Every comment, replies included — what a count should be over.
     */
    public function allComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->orderBy('created_at');
    }

    /**
     * The comment that answered this, where one has been marked.
     */
    public function helpfulComment(): MorphOne
    {
        return $this->morphOne(Comment::class, 'commentable')->whereNotNull('helpful_at');
    }

    /**
     * Say something, or reply to something already said.
     */
    public function addComment(User $user, string $content, ?int $parentId = null): Comment
    {
        return $this->allComments()->create([
            'user_id'   => $user->id,
            'content'   => $content,
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Whether the comment belongs to this model at all — a reply must be
     * to a comment on the same thing, or a client could graft a reply from
     * one discussion onto another.
     */
    public function owns(?Comment $comment): bool
    {
        return $comment !== null
            && $comment->commentable_type === $this->getMorphClass()
            && (int) $comment->commentable_id === (int) $this->getKey();
    }

    /**
     * Whether the member may read, and so comment on, this model. Defers
     * to the model's own rule where it has one.
     */
    public function isCommentableBy(?User $user): bool
    {
        if (method_exists($this, 'isVisibleTo')) {
            return (bool) $this->isVisibleTo($user);
        }

        return true;
    }

    /**
     * Whether the member may mark one of these comments as the one that
     * helped. Whoever wrote the thing being discussed decides, the same
     * way the thread author decides in the forum, and an admin may too.
     *
     * A model with no single owner - or one that does not want the
     * feature at all - overrides this and says so.
     */
    public function isHelpfulMarkableBy(?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $owner = $this->commentOwnerId();

        return $owner !== null && (int) $user->id === $owner;
    }

    /**
     * Who the thing being discussed belongs to. Most hosts keep that in
     * `user_id`; the ones that do not say where it is instead.
     */
    protected function commentOwnerId(): ?int
    {
        $owner = $this->getAttribute('user_id');

        return $owner === null ? null : (int) $owner;
    }

    /**
     * Called by Comment when one is added or removed, so the total is
     * maintained in one place whatever route created the comment.
     */
    public function changeCommentCount(int $by): void
    {
        $column = $this->commentCounterColumn();

        if ( ! $column) {
            return;
        }

        $by > 0
            ? $this->increment($column, $by)
            : $this->decrement($column, abs($by));
    }

    /**
     * The column holding the running total, for models that keep one.
     */
    protected function commentCounterColumn(): ?string
    {
        return null;
    }
}
