<?php

namespace App\Models\Tag;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasCache;

class Taxable extends Model
{
    use HasCache;

    protected $table = 'taxables';
    protected $cacheTag = 'taxables';
    /**
     * @inheritdoc
     */
    protected $fillable = [
        'taxonomy_id',
        'taxable_id',
        'taxable_type',
    ];

    /**
     * @return MorphTo
     */
    public function taxable()
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo
     */
    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class, 'taxonomy_id', 'id');
    }
}
