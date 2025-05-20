<?php

namespace App\Models\Event;

use App\Traits\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventGuest extends Model
{
    use HasTaxonomies;
    use SoftDeletes;

    protected $fillable = [
        'type_id',
        'user_id',
        'event_id',
        'type',
        'options',
        'approved_at',
        'created_at',
        'updated_at',
    ];

    public function event()
    {
        return $this->belongsTo("App\\Models\\Event\Event");
    }

    public function user()
    {
        return $this->belongsTo('App\\Models\\User');
    }
}
