<?php namespace App\Models\Tag;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Cviebrock\EloquentSluggable\Sluggable;
use App\Models\Tag\Taxonomy;


class Taxable extends Model
{

    protected $table = 'taxables';
    /**
     * @inheritdoc
     */
    protected $fillable = [
        'taxonomy_id',
        'taxable_id',
        'taxable_type'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function taxable()
    {
        return $this->morphTo();
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class, 'taxonomy_id', 'id');
    }
}
