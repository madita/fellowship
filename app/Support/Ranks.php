<?php

namespace App\Support;

use App\Models\Rank;
use Illuminate\Support\Facades\DB;

/**
 * Where members stand on the ladder, worked out for several at once.
 *
 * A rank comes from the points a member holds, which means a sum per
 * member. Asking one at a time down a list of forum posts would be a query
 * each, so the answers are worked out in one go and remembered for the rest
 * of the request.
 */
class Ranks
{
    /** @var array<int, array|null> user id => rank shape */
    private static array $known = [];

    /**
     * Work out the rank of each of these members in one query.
     */
    public static function prime(array $userIds): void
    {
        $missing = array_values(array_diff(array_unique(array_filter($userIds)), array_keys(self::$known)));

        if (! $missing) {
            return;
        }

        $points = DB::table('achievement_user')
            ->join('achievements', 'achievements.id', '=', 'achievement_user.achievement_id')
            ->whereIn('achievement_user.user_id', $missing)
            ->where('achievements.is_enabled', true)
            ->groupBy('achievement_user.user_id')
            ->select([
                'achievement_user.user_id',
                DB::raw('SUM(achievements.points) as total'),
            ])
            ->pluck('total', 'user_id');

        $ladder = Rank::ladder();

        foreach ($missing as $userId) {
            $rank = Rank::forPoints((int) ($points[$userId] ?? 0), $ladder);

            self::$known[$userId] = $rank ? self::shape($rank) : null;
        }
    }

    /**
     * One member's rank, priming it alone if nobody asked for it in bulk.
     */
    public static function of(?int $userId): ?array
    {
        if (! $userId) {
            return null;
        }

        if (! array_key_exists($userId, self::$known)) {
            self::prime([$userId]);
        }

        return self::$known[$userId] ?? null;
    }

    /**
     * Just enough to draw the rank beside a name.
     */
    private static function shape(Rank $rank): array
    {
        return [
            'name'      => $rank->name,
            'icon'      => $rank->icon,
            'image_url' => $rank->image_url,
            'color'     => $rank->color,
        ];
    }

    /**
     * Only for tests, which build a different ladder per case.
     */
    public static function forget(): void
    {
        self::$known = [];
    }
}
