<?php

namespace App\Traits;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * One publishing scheme for Pages and Posts.
 *
 * Everything hangs off a single nullable `published_at` timestamp:
 *
 *   null      → draft      (never public)
 *   > now()   → scheduled  (not public yet)
 *   <= now()  → published  (public)
 *
 * It replaces the old `pages.published` flag and `posts.status` string, which
 * stay usable as write-only aliases so existing payloads keep working.
 */
trait Publishable
{
    public function initializePublishable(): void
    {
        $this->mergeCasts(['published_at' => 'datetime']);

        // `published` and `status` are no longer columns — they are the legacy
        // aliases handled by the mutators below. Keeping them fillable means an
        // old payload ({published: 1} / {status: 'draft'}) still does the right
        // thing instead of being silently dropped.
        $this->mergeFillable(['published_at', 'published', 'status']);

        $this->append(['is_published', 'publish_status']);
    }

    // ─── Scopes ───

    /**
     * Live content: has a publish date and it is not in the future.
     *
     * The conditions are wrapped in a group so the scope can be combined with
     * an `orWhere(...)` (see RelateableHelper::applyVisibility) without the OR
     * escaping the publish check.
     */
    public function scopePublished(Builder $query): Builder
    {
        $column = $this->qualifyColumn('published_at');

        return $query->where(function (Builder $nested) use ($column) {
            $nested->whereNotNull($column)->where($column, '<=', now());
        });
    }

    /**
     * Queued up for a date in the future — not public yet.
     */
    public function scopeScheduled(Builder $query): Builder
    {
        $column = $this->qualifyColumn('published_at');

        return $query->where(function (Builder $nested) use ($column) {
            $nested->whereNotNull($column)->where($column, '>', now());
        });
    }

    /**
     * Never published and not scheduled.
     */
    public function scopeDraft(Builder $query): Builder
    {
        $column = $this->qualifyColumn('published_at');

        return $query->where(function (Builder $nested) use ($column) {
            $nested->whereNull($column);
        });
    }

    // ─── State ───

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(now());
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->greaterThan(now());
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * published | scheduled | draft.
     */
    public function getPublishStatusAttribute(): string
    {
        if ($this->isDraft()) {
            return 'draft';
        }

        return $this->isScheduled() ? 'scheduled' : 'published';
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->isPublished();
    }

    // ─── Transitions ───

    /**
     * Publish now, or schedule for $at. Persists when the model already exists,
     * so an unsaved model can still be prepared and saved by the caller.
     */
    public function publish(?DateTimeInterface $at = null): static
    {
        $this->published_at = $at ?? now();

        if ($this->exists) {
            $this->save();
        }

        return $this;
    }

    /**
     * Back to draft (also cancels a pending schedule).
     */
    public function unpublish(): static
    {
        $this->published_at = null;

        if ($this->exists) {
            $this->save();
        }

        return $this;
    }

    // ─── Mutators: published_at plus the legacy aliases ───

    /**
     * Accepts a real date/time, but also the on/off values the old flag and the
     * old status string used, so a Draft/Published control can post either.
     */
    public function setPublishedAtAttribute($value): void
    {
        $flag = $this->publishFlagFrom($value);

        if ($flag === null) {
            $this->attributes['published_at'] = $this->fromDateTime($value);

            return;
        }

        $this->applyPublishFlag($flag);
    }

    /**
     * Legacy `pages.published` flag (bool / 0 / 1).
     */
    public function setPublishedAttribute($value): void
    {
        $this->applyPublishFlag($this->publishFlagFrom($value) ?? (bool) $value);
    }

    /**
     * Legacy `posts.status` string ('published' / 'draft').
     */
    public function setStatusAttribute($value): void
    {
        $this->applyPublishFlag(strtolower(trim((string) $value)) === 'published');
    }

    /**
     * true/false for the legacy on/off values, null when the value is a date
     * that should be stored as-is.
     */
    private function publishFlagFrom($value): ?bool
    {
        if ($value === null || $value === false || $value === 0) {
            return false;
        }

        if ($value === true || $value === 1) {
            return true;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '', '0', 'false', 'draft' => false,
                '1', 'true', 'published'  => true,
                default                   => null,
            };
        }

        return null;
    }

    /**
     * Turning publishing on keeps an existing date (so a scheduled item is not
     * silently moved to "now"); turning it off clears it.
     */
    private function applyPublishFlag(bool $publish): void
    {
        if ( ! $publish) {
            $this->attributes['published_at'] = null;

            return;
        }

        if (($this->attributes['published_at'] ?? null) === null) {
            $this->attributes['published_at'] = $this->fromDateTime(now());
        }
    }
}
