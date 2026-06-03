<?php

namespace App\Models;

use App\Models\Concerns\Approvable;
use App\Models\Concerns\HasTickets;
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
    use HasTickets;
    use Approvable;

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
        'slug', 'status', 'parent_id', 'wikiable_type', 'wikiable_id',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($wiki) {
            // If creator has an auto-approve role, approve immediately (skip ticket)
            if ($wiki->shouldAutoApprove()) {
                $wiki->approve(auth()->user());
                return;
            }

            // Otherwise create approval ticket as before
            $wiki->autoCreateTicket('wiki_approval', [
                'title' => "New Wiki Page: {$wiki->title}",
                'description' => "A new wiki page has been created and needs review.",
                'priority' => 'normal',
            ]);
        });
    }

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
