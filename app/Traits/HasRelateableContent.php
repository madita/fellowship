<?php

namespace App\Traits;

use App\Models\Relateable;
use App\Support\RelateableHelper;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait HasRelateableContent
{
    /** @var Collection|null */
    protected $relatableCache;

    /**
     * Remove this model's links once it is really gone (soft deletes keep them,
     * so restoring an event restores its related content too).
     */
    public static function bootHasRelateableContent(): void
    {
        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->relatables()->delete();
            $model->relatedFrom()->delete();
        });
    }

    /**
     * Links where this model is the source.
     */
    public function relatables(): MorphMany
    {
        return $this->morphMany(Relateable::class, 'source');
    }

    /**
     * Links where this model is the related side (created from the other model).
     */
    public function relatedFrom(): MorphMany
    {
        return $this->morphMany(Relateable::class, 'related');
    }

    /**
     * Everything linked to this model in either direction, as
     * [{ item: summary of the other side, direction: outgoing|incoming, created_at }],
     * one entry per linked model, newest first. Links to models that no longer
     * exist (or that $filter rejects) are skipped.
     *
     * @param  (Closure(Model): bool)|null  $filter
     */
    public function relatedSummaries(?Closure $filter = null): Collection
    {
        $outgoing = $this->relatables()->get()->map(fn (Relateable $row) => [
            'type'       => $row->related_type,
            'id'         => (int) $row->related_id,
            'direction'  => 'outgoing',
            'created_at' => $row->created_at,
        ]);

        $incoming = $this->relatedFrom()->get()->map(fn (Relateable $row) => [
            'type'       => $row->source_type,
            'id'         => (int) $row->source_id,
            'direction'  => 'incoming',
            'created_at' => $row->created_at,
        ]);

        $links = $outgoing->concat($incoming)
            ->filter(fn (array $link) => RelateableHelper::kindForType($link['type']) !== null)
            ->sortByDesc(fn (array $link) => $link['created_at']?->getTimestamp() ?? 0)
            ->unique(fn (array $link) => $link['type'] . '#' . $link['id'])
            ->values();

        $models = RelateableHelper::findMany($links);

        return $links
            ->map(function (array $link) use ($models, $filter) {
                $model = $models[$link['type']][$link['id']] ?? null;
                if ( ! $model || ($filter && ! $filter($model))) {
                    return null;
                }

                return [
                    'item'       => RelateableHelper::summary($model),
                    'direction'  => $link['direction'],
                    'created_at' => $link['created_at']?->toIso8601String(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Returns a Collection of all related models. The results are cached as a property on the
     * model, you reload them using the `loadRelated` method.
     */
    public function getRelatedAttribute(): Collection
    {
        if ($this->relatableCache === null) {
            $this->loadRelated();
        }

        return $this->relatableCache;
    }

    public function loadRelated($reloadRelateables = true): Collection
    {
        if ($reloadRelateables) {
            $this->load('relatables');
        }

        return $this->relatableCache = $this->relatables
            ->filter(fn (Relateable $relatable) => RelateableHelper::kindForType($relatable->related_type) !== null)
            ->groupBy(function (Relateable $relatable) {
                return $this->getActualClassNameForMorph($relatable->related_type);
            })
            ->flatMap(function (Collection $typeGroup, string $type) {
                return $type::whereIn('id', $typeGroup->pluck('related_id'))->get();
            });
    }

    public function hasRelated(): bool
    {
        return ! $this->related->isEmpty();
    }

    /**
     * The `$item` parameter must be an Eloquent model or an ID. If you provide an ID, the model's
     * morph type must be specified as a second parameter.
     *
     * @param  Model|int  $item
     */
    public function relate($item, string $type = ''): Relateable
    {
        return Relateable::firstOrCreate(
            $this->getRelateableValues($item, $type)
        );
    }

    /**
     * The `$item` parameter must be an Eloquent model or an ID. If you provide an ID, the model's
     * morph type must be specified as a second parameter.
     *
     * @param  Model|int  $item
     */
    public function unrelate($item, string $type = ''): int
    {
        return Relateable::where($this->getRelateableValues($item, $type))->delete();
    }

    /**
     * The `$items` parameter can either contain an Eloquent collection of models, or an array
     * with the shape of [['id' => int, 'type' => string], ...].
     *
     * @param  \Illuminate\Database\Eloquent\Collection|array  $items
     * @param  bool  $detaching
     */
    public function syncRelated($items, $detaching = true)
    {
        $items = $this->getSyncRelatedValues($items);

        $current = $this->relatables->map(function (Relateable $relatable) {
            return $relatable->getRelateableValues();
        });

        $items->each(function (array $values) {
            $this->relate($values['id'], $values['type']);
        });

        if ( ! $detaching) {
            return;
        }

        $current
            ->filter(function (array $values) use ($items) {
                return ! $items->contains($values);
            })
            ->each(function (array $values) {
                $this->unrelate($values['id'], $values['type']);
            });
    }

    protected function getSyncRelatedValues($items): Collection
    {
        if ($items instanceof Collection) {
            return $items->map(function (Model $item): array {
                return [
                    'type' => $item->getMorphClass(),
                    'id'   => $item->getKey(),
                ];
            });
        }

        return collect($items);
    }

    /**
     * @param  Model|int  $item
     */
    protected function getRelateableValues($item, string $type = ''): array
    {
        if ( ! $item instanceof Model && empty($type)) {
            throw new InvalidArgumentException(
                'If an id is specified as an item, the type isn\'t allowed to be empty.'
            );
        }

        return [
            'source_id'    => $this->getKey(),
            'source_type'  => $this->getMorphClass(),
            'related_id'   => $item instanceof Model ? $item->getKey() : $item,
            'related_type' => $item instanceof Model ? $item->getMorphClass() : $type,
        ];
    }
}
