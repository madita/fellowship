<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * The raw table behind /admin/settings/taxonomy/taxonomies/table.
 *
 * The readable screen is the taxonomy manager (Admin\TaxonomyAdminController);
 * this one is the escape hatch for the columns the manager does not show.
 * It exists mainly so nothing has to be edited in the database by hand, so
 * it is strict about the two things that can actually corrupt the tree: the
 * term a row is named by, and its parent.
 */
class TaxonomyController extends DataTableController
{
    public function builder()
    {
        // term is in the model's $with already; parent.term is what the
        // Parent column reads, and withCount keeps the Used column from
        // running a query per row.
        return Taxonomy::query()
            ->with(['parent.term'])
            ->withCount('taxables');
    }

    /**
     * The name of a row lives on its term, and the term is reached through
     * an accessor rather than a column, so it has to be appended per row.
     */
    public function getRecords(Request $request)
    {
        $records = parent::getRecords($request);

        if ($records instanceof LengthAwarePaginator) {
            $records->getCollection()->each->append(['term_title', 'term_slug', 'parent_title']);
        }

        return $records;
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);

        $taxonomy = Taxonomy::create($data);

        return response()->json([
            'message'  => 'success',
            'taxonomy' => $taxonomy->fresh(['term', 'parent.term'])->append(['term_title', 'parent_title']),
        ], 201);
    }

    public function update($id, Request $request)
    {
        $taxonomy = Taxonomy::findOrFail($id);

        $taxonomy->update($this->validateRow($request, $taxonomy));

        return response()->json([
            'message'  => 'success',
            'taxonomy' => $taxonomy->fresh(['term', 'parent.term'])->append(['term_title', 'parent_title']),
        ]);
    }

    public function getUpdatableColumns()
    {
        return [
            'term_id',
            'taxonomy',
            'parent_id',
            'sort',
            'visible',
            'searchable',
            'color',
            'description',
            'lead',
            'content',
            'meta_desc',
            'properties',
        ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'term_title',
            'taxonomy',
            'parent_title',
            'description',
            'sort',
            'visible',
            'searchable',
            'taxables_count',
            'color',
            'properties',
            'updated_at',
        ];
    }

    /**
     * term_title, parent_title and taxables_count are accessors, not
     * columns; declaring them keeps the base class from hiding them.
     */
    public function getAppends()
    {
        return ['term_title', 'term_slug', 'parent_title', 'taxables_count'];
    }

    public function getCustomColumnsNames()
    {
        return [
            'term_title'     => 'Name',
            'term_id'        => 'Name (term)',
            'taxonomy'       => 'Vocabulary',
            'parent_title'   => 'Parent',
            'parent_id'      => 'Parent',
            'taxables_count' => 'Used by',
            'sort'           => 'Position',
            'meta_desc'      => 'Meta description',
        ];
    }

    public function getCustomInputFields()
    {
        return [
            'term_id'     => 'term',
            'taxonomy'    => 'taxonomy-type',
            'parent_id'   => 'parent',
            'visible'     => 'checkbox',
            'searchable'  => 'checkbox',
            'description' => 'textarea',
            'lead'        => 'textarea',
            'meta_desc'   => 'textarea',
            'content'     => 'wysiwyg',
            'color'       => 'color',
            'properties'  => 'json',
        ];
    }

    /**
     * color is a TEXT column holding a short colour value, not long text;
     * the two accessors are plain text the base class cannot type.
     */
    public function getColumnTypes(): array
    {
        return [
            'color'          => 'text',
            'term_title'     => 'text',
            'parent_title'   => 'text',
            'taxables_count' => 'number',
            'created_at'     => 'date',
            'updated_at'     => 'date',
        ];
    }

    /**
     * The free-text search adds the term title itself (see above), so the
     * column list only has to name the row's own text.
     */
    public function getSearchableColumns(): array
    {
        return ['taxonomy', 'description'];
    }

    /**
     * Only rows of the same vocabulary may be a parent — a forum category
     * under a game world would be unreachable from either screen.
     */
    protected function scopeParentOptions(Builder $builder, Request $request): Builder
    {
        $taxonomy = trim((string) $request->input('taxonomy', ''));

        return $builder
            ->with('term')
            ->when($taxonomy !== '', fn (Builder $query) => $query->where('taxonomy', $taxonomy))
            ->orderBy('sort');
    }

    protected function optionLabel(Model $record): string
    {
        /** @var Taxonomy $record */
        return (string) ($record->term?->title ?: '#' . $record->getKey());
    }

    protected function optionGroup(Model $record): ?string
    {
        /** @var Taxonomy $record */
        return $record->taxonomy;
    }

    /**
     * A row is found by the name it is shown under, which lives two tables
     * away, so the term title is OR-ed in on top of the normal columns.
     */
    protected function applyFreeTextSearch(Builder $builder, string $term): Builder
    {
        $pattern = '%' . $this->escapeLike($term) . '%';

        return $builder->where(function (Builder $query) use ($term, $pattern) {
            parent::applyFreeTextSearch($query, $term);

            $query->orWhereHas('term.translations', function (Builder $translations) use ($pattern) {
                $this->whereLike($translations, $translations->getModel()->qualifyColumn('title'), $pattern);
            });
        });
    }

    /**
     * Reject the two shapes that corrupt the tree: a row named by a term
     * already used in this vocabulary, and a parent that is the row itself,
     * one of its descendants, or a member of another vocabulary.
     */
    protected function validateRow(Request $request, ?Taxonomy $taxonomy = null): array
    {
        $rules = [
            'taxonomy'    => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'term_id'     => ['required', 'integer', 'exists:terms,id'],
            'parent_id'   => ['nullable', 'integer', 'exists:taxonomies,id'],
            'sort'        => ['nullable', 'integer', 'min:0', 'max:65535'],
            'visible'     => ['nullable', 'boolean'],
            'searchable'  => ['nullable', 'boolean'],
            'color'       => ['nullable', 'string', 'max:32'],
            'properties'  => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'lead'        => ['nullable', 'string'],
            'content'     => ['nullable', 'string'],
            'meta_desc'   => ['nullable', 'string'],
        ];

        if ($taxonomy !== null) {
            // A partial update may leave the identifying pair alone.
            $rules['taxonomy'][0] = 'sometimes';
            $rules['term_id'][0]  = 'sometimes';
        }

        $data = $request->validate($rules);

        $vocabulary = $data['taxonomy'] ?? $taxonomy?->taxonomy;
        $termId     = $data['term_id'] ?? $taxonomy?->term_id;

        if ($termId !== null && Term::find($termId) === null) {
            throw ValidationException::withMessages(['term_id' => __('messages.taxonomy.term_missing')]);
        }

        $clash = Taxonomy::query()
            ->where('taxonomy', $vocabulary)
            ->where('term_id', $termId)
            ->when($taxonomy !== null, fn ($query) => $query->where('id', '!=', $taxonomy->id))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'term_id' => __('messages.taxonomy.term_taken', [
                    'title'    => (string) Term::find($termId)?->title,
                    'taxonomy' => $vocabulary,
                ]),
            ]);
        }

        if (($data['parent_id'] ?? null) !== null) {
            $parent = Taxonomy::find($data['parent_id']);

            if ($parent === null || $parent->taxonomy !== $vocabulary) {
                throw ValidationException::withMessages([
                    'parent_id' => __('messages.taxonomy.parent_other_vocabulary'),
                ]);
            }

            if ($taxonomy !== null && in_array($parent->id, array_merge([$taxonomy->id], $taxonomy->descendantIds()), true)) {
                throw ValidationException::withMessages([
                    'parent_id' => __('messages.taxonomy.parent_descendant'),
                ]);
            }
        }

        return $data;
    }
}
