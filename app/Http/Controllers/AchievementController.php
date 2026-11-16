<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Rank;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * What members see: their own achievements, someone else's, and the list of
 * everything there is to earn.
 */
class AchievementController extends Controller
{
    public function __construct(private readonly AchievementService $achievements)
    {
    }

    /**
     * Every achievement with where the signed-in member stands on each.
     */
    public function index(): JsonResponse
    {
        $user   = Auth::user();
        $points = $user->achievementPoints();

        return response()->json([
            'data'   => $this->achievements->overviewFor($user),
            'points' => $points,
            'rank'   => $this->standing($points),
        ]);
    }

    /**
     * The whole ladder, so members can see what is ahead of them.
     */
    public function ranks(): JsonResponse
    {
        $points = Auth::check() ? Auth::user()->achievementPoints() : 0;

        return response()->json([
            'data'   => Rank::ladder()->map(fn (Rank $rank) => array_merge($this->rankShape($rank), [
                'reached' => $points >= $rank->points_required,
            ])),
            'points' => $points,
        ]);
    }

    /**
     * A member's badge case, as anyone may see it. Only what they have
     * earned — the locked ones are their own business.
     */
    public function forUser(User $user): JsonResponse
    {
        $earned = $user->achievements()->with('type.term')->get()->map(fn (Achievement $achievement) => [
            'id'          => $achievement->id,
            'key'         => $achievement->key,
            'name'        => $achievement->name,
            'description' => $achievement->description,
            'icon'        => $achievement->icon,
            'image_url'   => $achievement->image_url,
            'color'       => $achievement->color,
            'type'        => $achievement->typeName(),
            'points'      => $achievement->points,
            'awarded_at'  => $achievement->pivot->awarded_at,
        ]);

        return response()->json([
            'data'   => $earned,
            'points' => (int) $earned->sum('points'),
            'user'   => ['id' => $user->id, 'username' => $user->username],
            'rank'   => $user->rank() ? $this->rankShape($user->rank()) : null,
        ]);
    }

    /**
     * The members with the most points. Shown on the achievements page so
     * the list has something to aim at.
     */
    public function leaderboard(): JsonResponse
    {
        $leaders = DB::table('achievement_user')
            ->join('users', 'users.id', '=', 'achievement_user.user_id')
            ->join('achievements', 'achievements.id', '=', 'achievement_user.achievement_id')
            ->where('achievements.is_enabled', true)
            ->groupBy('users.id', 'users.username')
            ->orderByDesc(DB::raw('SUM(achievements.points)'))
            ->limit(20)
            ->get([
                'users.id',
                'users.username',
                DB::raw('COUNT(*) as achievements_count'),
                DB::raw('SUM(achievements.points) as points'),
            ]);

        // The ladder is read once and each row placed against it, rather
        // than asked per member
        $ladder = Rank::ladder();

        return response()->json([
            'data' => $leaders->map(function ($leader) use ($ladder) {
                $rank = Rank::forPoints((int) $leader->points, $ladder);

                $leader->rank       = $rank?->name;
                $leader->rank_icon  = $rank?->icon;
                $leader->rank_color = $rank?->color;

                return $leader;
            }),
        ]);
    }

    /**
     * The dashboard widget: what was earned lately and what is nearly there.
     */
    public function summary(): JsonResponse
    {
        $user      = Auth::user();
        $overview  = collect($this->achievements->overviewFor($user));
        $earned    = $overview->where('earned', true);
        $points    = $user->achievementPoints();

        return response()->json([
            'data' => [
                'points' => $points,
                'rank'   => $this->standing($points),
                'earned' => $earned->count(),
                'total'  => $overview->count(),
                'recent' => $earned->sortByDesc('awarded_at')->take(4)->values(),
                // The ones worth another push: started, not finished
                'closest' => $overview
                    ->where('earned', false)
                    ->where('trigger', 'metric')
                    ->filter(fn ($a) => $a['progress'] > 0)
                    ->sortByDesc(fn ($a) => $a['progress'] / max($a['threshold'], 1))
                    ->take(3)
                    ->values(),
            ],
        ]);
    }

    /**
     * Where a member stands on the ladder: the rank their points have
     * reached, the next one up, and how far along they are between them.
     */
    private function standing(int $points): array
    {
        $ladder = Rank::ladder();
        $now    = Rank::forPoints($points, $ladder);
        $next   = Rank::nextAfter($points, $ladder);

        // The stretch between the two, so a bar can show the climb rather
        // than progress from zero every time
        $from    = $now?->points_required ?? 0;
        $toGo    = $next ? max($next->points_required - $from, 1) : 0;
        $covered = $next ? max($points - $from, 0) : 0;

        return [
            'current' => $now ? $this->rankShape($now) : null,
            'next'    => $next ? $this->rankShape($next) : null,
            'to_next' => $next ? max($next->points_required - $points, 0) : null,
            'percent' => $next ? (int) round($covered / $toGo * 100) : 100,
        ];
    }

    private function rankShape(Rank $rank): array
    {
        return [
            'id'              => $rank->id,
            'key'             => $rank->key,
            'name'            => $rank->name,
            'description'     => $rank->description,
            'icon'            => $rank->icon,
            'image_url'       => $rank->image_url,
            'color'           => $rank->color,
            'points_required' => $rank->points_required,
        ];
    }
}
