<?php

namespace App\Models\Podcast;

use App\Models\User;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A show: the thing a listener subscribes to.
 *
 * The RSS feed is built straight off this row and its published episodes,
 * which is why fields a podcast directory asks for (author, language,
 * category, explicit) live here rather than being derived.
 */
class Podcast extends Model
{
    use Sluggable;

    protected $table = 'podcasts';

    protected $fillable = [
        'title',
        'slug',
        'subtitle',
        'description',
        'cover_image',
        'author',
        'language',
        'category',
        'explicit',
        'is_published',
        'created_by',
    ];

    protected $casts = [
        'explicit'     => 'boolean',
        'is_published' => 'boolean',
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

    public function episodes(): HasMany
    {
        return $this->hasMany(PodcastEpisode::class);
    }

    /**
     * The episodes a listener may see: published, and not dated in the
     * future. Ordered newest first, the way a feed reads.
     */
    public function publishedEpisodes(): HasMany
    {
        return $this->episodes()->published()->newestFirst();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? '/storage/' . $this->cover_image : null;
    }

    public function feedUrl(): string
    {
        return url("/podcast/{$this->slug}/feed.xml");
    }

    /**
     * The shape every endpoint hands a show back in.
     *
     * One place, so the list, the detail page and the admin screen cannot
     * drift apart — the same reason ForumController has transformCategory.
     */
    public function toApiArray(bool $detailed = false): array
    {
        $data = [
            'id'           => $this->id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'subtitle'     => $this->subtitle,
            'cover_image'  => $this->cover_image,
            'cover_url'    => $this->coverUrl(),
            'author'       => $this->author,
            'language'     => $this->language,
            'category'     => $this->category,
            'explicit'     => $this->explicit,
            'is_published' => $this->is_published,
            'feed_url'     => $this->feedUrl(),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];

        // episodes_count is only there when the caller asked for it
        if ($this->episodes_count !== null) {
            $data['episodes_count'] = (int) $this->episodes_count;
        }

        if ($detailed) {
            $data['description'] = $this->description;
            $data['creator']     = $this->creator ? [
                'id'       => $this->creator->id,
                'username' => $this->creator->username,
            ] : null;
        }

        return $data;
    }
}
