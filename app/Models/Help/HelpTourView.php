<?php

namespace App\Models\Help;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * That a member has been shown a tour.
 *
 * Its whole job is to stop a tour that starts by itself from starting
 * again on every visit. `completed_at` is set only when they walked it to
 * the end, so "shown" and "read" stay apart.
 */
class HelpTourView extends Model
{
    protected $fillable = [
        'help_tour_id',
        'user_id',
        'seen_at',
        'completed_at',
    ];

    protected $casts = [
        'seen_at'      => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(HelpTour::class, 'help_tour_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
