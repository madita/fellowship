<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDetail extends Model
{
    use HasFactory;

    public function event()
    {
        return $this->belongsTo("App\\Models\\Event\Event");
    }
}
