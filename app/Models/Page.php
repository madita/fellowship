<?php

namespace App\Models;

use App\Contracts\CanHaveTaxonomies;
use App\Models\Concerns\HasPolls;
use App\Traits\HasCache;
use App\Traits\HasTaxonomies;
use App\Traits\Revisionable;
//use Lecturize\Taxonomies\Traits\HasCategories;
use App\Traits\Wikiable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia, CanHaveTaxonomies, TranslatableContract
{
    use InteractsWithMedia;
    use HasTaxonomies;
    use Revisionable;
    use Wikiable;
    use Sluggable;
    use HasCache;
    use Translatable;
    use HasPolls;

    public $translatedAttributes = ['title', 'content'];

    protected $fillable = [
        'title',
        'published',
        'slug',
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
