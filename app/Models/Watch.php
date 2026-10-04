<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A member following something: a forum thread, a ticket, whatever else is
 * registered in App\Support\Watchables.
 */
class Watch extends Model
{
    protected $table = 'watches';

    protected $fillable = [
        'user_id',
        'watchable_type',
        'watchable_id',
    ];

    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
