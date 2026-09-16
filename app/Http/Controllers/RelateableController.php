<?php

namespace App\Http\Controllers;

use App\Models\Relateable;
use App\Models\User;
use App\Support\RelateableHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Related content: links between wiki pages, pages, posts, events and albums.
 * Reads are public (unpublished content stays hidden from non-editors);
 * writes need an editor or the author of the source item.
 */
class RelateableController extends Controller
{
    public function kinds(): JsonResponse
    {
        return response()->json(['data' => RelateableHelper::kinds()]);
    }

    public function items(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind'         => ['required', 'string', Rule::in(RelateableHelper::kindKeys())],
            'search'       => 'nullable|string|max:255',
            'limit'        => 'nullable|integer|min:1',
            'exclude_type' => 'nullable|string|max:255',
            'exclude_id'   => 'nullable|integer',
        ]);

        $items = RelateableHelper::search(
            $validated['kind'],
            $validated['search'] ?? null,
            (int) ($validated['limit'] ?? 20),
            $this->viewer($request),
            $validated['exclude_type'] ?? null,
            $validated['exclude_id'] ?? null,
        );

        return response()->json(['data' => $items]);
    }

    public function related(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(RelateableHelper::types())],
            'id'   => 'required|integer',
        ]);

        $viewer = $this->viewer($request);
        $model  = RelateableHelper::find($validated['type'], $validated['id']);

        if ( ! $model || ! RelateableHelper::isVisible($model, $viewer)) {
            return response()->json(['message' => __('messages.common.item_not_found')], 404);
        }

        return response()->json(['data' => $this->summariesFor($model, $viewer)]);
    }

    public function store(Request $request): JsonResponse
    {
        $types     = RelateableHelper::types();
        $validated = $request->validate([
            'source_type'  => ['required', 'string', Rule::in($types)],
            'source_id'    => 'required|integer',
            'items'        => 'required|array|min:1|max:20',
            'items.*.type' => ['required', 'string', Rule::in($types)],
            'items.*.id'   => 'required|integer',
        ]);

        $user   = $request->user();
        $source = $this->findSourceOrFail($validated['source_type'], $validated['source_id']);
        $this->authorizeSource($source, $user);

        $targets = [];
        foreach ($validated['items'] as $index => $item) {
            if ($item['type'] === $source->getMorphClass() && (int) $item['id'] === (int) $source->getKey()) {
                throw ValidationException::withMessages([
                    "items.{$index}.id" => 'An item cannot be linked to itself.',
                ]);
            }

            $target = RelateableHelper::find($item['type'], $item['id']);
            if ( ! $target) {
                throw ValidationException::withMessages([
                    "items.{$index}.id" => __('messages.common.item_not_found'),
                ]);
            }

            $targets[$item['type'] . '#' . $item['id']] = $target;
        }

        DB::transaction(function () use ($source, $targets) {
            foreach ($targets as $target) {
                // One row per pair: skip if the link already exists in either direction.
                if ( ! $this->linkQuery($source->getMorphClass(), $source->getKey(), $target->getMorphClass(), $target->getKey())->exists()) {
                    $source->relate($target);
                }
            }
        });

        return response()->json([
            'message' => count($targets) === 1 ? 'Related content linked.' : 'Related content linked (' . count($targets) . ' items).',
            'data'    => $this->summariesFor($source, $user),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $types     = RelateableHelper::types();
        $validated = $request->validate([
            'source_type'  => ['required', 'string', Rule::in($types)],
            'source_id'    => 'required|integer',
            'related_type' => ['required', 'string', Rule::in($types)],
            'related_id'   => 'required|integer',
        ]);

        $user   = $request->user();
        $source = $this->findSourceOrFail($validated['source_type'], $validated['source_id']);
        $this->authorizeSource($source, $user);

        $deleted = $this->linkQuery(
            $validated['source_type'],
            $validated['source_id'],
            $validated['related_type'],
            $validated['related_id'],
        )->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Relation not found.'], 404);
        }

        return response()->json([
            'message' => 'Related content removed.',
            'data'    => $this->summariesFor($source, $user),
        ]);
    }

    /**
     * The link between two items, whichever side created it.
     */
    private function linkQuery(string $typeA, $idA, string $typeB, $idB)
    {
        return Relateable::query()->where(function ($q) use ($typeA, $idA, $typeB, $idB) {
            $q->where(function ($q) use ($typeA, $idA, $typeB, $idB) {
                $q->where('source_type', $typeA)->where('source_id', $idA)
                    ->where('related_type', $typeB)->where('related_id', $idB);
            })->orWhere(function ($q) use ($typeA, $idA, $typeB, $idB) {
                $q->where('source_type', $typeB)->where('source_id', $idB)
                    ->where('related_type', $typeA)->where('related_id', $idA);
            });
        });
    }

    private function summariesFor(Model $model, ?User $viewer)
    {
        return $model->relatedSummaries(fn (Model $item) => RelateableHelper::isVisible($item, $viewer));
    }

    private function findSourceOrFail(string $type, $id): Model
    {
        $source = RelateableHelper::find($type, $id);
        abort_if( ! $source, 404, __('messages.common.item_not_found'));

        return $source;
    }

    private function authorizeSource(Model $source, ?User $user): void
    {
        abort_unless(RelateableHelper::canEdit($source, $user), 403, 'You are not allowed to change the related content of this item.');
    }

    /**
     * The signed-in user on public routes (Sanctum SPA cookie), if any.
     */
    private function viewer(Request $request): ?User
    {
        $user = $request->user() ?? $request->user('sanctum');

        return $user instanceof User ? $user : null;
    }
}
