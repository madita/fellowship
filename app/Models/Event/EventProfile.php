<?php

namespace App\Models\Event;

use App\Traits\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;

class EventProfile extends Model
{
    use HasTaxonomies;

    protected $fillable = [
        'name',
        'options',
        'created_at',
        'updated_at',
    ];
//    public function event()
//    {
//        return $this->belongsTo("App\\Models\\Event\EventType");
//    }

    public function creator()
    {
        return $this->belongsTo('App\\Models\\User');
    }
}
