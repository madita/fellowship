<?php

namespace App\Models\Menu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'location',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all menu items for this menu.
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('order');
    }

    /**
     * Get only active menu items.
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->where('is_active', true)
            ->orderBy('order');
    }

    /**
     * Get root-level menu items (no parent).
     */
    public function rootItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('order');
    }

    /**
     * Get menu by location.
     */
    public static function getByLocation(string $location): ?Menu
    {
        return static::where('location', $location)
            ->where('is_active', true)
            ->with(['rootItems.children'])
            ->first();
    }

    /**
     * Get menu by slug.
     */
    public static function getBySlug(string $slug): ?Menu
    {
        return static::where('slug', $slug)
            ->where('is_active', true)
            ->with(['rootItems.children'])
            ->first();
    }

    /**
     * Scope: Active menus only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
