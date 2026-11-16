<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many times a member has done one tracked action.
 *
 * `scope` narrows the count — an event type id, say — and the empty string
 * holds the plain total, kept alongside the narrowed rows so an
 * achievement that asks for "any event" does not have to add them up.
 */
class AchievementProgress extends Model
{
    protected $table = 'achievement_progress';

    protected $fillable = [
        'user_id',
        'metric',
        'scope',
        'count',
        'last_at',
    ];

    protected $casts = [
        'count'   => 'integer',
        'last_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
