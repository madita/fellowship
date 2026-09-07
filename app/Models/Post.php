<?php

namespace App\Models;

use App\Contracts\CanHaveTaxonomies;
use App\Traits\HasCache;
use App\Traits\HasTaxonomies;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements CanHaveTaxonomies, TranslatableContract
{
    use HasCache;
    use HasTaxonomies;
    use Sluggable;
    use Translatable;

    public $translatedAttributes = ['title', 'body'];

    protected $fillable = [
        'status',
        'slug',
        'user_id',
    ];

    protected $taxable_title = 'title';

    protected $primaryKey = 'id';

    protected $table = 'posts';

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }
    //    protected $revisionable = [
    //        'title',
    //        'slug',
    //        'body',
    //    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
