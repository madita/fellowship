<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Widget extends Model
{
    protected $table = 'widgets';

    protected $fillable = [
        'location',
        'section_id',
        'type',
        'title',
        'enabled',
        'order',
        'column',
        'content',
        'config',
        'anchor_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'order'   => 'integer',
        'column'  => 'integer',
        'content' => 'array',
        'config'  => 'array',
    ];

    protected $attributes = [
        'location' => 'home',
    ];

    /**
     * Get the section this widget belongs to.
     */
    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * Scope query to only enabled widgets.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope query to order widgets.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get all active widgets (cached).
     */
    public static function getActiveWidgets()
    {
        return Cache::remember('homepage.widgets', 3600, function () {
            return static::enabled()->ordered()->get();
        });
    }

    /**
     * Clear the homepage widgets cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('homepage.widgets');
    }

    /**
     * Boot the model and add event listeners.
     */
    protected static function booted(): void
    {
        // Scope all queries to home location
        static::addGlobalScope('location', function ($builder) {
            $builder->where('location', 'home');
        });

        // Clear cache whenever a widget is saved or deleted
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }
}
