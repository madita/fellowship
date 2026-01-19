<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomepageSection extends Model
{
    use HasFactory;

    protected $table = 'sections';

    protected $fillable = [
        'location',
        'title',
        'layout',
        'enabled',
        'order',
        'config',
        'anchor_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'order' => 'integer',
        'config' => 'array',
    ];

    protected $attributes = [
        'location' => 'home',
    ];

    /**
     * Get the widgets for this section
     */
    public function widgets()
    {
        return $this->hasMany(HomepageWidget::class, 'section_id')
            ->orderBy('column')
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
        return Cache::remember('homepage_sections_active', 3600, function () {
            return self::enabled()
                ->ordered()
                ->with(['widgets' => function ($query) {
                    $query->where('enabled', true)
                        ->orderBy('column')
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
        Cache::forget('homepage_sections_active');
        HomepageWidget::clearCache(); // Also clear widgets cache
    }

    /**
     * Boot method to clear cache on save/delete
     */
    protected static function boot()
    {
        parent::boot();

        // Add global scope for location
        static::addGlobalScope('location', function ($builder) {
            $builder->where('location', 'home');
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
            '2-col', '2-1-col', '1-2-col' => 2,
            '3-col' => 3,
            '4-col' => 4,
            default => 1,
        };
    }

    /**
     * Get the column widths for this layout
     */
    public function getColumnWidthsAttribute()
    {
        return match($this->layout) {
            '1-col' => [12],
            '2-col' => [6, 6],
            '3-col' => [4, 4, 4],
            '4-col' => [3, 3, 3, 3],
            '2-1-col' => [8, 4], // 66% / 33%
            '1-2-col' => [4, 8], // 33% / 66%
            default => [12],
        };
    }
}
