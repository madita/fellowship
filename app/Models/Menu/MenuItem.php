<?php

namespace App\Models\Menu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'type',
        'url',
        'route',
        'icon',
        'target',
        'permission',
        'role',
        'auth_required',
        'guest_only',
        'order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'auth_required' => 'boolean',
        'guest_only' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'order' => 'integer',
    ];

    protected $appends = ['href'];

    /**
     * Get the menu this item belongs to.
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Get parent menu item.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Get child menu items.
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('order');
    }

    /**
     * Get the URL/href for this menu item.
     */
    public function getHrefAttribute(): ?string
    {
        return match ($this->type) {
            'route' => $this->route,
            'custom', 'external' => $this->url,
            'page' => $this->url, // Could resolve to page slug
            default => '#',
        };
    }

    /**
     * Check if user can view this menu item.
     */
    public function canView($user = null): bool
    {
        // Guest-only items
        if ($this->guest_only && $user) {
            return false;
        }

        // Auth required
        if ($this->auth_required && ! $user) {
            return false;
        }

        // Role check
        if ($this->role && $user && ! $user->hasRole($this->role)) {
            return false;
        }

        // Permission check
        if ($this->permission && $user && ! $user->can($this->permission)) {
            return false;
        }

        return $this->is_active;
    }
}
