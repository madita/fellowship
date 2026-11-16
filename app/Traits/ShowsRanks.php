<?php

namespace App\Traits;

use App\Support\Ranks;
use Illuminate\Support\Collection;

/**
 * Showing a member's rank beside their name.
 *
 * A rank comes from the points a member holds, so it is worked out for the
 * whole page at once and then appended — asking per row would be a query
 * each. Not appended by default: most of the site serialises users without
 * wanting it.
 */
trait ShowsRanks
{
    /**
     * @param  Collection|iterable  $users
     */
    protected function attachRanks($users): void
    {
        $users = ($users instanceof Collection ? $users : collect($users))->filter();

        if ($users->isEmpty()) {
            return;
        }

        // One member can appear several times over — as the thread's author
        // and again on their posts — and each of those is its own object.
        // Look the rank up once, but append it to every one of them.
        Ranks::prime($users->pluck('id')->unique()->all());

        $users->each->append('rank');
    }
}
