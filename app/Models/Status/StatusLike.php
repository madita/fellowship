<?php

namespace App\Models\Status;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_id',
        'user_id',
        'reaction_type',
    ];

    protected $with = ['user'];

    /**
     * Get the status this like belongs to.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the user who liked.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
