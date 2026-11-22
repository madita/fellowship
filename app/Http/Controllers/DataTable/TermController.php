<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The words the site names things with, behind /admin/settings/taxonomy/terms.
 *
 * A term is only a word: `title`, `content` and `lead` live in
 * term_translations and the table itself holds no more than a slug. The
 * columns this controller used to name (name, desc, tag_taxonomy_id) were
 * left over from an older schema, so editing a term could change nothing
 * but its slug and creating one never worked at all.
 *
 * What a word is used *as* lives in the taxonomies table, and that is the
 * other half of this screen. A word in use could be seen here only as a
 * bare list of vocabulary names - "forum_cat, wiki" - which says nothing
 * about where in either tree it sits, and a word could not be filed into a
 * vocabulary from here at all. The vocabulary and parent fields below do
 * that now, with the same picker and the same refusals as the taxonomy
 * screens.
 */
class TermController extends DataTableController
{
    public function builder()
    {
        // Three levels of ancestry come along for free (a taxonomy eager
        // loads its own term), which is what the "Used as" column reads;
        // a deeper tree falls back to walking parents a query at a time.
        return Term::query()
            ->with(['taxonomies.parent.parent'])
            ->withCount('taxonomies');
    }

    /**
     * Where a word is used is the one thing an admin needs here that no
     * column holds, so it is worked out per row.
     */
    public function getRecords(Request $request)
    {
        $records = parent::getRecords($request);

        if ($records instanceof LengthAwarePaginator) {
            $records->getCollection()->each(fn (Term $term) => $this->describeRow($term));
        }

        return $records;
    }

    /**
     * The entries a word may be filed under, in hierarchy order.
     *
     * Terms have no parent of their own - a word does not sit under
     * another word - so this answers for the vocabulary named in the
     * request instead: the entries of that vocabulary, minus the one this
     * very word already is there and everything beneath it.
     */
    public function parentOptions(Request $request): JsonResponse
    {
        $vocabulary = trim((string) $request->input('taxonomy', ''));

        // A parent only means something inside one vocabulary, so until
        // the vocabulary field is filled there is nothing to offer.
        if ($vocabulary === '') {
            return response()->json(['data' => []]);
        }

        $rows = Taxonomy::query()
            ->where('taxonomy', $vocabulary)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        // "exclude" is the row being edited, which here is a term: what
        // has to go is the entry that word already is in this vocabulary.
        $termId = $request->integer('exclude') ?: null;
        $own    = $termId === null ? null : $rows->firstWhere('term_id', $termId);

        $excluded = $own === null ? [] : $this->descendantsOf($rows, 'parent_id', $own->id);

        $ids = $rows->pluck('id')->all();

        // An entry whose parent is gone belongs at the root rather than
        // out of sight, so an unknown parent is grouped with the roots.
        $byParent = $rows
            ->reject(fn (Taxonomy $row) => in_array($row->id, $excluded, true))
            ->groupBy(fn (Taxonomy $row) => in_array($row->parent_id, $ids, true) ? $row->parent_id : 0);

        return response()->json([
            'data' => $this->flattenOptions($byParent, 0, 0, []),
        ]);
    }

