<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Interface CanHaveTaxonomies.
 */
interface CanHaveTaxonomies
{
    public function taxonomies(): MorphToMany;
}
