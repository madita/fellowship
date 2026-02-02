<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache tags for different content types.
     */
    public const TAG_SETTINGS = 'settings';
    public const TAG_PAGES = 'pages';
    public const TAG_WIKI = 'wiki';
    public const TAG_POSTS = 'posts';
    public const TAG_MENUS = 'menus';
    public const TAG_WIDGETS = 'widgets';
    public const TAG_USERS = 'users';

    /**
     * Check if caching is enabled.
     */
    public static function isEnabled(): bool
    {
        return Setting::isCacheEnabled();
    }

    /**
     * Get cache lifetime in seconds.
     */
    public static function getLifetime(): int
    {
        return Setting::getCacheLifetime();
    }

    /**
     * Remember a value in cache (respects cache settings).
     */
    public static function remember(string $key, \Closure $callback, ?int $ttl = null, array $tags = [])
    {
        if (!static::isEnabled()) {
            return $callback();
        }

        $ttl = $ttl ?? static::getLifetime();

        // Use tags if cache driver supports it
        if (!empty($tags) && static::supportsTags()) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Store a value in cache.
     */
    public static function put(string $key, $value, ?int $ttl = null, array $tags = []): void
    {
        if (!static::isEnabled()) {
            return;
        }

        $ttl = $ttl ?? static::getLifetime();

        if (!empty($tags) && static::supportsTags()) {
            Cache::tags($tags)->put($key, $value, $ttl);
        } else {
            Cache::put($key, $value, $ttl);
        }
    }

    /**
     * Get a value from cache.
     */
    public static function get(string $key, $default = null, array $tags = [])
    {
        if (!static::isEnabled()) {
            return $default;
        }

        if (!empty($tags) && static::supportsTags()) {
            return Cache::tags($tags)->get($key, $default);
        }

        return Cache::get($key, $default);
    }

    /**
     * Forget a specific cache key.
     */
    public static function forget(string $key, array $tags = []): void
    {
        if (!empty($tags) && static::supportsTags()) {
            Cache::tags($tags)->forget($key);
        } else {
            Cache::forget($key);
        }
    }

    /**
     * Flush cache by tag.
     */
    public static function flushTag(string $tag): void
    {
        if (static::supportsTags()) {
            Cache::tags([$tag])->flush();
        }
    }

    /**
     * Flush multiple tags.
     */
    public static function flushTags(array $tags): void
    {
        if (static::supportsTags()) {
            Cache::tags($tags)->flush();
        }
    }

    /**
     * Flush all application cache.
     */
    public static function flushAll(): void
    {
        Cache::flush();
        Setting::clearCache();
        Setting::resetCacheConfig();
    }

    /**
     * Check if cache driver supports tags.
     */
    public static function supportsTags(): bool
    {
        try {
            return Cache::supportsTags();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate a cache key for a model.
     */
    public static function modelKey(string $model, $id): string
    {
        return strtolower($model).':'.$id;
    }

    /**
     * Generate a cache key for a list/collection.
     */
    public static function listKey(string $model, array $params = []): string
    {
        $key = strtolower($model).':list';
        if (!empty($params)) {
            $key .= ':'.md5(serialize($params));
        }

        return $key;
    }
}
