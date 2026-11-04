<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailCategory;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The kinds of mail, which are what a member switches on and off.
 *
 * An install can invent its own - a club might want one for the annual
 * meeting - and the name and the reason are translated, because they are
 * read by members rather than by admins.
 *
 * Two of the columns are deliberately not editable from here:
 *
 *   `key` cannot change on a category the code names, for the same
 *   reason a system template's cannot.
 *
 *   `is_mandatory` cannot be set at all. It marks the mail that gets
 *   somebody back into their account, and an admin who could tick it
 *   could make the newsletter unrefusable.
 */
class MailCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = MailCategory::with('translations')
            ->withCount(['templates', 'mailings'])
            ->ordered()
            ->get();

        return response()->json([
            'data'    => $categories->map(fn (MailCategory $category) => $this->present($category)),
            'locales' => Locales::all(),
            'default' => Locales::fallback(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $category = DB::transaction(function () use ($data) {
            $category = MailCategory::create(array_merge($data['category'], [
                'key'          => $this->uniqueKey($data['wording'][Locales::fallback()]['name'] ?? 'category'),
                'is_mandatory' => false,
                'sort_order'   => (int) MailCategory::max('sort_order') + 1,
            ], $data['wording']));

            $this->pruneWording($category, $data['wording']);

            return $category;
        });

        return response()->json([
            'message' => __('messages.mailings.category_saved'),
            'data'    => $this->present($category->fresh('translations')),
        ], 201);
    }

    public function update(Request $request, MailCategory $category): JsonResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($category, $data) {
            $category->update(array_merge($data['category'], $data['wording']));
            $this->pruneWording($category, $data['wording']);
        });

        return response()->json([
            'message' => __('messages.mailings.category_saved'),
            'data'    => $this->present($category->fresh('translations')),
        ]);
    }

    public function destroy(MailCategory $category): JsonResponse
    {
        if ($category->is_mandatory) {
            return response()->json(['message' => __('messages.mailings.category_locked')], 422);
        }

        // Its templates would go with it, and one of those may be a mail
        // the code sends. Moving them first is a decision for a person.
        if ($category->templates()->where('is_system', true)->exists()) {
            return response()->json(['message' => __('messages.mailings.category_in_use')], 422);
        }

        $category->delete();

        return response()->json(['message' => __('messages.mailings.category_deleted')]);
    }

    /**
     * The order the preferences page lists them in, as the whole list.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:mail_categories,id'],
        ]);

        foreach (array_values($validated['ids']) as $order => $id) {
            MailCategory::whereKey($id)->update(['sort_order' => $order]);
        }

        return response()->json(['message' => __('messages.mailings.reordered')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MailCategory $category): array
    {
        $translations = [];

        foreach ($category->translations as $translation) {
            $translations[$translation->locale] = [
                'name'        => $translation->name,
                'description' => $translation->description,
            ];
        }

        return [
            'id'              => $category->id,
            'key'             => $category->key,
            'name'            => $category->name,
            'description'     => $category->description,
            'icon'            => $category->icon,
            'color'           => $category->color,
            'is_mandatory'    => $category->is_mandatory,
            'default_opt_in'  => $category->default_opt_in,
            'sort_order'      => $category->sort_order,
            'templates_count' => $category->templates_count ?? null,
            'mailings_count'  => $category->mailings_count ?? null,
            'translations'    => $translations,
        ];
    }

    /**
     * @param  array<string, array<string, string|null>>  $wording
     */
    private function pruneWording(MailCategory $category, array $wording): void
    {
        $category->translations()
            ->whereNotIn('locale', array_keys($wording) ?: [''])
            ->delete();

        $category->unsetRelation('translations');
    }

    /**
     * @return array{category: array<string, mixed>, wording: array<string, array<string, string|null>>}
     */
    private function validated(Request $request): array
    {
        $locales = Locales::all();
        $default = Locales::fallback();

        $input = $request->validate([
            'icon'           => ['nullable', 'string', 'max:60'],
            'color'          => ['nullable', 'string', 'max:30'],
            'default_opt_in' => ['nullable', 'boolean'],

            'translations'                        => ['required', 'array'],
            "translations.{$default}.name"        => ['required', 'string', 'max:120'],
            'translations.*.name'                 => ['nullable', 'string', 'max:120'],
            'translations.*.description'          => ['nullable', 'string', 'max:500'],
        ]);

        $category = [
            'icon'           => $input['icon'] ?? null ?: 'mdi-email-outline',
            'color'          => $input['color'] ?? null ?: 'primary',
            'default_opt_in' => (bool) ($input['default_opt_in'] ?? true),
        ];

        $wording = [];

        foreach ($locales as $locale) {
            $name = trim((string) ($input['translations'][$locale]['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $wording[$locale] = [
                'name'        => $name,
                'description' => $input['translations'][$locale]['description'] ?? null,
            ];
        }

        return ['category' => $category, 'wording' => $wording];
    }

    private function uniqueKey(string $wanted): string
    {
        $base = Str::slug($wanted ?: 'category', '_') ?: 'category';
        $key  = $base;
        $n    = 2;

        while (MailCategory::where('key', $key)->exists()) {
            $key = $base . '_' . $n++;
        }

        return $key;
    }
}
