<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'vote',
    ];

    protected $casts = [
        'vote' => 'integer',
    ];

    /**
     * Get the ticket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who voted.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is an upvote.
     */
    public function isUpvote(): bool
    {
        return $this->vote === 1;
    }

    /**
     * Check if this is a downvote.
     */
    public function isDownvote(): bool
    {
        return $this->vote === -1;
    }
}
