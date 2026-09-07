<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user account from a legacy system, imported through the mapping tool
 * ("Legacy Users" target). Identity is (legacy_source, username) — the
 * same name in different systems can be different people.
 */
class MigrationLegacyUser extends Model
{
    protected $fillable = [
        'legacy_source',
        'username',
        'email',
        'legacy_user_id',
        'real_name',
        'registered_at',
        'assigned_user_id',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
