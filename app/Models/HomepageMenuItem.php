<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomepageMenuItem extends Model
{
    protected $fillable = [
        'label',
        'anchor_target',
        'order',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'order'   => 'integer',
    ];

    /**
     * Scope query to only enabled menu items.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope query to order menu items.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get all active menu items (cached).
     */
    public static function getActiveItems()
    {
        return Cache::remember('homepage.menu_items', 3600, function () {
            return static::enabled()->ordered()->get();
        });
    }

    /**
     * Clear the homepage menu items cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('homepage.menu_items');
    }

    /**
     * Boot the model and add event listeners.
     */
    protected static function booted(): void
    {
        // Clear cache whenever a menu item is saved or deleted
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }
}
