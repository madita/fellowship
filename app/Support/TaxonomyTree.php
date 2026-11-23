<?php

namespace App\Support;

use App\Models\Tag\Taxonomy;
use Illuminate\Support\Collection;

/**
 * One vocabulary as a flat list in reading order, each entry carrying the
 * branch above it.
 *
 * Every screen that files something under a category needs the same two
 * answers - what may I pick, and where does each candidate sit - and each
 * of them used to answer differently: the wiki walked the tree itself, the
 * page form and the admin drawer offered a flat list of names, and two
 * screens sharing a name could not be told apart at all. This is that list,
 * in one place, so a picker anywhere reads the same shape:
 *
 *   [{ id, title, slug, parent_id, depth, path, entries_count }]
 *
 * `path` is the titles from the root down to the entry, which is what makes
 * "Characters" under Lore readable next to "Characters" under Rules.
 */
class TaxonomyTree
{
    /**
     * How deep a parent_id chain is followed. A loop written by hand
     * straight into the table would otherwise recurse until the process
     * died.
     */
    private const MAX_DEPTH = 20;

    /**
     * The entries of one vocabulary, parents before their children.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function options(string $vocabulary): array
    {
        $rows = Taxonomy::query()
            ->where('taxonomy', $vocabulary)
            ->with('term')
            ->withCount('taxables')
            ->get()
            ->filter(fn (Taxonomy $taxonomy) => filled($taxonomy->term?->title));

        $ids = $rows->pluck('id')->all();

        // An entry whose parent is gone belongs at the root rather than out
        // of sight, so an unknown parent is grouped with the real roots.
        $byParent = $rows->groupBy(fn (Taxonomy $taxonomy) => in_array($taxonomy->parent_id, $ids, true)
            ? $taxonomy->parent_id
            : 0);

        return self::branch($byParent, 0, 0, []);
    }

    /**
     * One entry in the same shape, for the answer to a save: the screen
     * that created it can file itself under it without reloading the list.
     */
    public static function payload(Taxonomy $taxonomy): array
    {
        $path = $taxonomy->getTitlePath();

        return [
            'id'            => $taxonomy->id,
            'title'         => (string) $taxonomy->term?->title,
            'slug'          => (string) $taxonomy->term?->slug,
            'parent_id'     => $taxonomy->parent_id,
            'depth'         => max(count($path) - 1, 0),
            'path'          => $path,
            'entries_count' => $taxonomy->taxables()->count(),
        ];
    }

    /**
     * One level of the tree, flattened into the list a picker reads: the
     * names above an entry travel down with it as its path, so nothing has
     * to walk back up per row.
     *
     * @param  Collection<int|string,Collection<int,Taxonomy>>  $byParent
     * @param  array<int,string>  $trail
     * @return array<int,array<string,mixed>>
     */
    private static function branch(Collection $byParent, int $parentId, int $depth, array $trail): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $options = [];

        foreach ($byParent->get($parentId, collect())->sortBy([['sort', 'asc'], ['id', 'asc']]) as $row) {
            $path = array_merge($trail, [(string) $row->term->title]);

            $options[] = [
                'id'            => $row->id,
                'title'         => (string) $row->term->title,
                'slug'          => (string) $row->term->slug,
                'parent_id'     => $parentId === 0 ? null : $parentId,
                'depth'         => $depth,
                'path'          => $path,
                'entries_count' => (int) $row->taxables_count,
            ];

            $options = array_merge($options, self::branch($byParent, $row->id, $depth + 1, $path));
        }

        return $options;
    }
}
