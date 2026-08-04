<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FooterWidget extends Model
{
    protected $table = 'widgets';

    protected $fillable = [
        'location',
        'type',
        'config',
        'order',
        'enabled',
        'section_id',
        'column',
    ];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
        'order' => 'integer',
        'column' => 'integer',
        'section_id' => 'integer',
    ];

    protected $attributes = [
        'location' => 'footer',
    ];

    /**
     * Relationship to section.
     */
    public function section()
    {
        return $this->belongsTo(FooterSection::class, 'section_id');
    }

    /**
     * Scope query to only active widgets.
     */
    public function scopeActive($query)
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
        return Cache::remember('footer.widgets', 3600, function () {
            return static::active()->ordered()->get();
        });
    }

    /**
     * Clear the footer widgets cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('footer.widgets');
    }

    /**
     * Boot the model and add event listeners.
     */
    protected static function booted(): void
    {
        // Scope all queries to footer location
        static::addGlobalScope('location', function ($builder) {
            $builder->where('location', 'footer');
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
