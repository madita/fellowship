<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Links an imported record to the legacy username it belonged to on the
 * old site. When a registered user claims their legacy account, all
 * records attributed to that username are reassigned to them.
 */
class MigrationAttribution extends Model
{
    protected $fillable = [
        'attributable_type',
        'attributable_id',
        'legacy_source',
        'legacy_username',
        'assigned_user_id',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function attributable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @param string|null $legacySource the legacy system the username comes
     *                                  from (e.g. "wiki", "forum") — the same
     *                                  name in different systems can belong
     *                                  to different people
     */
    public static function record(Model $model, ?string $legacyUsername, ?string $legacySource = null): void
    {
        $legacyUsername = trim((string) $legacyUsername);
        if ($legacyUsername === '') {
            return;
        }

        static::firstOrCreate([
            'attributable_type' => get_class($model),
            'attributable_id' => $model->getKey(),
            'legacy_source' => trim((string) $legacySource) ?: 'legacy',
            'legacy_username' => $legacyUsername,
        ]);
    }
}
