<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class PageTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title', 'content'];
}
