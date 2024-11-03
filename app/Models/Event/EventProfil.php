<?php

namespace App\Models\Event;

use App\Traits\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;

class EventProfil extends Model
{

    use HasTaxonomies;

    protected $fillable = [
        'type_id',
        'user_id',
        'options',
        'created_at',
        'updated_at',
    ];
    public function event()
    {
        return $this->belongsTo("App\\Models\\Event\EventType");
    }

    public function user()
    {
        return $this->belongsTo("App\\Models\\User");
    }
}
