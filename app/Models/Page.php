<?php

namespace App\Models;

use App\Traits\HasTaxonomies;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Venturecraft\Revisionable\RevisionableTrait;

class Page extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasTaxonomies;
    use RevisionableTrait;
    use Sluggable;

    protected $revisionEnabled = true;
    protected $revisionCreationsEnabled = true;

    protected $fillable = [
        'published',
        'title',
        'slug',
        'body',
        'user_id',
    ];

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

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
