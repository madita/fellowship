<?php

namespace App\Models\Event;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventType extends Model
{
    protected $fillable = [
        'name',
        'color',
        'options',
        'created_at',
        'updated_at',
    ];
    public function event()
    {
        return $this->belongsTo("App\\Models\\Event\Event");
    }
}
