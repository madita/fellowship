<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Lets members vote for this model.
 *
 * Used by Ticket, where a vote is a plain up-vote, and by PollOption, where
 * a vote is a choice within its poll. Whether more than one option of a
 * poll may be chosen is the poll's business, not this trait's — see
 * PollVoteController.
 *
 * Shaped after the Watchable trait, which solves the same problem for
 * following things.
 */
trait Voteable
{
    /**
     * A morph has no foreign key to cascade through, so the rows go here.
     * Only on a real delete: a soft-deleted ticket that comes back should
     * come back with its score.
     */
    public static function bootVoteable(): void
    {
        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->votes()->delete();
        });
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'voteable');
    }

    /**
     * Who voted, as users — so withCount('votes') and withExists() read the
     * way the feedback queries already expect.
     */
    public function voters(): MorphToMany
    {
        return $this->morphToMany(User::class, 'voteable', 'voteable', null, 'user_id');
    }

    public function isVotedBy(?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        return $this->votes()->where('user_id', $user->id)->exists();
    }

    /**
     * Vote for this. Does nothing when the member already has.
     */
    public function vote(User $user): void
    {
        $this->votes()->firstOrCreate(['user_id' => $user->id]);
    }

    public function unvote(User $user): void
    {
        $this->votes()->where('user_id', $user->id)->delete();
    }

    /**
     * Add or remove the member's vote; returns whether they have voted now.
     */
    public function toggleVote(User $user): bool
    {
        if ($this->votes()->where('user_id', $user->id)->delete()) {
            return false;
        }

        $this->vote($user);

        return true;
    }
}
