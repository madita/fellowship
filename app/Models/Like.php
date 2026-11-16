<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A member liking something: a forum post, a timeline status.
 *
 * Replaces ForumPostLike and StatusLike.
 */
class Like extends Model
{
    protected $table = 'likeable';

    protected $fillable = [
        'user_id',
        'likeable_type',
        'likeable_id',
    ];

    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
