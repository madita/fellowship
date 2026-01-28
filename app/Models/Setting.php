<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * Default cache lifetime in seconds (used when setting not available)
     */
    protected static int $defaultCacheLifetime = 3600;

    /**
     * Cached value for cache_enabled to avoid infinite recursion
     */
    protected static ?bool $cacheEnabled = null;

    /**
     * Cached value for cache_lifetime to avoid infinite recursion
     */
    protected static ?int $cacheLifetime = null;

    /**
     * Check if caching is enabled
     */
    public static function isCacheEnabled(): bool
    {
        // Return cached value if already determined
        if (static::$cacheEnabled !== null) {
            return static::$cacheEnabled;
        }

        try {
            // Direct database query to avoid recursion
            $setting = static::where('key', 'cache_enabled')->first();
            static::$cacheEnabled = $setting ? static::castValue($setting->value, $setting->type) : true;
        } catch (\Exception $e) {
            static::$cacheEnabled = true; // Default to enabled
        }

        return static::$cacheEnabled;
    }

    /**
     * Get cache lifetime in seconds
     */
    public static function getCacheLifetime(): int
    {
        // Return cached value if already determined
        if (static::$cacheLifetime !== null) {
            return static::$cacheLifetime;
        }

        try {
            // Direct database query to avoid recursion
            $setting = static::where('key', 'cache_lifetime_minutes')->first();
            $minutes = $setting ? (int) $setting->value : 60;
            static::$cacheLifetime = $minutes * 60; // Convert to seconds
        } catch (\Exception $e) {
            static::$cacheLifetime = static::$defaultCacheLifetime;
        }

        return static::$cacheLifetime;
    }

    /**
     * Reset static cache (call after updating cache settings)
     */
    public static function resetCacheConfig(): void
    {
        static::$cacheEnabled = null;
        static::$cacheLifetime = null;
    }

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        // If caching is disabled, fetch directly from database
        if (!static::isCacheEnabled()) {
            $setting = static::where('key', $key)->first();
            if (!$setting) {
                return $default;
            }
            return static::castValue($setting->value, $setting->type);
        }

        $lifetime = static::getCacheLifetime();

        return Cache::remember("setting.{$key}", $lifetime, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );

        // Reset cache config if cache settings changed
        if (in_array($key, ['cache_enabled', 'cache_lifetime_minutes'])) {
            static::resetCacheConfig();
        }

        Cache::forget("setting.{$key}");
    }

    /**
     * Get all settings as key-value pairs
     */
    public static function getAllSettings(): array
    {
        // If caching is disabled, fetch directly from database
        if (!static::isCacheEnabled()) {
            $settings = static::query()->get();
            $result = [];
            foreach ($settings as $setting) {
                $result[$setting->key] = static::castValue($setting->value, $setting->type);
            }
            return $result;
        }

        $lifetime = static::getCacheLifetime();

        return Cache::remember('settings.all', $lifetime, function () {
            $settings = static::query()->get();
            $result = [];

            foreach ($settings as $setting) {
                $result[$setting->key] = static::castValue($setting->value, $setting->type);
            }

            return $result;
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget('settings.all');

        // Clear individual setting caches for all possible keys
        $commonKeys = [
            'app_name',
            'app_logo',
            'app_copyright',
            'contact_address',
            'contact_phone',
            'contact_email',
            'social_twitter',
            'social_facebook',
            'social_instagram',
            'default_language',
            'default_timezone',
            'date_format',
            'time_format',
            'language_change_enabled',
            'maintenance_mode',
            'maintenance_message',
            // SEO settings
            'meta_title',
            'meta_description',
            'meta_keywords',
            'og_title',
            'og_description',
            'og_image',
            'twitter_card_type',
            'twitter_site',
            'canonical_url',
            'indexing_enabled',
            'sitemap_enabled',
            'robots_txt_custom',
            // Custom scripts
            'custom_head_scripts',
            'custom_body_scripts',
        ];

        foreach ($commonKeys as $key) {
            Cache::forget("setting.{$key}");
        }

        // Also clear any keys that exist in database
        try {
            $dbKeys = static::pluck('key');
            foreach ($dbKeys as $key) {
                Cache::forget("setting.{$key}");
            }
        } catch (\Exception $e) {
            // Table might not exist yet during migrations
        }
    }

    /**
     * Cast value to appropriate type
     */
    protected static function castValue($value, string $type)
    {
        return match ($type) {
            'boolean' => in_array($value, ['1', 1, true, 'true', 'yes', 'on'], true),
            'integer' => (int) $value,
            'float' => (float) $value,
            'array', 'json' => json_decode($value, true),
            default => $value,
        };
    }
}
