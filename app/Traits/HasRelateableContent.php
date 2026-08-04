<?php

namespace App\Traits;

use App\Models\Relateable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait HasRelateableContent
{
    /** @var Collection|null */
    protected $relatableCache;

    public function relatables(): MorphMany
    {
        return $this->morphMany(Relateable::class, 'source');
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
     * @param  string|null  $type
     * @return \Spatie\Relateable\Relateable
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
     * @param  string|null  $type
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
            return $relatable->getRelatedValues();
        });

        $items->each(function (array $values) {
            $this->relate($values['id'], $values['type']);
        });

        if (! $detaching) {
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
                    'id' => $item->getKey(),
                ];
            });
        }

        return collect($items);
    }

    /**
     * @param  Model|int  $item
     * @param  string|null  $type
     */
    protected function getRelateableValues($item, string $type = ''): array
    {
        if (! $item instanceof Model && empty($type)) {
            throw new InvalidArgumentException(
                'If an id is specified as an item, the type isn\'t allowed to be empty.'
            );
        }

        return [
            'source_id' => $this->getKey(),
            'source_type' => $this->getMorphClass(),
            'related_id' => $item instanceof Model ? $item->getKey() : $item,
            'related_type' => $item instanceof Model ? $item->getMorphClass() : $type,
        ];
    }
}
