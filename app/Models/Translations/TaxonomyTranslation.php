<?php

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class TaxonomyTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = ['description', 'content', 'lead', 'meta_desc'];
}
