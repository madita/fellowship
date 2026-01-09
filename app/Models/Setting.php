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
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
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

        Cache::forget("setting.{$key}");
    }

    /**
     * Get all settings as key-value pairs
     */
    public static function getAllSettings(): array
    {
        return Cache::remember('settings.all', 3600, function () {
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
