<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class EventTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title', 'description'];
}
