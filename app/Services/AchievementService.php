<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\AchievementProgress;
use App\Models\Rank;
use App\Models\User;
use App\Notifications\AchievementEarned;
use App\Notifications\RankReached;
use App\Support\AchievementMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recording what members do, and handing out what they have earned.
 *
 * Everything that counts toward an achievement comes through record(): it
 * adds to the member's tally for that action and then checks whether any
 * achievement watching it has just been reached. Recording must never be
 * able to break the thing that triggered it — someone writing a forum post
 * should not see an error because an achievement misbehaved — so failures
 * here are logged and swallowed.
 */
class AchievementService
{
    /**
     * Count one action for a member.
     *
     * $scope narrows it where the metric supports that: the id of the event
     * type, say. Both the narrowed tally and the plain total are kept, so an
     * achievement can ask for either.
     */
    public function record(?User $user, string $metric, ?string $scope = null, int $times = 1): void
    {
        if (! $user || $times < 1 || ! AchievementMetrics::exists($metric)) {
            return;
        }

        try {
            $this->bump($user, $metric, '', $times);

            if ($scope !== null && $scope !== '' && AchievementMetrics::scopeOf($metric)) {
                $this->bump($user, $metric, (string) $scope, $times);
            }

            $this->awardEarned($user, $metric);
        } catch (\Throwable $e) {
            // An achievement must never break what the member was doing
            Log::warning('[achievements] could not record ' . $metric, [
                'user'  => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add to one tally, creating it the first time.
     */
    private function bump(User $user, string $metric, string $scope, int $times): void
    {
        $progress = AchievementProgress::firstOrNew([
            'user_id' => $user->id,
            'metric'  => $metric,
            'scope'   => $scope,
        ]);

        $progress->count   = (int) $progress->count + $times;
        $progress->last_at = now();
        $progress->save();
    }

    /**
     * Award every achievement watching this action that the member has now
     * reached and does not already hold.
     */
    private function awardEarned(User $user, string $metric): void
    {
        $candidates = Achievement::forMetric($metric)->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $held = $user->achievements()->pluck('achievements.id')->all();

        foreach ($candidates as $achievement) {
            if (in_array($achievement->id, $held, true)) {
                continue;
            }

            $progress = $achievement->progressFor($user);

            if ($progress >= $achievement->threshold) {
                $this->award($user, $achievement, null, null, $progress);
            }
        }
    }

    /**
     * Give a member an achievement.
     *
     * Used both by the counting above and by an admin awarding one by hand;
     * $awardedBy separates the two. Awarding twice is not an error — the
     * member simply keeps the one they have.
     */
    public function award(
        User $user,
        Achievement $achievement,
        ?User $awardedBy = null,
        ?string $note = null,
        int $countAtAward = 0
    ): bool {
        $inserted = false;
        // Where they stood before, so a rank crossed by this award can be
        // told apart from one they already held
        $rankBefore = $user->rank();

        DB::transaction(function () use ($user, $achievement, $awardedBy, $note, $countAtAward, &$inserted) {
            $already = $user->achievements()
                ->where('achievements.id', $achievement->id)
                ->lockForUpdate()
                ->exists();

            if ($already) {
                return;
            }

            $user->achievements()->attach($achievement->id, [
                'awarded_at'     => now(),
                'awarded_by'     => $awardedBy?->id,
                'note'           => $note,
                'count_at_award' => $countAtAward,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $inserted = true;
        });

        if ($inserted) {
            $user->notify(new AchievementEarned($achievement, $awardedBy, $note));
            $this->announceRank($user, $rankBefore);
        }

        return $inserted;
    }

    /**
     * Tell a member when the points they just gained carried them into a
     * rank they had not reached before. Losing a rank — an award taken
     * back — passes in silence; there is nothing kind to say about it.
     */
    private function announceRank(User $user, ?Rank $before): void
    {
        $points = $user->achievementPoints();
        $now    = Rank::forPoints($points);

        if (! $now || $now->id === $before?->id) {
            return;
        }

        // Only upward: a threshold edited downward should not congratulate
        // somebody for standing still
        if ($before && $now->points_required <= $before->points_required) {
            return;
        }

        $user->notify(new RankReached($now, $points));
    }

    /**
     * Take an achievement back — a mistaken award, or one given to the
     * wrong member.
     */
    public function revoke(User $user, Achievement $achievement): bool
    {
        return $user->achievements()->detach($achievement->id) > 0;
    }

    /**
     * Every achievement a member can see, with where they stand on each.
     * Secret ones stay hidden until earned.
     */
    public function overviewFor(User $user): array
    {
        $earned = $user->achievements()
            ->withPivot(['awarded_at', 'note', 'awarded_by', 'count_at_award'])
            ->get()
            ->keyBy('id');

        return Achievement::enabled()->with('type.term')->inOrder()->get()
            ->filter(fn (Achievement $a) => ! $a->is_secret || $earned->has($a->id))
            ->map(function (Achievement $achievement) use ($earned, $user) {
                $held = $earned->get($achievement->id);

                return [
                    'id'          => $achievement->id,
                    'key'         => $achievement->key,
                    'name'        => $achievement->name,
                    'description' => $achievement->description,
                    'icon'        => $achievement->icon,
                    'image_url'   => $achievement->image_url,
                    'color'       => $achievement->color,
                    'type'        => $achievement->typeName(),
                    'type_id'     => $achievement->taxonomy_id,
                    'points'      => $achievement->points,
                    'trigger'     => $achievement->trigger,
                    'threshold'   => $achievement->threshold,
                    'earned'      => $held !== null,
                    'awarded_at'  => $held?->pivot?->awarded_at,
                    'note'        => $held?->pivot?->note,
                    // Manual ones have nothing to count toward
                    'progress'    => $held ? $achievement->threshold : $achievement->progressFor($user),
                ];
            })
            ->values()
            ->all();
    }
}
