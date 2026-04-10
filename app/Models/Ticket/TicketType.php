<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'config',
        'is_active',
        'auto_create',
        'position',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'auto_create' => 'boolean',
    ];

    /**
     * Get all tickets of this type.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get active ticket types ordered by position.
     */
    public static function active()
    {
        return static::where('is_active', true)
            ->orderBy('position')
            ->get();
    }

    /**
     * Get auto-create ticket type by slug.
     */
    public static function findAutoCreateBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)
            ->where('auto_create', true)
            ->first();
    }
}
