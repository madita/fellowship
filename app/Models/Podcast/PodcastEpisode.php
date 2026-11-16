<?php

namespace App\Models\Podcast;

use App\Models\User;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One episode of a show.
 *
 * The audio is either a file we hold (audio_path) or an episode hosted
 * somewhere else (audio_url); audioUrl() answers with whichever is set so
 * no caller has to care which.
 */
class PodcastEpisode extends Model
{
    use Sluggable;

    protected $table = 'podcast_episodes';

    protected $fillable = [
        'podcast_id',
        'title',
        'slug',
        'summary',
        'show_notes',
        'cover_image',
        'audio_path',
        'audio_url',
        'audio_mime',
        'audio_size',
        'duration',
        'episode_number',
        'season_number',
        'explicit',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'explicit'     => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'audio_size'   => 'integer',
        'duration'     => 'integer',
        'plays'        => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    /**
     * Two shows may each have a "Pilot", so a slug only has to be unique
     * inside its own show — which is what the table's unique index says.
     * Without this the package would look across every show and suffix a
     * slug that was never taken here.
     */
    public function scopeWithUniqueSlugConstraints(
        Builder $query,
        Model $model,
        string $attribute,
        array $config,
        string $slug
    ): Builder {
        return $query->where('podcast_id', $model->podcast_id);
    }

    public function podcast(): BelongsTo
    {
        return $this->belongsTo(Podcast::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Published, and not dated in the future: an episode scheduled for
     * next week is not an episode anyone may fetch yet.
     *
     * Ordering is deliberately not part of this — withCount() wraps the
     * scope in an aggregate subquery, where an ORDER BY is at best wasted
     * and at worst rejected.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * The order a feed reads in: newest first, by the date the episode
     * says it was published and only then by when the row was made.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('id');
    }

    public function isVisible(): bool
    {
        return $this->is_published
            && ($this->published_at === null || ! $this->published_at->isFuture());
    }

    /**
     * Where the audio actually is, whoever hosts it.
     */
    public function audioUrl(): ?string
    {
        if ($this->audio_path) {
            return '/storage/' . $this->audio_path;
        }

        return $this->audio_url ?: null;
    }

    public function coverUrl(): ?string
    {
        if ($this->cover_image) {
            return '/storage/' . $this->cover_image;
        }

        return $this->podcast?->coverUrl();
    }

    /**
     * The byte length an RSS enclosure has to carry. Hosted files know it
     * from the upload; for a remote URL we have nothing to measure, and 0
     * is what feeds use for "unknown".
     */
    public function enclosureLength(): int
    {
        if ($this->audio_size) {
            return (int) $this->audio_size;
        }

        if ($this->audio_path && Storage::disk('public')->exists($this->audio_path)) {
            return Storage::disk('public')->size($this->audio_path);
        }

        return 0;
    }

    public function deleteAudioFile(): void
    {
        if ($this->audio_path && Storage::disk('public')->exists($this->audio_path)) {
            Storage::disk('public')->delete($this->audio_path);
        }
    }

    public function toApiArray(bool $detailed = false): array
    {
        $data = [
            'id'             => $this->id,
            'podcast_id'     => $this->podcast_id,
            'title'          => $this->title,
            'slug'           => $this->slug,
            'summary'        => $this->summary,
            'cover_url'      => $this->coverUrl(),
            'audio_url'      => $this->audioUrl(),
            'audio_mime'     => $this->audio_mime,
            'audio_size'     => $this->audio_size,
            'duration'       => $this->duration,
            'episode_number' => $this->episode_number,
            'season_number'  => $this->season_number,
            'plays'          => (int) $this->plays,
            'explicit'       => $this->explicit,
            'is_published'   => $this->is_published,
            'published_at'   => $this->published_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['show_notes'] = $this->show_notes;
            $data['podcast']    = $this->relationLoaded('podcast') && $this->podcast
                ? $this->podcast->toApiArray()
                : null;
        }

        return $data;
    }
}
