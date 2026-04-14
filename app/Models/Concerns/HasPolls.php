<?php

namespace App\Models\Concerns;

use App\Models\Poll\Poll;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPolls
{
    public function polls(): MorphMany
    {
        return $this->morphMany(Poll::class, 'pollable');
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