    /**
     * Everything one word is used as, for the relation panel of the drawer.
     */
    public function taxonomies($id): JsonResponse
    {
        $term = Term::findOrFail($id);

        $rows = Taxonomy::query()
            ->withCount('taxables')
            ->where('term_id', $term->id)
            ->get()
            ->sortBy([['taxonomy', 'asc'], ['id', 'asc']])
            ->map(fn (Taxonomy $taxonomy) => [
                'id'     => $taxonomy->id,
                'url'    => '/admin/settings/taxonomy/taxonomies',
                'values' => [
                    'vocabulary' => $taxonomy->taxonomy,
                    'path'       => implode(' › ', $taxonomy->getTitlePath()),
                    'used_by'    => (int) $taxonomy->taxables_count,
                ],
            ])
            ->values();

        return response()->json([
            'data' => [
                'columns' => [
                    ['key' => 'vocabulary', 'title' => 'Vocabulary', 'type' => 'text'],
                    ['key' => 'path', 'title' => 'Where it sits', 'type' => 'text'],
                    ['key' => 'used_by', 'title' => 'Used by', 'type' => 'number'],
                ],
                'rows' => $rows,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'slug'      => ['nullable', 'string', 'max:255', 'unique:terms,slug'],
            'lead'      => ['nullable', 'string'],
            'content'   => ['nullable', 'string'],
            'taxonomy'  => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'parent_id' => ['nullable', 'integer', 'exists:taxonomies,id'],
        ]);

        $title = trim($data['title']);

        if ($existing = Term::whereTranslation('title', $title)->first()) {
            throw ValidationException::withMessages([
                'title' => __('messages.taxonomy.term_exists', ['title' => $existing->title]),
            ]);
        }

        $data['title'] = $title;

        // Both halves or neither: a refused parent must not leave behind a
        // word nobody asked for on its own.
        $term = DB::transaction(function () use ($data, $request) {
            $term = Term::create(Arr::only($data, ['title', 'slug', 'lead', 'content']));

            $this->fileUnder($term, $request);

            return $term;
        });

        return response()->json([
            'message' => 'success',
            'term'    => $this->describeRow($this->reload($term)),
        ], 201);
    }

    public function update($id, Request $request)
    {
        $term = Term::findOrFail($id);

        $data = $request->validate([
            'title'     => ['sometimes', 'required', 'string', 'max:255'],
            'slug'      => ['nullable', 'string', 'max:255', 'unique:terms,slug,' . $term->id],
            'lead'      => ['nullable', 'string'],
            'content'   => ['nullable', 'string'],
            'taxonomy'  => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'parent_id' => ['nullable', 'integer', 'exists:taxonomies,id'],
        ]);

        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);

            $clash = Term::whereTranslation('title', $data['title'])->where('id', '!=', $term->id)->first();

            if ($clash !== null) {
                throw ValidationException::withMessages([
                    'title' => __('messages.taxonomy.term_exists', ['title' => $clash->title]),
                ]);
            }
        }

        DB::transaction(function () use ($term, $data, $request) {
            $term->update(Arr::only($data, ['title', 'slug', 'lead', 'content']));

            $this->fileUnder($term, $request);
        });

