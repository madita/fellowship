<?php

namespace App\Models\Poll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Poll extends Model
{
    protected $fillable = [
        'pollable_type',
        'pollable_id',
        'title',
        'description',
        'type',
        'anonymous',
        'closes_at',
        'created_by',
    ];

    protected $casts = [
        'anonymous' => 'boolean',
        'closes_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['is_open', 'total_votes'];

    public function pollable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function getIsOpenAttribute(): bool
    {
        if ($this->closes_at === null) {
            return true;
        }

        return $this->closes_at->isFuture();
    }

    public function getTotalVotesAttribute(): int
    {
        return $this->votes()->count();
    }

    public function hasVoted(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->votes()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function userVotes(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return $this->votes()
            ->where('user_id', $user->id)
            ->pluck('poll_option_id')
            ->toArray();
    }

    public function results(): array
    {
        $totalVotes = $this->total_votes;

        return $this->options->map(function ($option) use ($totalVotes) {
            $voteCount = $option->votes()->count();
            $percentage = $totalVotes > 0 ? ($voteCount / $totalVotes) * 100 : 0;

            return [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'votes' => $voteCount,
                'percentage' => round($percentage, 1),
            ];
        })->toArray();
    }
}
