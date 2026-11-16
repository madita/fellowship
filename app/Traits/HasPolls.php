<?php

namespace App\Traits;

use App\Models\Poll\Poll;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasPolls
{
    public function polls(): MorphMany
    {
        return $this->morphMany(Poll::class, 'pollable');
    }

    /**
     * The most recently created poll (open or closed) — what the
     * thread/status payloads embed as `poll`. Eager-loadable.
     */
    public function latestPoll(): MorphOne
    {
        return $this->morphOne(Poll::class, 'pollable')->latestOfMany();
    }

    public function activePoll(): ?Poll
    {
        return $this->polls()
            ->where(function ($query) {
                $query->whereNull('closes_at')
                    ->orWhere('closes_at', '>', now());
            })
            ->latest()
            ->first();
    }
}
