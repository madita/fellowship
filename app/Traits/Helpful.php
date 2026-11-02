<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lets an answer be marked as the helpful one.
 *
 * Used by ForumPost, where it is the reply that answered the thread, and by
 * Comment, where it is the comment that answered whatever was commented on.
 *
 * It grew out of `forum_posts.is_solution`, a boolean the forum set with a
 * mass update. Three things were wrong with that and are fixed here:
 *
 *  - Nothing recorded who decided, or when. The thread author and a
 *    moderator are not the same answer to "why is this the answer?", and a
 *    boolean cannot tell them apart.
 *  - A mass update fires no model events, so the reply that stopped being
 *    the answer never reached the search index or the activity log, while
 *    the one that became it did. Here each one is saved on its own.
 *  - It could only ever be set. The button said "Unmark Helpful" and sent
 *    the same request as "Mark as Helpful", so unmarking did nothing.
 *
 * Everything hangs off the nullable `helpful_at` timestamp, so there is no
 * second flag that can disagree with it. `is_helpful` is appended for the
 * clients that only want the answer to the question.
 *
 * A model using this trait decides two things of its own:
 *
 *  - helpfulSiblings(): the other answers to the same question, at most one
 *    of which may be the helpful one. Returning null means several can be.
 *  - canBeMarkedHelpfulBy(): who is allowed to decide. Nobody, until the
 *    model says otherwise.
 */
trait Helpful
{
    public function initializeHelpful(): void
    {
        $this->mergeCasts(['helpful_at' => 'datetime']);

        // Deliberately not fillable: being the helpful answer is decided
        // through markHelpful(), not by whatever a request body happens to
        // carry into an update of the body text.
        $this->append('is_helpful');
    }

    /**
     * Answers that have been marked helpful.
     */
    public function scopeHelpful(Builder $query): Builder
    {
        return $query->whereNotNull('helpful_at');
    }

    /**
     * Whoever decided this was the answer. Null for the marks that were
     * carried over from the boolean, which recorded nobody.
     */
    public function markedHelpfulBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helpful_by_id');
    }

    public function getIsHelpfulAttribute(): bool
    {
        return $this->helpful_at !== null;
    }

    /**
     * Mark this as the helpful answer, and unmark whatever held the title
     * before it.
     */
    public function markHelpful(User $by): void
    {
        $this->unmarkSiblings();

        $this->forceFill([
            'helpful_at'    => now(),
            'helpful_by_id' => $by->id,
        ])->save();
    }

    public function unmarkHelpful(): void
    {
        $this->forceFill([
            'helpful_at'    => null,
            'helpful_by_id' => null,
        ])->save();
    }

    /**
     * Mark it or take the mark away; returns whether it is the helpful
     * answer now.
     */
    public function toggleHelpful(User $by): bool
    {
        if ($this->is_helpful) {
            $this->unmarkHelpful();

            return false;
        }

        $this->markHelpful($by);

        return true;
    }

    /**
     * The other answers to the same question. Null where there is no such
     * thing, and then more than one answer may be marked.
     */
    protected function helpfulSiblings(): ?Builder
    {
        return null;
    }

    /**
     * Who may decide. False until the model says, because the wrong answer
     * to this is "anybody who asks".
     */
    public function canBeMarkedHelpfulBy(?User $user): bool
    {
        return false;
    }

    private function unmarkSiblings(): void
    {
        $siblings = $this->helpfulSiblings();

        if ( ! $siblings) {
            return;
        }

        // Saved one at a time rather than in a single update, so the answer
        // that is no longer the answer reaches the search index and the
        // activity log the same way the new one does. There is at most one.
        $siblings->helpful()->get()->each->unmarkHelpful();
    }
}
