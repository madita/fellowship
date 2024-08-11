<?php

namespace App\Helpers;

use App\Models\Tag\Taxonomy;
use Lecturize\Taxonomies\Models\Term;

class TaxonomyHelper
{
    /**
     * @param mixed  $terms
     * @param string $taxonomy
     * @param int    $parent
     * @param int    $order
     */
    public static function createTaxables($terms, $taxonomy, $parent = 0, $order = 0)
    {
        $terms = self::makeTermsArray($terms);

        self::createTerms($terms);
        self::createTaxonomies($terms, $taxonomy, $parent, $order);
    }

    /**
     * @param array $terms
     */
    public static function createTerms(array $terms)
    {
        if (count($terms) > 0) {
            $found = Term::whereIn('title', $terms)->pluck('title')->all();

            if (!is_array($found)) {
                $found = [];
            }

            foreach (array_diff($terms, $found) as $title) {
                if (Term::where('title', $title)->first()) {
                    continue;
                }

                $term = new Term();
                $term->title = $title;
                $term->save();
            }
        }
    }

    /**
     * @param array  $terms
     * @param string $taxonomy
     * @param int    $parent
     * @param int    $order
     */
    public static function createTaxonomies(array $terms, $taxonomy, $parent = 0, $order = 0)
    {
        if (count($terms) > 0) {
            // only keep terms with existing entries in terms table
            $terms = Term::whereIn('title', $terms)->pluck('title')->all();

            // create taxonomy entries for given terms
            foreach ($terms as $term) {
                $term_id = Term::where('title', $term)->first()->id;

                if (Taxonomy::where('taxonomy', $taxonomy)->where('term_id', $term_id)->first()) {
                    //->where('sort', $order)->first()
                    continue;
                }

                $model = new Taxonomy();
                $model->taxonomy = $taxonomy;
                $model->term_id = $term_id;
                if ($parent > 0) {
                    $model->parent_id = $parent;
                }

//                $model->sort = $order;
                $model->save();
            }
        }
    }

    /**
     * @param string|array $terms
     *
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
}
