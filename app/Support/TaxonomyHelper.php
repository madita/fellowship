<?php

namespace App\Support;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;

class TaxonomyHelper
{
    /**
     * @param  mixed  $terms
     * @param  string  $taxonomy
     * @param  int  $parent
     * @param  int  $order
     */
    public static function createTaxables($terms, $taxonomy, $parent = 0, $order = 0)
    {
        $terms = self::makeTermsArray($terms);

        self::createTerms($terms);
        $taxonomy = self::createTaxonomies($terms, $taxonomy, $parent, $order);

        return $taxonomy;
    }

    public static function createTerms(array $terms)
    {
        // Titles live in term_translations, so each one is looked up through the translation
        $titles = array_unique(array_filter(
            array_map(fn ($title) => trim((string) $title), $terms),
            fn ($title) => $title !== ''
        ));

        foreach ($titles as $title) {
            Term::firstOrCreateByTitle($title);
        }
    }

    /**
     * @param  string  $taxonomy
     * @param  int  $parent
     * @param  int  $order
     */
    public static function createTaxonomies(array $terms, $taxonomy, $parent = 0, $order = 0)
    {
        if (count($terms) > 0) {
            // create taxonomy entries for the given terms that exist (titles are translated)
            foreach ($terms as $title) {
                $term = Term::whereTranslation('title', trim((string) $title))->first();
                if ($term === null) {
                    continue;
                }
                $term_id = $term->id;

                if (Taxonomy::where('taxonomy', $taxonomy)->where('term_id', $term_id)->first()) {
                    // ->where('sort', $order)->first()
                    continue;
                }

                $model           = new Taxonomy;
                $model->taxonomy = $taxonomy;
                $model->term_id  = $term_id;
                if ($parent > 0) {
                    $model->parent_id = $parent;
                }

                //                $model->sort = $order;
                $model->save();

                return $model;
            }
        }

        return null;
    }

    /**
     * @param  string|array  $terms
     * @return array
     */
    public static function makeTermsArray($terms)
    {
        if (is_array($terms)) {
            return $terms;
        } elseif (is_string($terms)) {
            return explode('|', $terms);
        }

        return (array) $terms;
    }

    /**
     * @param  string|array  $terms
     * @return array
     */
    public static function getTaxonomy()
    {
        $taxonomy = collect(Taxonomy::select('taxonomy')
            ->distinct()
            ->get())->map(function (Taxonomy $taxonomy) {
                return [
                    'name' => $taxonomy->taxonomy,
                    'id'   => $taxonomy->taxonomy,
                ];
            });

        return $taxonomy;
    }
}
