<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use App\Services\ImageOptimizationService;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Setting up the ladder members climb with their achievement points.
 */
class RankAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $ranks = Rank::inOrder()
            ->with('translations')
            ->get()
            ->map(fn (Rank $rank) => array_merge($rank->toArray(), [
                'translations' => $this->translationsOf($rank),
                // How many members stand here right now
                'members'      => $this->membersAt($rank),
            ]));

        return response()->json([
            'data'    => $ranks,
            'locales' => Locales::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $name = $data[Locales::fallback()]['name'];

        $rank = Rank::create(array_merge($data, ['key' => $this->uniqueKey($name)]));

        return response()->json(['data' => $rank], 201);
    }

    public function update(Request $request, Rank $rank): JsonResponse
    {
        $rank->update($this->validated($request));

        return response()->json(['data' => $rank->fresh()]);
    }

    public function destroy(Rank $rank): JsonResponse
    {
        $this->removeImage($rank);
        $rank->delete();

        return response()->json(['message' => __('messages.ranks.deleted')]);
    }

    /**
     * A rank can wear a picture instead of its icon, the way a badge can.
     */
    public function uploadImage(Request $request, Rank $rank): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:2048'],
        ]);

        $file = $request->file('image');

        if (! $file || ! $file->isValid()) {
            return response()->json(['message' => __('messages.media.invalid_upload')], 422);
        }

        $this->removeImage($rank);

        $extension = strtolower($file->getClientOriginalExtension());
        $path      = 'ranks/' . uniqid('rank_') . '.' . $extension;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath() ?: $file->getPathname()));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            ImageOptimizationService::optimize(Storage::disk('public')->path($path), [
                'quality'    => 85,
                'max_width'  => 512,
                'max_height' => 512,
            ]);
        }

        $rank->update(['image_path' => $path]);

        return response()->json([
            'message' => __('messages.ranks.image_uploaded'),
            'data'    => $rank->fresh(),
        ]);
    }

    public function deleteImage(Rank $rank): JsonResponse
    {
        $this->removeImage($rank);
        $rank->update(['image_path' => null]);

        return response()->json([
            'message' => __('messages.ranks.image_removed'),
            'data'    => $rank->fresh(),
        ]);
    }

    private function removeImage(Rank $rank): void
    {
        if ($rank->image_path && Storage::disk('public')->exists($rank->image_path)) {
            Storage::disk('public')->delete($rank->image_path);
        }
    }

    /**
     * How many members have reached this rank and no higher — what the
     * ladder actually looks like in practice.
     */
    private function membersAt(Rank $rank): int
    {
        $next = Rank::enabled()
            ->where('points_required', '>', $rank->points_required)
            ->orderBy('points_required')
            ->first();

        $totals = DB::table('achievement_user')
            ->join('achievements', 'achievements.id', '=', 'achievement_user.achievement_id')
            ->groupBy('achievement_user.user_id')
            ->havingRaw('SUM(achievements.points) >= ?', [$rank->points_required]);

        if ($next) {
            $totals->havingRaw('SUM(achievements.points) < ?', [$next->points_required]);
        }

        return $totals->select('achievement_user.user_id')->get()->count();
    }

    private function translationsOf(Rank $rank): array
    {
        $wording = [];

        foreach (Locales::all() as $locale) {
            $translation = $rank->translate($locale, true);

            $wording[$locale] = [
                'name'        => $translation?->name ?? '',
                'description' => $translation?->description ?? '',
            ];
        }

        return $wording;
    }

    private function validated(Request $request): array
    {
        $locales = Locales::all();
        $default = Locales::fallback();

        $data = $request->validate([
            'translations'                 => ['required', 'array'],
            "translations.{$default}.name" => ['required', 'string', 'max:60'],
            'translations.*.name'          => ['nullable', 'string', 'max:60'],
            'translations.*.description'   => ['nullable', 'string', 'max:300'],
            'points_required' => ['required', 'integer', 'min:0', 'max:1000000'],
            'icon'            => ['nullable', 'string', 'max:60'],
            'color'           => ['nullable', 'string', 'max:30'],
            'is_enabled'      => ['nullable', 'boolean'],
        ]);

        $data['icon']  = $data['icon'] ?? null ?: 'mdi-shield-outline';
        $data['color'] = $data['color'] ?? null ?: 'blue-grey';

        foreach ($locales as $locale) {
            $name = trim((string) ($data['translations'][$locale]['name'] ?? ''));

            if ($name === '') {
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
        $base = Str::slug($name) ?: 'rank';
        $key  = $base;
        $next = 2;

        while (Rank::where('key', $key)->exists()) {
            $key = "{$base}-{$next}";
            $next++;
        }

        return $key;
    }
}
