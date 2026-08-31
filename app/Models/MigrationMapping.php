<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationMapping extends Model
{
    protected $fillable = [
        'migration_source_id',
        'name',
        'target',
        'source_table',
        'field_map',
        'options',
    ];

    protected $casts = [
        'field_map' => 'array',
        'options' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(MigrationSource::class, 'migration_source_id');
    }
}
