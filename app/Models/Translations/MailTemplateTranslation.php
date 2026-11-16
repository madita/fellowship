<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class MailTemplateTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['subject', 'body'];
}
