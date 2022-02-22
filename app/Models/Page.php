<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use Sluggable;

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