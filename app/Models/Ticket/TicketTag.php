<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class TicketTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get tickets with this tag.
     */
    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class);
    }

    /**
     * Scope: Active tags only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::creating(function (TicketTag $tag): void {
            $tag->slug = $tag->slug ?: Str::slug($tag->name);
        });
    }
}
