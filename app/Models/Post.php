<?php

namespace App\Models;

use App\Contracts\CanHaveTaxonomies;
use App\Traits\HasCache;
use App\Traits\HasRelateableContent;
use App\Traits\HasTaxonomies;
use App\Traits\Publishable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements CanHaveTaxonomies, TranslatableContract
{
    use HasCache;
    use HasRelateableContent;
    use HasTaxonomies;
    use Publishable;
    use Sluggable;
    use Translatable;

    public $translatedAttributes = ['title', 'body'];

    protected $fillable = [
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
