<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class CollectionTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['name'];
}
