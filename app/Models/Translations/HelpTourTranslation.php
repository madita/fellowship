<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class HelpTourTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title', 'description'];
}
