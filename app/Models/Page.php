<?php

namespace App\Models;

use App\Traits\HasTaxonomies;
use App\Traits\Revisionable;
use App\Traits\Wikiable;
//use Lecturize\Taxonomies\Traits\HasCategories;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Lecturize\Taxonomies\Contracts\CanHaveCategories;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia, CanHaveCategories
{
    use InteractsWithMedia;
    use HasTaxonomies;
    use Revisionable;
    use Wikiable;
    use Sluggable;

    protected $fillable = [
        'published',
        'title',
        'slug',
        'content',
        'parent_id',
        'user_id',
        'created_at',
        'updated_at',
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

    protected $wikiable = [
        'title' => 'title',
        'slug'  => 'slug',
    ];

    protected $revisionable = [
        'title',
        'slug',
        'content',
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

        while (!is_null($parent)) {
            $parents->push($parent);
            $parent = $parent->parent;
        }

        return $parents;
    }
}
