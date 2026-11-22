<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The taxonomy manager behind /admin/settings/taxonomy/taxonomies.
 *
 * A taxonomy row has no name of its own: it is a term *used as* something
 * (a forum category, a kind of achievement, a game world). Every payload
 * here therefore carries the term's title as the row's name, and the
 * parent as a readable path rather than a bare id - which is the whole
 * point of this screen existing next to the generic data table.
 */
class TaxonomyAdminController extends Controller
{
    /** How deep the tree may be walked before legacy parent_id cycles are assumed. */
    private const MAX_DEPTH = 20;

    /**
     * Every vocabulary with its rows as a tree.
     */
    public function index(Request $request): JsonResponse
    {
        $rows = $this->rows();

        $groups = $rows
            ->groupBy('taxonomy')
            ->map(fn (Collection $group, string $taxonomy) => [
                'taxonomy' => $taxonomy,
                'label'    => $this->humanize($taxonomy),
                'total'    => $group->count(),
                'nodes'    => $this->tree($group),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['data' => $groups]);
    }

    /**
     * The vocabularies that exist, for the "type" picker. Types are not a
     * table of their own - a new one comes into being with its first row,
     * so the picker has to both list and accept a new value.
     */
    public function types(): JsonResponse
    {
        $types = Taxonomy::query()
            ->select('taxonomy')
            ->selectRaw('count(*) as total')
            ->groupBy('taxonomy')
            ->orderBy('taxonomy')
            ->get()
            ->map(fn (Taxonomy $row) => [
                'taxonomy' => $row->taxonomy,
                'label'    => $this->humanize($row->taxonomy),
                'total'    => (int) $row->getAttribute('total'),
            ]);

        return response()->json(['data' => $types]);
    }

    /**
     * Terms for the name picker, newest translations resolved. `q` filters
     * by title, `taxonomy` marks the ones already used in that vocabulary
     * so the drawer can grey them out instead of failing on save.
     */
    public function terms(Request $request): JsonResponse
    {
        $query = Term::query()->with('translations');

        if ($search = trim((string) $request->input('q', ''))) {
            $query->whereTranslationLike('title', '%' . $search . '%');
        }

        $terms = $query->limit(200)->get()
            ->sortBy(fn (Term $term) => Str::lower((string) $term->title), SORT_NATURAL)
            ->values();

        $taken = [];

        if ($taxonomy = trim((string) $request->input('taxonomy', ''))) {
            $taken = Taxonomy::query()
                ->where('taxonomy', $taxonomy)
                ->when($request->filled('exclude'), fn ($q) => $q->where('id', '!=', $request->integer('exclude')))
                ->pluck('term_id')
                ->filter()
                ->all();
        }

        return response()->json([
            'data' => $terms->map(fn (Term $term) => [
                'id'    => $term->id,
                'title' => (string) $term->title,
                'slug'  => $term->slug,
                'taken' => in_array($term->id, $taken, true),
            ]),
        ]);
    }

    /**
     * The rows of one vocabulary that may be picked as a parent, flattened
     * with their depth and full path. Excludes the row being edited and
     * everything under it.
     */
    public function parentOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'taxonomy' => ['required', 'string', 'max:255'],
            'exclude'  => ['nullable', 'integer'],
        ]);

        $rows = $this->rows()->where('taxonomy', $data['taxonomy']);

        if ($exclude = $data['exclude'] ?? null) {
            $rows = $rows->reject(
                fn (Taxonomy $row) => in_array($row->id, $this->branchIds($rows, (int) $exclude), true)
            );
        }

        return response()->json(['data' => $this->flatten($rows)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $taxonomy = DB::transaction(function () use ($data) {
            $row = new Taxonomy;
            $this->fill($row, $data);
            $row->save();

            return $row;
        });

        return response()->json([
            'message' => __('messages.taxonomy.saved'),
            'data'    => $this->node($taxonomy->fresh(['term', 'parent.term'])),
        ], 201);
    }

    public function update(Taxonomy $taxonomy, Request $request): JsonResponse
    {
        $data = $this->validated($request, $taxonomy);

        DB::transaction(function () use ($taxonomy, $data) {
            $this->fill($taxonomy, $data);
            $taxonomy->save();
        });

        return response()->json([
            'message' => __('messages.taxonomy.saved'),
            'data'    => $this->node($taxonomy->fresh(['term', 'parent.term'])),
        ]);
    }

    /**
     * Soft-delete one row. Children would be orphaned by the database's
     * cascade, so they are lifted to the deleted row's own parent first -
     * losing a sub-category should not silently lose its children too.
     */
    public function destroy(Taxonomy $taxonomy): JsonResponse
    {
        $moved = DB::transaction(function () use ($taxonomy) {
            $children = Taxonomy::where('parent_id', $taxonomy->id)->get();

            foreach ($children as $child) {
                $child->parent_id = $taxonomy->parent_id;
                $child->save();
            }

            $taxonomy->delete();

            return $children->count();
        });

        return response()->json([
            'message'        => __('messages.taxonomy.deleted'),
            'moved_children' => $moved,
        ]);
    }

    /**
     * Persist a drag or a move: [{ id, parent_id, sort }, ...]. Applied in
     * one transaction so a half-reordered tree is never left behind.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'             => ['required', 'array', 'min:1'],
            'items.*.id'        => ['required', 'integer', 'exists:taxonomies,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:taxonomies,id'],
            'items.*.sort'      => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $row = Taxonomy::find($item['id']);

                if ($row === null) {
                    continue;
                }

                $parentId = $item['parent_id'] ?? null;

                // Never let a drag write a cycle, whatever the client sent.
                if ($parentId !== null && in_array($parentId, array_merge([$row->id], $row->descendantIds()), true)) {
                    continue;
                }

                if ($parentId !== null) {
                    $parent = Taxonomy::find($parentId);

                    if ($parent === null || $parent->taxonomy !== $row->taxonomy) {
                        continue;
                    }
                }

                $row->parent_id = $parentId;
                $row->sort      = $item['sort'];
                $row->save();
            }
        });

        return response()->json(['message' => __('messages.taxonomy.saved')]);
    }

    // ── validation ──────────────────────────────────────────────────

    /**
     * A row is named either by picking an existing term or by typing a new
     * title; one of the two is required, and `title` wins when both come in
     * so that renaming in the dialog does what it looks like it does.
     */
    private function validated(Request $request, ?Taxonomy $taxonomy = null): array
    {
        $data = $request->validate([
            'taxonomy'   => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'term_id'    => ['nullable', 'integer', 'exists:terms,id', Rule::requiredIf(fn () => ! $request->filled('title'))],
            'title'      => ['nullable', 'string', 'max:255'],
            'parent_id'  => ['nullable', 'integer', 'exists:taxonomies,id'],
            'sort'       => ['nullable', 'integer', 'min:0', 'max:65535'],
            'visible'    => ['nullable', 'boolean'],
            'searchable' => ['nullable', 'boolean'],
            'color'      => ['nullable', 'string', 'max:32'],
            'properties' => ['nullable', 'array'],
            // Translated, so they are written through the model's accessor
            'description' => ['nullable', 'string'],
            'lead'        => ['nullable', 'string'],
            'content'     => ['nullable', 'string'],
            'meta_desc'   => ['nullable', 'string'],
        ]);

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = Taxonomy::find($parentId);

            if ($parent === null || $parent->taxonomy !== $data['taxonomy']) {
                throw ValidationException::withMessages([
                    'parent_id' => __('messages.taxonomy.parent_other_vocabulary'),
                ]);
            }

            if ($taxonomy !== null && in_array($parentId, array_merge([$taxonomy->id], $taxonomy->descendantIds()), true)) {
                throw ValidationException::withMessages([
                    'parent_id' => __('messages.taxonomy.parent_descendant'),
                ]);
            }
        }

        return $data;
    }

    /**
     * Write the validated payload onto the row, resolving the term last so
     * the uniqueness of (term_id, taxonomy) can be reported on the field
     * the user actually filled in.
     */
    private function fill(Taxonomy $row, array $data): void
    {
        $row->taxonomy  = $data['taxonomy'];
        $row->parent_id = $data['parent_id'] ?? null;

        foreach (['sort', 'color'] as $key) {
            if (array_key_exists($key, $data)) {
                $row->{$key} = $data[$key];
            }
        }

        foreach (['visible', 'searchable'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $row->{$key} = (bool) $data[$key];
            }
        }

        if (array_key_exists('properties', $data)) {
            $row->properties = $data['properties'];
        }

        foreach (['description', 'lead', 'content', 'meta_desc'] as $key) {
            if (array_key_exists($key, $data)) {
                $row->{$key} = $data[$key];
            }
        }

        $row->term_id = $this->resolveTermId($row, $data);
    }

    /**
     * The term that names this row. A typed title renames the term when the
     * row already has one of its own, and otherwise finds or creates the
     * matching term - the same term is deliberately reusable across
     * vocabularies ("Events" as both an achievement type and a forum).
     */
    private function resolveTermId(Taxonomy $row, array $data): int
    {
        $title = isset($data['title']) ? trim((string) $data['title']) : '';

        if ($title !== '') {
            $current = $row->exists ? $row->term : null;

            // Renaming a term that only this row uses changes the term in
            // place; a shared term is left alone and this row is pointed at
            // the term with the new title instead.
            if ($current !== null && $current->taxonomies()->count() <= 1) {
                $existing = Term::whereTranslation('title', $title)->where('id', '!=', $current->id)->first();

                if ($existing !== null) {
                    $term = $existing;
                } else {
                    $current->title = $title;
                    $current->slug  = null;
                    $current->save();

                    $term = $current;
                }
            } else {
                $term = Term::firstOrCreateByTitle($title);
            }
        } else {
            $term = Term::find($data['term_id']);
        }

        if ($term === null) {
            throw ValidationException::withMessages(['title' => __('messages.taxonomy.term_missing')]);
        }

        $clash = Taxonomy::query()
            ->where('taxonomy', $data['taxonomy'])
            ->where('term_id', $term->id)
            ->when($row->exists, fn ($q) => $q->where('id', '!=', $row->id))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                $title !== '' ? 'title' : 'term_id' => __('messages.taxonomy.term_taken', [
                    'title'    => $term->title,
                    'taxonomy' => $this->humanize($data['taxonomy']),
                ]),
            ]);
        }

        return $term->id;
    }

    // ── shaping ─────────────────────────────────────────────────────

    /**
     * Every row, with the relations the payloads need loaded up front.
     *
     * @return Collection<int,Taxonomy>
     */
    private function rows(): Collection
    {
        return Taxonomy::query()
            ->with(['term.translations', 'translations'])
            ->withCount('taxables')
            ->orderBy('sort')
            ->get()
            ->sortBy([
                ['sort', 'asc'],
                fn (Taxonomy $a, Taxonomy $b) => Str::lower((string) $a->term_title) <=> Str::lower((string) $b->term_title),
            ])
            ->values();
    }

    /**
     * Nest a flat set of rows. Rows whose parent is missing or sits in
     * another vocabulary are shown at the root rather than dropped, so a
     * broken parent_id is visible in the UI instead of hiding the row.
     *
     * @param  Collection<int,Taxonomy>  $rows
     */
    private function tree(Collection $rows): array
    {
        $ids      = $rows->pluck('id')->all();
        $byParent = $rows->groupBy(
            fn (Taxonomy $row) => in_array($row->parent_id, $ids, true) ? $row->parent_id : 0
        );

        return $this->branch($byParent, 0, 0, []);
    }

    /**
     * @param  array<int,string>  $ancestors  Titles from the root down to $parent.
     */
    private function branch(Collection $byParent, int $parent, int $depth, array $ancestors): array
    {
        $nodes = [];

        foreach ($byParent->get($parent, collect()) as $row) {
            $path = array_merge($ancestors, [(string) ($row->term_title ?: '#' . $row->id)]);

            // The path is carried down the walk rather than read back off
            // each row, so showing the tree stays one query.
            $node             = $this->node($row, $depth, $path);
            $node['children'] = $depth < self::MAX_DEPTH
                ? $this->branch($byParent, $row->id, $depth + 1, $path)
                : [];

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * One row as the manager reads it.
     *
     * @param  array<int,string>|null  $path  Ancestor titles plus this row's.
     */
    private function node(Taxonomy $row, int $depth = 0, ?array $path = null): array
    {
        return [
            'id'          => $row->id,
            'taxonomy'    => $row->taxonomy,
            'term_id'     => $row->term_id,
            'title'       => (string) $row->term_title,
            'slug'        => $row->term_slug,
            'has_term'    => $row->term !== null,
            'parent_id'   => $row->parent_id,
            'path'        => $path ?? $row->getTitlePath(),
            'depth'       => $depth,
            'description' => $row->description,
            'lead'        => $row->lead,
            'content'     => $row->content,
            'meta_desc'   => $row->meta_desc,
            'color'       => $row->color,
            'sort'        => (int) $row->sort,
            'visible'     => (bool) $row->visible,
            'searchable'  => (bool) $row->searchable,
            'properties'  => $row->properties,
            'usage_count' => (int) ($row->taxables_count ?? 0),
            'children'    => [],
        ];
    }

    /**
     * Flatten rows into picker options carrying depth and full path.
     *
     * @param  Collection<int,Taxonomy>  $rows
     */
    private function flatten(Collection $rows): array
    {
        $ids      = $rows->pluck('id')->all();
        $byParent = $rows->groupBy(
            fn (Taxonomy $row) => in_array($row->parent_id, $ids, true) ? $row->parent_id : 0
        );

        $walk = function (int $parent, int $depth, array $ancestors) use (&$walk, $byParent): array {
            $options = [];

            foreach ($byParent->get($parent, collect()) as $row) {
                $title = (string) ($row->term_title ?? '#' . $row->id);
                $path  = array_merge($ancestors, [$title]);

                $options[] = [
                    'id'    => $row->id,
                    'title' => $title,
                    'path'  => $path,
                    'depth' => $depth,
                ];

                if ($depth < self::MAX_DEPTH) {
                    $options = array_merge($options, $walk($row->id, $depth + 1, $path));
                }
            }

            return $options;
        };

        return $walk(0, 0, []);
    }

    /**
     * $id and every row beneath it, within the given set.
     *
     * @param  Collection<int,Taxonomy>  $rows
     * @return array<int,int>
     */
    private function branchIds(Collection $rows, int $id): array
    {
        $byParent = $rows->groupBy(fn (Taxonomy $row) => $row->parent_id ?? 0);

        $branch = [$id];
        $queue  = [$id];

        while ($queue !== []) {
            foreach ($byParent->get(array_shift($queue), collect()) as $child) {
                if ( ! in_array($child->id, $branch, true)) {
                    $branch[] = $child->id;
                    $queue[]  = $child->id;
                }
            }
        }

        return $branch;
    }

    /**
     * "forum_cat" → "Forum cat". A known vocabulary gets a proper name from
     * the frontend's translations; this is the fallback for the rest.
     */
    private function humanize(string $taxonomy): string
    {
        return Str::ucfirst(trim(str_replace(['_', '-'], ' ', $taxonomy)));
    }
}
