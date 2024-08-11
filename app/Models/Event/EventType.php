<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    public function event()
    {
        return $this->belongsTo("App\\Models\\Event\Event");
    }
}
