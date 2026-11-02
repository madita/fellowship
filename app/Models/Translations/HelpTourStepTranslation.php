<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class HelpTourStepTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title', 'content'];
}
