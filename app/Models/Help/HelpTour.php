<?php

namespace App\Models\Help;

use App\Models\User;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Help written for a page: a walkthrough of it, or markers sitting beside
 * the things on it.
 *
 * `route_pattern` decides where it shows up and is matched against the
 * path the browser is on, so `/wiki/*` covers the whole wiki and `/forum`
 * only the one page. `audience` and `roles` decide who it is for.
 */
class HelpTour extends Model implements TranslatableContract
{
    use HasFactory;
    use Translatable;

    /** Walk the steps one at a time, or scatter them beside their elements. */
    public const MODES = ['tour', 'hints'];

    public const AUDIENCES = ['everyone', 'members', 'guests'];

    /** {@inheritdoc} */
    public $translatedAttributes = ['title', 'description'];

    protected $fillable = [
        'key',
        'route_pattern',
        'mode',
        'icon',
        'color',
        'audience',
        'roles',
        'auto_start',
        'show_launcher',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'roles'         => 'array',
        'auto_start'    => 'boolean',
        'show_launcher' => 'boolean',
        'is_enabled'    => 'boolean',
        'sort_order'    => 'integer',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(HelpTourStep::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Members who have already been shown it. */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'help_tour_views')
            ->withPivot(['seen_at', 'completed_at']);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Does this tour belong to the given path?
     *
     * The query string is none of its business, and a trailing slash is
     * the same page, so both are taken off before matching.
     */
    public function matchesPath(string $path): bool
    {
        // The pattern goes through the same tidying, so a stored
        // `/forum/` still matches the `/forum` the browser reports
        return Str::is(self::normalisePath($this->route_pattern), self::normalisePath($path));
    }

    /**
     * Is this tour for the given viewer? A guest-only tour is no use to a
     * member and the other way round, and `roles` narrows it further.
     */
    public function isFor(?User $user): bool
    {
        if ($this->audience === 'members' && ! $user) {
            return false;
        }

        if ($this->audience === 'guests' && $user) {
            return false;
        }

        $roles = array_filter((array) $this->roles);

        if ($roles === []) {
            return true;
        }

        // A tour narrowed to roles is for signed-in members by definition
        return $user !== null && $user->hasAnyRole($roles);
    }

    /**
     * A path as the matcher wants it: no query string, no trailing slash,
     * and never empty.
     */
    public static function normalisePath(string $path): string
    {
        $path = strtok($path, '?') ?: '/';
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
