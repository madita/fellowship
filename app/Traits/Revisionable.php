<?php

namespace App\Traits;

use App\Models\Revision;
use App\Presenters\RevisionPresenter;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Collection;
use App\Listeners\RevisionListener;
use Psy\VarDumper\Presenter;

trait Revisionable
{
    /**
     * Boot the trait for a model.
     */
    protected static function bootRevisionable()
    {
        static::observe(RevisionListener::class);
    }

    /**
     * Get record version at given timestamp.
     *
     * @param \DateTime|string $timestamp DateTime|Carbon object or parsable date string @see strtotime()
     *
     * @return Revision|RevisionPresenter|null
     */
    public function snapshot($timestamp)
    {
        /** @var Revision $revision */
        $revision = $this->revisions()
            ->where('created_at', '<=', Carbon::parse($timestamp))
            ->first();

        return $this->wrapRevision($revision);
    }

    /**
     * Get record version at given step back in history.
     *
     * @param int $step
     *
     * @return Revision|RevisionPresenter|null
     */
    public function historyStep($step)
    {
        /** @var Revision $revision */
        $revision = $this->revisions();
        return $this->wrapRevision($revision->skip($step)->first());
    }

    /**
     * Determine if model has history at given timestamp if provided or any at all.
     *
     * @param \DateTime|string $timestamp DateTime|Carbon object or parsable date string @see strtotime()
     *
     * @return bool
     */
    public function hasHistory($timestamp = null)
    {
        if ($timestamp) {
            return (bool)$this->snapshot($timestamp);
        }

        return $this->revisions()->exists();
    }

    /**
     * Get an array of updated revisionable attributes.
     *
     * @return array
     */
    public function getDiff()
    {
        return array_diff_assoc($this->getNewAttributes(), $this->getOldAttributes());
    }

    /**
     * Get an array of original revisionable attributes.
     *
     * @return array
     */
    public function getOldAttributes()
    {
        $attributes = $this->getRevisionableItems($this->original);

        return $this->prepareAttributes($attributes);
    }

    /**
     * Get an array of current revisionable attributes.
     *
     * @return array
     */
    public function getNewAttributes()
    {
        $attributes = $this->getRevisionableItems($this->attributes);

        return $this->prepareAttributes($attributes);
    }

    /**
     * Stringify revisionable attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function prepareAttributes(array $attributes)
    {
        return array_map(function ($attribute) {
            return ($attribute instanceof DateTime)
                ? $this->fromDateTime($attribute)
                : (string)$attribute;
        }, $attributes);
    }

    /**
     * Get an array of revisionable attributes.
     *
     * @param array $values
     *
     * @return array
     */
    protected function getRevisionableItems(array $values)
    {
        if (count($this->getRevisionable()) > 0) {
            return array_intersect_key($values, array_flip($this->getRevisionable()));
        }

        return array_diff_key($values, array_flip($this->getNonRevisionable()));
    }

    /**
     * Attributes being revisioned.
     *
     * @var array
     */
    public function getRevisionable()
    {
        return property_exists($this, 'revisionable') ? (array)$this->revisionable : [];
    }

    /**
     * Attributes hidden from revisioning if revisionable are not provided.
     *
     * @var array
     */
    public function getNonRevisionable()
    {
        return property_exists($this, 'nonRevisionable')
            ? (array)$this->nonRevisionable
            : ['created_at', 'updated_at', 'deleted_at'];
    }

    /**
     * Model has many Revisions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function revisions()
    {
        return $this->morphMany(Revision::class, 'revisionable', 'revisionable_type', 'revisionable_id')
            ->ordered();
    }

    /**
     * Model has one latestRevision.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function latestRevision()
    {
        return $this->morphOne(Revision::class, 'revisionable', 'revisionable_type', 'revisionable_id')
            ->ordered();
    }

    /**
     * Accessor for revisions property.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRevisionsAttribute()
    {
        if (!$this->relationLoaded('revisions')) {
            $this->load('revisions');
        }

        return $this->wrapRevision($this->getRelation('revisions'));
    }

    /**
     * Accessor for latestRevision attribute.
     *
     * @link https://laravel.com/docs/eloquent-mutators#accessors-and-mutators
     *
     * @return RevisionPresenter|Revision
     */
    public function getLatestRevisionAttribute()
    {
        if (!$this->relationLoaded('latestRevision')) {
            $this->load('latestRevision');
        }

        return $this->wrapRevision($this->getRelation('latestRevision'));
    }

    /**
     * Wrap revision model with the presenter if provided.
     *
     * @param Revision|\Illuminate\Database\Eloquent\Collection $history
     *
     * @return RevisionPresenter|Revision
     */
    public function wrapRevision($history)
    {
        if ($history && $presenter = $this->getRevisionPresenter()) {
            return $presenter::make($history, $this);
        }

        return $history;
    }

    /**
     * Get revision presenter class for the model.
     *
     * @return string|null
     */
    public function getRevisionPresenter()
    {
        if (!property_exists($this, 'revisionPresenter')) {
            return null;
        }

        return class_exists($this->revisionPresenter)
            ? $this->revisionPresenter
            : Presenter::class;
    }

    /**
     * Get all updates for a given field.
     *
     * @param string $field
     * @return Collection
     */
    public function getFieldHistory(string $field): Collection
    {
        return $this->revisions->map(function ($revision) use ($field) : ?array {
            if ($revision->old_value($field) == $revision->new_value($field)) {
                return null;
            }

            return [
                'created_at' => (string)$revision->created_at,
                'user_id' => $revision->executor->id ?? null,
                'user_email' => $revision->executor->email ?? null,
                'old_value' => $revision->old_value($field),
                'new_value' => $revision->new_value($field),
            ];
        })->filter()->values();
    }
}
