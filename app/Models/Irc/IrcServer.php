<?php

namespace App\Models\Irc;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IrcServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'use_ssl',
        'password',
        'description',
        'is_active',
        'order',
        'metadata',
    ];

    protected $casts = [
        'port' => 'integer',
        'use_ssl' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get all connections for this server.
     */
    public function connections(): HasMany
    {
        return $this->hasMany(IrcConnection::class);
    }

    /**
     * Get the connection URL.
     */
    public function getConnectionUrlAttribute(): string
    {
        $protocol = $this->use_ssl ? 'ircs' : 'irc';
        return "{$protocol}://{$this->host}:{$this->port}";
    }

    /**
     * Scope: Active servers only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }
}
