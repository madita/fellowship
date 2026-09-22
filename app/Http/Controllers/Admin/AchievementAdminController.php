<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AchievementProgress;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\ImageOptimizationService;
use App\Support\AchievementMetrics;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            ->with(['type.term', 'translations'])
            ->withCount('holders')
            ->get()
            ->map(fn (Achievement $achievement) => array_merge($achievement->toArray(), [
                // An achievement pointing at an action the site no longer
                // counts would never be earned — say so rather than leave
                // it looking fine
                'metric_is_known' => $achievement->metricIsKnown(),
                'type'            => $achievement->typeName(),
                // Every locale's wording, so the editor can show them all
                'translations'    => $this->translationsOf($achievement),
            ]));

        return response()->json([
            'data'    => $achievements,
            'metrics' => AchievementMetrics::forPicker(),
            'types'   => $this->types(),
            'locales' => Locales::all(),
        ]);
    }

    /**
     * The kinds of achievement, as a taxonomy so admins can add one.
     */
    private function types(): array
    {
        return Taxonomy::where('taxonomy', Achievement::TYPE_TAXONOMY)
            ->with('term')
            ->orderBy('sort')
            ->get()
            ->map(fn (Taxonomy $taxonomy) => [
                'id'   => $taxonomy->id,
                'name' => $taxonomy->term?->title,
                'slug' => $taxonomy->term?->slug,
            ])
            ->all();
    }

    /**
     * What the achievement is called in each locale, blank where it has
     * not been written yet.
     */
    private function translationsOf(Achievement $achievement): array
    {
        $wording = [];

        foreach (Locales::all() as $locale) {
            $translation = $achievement->translate($locale, true);

            $wording[$locale] = [
                'name'        => $translation?->name ?? '',
                'description' => $translation?->description ?? '',
            ];
        }

        return $wording;
    }

    /**
     * Add a kind of achievement. The name goes on a term, so it is
     * translated like every other taxonomy on the site.
     */
    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $term = Term::firstOrCreateByTitle($data['name']);

        $taxonomy = Taxonomy::firstOrCreate(
            ['term_id' => $term->id, 'taxonomy' => Achievement::TYPE_TAXONOMY],
            ['sort' => 0, 'visible' => true, 'searchable' => false]
        );

        return response()->json([
            'data' => ['id' => $taxonomy->id, 'name' => $term->title, 'slug' => $term->slug],
        ], 201);
    }

    /**
     * Remove a kind. The achievements that carried it keep everything else
     * and simply have no kind until one is picked.
     */
    public function destroyType(Taxonomy $taxonomy): JsonResponse
    {
        if ($taxonomy->taxonomy !== Achievement::TYPE_TAXONOMY) {
            return response()->json(['message' => __('messages.error.validation')], 422);
        }

        Achievement::where('taxonomy_id', $taxonomy->id)->update(['taxonomy_id' => null]);
        $taxonomy->delete();

        return response()->json(['message' => __('messages.achievements.type_deleted')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        // The key reads from the fallback locale's name, which validation
        // has already insisted on
        $default = config('app.fallback_locale', 'en');

        $achievement = Achievement::create(array_merge($data, [
            'key'        => $this->uniqueKey($data[$default]['name']),
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
        // Take its picture with it rather than leaving the file orphaned
        $this->removeBadgeFile($achievement);
        $achievement->delete();

        return response()->json(['message' => __('messages.achievements.deleted')]);
    }

    /**
     * Give a badge a picture of its own instead of one of the built-in
     * icons. The icon stays on the record as the fallback, so removing the
     * picture later leaves the achievement with a look.
     */
    public function uploadBadge(Request $request, Achievement $achievement): JsonResponse
    {
        $request->validate([
            // Square-ish artwork at a modest size; svg is allowed because a
            // drawn badge is exactly the case for it
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:2048'],
        ]);

        $file = $request->file('image');

        if (! $file || ! $file->isValid()) {
            return response()->json(['message' => __('messages.media.invalid_upload')], 422);
        }

        $this->removeBadgeFile($achievement);

        $extension = strtolower($file->getClientOriginalExtension());
        $path      = 'achievements/' . uniqid('badge_') . '.' . $extension;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath() ?: $file->getPathname()));

        // A badge is never shown large, so there is no reason to keep a
        // full-size photograph around
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            ImageOptimizationService::optimize(Storage::disk('public')->path($path), [
                'quality'    => 85,
                'max_width'  => 512,
                'max_height' => 512,
            ]);
        }

        $achievement->update(['image_path' => $path]);

        return response()->json([
            'message' => __('messages.achievements.badge_uploaded'),
            'data'    => $achievement->fresh(),
        ]);
    }

    /**
     * Drop the picture and fall back to the icon.
     */
    public function deleteBadge(Achievement $achievement): JsonResponse
    {
        $this->removeBadgeFile($achievement);
        $achievement->update(['image_path' => null]);

        return response()->json([
            'message' => __('messages.achievements.badge_removed'),
            'data'    => $achievement->fresh(),
        ]);
    }

    /**
     * Take the stored file with it, so replacing a badge does not leave the
     * old one behind on disk.
     */
    private function removeBadgeFile(Achievement $achievement): void
    {
        if ($achievement->image_path && Storage::disk('public')->exists($achievement->image_path)) {
            Storage::disk('public')->delete($achievement->image_path);
        }
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
        $locales = Locales::all();
        $default = config('app.fallback_locale', 'en');

        $data = $request->validate([
            // The wording per locale; only the fallback has to be written,
            // the rest fall back to it
            'translations'                    => ['required', 'array'],
            "translations.{$default}.name"    => ['required', 'string', 'max:80'],
            'translations.*.name'             => ['nullable', 'string', 'max:80'],
            'translations.*.description'      => ['nullable', 'string', 'max:500'],
            'icon'        => ['nullable', 'string', 'max:60'],
            'color'       => ['nullable', 'string', 'max:30'],
            'taxonomy_id' => [
                'nullable',
                Rule::exists('taxonomies', 'id')->where('taxonomy', Achievement::TYPE_TAXONOMY),
            ],
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

        // Both are optional, so they may not be in the payload at all
        $data['icon']  = $data['icon'] ?? null ?: 'mdi-trophy-outline';
        $data['color'] = $data['color'] ?? null ?: 'amber';

        // Astrotomic takes the wording keyed by locale, so the payload is
        // flattened into what it expects and a locale left blank is dropped
        // rather than saved as an empty name.
        foreach ($locales as $locale) {
            $name = trim((string) ($data['translations'][$locale]['name'] ?? ''));

            if ($name === '') {
                unset($data['translations'][$locale]);
                continue;
            }

            $data[$locale] = [
                'name'        => $name,
                'description' => $data['translations'][$locale]['description'] ?? null,
            ];
        }

        unset($data['translations']);

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
