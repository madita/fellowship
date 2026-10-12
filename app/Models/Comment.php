<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on something: a wiki page's discussion, a timeline status.
 *
 * Replaces StatusComment. Ticket and sandbox comments keep their own
 * tables — see the create_commentable_table migration for why.
 */
class Comment extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * How long the author has to think better of it. Admins are not held
     * to it. Inherited from the status comments this grew out of.
     */
    public const EDIT_WINDOW_MINUTES = 15;

    protected $table = 'commentable';

    protected $fillable = [
        'user_id',
        'commentable_type',
        'commentable_id',
        'parent_id',
        'content',
    ];

    // The author travels with the comment; every caller shows it
    protected $with = ['user'];

    protected $appends = ['time_ago'];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function getTimeAgoAttribute(): ?string
    {
        return $this->created_at?->diffForHumans();
    }

    public function canEdit(?User $user = null): bool
    {
        if ( ! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $user->id === (int) $this->user_id
            && $this->created_at->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    public function canDelete(?User $user = null): bool
    {
        if ( ! $user) {
            return false;
        }

        return (int) $user->id === (int) $this->user_id || $user->isAdmin();
    }

    /**
     * The running total on the commented-on thing is kept here rather than
     * by whoever happens to write a comment.
     *
     * It used to be bumped inside Status::addComment() and dropped again
     * in the controller, which meant a comment created any other way — as
     * the tests did — never counted, and a deleted one only sometimes
     * stopped counting.
     */
    protected static function booted(): void
    {
        static::created(fn (Comment $comment) => $comment->adjustSubjectCount(1));

        static::deleted(function (Comment $comment): void {
            // A force delete cascades the replies in the database, which
            // fires no model events, so they are not discounted here
            $comment->adjustSubjectCount(-1);
        });

        static::restored(fn (Comment $comment) => $comment->adjustSubjectCount(1));
    }

    private function adjustSubjectCount(int $by): void
    {
        $subject = $this->commentable;

        if ( ! $subject || ! method_exists($subject, 'changeCommentCount')) {
            return;
        }

        $subject->changeCommentCount($by);
    }
}
