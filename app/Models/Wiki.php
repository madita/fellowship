<?php

namespace App\Models;

use App\Traits\HasCache;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Wiki extends Model implements TranslatableContract
{
    use Sluggable;
    use HasCache;
    use Translatable;

    protected $table = 'wikiables';
    protected $cacheTag = 'wikiables';
//    protected $guard_name = 'api';

    public $translatedAttributes = ['title'];
    public $translationForeignKey = 'wiki_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug', 'status', 'parent_id',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Wiki::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Wiki::class, 'parent_id');
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
//
//    public function wikiable() {
//        return $this->morphTo();
//    }
}
