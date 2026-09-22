<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class AchievementTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['name', 'description'];
}
