<?php

namespace App\Http\Controllers;

use App\Models\Tag\Taxable;
use App\Models\Tag\Taxonomy;
// use App\Models\Tag\Taxonomy;

use App\Models\Tag\Term;
// use App\Models\Tag\Term;
use App\Support\TaxonomyTree;
// use Lecturize\Taxonomies\Models\Taxonomy;
// use Lecturize\Taxonomies\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaxonomyController extends Controller
{
    public function getTaxonomies()
    {
        $taxonomies = Taxonomy::all();

        return response()->json($taxonomies);
    }

    public function getTerms($taxonomy = null)
    {
        if ($taxonomy) {
            $terms = collect();
            $tax   = collect(Taxonomy::where('taxonomy', $taxonomy)->with('term')->get())->each(function (Taxonomy $taxonomy) use ($terms) {
                $term = $taxonomy->term;

                if ($term) {
                    $terms->add([
                        'id'        => $term->id,
                        'title'     => $term->title,
                        'slug'      => $term->slug,
                        'parent_id' => $taxonomy->id,
                    ]);
                }
            });
        } else {
            $terms = Term::all();
        }

        $capital = $terms->sortBy(['slug'])->groupBy(function ($item, $key) {
            return $item['slug'][0];
        });

        $data = [
            'terms'   => $terms,
            'total'   => $terms->count(),
            'capital' => $capital,
        ];

        return response()->json($data);
    }

    public function getTermInfo($term)
    {
        return response()->json($term);
    }

    public function getTaxables(Request $request)
    {
        $params = $request->all();
        $term   = Term::where('slug', $params['term'])->first();

        if ( ! isset($term->id)) {
            return response()->json(['message' => __('messages.common.no_data'), 'data' => null, 'category' => null]);
        }

        $taxonomy = Taxonomy::where('term_id', $term->id);

        //        $taxonomy = Taxonomy::where('taxonomy', 'tags');

        // how to get only wiki items???
        //        if ($params['taxonomy'] != null) {
        //            $taxonomy = $taxonomy->where('taxonomy', $params['taxonomy']);
        //        }

        $taxables = Taxable::whereIn('taxonomy_id', $taxonomy->pluck('id'));

        if ($params['model'] != null) {
            $taxables = $taxables->where('taxable_type', 'like', '%' . $params['model']);
        }

        $taxableCollection = collect($taxables->orderBy('taxable_type')->orderBy('taxable_id')->get())->map(function (Taxable $taxable) use ($taxonomy) {
            $model = app($taxable->taxable_type);
            $data  = $model::where('id', $taxable->taxable_id)->first();

            return [
                'type'              => Str::lower(Str::afterLast($taxable->taxable_type, '\\')),
                'taxable_title'     => $data->{$data->getTaxableTitle()},
                'category'          => $taxonomy,
                'data'              => $data,
                // description lives in taxonomy_translations — the translated
                // attribute is appended on serialization, don't select it.
                // values() so the filtered collection serialises as a JSON
                // array (the frontend reads taxonomy[0]).
                'taxonomy'          => collect($taxonomy->get(['id', 'taxonomy', 'parent_id', 'term_id']))->where('id', $taxable->taxonomy_id)->values(),
            ];
        });

        $capital = $taxableCollection->unique('data')->groupBy(function ($item, $key) {
            return $item['data']['slug'][0];     // treats the name string as an array
        });

        $data = [
            'category' => $taxonomy->with('children')->with('parent')->first(),
            'data'     => [
                'total'   => $taxableCollection->unique('data')->count(),
                'type'    => $taxableCollection->unique('data')->groupBy('type'),
                'capital' => $capital,
            ],
        ];

        return response()->json($data);
    }

    /**
     * One vocabulary as the list every picker reads: parents before their
     * children, each entry with its depth and the names above it.
     *
     * The terms endpoint above answers with the words alone, in
     * alphabetical order, and with a "parent_id" that is in fact the id of
     * the entry itself - which is why every screen that offered a parent
     * offered a flat list that could not say where a choice would land.
     */
    public function getOptions($taxonomy)
    {
        return response()->json([
            'data' => TaxonomyTree::options((string) $taxonomy),
        ]);
    }

    /**
     * Add an entry to a vocabulary, under the entry that was chosen.
     *
     * The parent used to arrive as the whole picked row under "parent",
     * whose own "parent_id" key was read as the choice. That shape is
     * still read, since a client may be an open page, but what the
     * screens send now is a plain parent_id - and an entry whose name is
     * already in use is refused rather than quietly skipped, which used to
     * leave the caller selecting a category it had not created and could
     * not see.
     */
    public function saveTerms(Request $request)
    {
        $validated = $request->validate([
            'term'      => ['required', 'string', 'max:255'],
            'taxonomy'  => ['required'],
            'parent_id' => ['nullable', 'integer'],
            'parent'    => ['nullable'],
            'content'   => ['nullable', 'string'],
        ]);

        $vocabulary = $this->resolveVocabulary($validated['taxonomy']);

        if ($vocabulary === '') {
            throw ValidationException::withMessages([
                'taxonomy' => __('messages.taxonomy.vocabulary_required'),
            ]);
        }

        $title    = trim($validated['term']);
        $parentId = $this->resolveParent($request, $vocabulary);

        $term = Term::firstOrCreateByTitle($title);

        // One word names one entry per vocabulary: (term_id, taxonomy) is
        // what the tree is keyed by.
        if (Taxonomy::where('taxonomy', $vocabulary)->where('term_id', $term->id)->exists()) {
            throw ValidationException::withMessages([
                'term' => __('messages.taxonomy.term_taken', ['title' => $title, 'taxonomy' => $vocabulary]),
            ]);
        }

        $entry            = new Taxonomy;
        $entry->taxonomy  = $vocabulary;
        $entry->term_id   = $term->id;
        $entry->parent_id = $parentId;

        if (filled($validated['content'] ?? null)) {
            $entry->description = $validated['content'];
        }

        $entry->save();

        return response()->json([
            'success'  => true,
            'message'  => __('messages.taxonomy.saved'),
            'term'     => $title,
            // The created entry in the shape the pickers read, so whoever
            // asked for it can select it without reloading the list.
            'category' => TaxonomyTree::payload($entry->load('term')),
        ], 201);
    }

    /**
     * The vocabulary as a name. Some screens send the whole row they
     * picked it from.
     */
    private function resolveVocabulary(mixed $taxonomy): string
    {
        if (is_array($taxonomy)) {
            $taxonomy = $taxonomy['taxonomy'] ?? '';
        }

        return trim((string) $taxonomy);
    }

    /**
     * The chosen parent as an id, or null for the top level.
     *
     * input() rather than get(): a JSON body is not in the request bag, so
     * get() would see nothing of it. The legacy shape is read for its
     * parent_id key alone - its "id" was the id of a term, not of an
     * entry, and reading that would file the entry under a stranger.
     */
    private function resolveParent(Request $request, string $vocabulary): ?int
    {
        $raw = $request->has('parent_id') ? $request->input('parent_id') : $request->input('parent');

        if (is_array($raw)) {
            $raw = $raw['parent_id'] ?? null;
        }

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

        return $parentId;
    }
}
