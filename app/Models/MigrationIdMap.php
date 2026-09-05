<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Legacy id → imported record. Lets dependent imports link up (forum
 * posts find their thread by the old topic id) and makes re-runs skip
 * rows that were already imported.
 */
class MigrationIdMap extends Model
{
    protected $fillable = [
        'context',
        'legacy_id',
        'mappable_type',
        'mappable_id',
    ];

    public function mappable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function remember(string $context, string|int|null $legacyId, Model $model): void
    {
        $legacyId = trim((string) $legacyId);
        if ($legacyId === '') {
            return;
        }

        static::firstOrCreate(
            ['context' => $context, 'legacy_id' => $legacyId],
            ['mappable_type' => get_class($model), 'mappable_id' => $model->getKey()]
        );
    }

    public static function lookup(string $context, string|int|null $legacyId): ?Model
    {
        $legacyId = trim((string) $legacyId);
        if ($legacyId === '') {
            return null;
        }

        return static::where('context', $context)
            ->where('legacy_id', $legacyId)
            ->first()?->mappable;
    }

    public static function exists_for(string $context, string|int|null $legacyId): bool
    {
        $legacyId = trim((string) $legacyId);

        return $legacyId !== '' && static::where('context', $context)->where('legacy_id', $legacyId)->exists();
    }
}
