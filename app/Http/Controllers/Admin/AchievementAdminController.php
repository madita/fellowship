<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AchievementProgress;
use App\Models\User;
use App\Services\AchievementService;
use App\Support\AchievementMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Setting up achievements, and handing out the ones the site cannot see.
 */
class AchievementAdminController extends Controller
{
    public function __construct(private readonly AchievementService $achievements)
    {
    }

    /**
     * Every achievement, with what an admin needs to build another: the
     * actions the site can count and how each can be narrowed.
     */
    public function index(): JsonResponse
    {
        $achievements = Achievement::inOrder()
            ->withCount('holders')
            ->get()
            ->map(fn (Achievement $achievement) => array_merge($achievement->toArray(), [
                // An achievement pointing at an action the site no longer
                // counts would never be earned — say so rather than leave
                // it looking fine
                'metric_is_known' => $achievement->metricIsKnown(),
            ]));

        return response()->json([
            'data'       => $achievements,
            'metrics'    => AchievementMetrics::forPicker(),
            'categories' => Achievement::CATEGORIES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $achievement = Achievement::create(array_merge($data, [
            'key'        => $this->uniqueKey($data['name']),
            'sort_order' => (int) Achievement::max('sort_order') + 1,
        ]));

        return response()->json(['data' => $achievement], 201);
    }

    public function update(Request $request, Achievement $achievement): JsonResponse
    {
        $achievement->update($this->validated($request));

        return response()->json(['data' => $achievement->fresh()]);
    }

    /**
     * Deleting takes it off everyone who holds it, so it is worth saying so
     * in the confirmation. Switching it off instead keeps the holders.
     */
    public function destroy(Achievement $achievement): JsonResponse
    {
        $achievement->delete();

        return response()->json(['message' => __('messages.achievements.deleted')]);
    }

    /**
     * Who holds an achievement, and when they got it.
     */
    public function holders(Achievement $achievement): JsonResponse
    {
        $holders = $achievement->holders()
            ->select(['users.id', 'users.username', 'users.name'])
            ->get()
            ->map(fn (User $user) => [
                'id'         => $user->id,
                'username'   => $user->username,
                'name'       => $user->name,
                'awarded_at' => $user->pivot->awarded_at,
                'awarded_by' => $user->pivot->awarded_by,
                'note'       => $user->pivot->note,
            ]);

        return response()->json(['data' => $holders]);
    }

    /**
     * Hand an achievement to a member — the real-world ones, where somebody
     * has to vouch that it happened.
     */
    public function award(Request $request, Achievement $achievement): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'note'    => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($data['user_id']);

        $awarded = $this->achievements->award(
            $user,
            $achievement,
            $request->user(),
            $data['note'] ?? null
        );

        return response()->json([
            'message' => $awarded
                ? __('messages.achievements.awarded', ['name' => $user->username])
                : __('messages.achievements.already_held', ['name' => $user->username]),
            'awarded' => $awarded,
        ]);
    }

    public function revoke(Request $request, Achievement $achievement): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);

        $this->achievements->revoke(User::findOrFail($data['user_id']), $achievement);

        return response()->json(['message' => __('messages.achievements.revoked')]);
    }

    /**
     * How the achievements are doing: what has been earned, what nobody has
     * managed yet, and who is collecting them.
     */
    public function stats(): JsonResponse
    {
        $awarded = DB::table('achievement_user');

        $perAchievement = Achievement::inOrder()
            ->withCount('holders')
            ->get(['id', 'name', 'icon', 'color', 'points', 'is_enabled', 'trigger']);

        $topMembers = DB::table('achievement_user')
            ->join('users', 'users.id', '=', 'achievement_user.user_id')
            ->join('achievements', 'achievements.id', '=', 'achievement_user.achievement_id')
            ->groupBy('users.id', 'users.username')
            ->orderByDesc(DB::raw('SUM(achievements.points)'))
            ->limit(10)
            ->get([
                'users.id',
                'users.username',
                DB::raw('COUNT(*) as achievements_count'),
                DB::raw('SUM(achievements.points) as points'),
            ]);

        return response()->json([
            'data' => [
                'achievements'   => Achievement::count(),
                'enabled'        => Achievement::enabled()->count(),
                'manual'         => Achievement::where('trigger', 'manual')->count(),
                'awarded'        => (clone $awarded)->count(),
                'members'        => (clone $awarded)->distinct('user_id')->count('user_id'),
                'awarded_by_hand' => (clone $awarded)->whereNotNull('awarded_by')->count(),
                'recent'         => (clone $awarded)->where('awarded_at', '>=', now()->subDays(30))->count(),
                // Nobody has managed these — either very hard, or misconfigured
                'unearned'       => $perAchievement->where('holders_count', 0)->pluck('name')->values(),
                'per_achievement' => $perAchievement,
                'top_members'    => $topMembers,
                'tracked_actions' => AchievementProgress::distinct('metric')->count('metric'),
            ],
        ]);
    }

    /**
     * The rules an achievement has to satisfy. A metric-triggered one has to
     * name an action the site actually counts, and may only be narrowed by
     * values that action supports.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon'        => ['nullable', 'string', 'max:60'],
            'color'       => ['nullable', 'string', 'max:30'],
            'category'    => ['required', Rule::in(Achievement::CATEGORIES)],
            'points'      => ['required', 'integer', 'min:0', 'max:1000'],
            'trigger'     => ['required', Rule::in(Achievement::TRIGGERS)],
            'metric'      => ['nullable', 'required_if:trigger,metric', Rule::in(AchievementMetrics::keys())],
            'threshold'   => ['required', 'integer', 'min:1', 'max:100000'],
            'filters'            => ['nullable', 'array'],
            'filters.values'     => ['nullable', 'array'],
            'filters.values.*'   => ['string', 'max:60'],
            'is_enabled'  => ['nullable', 'boolean'],
            'is_secret'   => ['nullable', 'boolean'],
        ]);

        // A narrowing only means something for an action that supports one
        if (($data['trigger'] ?? null) !== 'metric' || ! AchievementMetrics::scopeOf($data['metric'] ?? '')) {
            $data['filters'] = null;
        }

        if (($data['trigger'] ?? null) === 'manual') {
            $data['metric'] = null;
        }

        $data['icon']  = $data['icon'] ?: 'mdi-trophy-outline';
        $data['color'] = $data['color'] ?: 'amber';

        return $data;
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'achievement';
        $key  = $base;
        $next = 2;

        while (Achievement::where('key', $key)->exists()) {
            $key = "{$base}-{$next}";
            $next++;
        }

        return $key;
    }
}
