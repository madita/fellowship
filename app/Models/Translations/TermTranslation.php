<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class TermTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title', 'content', 'lead'];
}
