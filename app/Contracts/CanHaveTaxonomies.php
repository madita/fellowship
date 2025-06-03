<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Interface CanHaveTaxonomies.
 *
 * @package App\Contracts
 */
interface CanHaveTaxonomies
{
    /** @return MorphToMany */
    public function taxonomies(): MorphToMany;
}
