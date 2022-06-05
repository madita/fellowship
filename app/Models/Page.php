<?php

namespace App\Models;

use App\Traits\HasTaxonomies;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Traits\Revisionable;

class Page extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasTaxonomies;
    use Revisionable;
    use Sluggable;

    protected $fillable = [
        'published',
        'title',
        'slug',
        'body',
        'user_id',
    ];

    protected $taxable_title = 'title';

    protected $primaryKey = 'id';
    protected $table = 'pages';

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    protected $revisionable = [
        'title',
        'slug',
        'body',
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
