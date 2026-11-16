<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class MailCategoryTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['name', 'description'];
}
