<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    protected $fillable = [
        'name',
        'color',
        'options',
        'event_profile_id',
        'created_at',
        'updated_at',
    ];
    //    public function event()
    //    {
    //        return $this->belongsTo("App\\Models\\Event\Event");
    //    }

    public function event()
    {
        return $this->belongsTo("App\\Models\\Event\EventProfile");
    }
}
