<?php

namespace App\Models\Poll;

use App\Traits\Voteable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollOption extends Model
{
    use Voteable;

    protected $fillable = [
        'poll_id',
        'option_text',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    // votes() comes from the Voteable trait: a vote for a poll is a vote
    // for one of its options, so the option is what gets voted for.
}
