<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FooterSection extends Model
{
    protected $table = 'sections';

    protected $fillable = [
        'location',
        'title',
        'layout',
        'enabled',
        'order',
        'config',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'order' => 'integer',
        'config' => 'array',
    ];

    protected $attributes = [
        'location' => 'footer',
    ];

    /**
     * Get the widgets for this section
     */
    public function widgets()
    {
        return $this->hasMany(FooterWidget::class, 'section_id')
            ->orderBy('order');
    }

    /**
     * Scope to get only enabled sections
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to order sections
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get active sections with their widgets (cached)
     */
    public static function getActiveSections()
    {
        return Cache::remember('footer_sections_active', 3600, function () {
            return self::enabled()
                ->ordered()
                ->with(['widgets' => function ($query) {
                    $query->where('enabled', true)
                        ->orderBy('order');
                }])
                ->get();
        });
    }

    /**
     * Clear the cache
     */
    public static function clearCache()
    {
        Cache::forget('footer_sections_active');
        FooterWidget::clearCache();
    }

    /**
     * Boot method to clear cache on save/delete
     */
    protected static function boot()
    {
        parent::boot();

        // Add global scope for location
        static::addGlobalScope('location', function ($builder) {
            $builder->where('location', 'footer');
        });

        static::deleting(function ($section) {
            // Delete all widgets in this section
            $section->widgets()->delete();
        });

        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * Get the number of columns for this section's layout
     */
    public function getColumnCountAttribute()
    {
        return match($this->layout) {
            '1-col' => 1,
            '2-col' => 2,
            '3-col' => 3,
            '4-col' => 4,
            default => 1,
        };
    }

    /**
     * Get the column widths for this layout (Vuetify grid system, 12 columns)
     */
    public function getColumnWidthsAttribute()
    {
        return match($this->layout) {
            '1-col' => [12],
            '2-col' => [6, 6],
            '3-col' => [4, 4, 4],
            '4-col' => [3, 3, 3, 3],
            default => [12],
        };
    }
}
