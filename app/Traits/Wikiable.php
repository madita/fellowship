<?php

namespace App\Traits;

use App\Models\Wiki;

trait Wikiable
{
    public function wikiable()
    {
        return $this->morphMany(Wiki::class, 'wikiable');
    }

//    public function getWikiTitle()
//    {
//        return property_exists($this, 'wikiable') ? $this->wikiable['title'] : null;
//    }
}
