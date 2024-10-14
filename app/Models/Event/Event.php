<?php

namespace App\Models\Event;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use Sluggable;
    use SoftDeletes;

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    public function details()
    {
        return $this->hasOne("App\\Models\\Event\EventDetail");
    }

    public function type()
    {
        return $this->hasOne("App\\Models\\Event\EventType");
    }

    public function user()
    {
        return $this->belongsTo("App\\Models\User");
    }

//    public function posts(){
//        return $this->hasMany("App\\Models\\Post")->orderBy("created_at", "desc")->with("comments")->withTrashed();
//    }

    public function getImage()
    {
        if ($this->image) {
            return '/uploads/'.$this->image;
        } else {
            return 'img/cover.jpeg';
        }
    }

//    public function hasImages(){
//        $posts = Post::where("event_id", "=", $this->id)->whereNotNull("image")->get();
//
//        return count($posts);
//    }

//    public function getImages(){
//        $posts = Post::where("event_id", "=", $this->id)->orderBy("created_at", "desc")->take(2)->get();
//        return $posts;
//    }

//    public function countImages(){
//        $posts = Post::where("event_id", "=", $this->id)->get();
//        $images = array();
//
//        foreach($posts as $post){
//            array_push($images, $post->getImage());
//        }
//
//        return count(array_filter($images));
//    }

//    public function going()
//    {
//        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', 'going');
//    }
//
//    public function notgoing()
//    {
//        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', 'notgoing');
//    }
//
//    public function maybegoing()
//    {
//        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', 'maybe');
//    }

    public function answer($answer)
    {
        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', $answer)->withPivot('approved_at');
    }


//    public function categories(){
//        return $this->belongsToMany("App\\Models\\Category");
//    }

    public function allUsers()
    {
        return $this->belongsToMany('App\\Models\\User', 'event_guests')
            ->withPivot('type')
            ->withTimestamps();
    }
}
