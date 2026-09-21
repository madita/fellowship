<?php

namespace App\Models;

use App\Support\AchievementMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Something a member can earn: a name, a look, and how it is earned.
 *
 * `trigger` is either 'metric' — the site counts a tracked action and
 * awards it once the threshold is met — or 'manual', for anything the site
 * cannot see, like whether someone actually cooked.
 */
class Achievement extends Model
{
    use HasFactory;

    public const TRIGGERS = ['metric', 'manual'];

    public const CATEGORIES = ['community', 'content', 'events', 'support', 'special'];

    protected $fillable = [
        'key',
        'name',
        'description',
        'icon',
        'image_path',
        'color',
        'category',
        'points',
        'trigger',
        'metric',
        'threshold',
        'filters',
        'is_enabled',
        'is_secret',
        'sort_order',
    ];

    protected $casts = [
        'filters'    => 'array',
        'is_enabled' => 'boolean',
        'is_secret'  => 'boolean',
        'points'     => 'integer',
        'threshold'  => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * The picture goes out as an address the client can use, alongside the
     * stored path.
     */
    protected $appends = ['image_url'];

    /**
     * Where the badge picture lives, or null when it falls back to an icon.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function holders(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['awarded_at', 'awarded_by', 'note', 'count_at_award'])
            ->withTimestamps();
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
     * Achievements the site awards by counting this action.
     */
    public function scopeForMetric(Builder $query, string $metric): Builder
    {
        return $query->enabled()->where('trigger', 'metric')->where('metric', $metric);
    }

    /**
     * Whether this achievement counts an action narrowed to certain kinds —
     * three events, but only the cooking ones.
     */
    public function isNarrowed(): bool
    {
        return $this->trigger === 'metric' && ! empty($this->filters['values']);
    }

    /**
     * The scope values this achievement counts, as strings.
     */
    public function scopeValues(): array
    {
        return array_map('strval', $this->filters['values'] ?? []);
    }

    /**
     * How far a member is toward earning this, out of its threshold.
     */
    public function progressFor(User $user): int
    {
        if ($this->trigger !== 'metric' || ! $this->metric) {
            return 0;
        }

        $query = AchievementProgress::query()
            ->where('user_id', $user->id)
            ->where('metric', $this->metric);

        // A narrowed achievement adds up only the kinds it asks for; an
        // unnarrowed one uses the plain total kept alongside them.
        $query = $this->isNarrowed()
            ? $query->whereIn('scope', $this->scopeValues())
            : $query->where('scope', '');

        return (int) $query->sum('count');
    }

    /**
     * The metric this points at, if the site still knows how to count it.
     */
    public function metricIsKnown(): bool
    {
        return $this->trigger !== 'metric' || AchievementMetrics::exists((string) $this->metric);
    }
}
