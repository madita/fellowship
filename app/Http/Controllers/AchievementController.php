<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
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
        $user = Auth::user();

        return response()->json([
            'data'   => $this->achievements->overviewFor($user),
            'points' => $user->achievementPoints(),
        ]);
    }

    /**
     * A member's badge case, as anyone may see it. Only what they have
     * earned — the locked ones are their own business.
     */
    public function forUser(User $user): JsonResponse
    {
        $earned = $user->achievements()->get()->map(fn (Achievement $achievement) => [
            'id'          => $achievement->id,
            'key'         => $achievement->key,
            'name'        => $achievement->name,
            'description' => $achievement->description,
            'icon'        => $achievement->icon,
            'color'       => $achievement->color,
            'category'    => $achievement->category,
            'points'      => $achievement->points,
            'awarded_at'  => $achievement->pivot->awarded_at,
        ]);

        return response()->json([
            'data'   => $earned,
            'points' => (int) $earned->sum('points'),
            'user'   => ['id' => $user->id, 'username' => $user->username],
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

        return response()->json(['data' => $leaders]);
    }

    /**
     * The dashboard widget: what was earned lately and what is nearly there.
     */
    public function summary(): JsonResponse
    {
        $user      = Auth::user();
        $overview  = collect($this->achievements->overviewFor($user));
        $earned    = $overview->where('earned', true);

        return response()->json([
            'data' => [
                'points' => $user->achievementPoints(),
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
}
