<?php

namespace App\Models\Tag;

use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lecturize\Taxonomies\Models\Taxonomy as TaxonomyBase;
use Illuminate\Database\Eloquent\SoftDeletes;

class Taxonomy extends TaxonomyBase
{
    use SoftDeletes;

//    protected $table = 'taxonomies';
    /**
     * @inheritdoc
     */
//    protected $fillable = [
//        'term_id',
//        'taxonomy',
//        'desc',
//        'parent_id',
//        'archived',
//        'sort',
//    ];

    protected $hidden = [''];

    /**
     * @inheritdoc
     */
    protected $dates = ['deleted_at'];

//    public function getTaxableTitle()
//    {
//        return property_exists($this, 'taxable_title') ? $this->taxable_title : null;
//    }

    /**
     * Get the term this taxonomy belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
//    public function term()
//    {
//        return $this->belongsTo(Term::class);
//    }

    /**
     * Get the parent taxonomy.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
//    public function parent()
//    {
//        return $this->belongsTo(Taxonomy::class, 'parent_id');
//    }
//
//    /**
//     * Get the children taxonomies.
//     *
//     * @return \Illuminate\Database\Eloquent\Relations\HasMany
//     */
//    public function children()
//    {
//        return $this->hasMany(Taxonomy::class, 'parent_id');
//    }

//    public function childrenRecursive()
//    {
//        return $this->children()->with('childrenRecursive');
//    }

    /**
     * An example for a related posts model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function posts()
    {
        return $this->morphedByMany('App\Models\Post', 'taxable', 'taxables');
    }

    /**
     * An example for a related posts model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function pages()
    {
//        return $this->morphedByMany('App\Models\Page', 'taxable', 'taxables');
        return $this->morphedByMany(Page::class, 'taxable', 'taxables')
            ->withTimestamps();
    }


    /**
     * Scope taxonomies.
     *
     * @param object $query
     * @param string $taxonomy
     *
     * @return mixed
     */
//    public function scopeTaxonomy($query, $taxonomy)
//    {
//        return $query->where('taxonomy', $taxonomy);
//    }

    /**
     * Scope terms.
     *
     * @param object $query
     * @param string $term
     * @param string $taxonomy
     *
     * @return mixed
     */
    public function scopeTerm($query, $term, $taxonomy = 'major')
    {
        return $query->whereHas('term', function ($q) use ($term) {
            $q->where('name', $term);
        });
    }

    /**
     * A simple search scope.
     *
     * @param object $query
     * @param string $searchTerm
     * @param string $taxonomy
     *
     * @return mixed
     */
//    public function scopeSearch($query, $searchTerm, $taxonomy = 'major')
//    {
//        return $query->whereHas('term', function ($q) use ($searchTerm) {
//            $q->where('name', 'like', '%'.$searchTerm.'%');
//        });
//    }

    /**
     * Creates terms and taxonomies.
     *
     * @param  string|array        $categories
     * @param  string              $taxonomy
     * @param  \App\Models\Tag\Taxonomy|null  $parent
     * @param  int|null            $sort
     * @return Collection
     */
//    public static function createCategories($categories, string $taxonomy, ? Taxonomy $parent = null, ?int $sort = null): ?Collection
//    {
//        if (is_string($categories))
//            $categories = explode('|', $categories);
//
//        $terms      = collect();
//        $taxonomies = collect();
//
//
//        if (count($categories) > 0)
//            foreach ($categories as $category) {
//                if(is_string($category)) {
//                    $term = Term::firstOrCreate(['title' => $category]);
//                } else {
//                    $term = Term::firstOrCreate(['title' => $category['title']]);
//                    $term->color = $category['color'];
//                }
//
//
//                $term->save();
//                $terms->push($term);
//            }
//
//
//        foreach ($terms as $term) {
//            $tax = Taxonomy::firstOrNew([
//                                                 'term_id'  => $term->id,
//                                                 'taxonomy' => $taxonomy,
//                                             ]);
//
//            if ($tax) {
//                if ($parent instanceof Taxonomy && $tax->parent_id !== $parent->id)
//                    $tax->parent_id = $parent->id;
//
//                if (is_integer($sort) && $tax->sort !== $sort)
//                    $tax->sort = $sort;
//
//                $tax->save();
//
//                $taxonomies->push($tax);
//            }
//        }
//
//        return $taxonomies;
//    }
}
