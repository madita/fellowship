<?php

namespace App\Traits;

use App\Services\CacheService;

trait HasCache
{
    /**
     * Boot the trait - clear cache on model events.
     */
    public static function bootHasCache(): void
    {
        // Clear cache when model is created, updated, or deleted
        static::created(function ($model) {
            $model->clearModelCache();
        });

        static::updated(function ($model) {
            $model->clearModelCache();
        });

        static::deleted(function ($model) {
            $model->clearModelCache();
        });
    }

    /**
     * Get the cache tag for this model.
     */
    public function getCacheTag(): string
    {
        return property_exists($this, 'cacheTag')
            ? $this->cacheTag
            : strtolower(class_basename($this)).'s';
    }

    /**
     * Get cache key for this model instance.
     */
    public function getCacheKey(): string
    {
        return CacheService::modelKey(class_basename($this), $this->getKey());
    }

    /**
     * Clear cache for this model.
     */
    public function clearModelCache(): void
    {
        // Clear individual model cache
        CacheService::forget($this->getCacheKey(), [$this->getCacheTag()]);

        // Clear list cache for this model type
        CacheService::flushTag($this->getCacheTag());
    }

    /**
     * Find a model by ID with caching.
     */
    public static function findCached($id)
    {
        $model = new static;
        $key = CacheService::modelKey(class_basename($model), $id);
        $tag = $model->getCacheTag();

        return CacheService::remember($key, function () use ($id) {
            return static::find($id);
        }, null, [$tag]);
    }

    /**
     * Find a model by slug with caching.
     */
    public static function findBySlugCached(string $slug)
    {
        $model = new static;
        $key = CacheService::modelKey(class_basename($model), 'slug:'.$slug);
        $tag = $model->getCacheTag();

        return CacheService::remember($key, function () use ($slug) {
            return static::where('slug', $slug)->first();
        }, null, [$tag]);
    }

    /**
     * Get all models with caching.
     */
    public static function allCached()
    {
        $model = new static;
        $key = CacheService::listKey(class_basename($model));
        $tag = $model->getCacheTag();

        return CacheService::remember($key, function () {
            return static::all();
        }, null, [$tag]);
    }

    /**
     * Get paginated models with caching.
     */
    public static function paginateCached(int $perPage = 15, array $params = [])
    {
        $model = new static;
        $page = request()->get('page', 1);
        $cacheParams = array_merge($params, ['page' => $page, 'per_page' => $perPage]);
        $key = CacheService::listKey(class_basename($model), $cacheParams);
        $tag = $model->getCacheTag();

        return CacheService::remember($key, function () use ($perPage) {
            return static::paginate($perPage);
        }, null, [$tag]);
    }
}
