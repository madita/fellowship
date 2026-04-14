<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class HomepageMenuItemTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['label'];
}
