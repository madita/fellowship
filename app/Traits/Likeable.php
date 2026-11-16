<?php

namespace App\Traits;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Lets members like this model.
 *
 * Replaces the two implementations this grew out of — forum post likes and
 * status likes — which were the same feature with different table names,
 * different endpoints and different JSON keys.
 *
 * Both hosts keep a running total in a column of their own rather than
 * counting rows, and they do not even agree on its name (`like_count` on a
 * forum post, `likes_count` on a status). A model says which column it
 * keeps through likeCounterColumn() and the trait maintains it; a model
 * with no such column simply counts rows.
 */
trait Likeable
{
    /**
     * A morph has no foreign key to cascade through, so the rows go here.
     * Only on a real delete: both hosts soft-delete, and a post that comes
     * back should come back with its likes.
     */
    public static function bootLikeable(): void
    {
        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->likes()->delete();
        });
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Who liked this, as users — so withCount('likes') and withExists()
     * read naturally where a caller wants them.
     */
    public function likers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'likeable', 'likeable', null, 'user_id');
    }

    public function isLikedBy(?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Like it. Does nothing when the member already has, so the counter
     * cannot drift by being called twice.
     */
    public function like(User $user): void
    {
        $like = $this->likes()->firstOrCreate(['user_id' => $user->id]);

        if ($like->wasRecentlyCreated) {
            $this->changeLikeCount(1);
        }
    }

    public function unlike(User $user): void
    {
        if ($this->likes()->where('user_id', $user->id)->delete()) {
            $this->changeLikeCount(-1);
        }
    }

    /**
     * Add or remove the member's like; returns whether they like it now.
     */
    public function toggleLike(User $user): bool
    {
        if ($this->isLikedBy($user)) {
            $this->unlike($user);

            return false;
        }

        $this->like($user);

        return true;
    }

    /**
     * The column holding the running total, for models that keep one.
     */
    protected function likeCounterColumn(): ?string
    {
        return null;
    }

    private function changeLikeCount(int $by): void
    {
        $column = $this->likeCounterColumn();

        if ( ! $column) {
            return;
        }

        $by > 0
            ? $this->increment($column, $by)
            : $this->decrement($column, abs($by));
    }
}
