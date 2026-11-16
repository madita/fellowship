<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class RankTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['name', 'description'];
}
