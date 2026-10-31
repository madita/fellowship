<?php

namespace App\Models\Podcast;

use App\Models\User;
use App\Traits\Approvable;
use App\Traits\HasTaxonomies;
use App\Traits\HasTickets;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A show: the thing a listener subscribes to.
 *
 * The RSS feed is built straight off this row and its published episodes,
 * which is why fields a podcast directory asks for (author, language,
 * category, explicit) live here rather than being derived.
 */
class Podcast extends Model
{
    use Approvable;
    use HasTaxonomies;
    use HasTickets;
    use Sluggable;

    /** The taxonomy a show's categories are terms of. */
    public const TAXONOMY = 'podcast';

    protected $table = 'podcasts';

    protected $fillable = [
        'title',
        'slug',
        'subtitle',
        'description',
        'cover_image',
        'language',
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

    /** Every episode, newest first — for whoever may see the drafts. */
    public function allEpisodes(): HasMany
    {
        return $this->episodes()->newestFirst();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who makes the show. The creator is attached when it is made, and a
     * co-author is another row rather than a name typed into a string.
     *
     * Ordered by when they were added, so the person who started the
     * show is named first in the feed.
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'podcast_authors')
            ->withTimestamps()
            ->orderBy('podcast_authors.id');
    }

    /**
     * The itunes:author line: everyone who makes the show, named.
     *
     * A directory wants one string, so the names are joined — "Ada and
     * Grace", "Ada, Grace and Hedy" — rather than a list it cannot read.
     */
    public function authorLine(): ?string
    {
        $names = $this->authors->map(fn (User $user) => $user->username)->filter()->values();

        if ($names->isEmpty()) {
            return null;
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        return $names->slice(0, -1)->implode(', ') . ' & ' . $names->last();
    }

    /**
     * What the show is about, as terms of the `podcast` taxonomy — the
     * same mechanism the wiki and the pages use, so the vocabulary is
     * shared rather than being one show's spelling of a word.
     */
    public function categoryTerms(): Collection
    {
        return $this->taxonomies
            ->filter(fn ($taxonomy) => $taxonomy->taxonomy === self::TAXONOMY && $taxonomy->term)
            ->map(fn ($taxonomy) => [
                'id'    => $taxonomy->term->id,
                'title' => $taxonomy->term->title,
                'slug'  => $taxonomy->term->slug,
            ])
            ->values();
    }

    /**
     * The author's own switch: are they done with it.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * What the public may see: the author says it is ready *and* somebody
     * has approved it.
     *
     * Two different decisions, deliberately not collapsed into one. A
     * show is held back until it has been looked at, and that matters
     * more here than elsewhere on the site: a feed is pulled by podcast
     * apps that cache and redistribute it, so letting something out and
     * retracting it afterwards does not really retract anything.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->published()->approved();
    }

    /**
     * Whoever made the show, and whoever is named on it, can always see
     * it — approved or not, published or not. Being unable to see your
     * own draft is what made one impossible to work on.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->is_published && $this->isApproved()) {
            return true;
        }

        if ( ! $user) {
            return false;
        }

        return $this->created_by === $user->id
            || $this->authors->contains('id', $user->id);
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
            'language'     => $this->language,
            'explicit'     => $this->explicit,
            'is_published' => $this->is_published,
            // Published is the author's call, approved is somebody
            // else's; a show needs both before anyone else hears it
            'is_approved'  => $this->isApproved(),
            'feed_url'     => $this->feedUrl(),
            'created_at'   => $this->created_at?->toIso8601String(),
            // Who makes it and what it is about, both of them real
            // records now rather than a line of text
            'authors'      => $this->authors->map(fn (User $user) => [
                'id'       => $user->id,
                'username' => $user->username,
            ])->values(),
            'author_line'  => $this->authorLine(),
            'categories'   => $this->categoryTerms(),
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

    /**
     * Opening a show for review, or letting it straight through.
     *
     * Mirrors Wiki: a role named in auto_approve_roles_podcast skips the
     * ticket, everyone else gets one raised against the show itself, so
     * the moderation queue is the ticket list people already watch.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function (self $podcast): void {
            if ($podcast->shouldAutoApprove()) {
                $podcast->approve(auth()->user());

                return;
            }

            $podcast->autoCreateTicket('podcast_approval', [
                'title'       => "New Podcast: {$podcast->title}",
                'description' => 'A new podcast show has been created and needs review.',
                'priority'    => 'normal',
                // The show records who made it, so the ticket is
                // credited from that rather than from whoever happens to
                // be signed in — a show created by a command or a seeder
                // would otherwise raise a ticket belonging to nobody.
                'created_by_user_id' => $podcast->created_by,
            ]);
        });
    }
}
