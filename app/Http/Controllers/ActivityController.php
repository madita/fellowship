<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Activities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    /**
     * What has been happening, newest first.
     *
     * Two things this endpoint used to do and no longer does. It eager
     * loaded the whole subject model and serialised it, so an anonymous
     * request came back with entire forum post bodies — including threads
     * in private categories — and nothing on the frontend ever read them.
     * And it applied no visibility rule at all, so activity about things
     * the reader cannot open was listed with its title.
     *
     * It is still open to guests, because the forum and the wiki are, but
     * each entry is now checked against what the reader may see and
     * flattened to what a feed row actually needs.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'log'        => ['nullable', 'string', Rule::in(Activities::logs())],
            // A short name, not a class — see Activities::subjects()
            'subject'    => ['nullable', 'string', Rule::in(array_keys(Activities::subjects()))],
            'subject_id' => ['nullable', 'integer'],
            'causer_id'  => ['nullable', 'integer'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ]);

        /** @var User|null $viewer */
        $viewer = $request->user();

        $perPage = (int) ($validated['per_page'] ?? self::PER_PAGE);

        $query = Activity::query()
            // The subject is loaded because the visibility pass needs it,
            // never to be serialised — present() builds the row by hand
            ->with(['causer:id,username', 'subject'])
            ->when($validated['log'] ?? null, fn ($q, $log) => $q->where('log_name', $log))
            ->when($validated['causer_id'] ?? null, fn ($q, $id) => $q->where('causer_id', $id))
            ->when($validated['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when(
                Activities::subjectClass($validated['subject'] ?? null),
                fn ($q, $class) => $q->where('subject_type', $class)
            )
            ->orderByDesc('created_at');

        // Over-fetched because the visibility pass below drops rows, and a
        // page of twenty should still come back with twenty where it can
        $page = $query->paginate($perPage * 2, ['*'], 'page', (int) $request->query('page', 1));

        $rows = $page->getCollection()
            ->filter(fn (Activity $activity) => $this->visibleTo($activity, $viewer))
            ->take($perPage)
            ->map(fn (Activity $activity) => $this->present($activity))
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'has_more'     => $page->hasMorePages(),
            ],
        ]);
    }

    /**
     * What a feed row needs, and nothing else.
     *
     * The subject is reduced to the title and url its properties already
     * carry — see Activities::record() — rather than being serialised.
     */
    private function present(Activity $activity): array
    {
        $properties = $activity->properties ?? collect();

        return [
            'id'          => $activity->id,
            'log'         => $activity->log_name,
            'event'       => $activity->event,
            // The English fallback, for an event the frontend has no
            // wording for yet
            'description' => $activity->description,
            'title'       => $properties['title'] ?? null,
            'url'         => $properties['url'] ?? null,
            'properties'  => $properties,
            'causer'      => $activity->causer ? [
                'id'       => $activity->causer->id,
                'username' => $activity->causer->username,
            ] : null,
            'created_at'  => $activity->created_at,
        ];
    }

    /**
     * Whether the reader may know this happened.
     *
     * The models that restrict themselves already say so — the watch and
     * comment features ask them the same question — so this defers to
     * isVisibleTo() wherever a subject defines it. A subject that has been
     * deleted since is dropped: its title is no business of anyone's.
     */
    private function visibleTo(Activity $activity, ?User $viewer): bool
    {
        $subject = $activity->subject;

        if ( ! $subject) {
            return false;
        }

        if (method_exists($subject, 'isVisibleTo')) {
            return (bool) $subject->isVisibleTo($viewer);
        }

        return true;
    }
}
