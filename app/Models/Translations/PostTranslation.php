<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class PostTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title', 'body'];
}
