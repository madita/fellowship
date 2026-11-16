<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A title a member reaches by collecting achievement points.
 *
 * Nothing is stored on the member: a rank is worked out from the points
 * they have right now, so raising a threshold re-ranks everyone at once
 * instead of leaving stale titles behind.
 */
class Rank extends Model implements TranslatableContract
{
    use HasFactory;
    use Translatable;

    /** {@inheritdoc} */
    public $translatedAttributes = ['name', 'description'];

    protected $fillable = [
        'key',
        'points_required',
        'icon',
        'image_path',
        'color',
        'is_enabled',
    ];

    protected $casts = [
        'points_required' => 'integer',
        'is_enabled'      => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('points_required')->orderBy('id');
    }

    /**
     * Every rank that can be reached, lowest first.
     */
    public static function ladder(): Collection
    {
        return static::enabled()->with('translations')->inOrder()->get();
    }

    /**
     * The rank these points have reached: the highest whose threshold they
     * meet. Null when no rank is low enough — a ladder that starts above
     * zero leaves a new member without one, which is allowed.
     */
    public static function forPoints(int $points, ?Collection $ladder = null): ?self
    {
        return ($ladder ?? static::ladder())
            ->filter(fn (self $rank) => $points >= $rank->points_required)
            ->last();
    }

    /**
     * The next rank up from these points, or null at the top.
     */
    public static function nextAfter(int $points, ?Collection $ladder = null): ?self
    {
        return ($ladder ?? static::ladder())
            ->first(fn (self $rank) => $rank->points_required > $points);
    }
}
