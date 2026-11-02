<?php

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Models\Help\HelpTour;
use App\Models\Help\HelpTourStep;
use App\Support\HelpTourPresenter;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Writing the help: Admin → Settings → Help & Tours.
 *
 * A tour and its steps are saved in one request. A step editor is a list
 * that gets dragged about, renumbered and half deleted before anybody
 * presses save, so sending each change on its own would leave the order
 * wrong whenever one of them failed. What arrives is the whole list, and
 * it replaces what was there inside a transaction.
 */
class HelpTourManageController extends Controller
{
    public function index(): JsonResponse
    {
        $tours = HelpTour::inOrder()
            ->with(['translations', 'steps.translations'])
            ->get()
            ->map(fn (HelpTour $tour) => HelpTourPresenter::forEditor($tour));

        return response()->json([
            'data'    => $tours,
            'locales' => Locales::all(),
            'default' => Locales::fallback(),
            // Offered as a way to narrow who a tour is for
            'roles'   => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $tour = DB::transaction(function () use ($data) {
            $tour = HelpTour::create(array_merge(
                $data['tour'],
                ['key' => $this->uniqueKey($data['name'])],
                $data['wording']
            ));

            $this->pruneWording($tour, $data['wording']);
            $this->syncSteps($tour, $data['steps']);

            return $tour;
        });

        return response()->json([
            'data' => HelpTourPresenter::forEditor($tour->fresh(['translations', 'steps.translations'])),
        ], 201);
    }

    public function update(Request $request, HelpTour $tour): JsonResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($tour, $data) {
            $tour->update(array_merge($data['tour'], $data['wording']));
            $this->pruneWording($tour, $data['wording']);
            $this->syncSteps($tour, $data['steps']);
        });

        return response()->json([
            'data' => HelpTourPresenter::forEditor($tour->fresh(['translations', 'steps.translations'])),
        ]);
    }

    public function destroy(HelpTour $tour): JsonResponse
    {
        $tour->delete();

        return response()->json(['message' => __('messages.help.deleted')]);
    }

    /**
     * Start a new tour from an existing one — the quickest way to write
     * the German half of a tour that exists in English, or a variation of
     * one for a neighbouring page.
     */
    public function duplicate(HelpTour $tour): JsonResponse
    {
        $tour->load(['translations', 'steps.translations']);

        $copy = DB::transaction(function () use ($tour) {
            $wording = [];

            foreach ($tour->translations as $translation) {
                $wording[$translation->locale] = [
                    'title'       => $translation->title . ' (' . __('messages.help.copy') . ')',
                    'description' => $translation->description,
                ];
            }

            $copy = HelpTour::create(array_merge($tour->only([
                'route_pattern', 'mode', 'icon', 'color', 'audience', 'roles', 'show_launcher',
            ]), [
                'key' => $this->uniqueKey($tour->key),
                // A copy is nobody's finished work yet, so it neither
                // starts by itself nor shows up until somebody says so
                'auto_start' => false,
                'is_enabled' => false,
                'sort_order' => (int) HelpTour::max('sort_order') + 1,
            ], $wording));

            foreach ($tour->steps as $step) {
                $new = $copy->steps()->create($step->only(['target', 'placement', 'sort_order']));

                foreach ($step->translations as $translation) {
                    // Through the translatable relation rather than
                    // creating the row: `locale` is not a fillable
                    // attribute of a translation, so a plain create()
                    // silently drops it
                    $wording = $new->translateOrNew($translation->locale);

                    $wording->title   = $translation->title;
                    $wording->content = $translation->content;
                }

                $new->save();
            }

            return $copy;
        });

        return response()->json([
            'data' => HelpTourPresenter::forEditor($copy->fresh(['translations', 'steps.translations'])),
        ], 201);
    }

    /**
     * Replace the tour's steps with the list that arrived.
     *
     * Steps carrying an id keep it, so the rows a member has already been
     * shown are the same rows afterwards; the ones left out are gone.
     *
     * @param  array<int, array>  $steps
     */
    private function syncSteps(HelpTour $tour, array $steps): void
    {
        $kept = [];

        foreach ($steps as $position => $step) {
            $attributes = [
                'target'     => $step['target'] ?: null,
                'placement'  => $step['placement'] ?? 'auto',
                'sort_order' => $position,
            ];

            $record = isset($step['id'])
                ? $tour->steps()->whereKey($step['id'])->first()
                : null;

            if ($record) {
                $record->update($attributes);
            } else {
                $record = $tour->steps()->create($attributes);
            }

            foreach ($step['wording'] as $locale => $wording) {
                $translation = $record->translateOrNew($locale);

                $translation->title   = $wording['title'];
                $translation->content = $wording['content'];
            }

            $record->save();
            $this->pruneWording($record, $step['wording']);
            $kept[] = $record->id;
        }

        $tour->steps()->whereNotIn('id', $kept ?: [0])->delete();
    }

