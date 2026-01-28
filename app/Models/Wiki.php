<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCache;

class Wiki extends Model
{
    use Sluggable;
    use HasCache;

    protected $table = 'wikiables';
    protected $cacheTag = 'wikiables';
//    protected $guard_name = 'api';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'status', 'parent_id',
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
