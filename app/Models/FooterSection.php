<?php

namespace App\Models;

use App\Models\Translations\SectionTranslation;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FooterSection extends Model implements TranslatableContract
{
    use Translatable;

    protected $table = 'sections';

    public $translatedAttributes = ['title'];

    public $translationModel = SectionTranslation::class;

    public $translationForeignKey = 'section_id';

    protected $fillable = [
        'location',
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
     * Get the widgets for this section.
     */
    public function widgets()
    {
        return $this->hasMany(FooterWidget::class, 'section_id')
            ->orderBy('column')
            ->orderBy('order');
    }

    /**
     * Scope to get only enabled sections.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to order sections.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get active sections with their widgets (cached).
     */
    public static function getActiveSections()
    {
        return Cache::remember('footer_sections_active', 3600, function () {
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
     * Clear the cache.
     */
    public static function clearCache()
    {
        Cache::forget('footer_sections_active');
        FooterWidget::clearCache();
    }

    /**
     * Boot method to clear cache on save/delete.
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
}
