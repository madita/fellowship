<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Relateable;
use App\Support\RelateableHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin overview of every related-content link: list with filters, a few
 * numbers, and removing links regardless of who created them.
 */
class RelationAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind'     => ['nullable', 'string', Rule::in(RelateableHelper::kindKeys())],
            'search'   => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page'     => 'nullable|integer|min:1',
        ]);

        $query = $this->baseQuery();

        if ( ! empty($validated['kind'])) {
            $type = RelateableHelper::typeForKind($validated['kind']);
            $query->where(fn (Builder $q) => $q->where('source_type', $type)->orWhere('related_type', $type));
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        $page = $query
            ->orderByDesc('created_at')
            ->orderBy('source_type')->orderBy('source_id')
            ->orderBy('related_type')->orderBy('related_id')
            ->paginate($validated['per_page'] ?? 20);

        $rows   = $page->getCollection();
        $models = RelateableHelper::findMany($rows->flatMap(fn (Relateable $row) => [
            ['type' => $row->source_type, 'id' => $row->source_id],
            ['type' => $row->related_type, 'id' => $row->related_id],
        ]));

        $summary = function (string $type, $id) use ($models) {
            $model = $models[$type][(int) $id] ?? null;

            return $model ? RelateableHelper::summary($model) : RelateableHelper::missingSummary($type, $id);
        };

        return response()->json([
            'data' => $rows->map(fn (Relateable $row) => [
                'source'     => $summary($row->source_type, $row->source_id),
                'related'    => $summary($row->related_type, $row->related_id),
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $byKind = [];
        foreach (RelateableHelper::kindKeys() as $kind) {
            $type          = RelateableHelper::typeForKind($kind);
            $byKind[$kind] = $this->baseQuery()
                ->where(fn (Builder $q) => $q->where('source_type', $type)->orWhere('related_type', $type))
                ->count();
        }

        $pairs = $this->baseQuery()
            ->select('source_type', 'related_type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('source_type', 'related_type')
            ->get()
            ->map(fn ($row) => [
                'source_kind'  => RelateableHelper::kindForType($row->source_type),
                'related_kind' => RelateableHelper::kindForType($row->related_type),
                'count'        => (int) $row->aggregate,
            ])
            ->sortByDesc('count')
            ->values();

        return response()->json([
            'data' => [
                'total'       => $this->baseQuery()->count(),
                'by_kind'     => $byKind,
                'pairs'       => $pairs,
                'most_linked' => $this->mostLinked(5),
                'recent_7d'   => $this->baseQuery()->where('created_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
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

        $deleted = Relateable::query()
            ->where(function (Builder $q) use ($validated) {
                $q->where(fn (Builder $q) => $q
                    ->where('source_type', $validated['source_type'])->where('source_id', $validated['source_id'])
                    ->where('related_type', $validated['related_type'])->where('related_id', $validated['related_id']))
                    ->orWhere(fn (Builder $q) => $q
                        ->where('source_type', $validated['related_type'])->where('source_id', $validated['related_id'])
                        ->where('related_type', $validated['source_type'])->where('related_id', $validated['source_id']));
            })
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Relation not found.'], 404);
        }

        return response()->json(['message' => 'Relation removed.']);
    }

    /**
     * Only rows between registered content types.
     */
    private function baseQuery(): Builder
    {
        $types = RelateableHelper::types();

        return Relateable::query()->whereIn('source_type', $types)->whereIn('related_type', $types);
    }

    /**
     * Match rows where either side's title (any locale) contains $search:
     * look up the matching ids per type first, then filter the rows by them.
     */
    private function applySearch(Builder $query, string $search): void
    {
        $matches = [];
        foreach (RelateableHelper::kindKeys() as $kind) {
            $type = RelateableHelper::typeForKind($kind);
            $ids  = $type::query()
                ->whereTranslationLike(RelateableHelper::titleAttribute($kind), '%' . $search . '%')
                ->pluck((new $type())->getQualifiedKeyName())
                ->all();

            if ($ids !== []) {
                $matches[$type] = $ids;
            }
        }

        if ($matches === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($matches) {
            foreach ($matches as $type => $ids) {
                $q->orWhere(fn (Builder $q) => $q->where('source_type', $type)->whereIn('source_id', $ids))
                    ->orWhere(fn (Builder $q) => $q->where('related_type', $type)->whereIn('related_id', $ids));
            }
        });
    }

    /**
     * The items with the most links (either side), skipping rows whose model is gone.
     */
    private function mostLinked(int $limit): array
    {
        $types = RelateableHelper::types();
        $sides = DB::table('relateables')
            ->select('source_type as type', 'source_id as id')
            ->whereIn('source_type', $types)->whereIn('related_type', $types)
            ->unionAll(
                DB::table('relateables')
                    ->select('related_type as type', 'related_id as id')
                    ->whereIn('source_type', $types)->whereIn('related_type', $types)
            );

        $counts = DB::query()->fromSub($sides, 'sides')
            ->select('type', 'id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('type', 'id')
            ->orderByDesc('aggregate')->orderBy('type')->orderBy('id')
            ->limit($limit * 4)
            ->get();

        $models = RelateableHelper::findMany($counts->map(fn ($row) => ['type' => $row->type, 'id' => $row->id]));

        return $counts
            ->map(function ($row) use ($models) {
                $model = $models[$row->type][(int) $row->id] ?? null;

                return $model ? ['item' => RelateableHelper::summary($model), 'count' => (int) $row->aggregate] : null;
            })
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }
}
