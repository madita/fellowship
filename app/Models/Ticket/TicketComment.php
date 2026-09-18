<?php

namespace App\Models\Ticket;

use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'comment',
        'is_internal',
        'is_official',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_official' => 'boolean',
    ];

    protected $with = ['user'];

    protected static function booted(): void
    {
        // An admin's comment is the team's official answer, wherever it was written
        static::creating(function (TicketComment $comment): void {
            $comment->is_official = (bool) $comment->user?->isAdmin();
        });

        static::created(function (TicketComment $comment): void {
            // Mentioned members get the mention, not also the watcher notice
            $mentioned = $comment->ticket->notifyMentions($comment->comment, null, $comment->user, $comment);

            if ( ! $comment->is_internal) {
                $comment->ticket->notifyWatchers(
                    new TicketActivityNotification($comment->ticket, 'comment', $comment),
                    [$comment->user_id, ...$mentioned]
                );
            }
        });

        static::updated(function (TicketComment $comment): void {
            if ($comment->wasChanged('comment')) {
                $comment->ticket->notifyMentions($comment->comment, $comment->getOriginal('comment'), $comment->user, $comment);
            }
        });
    }

    /**
     * Get the ticket this comment belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who wrote the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user can edit this comment.
     */
    public function canEdit(?User $user = null): bool
    {
        if ( ! $user) {
            return false;
        }

        // Allow editing within 15 minutes or if admin
        $editWindow = now()->subMinutes(15);

        return ((int) $user->id === (int) $this->user_id && $this->created_at->gt($editWindow)) || $user->isAdmin();
    }

    /**
     * Check if user can delete this comment.
     */
    public function canDelete(?User $user = null): bool
    {
        if ( ! $user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
    }

}
