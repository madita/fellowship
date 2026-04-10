<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class WikiTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title'];
}
