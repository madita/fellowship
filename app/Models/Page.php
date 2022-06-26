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
//        'user_id',
        'parent_id',
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
        return $this->belongsTo('App\Models\User');
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    public function getParentsAttribute()
    {
        $parents = collect([]);

        $parent = $this->parent;

        while(!is_null($parent)) {
            $parents->push($parent);
            $parent = $parent->parent;
        }

        return $parents;
    }
}