    /**
     * Drop the languages that were cleared.
     *
     * Saving only writes the locales that arrived, so emptying the German
     * title of a tour would otherwise leave the old German text in place
     * and the editor would look like it had ignored the change.
     *
     * @param  HelpTour|HelpTourStep  $model
     * @param  array<string, array>  $wording
     */
    private function pruneWording($model, array $wording): void
    {
        $model->translations()
            ->whereNotIn('locale', array_keys($wording) ?: [''])
            ->delete();

        // The relation still holds what was just deleted, and the next
        // read of it would hand back a translation that no longer exists
        $model->unsetRelation('translations');
    }

    /**
     * What arrived, checked and split into the three things that are done
     * with it: the tour's own columns, its wording, and its steps.
     *
     * @return array{tour: array, wording: array, steps: array, name: string}
     */
    private function validated(Request $request): array
    {
        $locales = Locales::all();
        $default = Locales::fallback();

        $rules = [
            'route_pattern' => ['required', 'string', 'max:255'],
            'mode'          => ['required', Rule::in(HelpTour::MODES)],
            'icon'          => ['nullable', 'string', 'max:60'],
            'color'         => ['nullable', 'string', 'max:30'],
            'audience'      => ['required', Rule::in(HelpTour::AUDIENCES)],
            'roles'         => ['nullable', 'array'],
            'roles.*'       => ['string', 'max:100'],
            'auto_start'    => ['nullable', 'boolean'],
            'show_launcher' => ['nullable', 'boolean'],
            'is_enabled'    => ['nullable', 'boolean'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:100000'],

            'translations'                  => ['required', 'array'],
            "translations.{$default}.title" => ['required', 'string', 'max:120'],
            'translations.*.title'          => ['nullable', 'string', 'max:120'],
            'translations.*.description'    => ['nullable', 'string', 'max:500'],

            // A tour with no steps has nothing to show
            'steps'                                => ['required', 'array', 'min:1', 'max:30'],
            'steps.*.id'                           => ['nullable', 'integer'],
            'steps.*.target'                       => ['nullable', 'string', 'max:255'],
            'steps.*.placement'                    => ['nullable', Rule::in(HelpTourStep::PLACEMENTS)],
            'steps.*.translations'                 => ['required', 'array'],
            "steps.*.translations.{$default}.title" => ['required', 'string', 'max:120'],
            'steps.*.translations.*.title'         => ['nullable', 'string', 'max:120'],
            'steps.*.translations.*.content'       => ['nullable', 'string', 'max:1000'],
        ];

        $input = $request->validate($rules);

        $tour = [
            'route_pattern' => $input['route_pattern'],
            'mode'          => $input['mode'],
            'icon'          => $input['icon'] ?? null ?: 'mdi-help-circle-outline',
            'color'         => $input['color'] ?? null ?: 'primary',
            'audience'      => $input['audience'],
            // An empty list and no list mean the same thing — everybody
            'roles'         => array_values(array_filter($input['roles'] ?? [])) ?: null,
            'auto_start'    => (bool) ($input['auto_start'] ?? false),
            'show_launcher' => (bool) ($input['show_launcher'] ?? true),
            'is_enabled'    => (bool) ($input['is_enabled'] ?? true),
            'sort_order'    => (int) ($input['sort_order'] ?? 0),
        ];

        // Only the locales actually written in are saved, so an untouched
        // language stays absent rather than becoming an empty translation
        $wording = [];

        foreach ($locales as $locale) {
            $title = trim((string) ($input['translations'][$locale]['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $wording[$locale] = [
                'title'       => $title,
                'description' => $input['translations'][$locale]['description'] ?? null,
            ];
        }

        $steps = [];

        // The validator builds its result by walking the rules rather than
        // the input, so a step carrying an `id` has its key created before
        // one that does not — and the list comes back out of order. The
        // order is the tour, so it is restored from the keys.
        $incoming = $input['steps'];
        ksort($incoming);

        foreach ($incoming as $step) {
            $stepWording = [];

            foreach ($locales as $locale) {
                $title = trim((string) ($step['translations'][$locale]['title'] ?? ''));

                if ($title === '') {
                    continue;
                }

                $stepWording[$locale] = [
                    'title'   => $title,
                    'content' => $step['translations'][$locale]['content'] ?? null,
                ];
            }

            $steps[] = [
                'id'        => $step['id'] ?? null,
                'target'    => $step['target'] ?? null,
                'placement' => $step['placement'] ?? 'auto',
                'wording'   => $stepWording,
            ];
        }

        return [
            'tour'    => $tour,
            'wording' => $wording,
            'steps'   => $steps,
            'name'    => $input['translations'][$default]['title'],
        ];
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'tour';
        $key  = $base;
        $next = 2;

        while (HelpTour::where('key', $key)->exists()) {
            $key = "{$base}-{$next}";
            $next++;
        }

        return $key;
    }
}
