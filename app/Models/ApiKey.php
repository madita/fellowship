<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key',
        'secret_hash',
        'abilities',
        'last_used_at',
        'expires_at',
        'is_active',
        'request_count',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'request_count' => 'integer',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    /**
     * Get the user that owns the API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key pair.
     */
    public static function generateKeyPair(): array
    {
        $key = 'fk_'.Str::random(32); // fk = fellowship key
        $secret = Str::random(48);

        return [
            'key' => $key,
            'secret' => $secret,
            'secret_hash' => hash('sha256', $secret),
        ];
    }

    /**
     * Verify the secret against the stored hash.
     */
    public function verifySecret(string $secret): bool
    {
        return hash_equals($this->secret_hash, hash('sha256', $secret));
    }

    /**
     * Check if the API key is valid (active and not expired).
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the API key has a specific ability.
     */
    public function hasAbility(string $ability): bool
    {
        if (empty($this->abilities)) {
            return true; // No restrictions means all abilities
        }

        if (in_array('*', $this->abilities)) {
            return true;
        }

        return in_array($ability, $this->abilities);
    }

    /**
     * Record usage of the API key.
     */
    public function recordUsage(): void
    {
        $this->increment('request_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope to get only active keys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Find an API key by its key string.
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
