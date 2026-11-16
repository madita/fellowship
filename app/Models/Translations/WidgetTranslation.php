<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class WidgetTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['title', 'content'];

    protected $casts = [
        'content' => 'array',
    ];
}
