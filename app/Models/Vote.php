<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A vote for something: a poll option, a ticket.
 *
 * Replaces PollVote and TicketVote. The voteable is whatever was voted
 * *for* — see the create_voteable_table migration for why.
 */
class Vote extends Model
{
    protected $table = 'voteable';

    protected $fillable = [
        'user_id',
        'voteable_type',
        'voteable_id',
    ];

    public function voteable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
