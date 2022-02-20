<?php
namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model {

    use Sluggable;

	protected $fillable = [
		'status',
		'title',
		'slug',
		'body',
		'user_id'
    ];

	protected $primaryKey = 'id';
	protected $table = 'posts';

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title'
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