        return response()->json([
            'message' => 'success',
            'term'    => $this->describeRow($this->reload($term)),
        ]);
    }

    /**
     * A term that still names a taxonomy is refused rather than deleted:
     * without it the forum category or achievement type it names would
     * show up blank, and nothing in the schema would say why.
     */
    public function destroy($ids, Request $request)
    {
        $inUse = Term::query()
            ->whereKey($this->parseIds($ids))
            ->has('taxonomies')
            ->with('taxonomies')
            ->get();

        if ($inUse->isNotEmpty()) {
            return response()->json([
                'message' => __('messages.taxonomy.term_in_use', [
                    'titles' => $inUse->map(fn (Term $term) => $term->title)->implode(', '),
                ]),
                'deleted' => 0,
            ], 422);
        }

        return parent::destroy($ids, $request);
    }

    public function getUpdatableColumns()
    {
        return [
            'title',
            'slug',
            'lead',
            'content',
            'taxonomy',
            'parent_id',
        ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'title',
            'slug',
            'lead',
            'taxonomies_count',
            'used_as',
            'updated_at',
        ];
    }

    public function getAppends()
    {
        return ['used_as', 'taxonomies_count'];
    }

    /**
     * Every entry the word names, which is what the two fields of the
     * drawer only ever show one of.
     */
    public function getRelations(): array
    {
        return [
            [
                'key'      => 'taxonomies',
                'title'    => 'Used as',
                'icon'     => 'mdi-file-tree-outline',
                'endpoint' => '/datatable/terms/{id}/taxonomies',
            ],
        ];
    }

    public function getCustomColumnsNames()
    {
        return [
            'title'            => 'Name',
            'taxonomies_count' => 'Uses',
            'used_as'          => 'Used as',
            'lead'             => 'Lead',
            'taxonomy'         => 'Used as (vocabulary)',
            'parent_id'        => 'Sits under',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'lead'      => 'textarea',
            'content'   => 'wysiwyg',
            'taxonomy'  => 'taxonomy-type',
            'parent_id' => 'parent',
        ];
    }

    public function getColumnTypes(): array
    {
        return [
            'used_as'          => 'text',
            'taxonomies_count' => 'number',
            'created_at'       => 'date',
            'updated_at'       => 'date',
        ];
    }

    public function getSearchableColumns(): array
    {
        return ['title', 'slug'];
    }

    /**
     * The picker lists taxonomy entries, whose name lives on their term.
     */
    protected function optionLabel(Model $record): string
    {
        if ($record instanceof Taxonomy) {
            return (string) ($record->term?->title ?: '#' . $record->getKey());
        }

        return parent::optionLabel($record);
    }

    /**
     * One vocabulary at a time, so a subheader would read the same on
     * every line.
     */
    protected function optionGroup(Model $record): ?string
    {
        return null;
    }

    /**
     * "wiki" finds every word used as a wiki entry: the vocabularies are
     * the only thing on this screen that is not a column of the row.
     */
    protected function applyFreeTextSearch(Builder $builder, string $term): Builder
    {
        $pattern = '%' . $this->escapeLike($term) . '%';

        return $builder->where(function (Builder $query) use ($term, $pattern) {
            parent::applyFreeTextSearch($query, $term);

            $query->orWhereHas('taxonomies', function (Builder $taxonomies) use ($pattern) {
                $this->whereLike($taxonomies, $taxonomies->getModel()->qualifyColumn('taxonomy'), $pattern);
            });
        });
    }

    /**
     * File a word under a vocabulary, and under a parent inside it.
     *
     * The vocabulary field names the entry the parent field is about. A
     * vocabulary the word is not used in yet gets a new entry - filing a
     * word from here is the point of the two fields - while one it is
     * already used in is moved to the chosen parent. Nothing is ever
     * removed: dropping a use belongs in the taxonomy manager, where what
     * is filed under it can be seen.
     */
    private function fileUnder(Term $term, Request $request): void
    {
        $vocabulary = trim((string) $request->input('taxonomy', ''));

        // A word is allowed to exist without being used as anything.
        if ($vocabulary === '') {
            return;
        }

        $entry = Taxonomy::query()
            ->where('taxonomy', $vocabulary)
            ->where('term_id', $term->id)
            ->first();

        $parentId = $this->resolveParent($request, $vocabulary, $entry);

        if ($entry === null) {
            $entry            = new Taxonomy;
            $entry->taxonomy  = $vocabulary;
            $entry->term_id   = $term->id;
            $entry->parent_id = $parentId;
            $entry->save();

            return;
        }

        // A save that never mentions a parent leaves the entry where it
        // is; an empty one moves it back to the top level.
        if ($request->has('parent_id')) {
            $entry->parent_id = $parentId;
            $entry->save();
        }
    }

    /**
     * The chosen parent as an id, or null for the top level.
     *
     * input() rather than get(): a JSON body is not in the request bag, so
     * get() would see nothing of it.
     */
    private function resolveParent(Request $request, string $vocabulary, ?Taxonomy $entry): ?int
    {
        $raw = $request->input('parent_id');

        $parentId = is_numeric($raw) ? (int) $raw : 0;

        if ($parentId <= 0) {
            return null;
        }

        $parent = Taxonomy::find($parentId);

        if ($parent === null || $parent->taxonomy !== $vocabulary) {
            throw ValidationException::withMessages([
                'parent_id' => __('messages.taxonomy.parent_other_vocabulary'),
            ]);
        }

        if ($entry !== null && in_array($parentId, array_merge([$entry->id], $entry->descendantIds()), true)) {
            throw ValidationException::withMessages([
                'parent_id' => __('messages.taxonomy.parent_descendant'),
            ]);
        }

        return $parentId;
    }

    /**
     * The row as the table and the drawer read it.
     */
    private function describeRow(Term $term): Term
    {
        $term->append(['vocabularies', 'used_as']);

        // Neither is a column of terms: the pair says which entry the
        // vocabulary and parent fields are about. Most words name one
        // thing; where a word names several, the relation panel lists them
        // all and these two show the oldest. Set on a row that is only
        // ever serialised from here - saving it would look for columns the
        // terms table does not have.
        $entry = $term->taxonomies->sortBy('id')->first();

        $term->setAttribute('taxonomy', $entry?->taxonomy);
        $term->setAttribute('parent_id', $entry?->parent_id);

        return $term;
    }

    /**
     * The saved term with everything the row payload reads.
     */
    private function reload(Term $term): Term
    {
        return Term::query()
            ->with(['taxonomies.parent.parent'])
            ->withCount('taxonomies')
            ->findOrFail($term->id);
    }
}
