<?php

namespace App\Support;

use App\Models\User;
use App\Services\AchievementService;

/**
 * A short way to count an action toward achievements from wherever it
 * happens, so a call site reads as one line:
 *
 *     Achievements::record($post->user, 'forum.post.created');
 *     Achievements::record($event->user, 'event.organised', $event->event_type_id);
 *
 * Recording never throws — see the service — so a call can sit inside a
 * model hook without putting the member's actual work at risk.
 */
class Achievements
{
    public static function record(?User $user, string $metric, $scope = null, int $times = 1): void
    {
        if ( ! $user) {
            return;
        }

        app(AchievementService::class)->record($user, $metric, $scope === null ? null : (string) $scope, $times);
    }
}
