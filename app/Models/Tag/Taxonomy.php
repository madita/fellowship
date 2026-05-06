<?php

namespace App\Models\Tag;

use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Traits\HasCache;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

//use Webpatser\Uuid\Uuid;

class Taxonomy extends Model implements TranslatableContract
{
//    protected $table = 'taxonomies';
    /**
     * @inheritdoc
     */
    use SoftDeletes;
    use HasCache;
    use Translatable;

    public $translatedAttributes = ['description', 'content', 'lead', 'meta_desc'];

    /** @inheritdoc */
    protected $fillable = [
        'parent_id',
        'alias_id',
        'term_id',
        'taxonomy',

        'sort',
        'visible',
        'searchable',

        'properties',
    ];

    /** @inheritdoc */
    protected $casts = [
        'visible'    => 'boolean',
        'searchable' => 'boolean',
        'properties' => 'array',

        'deleted_at' => 'datetime',
    ];

    /** @inheritdoc */
    protected $with = [
        'term',
    ];

    /** @inheritdoc */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('lecturize.taxonomies.taxonomies.table', 'taxonomies');
    }

    /** @inheritdoc */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Taxonomy $model) {
            if ($model->getConnection()
                ->getSchemaBuilder()
                ->hasColumn($model->getTable(), 'uuid')) {
                $model->uuid = Str::uuid()->toString();
            }
        });

        static::saving(function (Taxonomy $model) {
            if (isset($model->term) && $model->term->title && !$model->description) {
                $model->description = $model->term->title;
            }

            if (!$model->sort) {
                $sort = ($siblings = $model->siblings()->get()) ? $siblings->max('sort') : 0;
                $model->sort = ($sort + 1);
            }
        });
    }

    /**
     * Get the term, that will be displayed as these taxonomies (categories) title.
     *
     * @return BelongsTo
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Get the parent taxonomy (categories).
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'parent_id');
    }

    /**
     * Get the children taxonomies (categories).
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Taxonomy::class, 'parent_id');
    }

    /**
     * Get the children taxonomies (categories).
     *
     * @return Builder
     */
    public function siblings(): Builder
    {
        $class = Taxonomy::class;

        return (new $class())->taxonomy($this->taxonomy)
            ->where('parent_id', $this->parent_id)
            ->orderBy('sort');
    }

    /**
     * Get the parent taxonomy (categories).
     *
     * @return BelongsTo
     */
    public function alias(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'alias_id');
    }

    /**
     * Get the breadcrumbs for this Taxonomy.
     *
     * @param bool $exclude_self
     *
     * @return Collection
     */
    public function getBreadcrumbs(bool $exclude_self = true): Collection
    {
        $key = "taxonomies.$this->id.breadcrumbs";
        $key .= $exclude_self ? '.self-excluded' : '';

        return Cache::tags([
            'taxonomies',
            'taxonomies:taxonomy',
            "taxonomies:taxonomy:{$this->id}",
        ])->rememberForever($key, function () use ($exclude_self) {
            $parameters = $this->getParentBreadcrumbs();

            if (!$exclude_self) {
                $parameters->push($this->taxonomy);
            }

            return $parameters->reverse()->values();
        });
    }

    /**
     * Add parent breadcrumb.
     *
     * @param Collection|null $parameters
     *
     * @return Collection
     */
    public function getParentBreadcrumbs(?Collection $parameters = null): Collection
    {
        if ($parameters === null) {
            $parameters = collect();
        }

        $parameters->push([
            'title'  => $this->term->title,
            'slug'   => $this->term->slug,
            'params' => $this->getRouteParameters(),
        ]);

        if ($parent = $this->parent) {
            return $parent->getParentBreadcrumbs($parameters);
        }

        return $parameters;
    }

    /**
     * Get route parameters.
     *
     * @param bool $exclude_taxonomy
     *
     * @return array
     */
    public function getRouteParameters(bool $exclude_taxonomy = true): array
    {
        $key = "taxonomies.$this->id.route-parameters";
        $key .= $exclude_taxonomy ? '.without-taxonomy' : '';

        return maybe_tagged_cache(['taxonomies', 'taxonomies:taxonomy', "taxonomies:taxonomy:$this->id"])->rememberForever($key, function () use ($exclude_taxonomy) {
            $parameters = $this->getParentSlugs();

            if (!$exclude_taxonomy) {
                $parameters[] = $this->taxonomy;
            }

            return array_reverse($parameters);
        });
    }

    /**
     * Get slugs of parent terms.
     *
     * @param array $parameters
     *
     * @return array
     */
    public function getParentSlugs(array $parameters = []): array
    {
        $parameters[] = $this->term->slug;

        if ($parent = $this->parent) {
            return $parent->getParentSlugs($parameters);
        }

        return $parameters;
    }

    /**
     * Scope by a given taxonomy (e.g. "blog_cat" for blog posts or "shop_cat" for shop products).
     *
     * @param Builder $query
     * @param string  $taxonomy
     *
     * @return Builder
     */
    public function scopeTaxonomy(Builder $query, string $taxonomy): Builder
    {
        return $query->where('taxonomy', $taxonomy);
    }

    /**
     * Scope by a given taxonomy prefix (e.g. to retrieve both "shop_cat_a" and "shop_cat_b" you would scope "shop_cat%").
     *
     * @param Builder $query
     * @param string  $taxonomy_prefix
     *
     * @return Builder
     */
    public function scopeTaxonomyStartsWith(Builder $query, string $taxonomy_prefix): Builder
    {
        return $query->where('taxonomy', 'like', "$taxonomy_prefix%");
    }

    /**
     * Scope by given taxonomies array, e.g. ['shop_cat_a', 'shop_cat_b'].
     *
     * @param Builder $query
     * @param array   $taxonomies
     *
     * @return Builder
     */
    public function scopeTaxonomies(Builder $query, array $taxonomies): Builder
    {
        return $query->whereIn('taxonomy', $taxonomies);
    }

    /**
     * Scope terms (category title) by given taxonomy.
     *
     * @param Builder    $query
     * @param string|int $term
     * @param string     $term_field
     *
     * @return Builder
     */
    public function scopeByTerm(Builder $query, string|int $term, string $term_field = 'title'): Builder
    {
        $term_field = !in_array($term_field, ['id', 'title', 'slug']) ? 'title' : $term_field;

        return $query->whereHas('term', function (Builder $q) use ($term, $term_field) {
            $q->where($term_field, $term);
        });
    }

    /**
     * A simple search scope.
     *
     * @param Builder $query
     * @param string  $term
     * @param string  $taxonomy
     *
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $term, string $taxonomy): Builder
    {
        return $query->whereHas('term', function (Builder $q) use ($term) {
            $q->where('title', 'like', '%'.$term.'%');
        });
    }

    /**
     * Scope visible taxonomies.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', 1);
    }

    /**
     * Scope searchable taxonomies.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('searchable', 1);
    }

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
     * @return BelongsTo
     */
//    public function term()
//    {
//        return $this->belongsTo(Term::class);
//    }

    /**
     * Get the parent taxonomy.
     *
     * @return BelongsTo
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

//    public function parent()
//    {
//        return $this->belongsTo(Taxonomy::class, 'parent_id');
//    }
//
//    public function children()
//    {
//        return $this->hasMany(Taxonomy::class, 'parent_id');
//    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

//    public function getTaxonomyAttribute()
//    {
//        $parents = collect([]);
//
//        $parent = $this->parent;
//
//        while(!is_null($parent)) {
//            $parents->push($parent);
//            $parent = $parent->parent;
//        }
//
//        return $parents;
//    }

    /**
     * Get forum threads belonging to this taxonomy (for forum_cat type).
     */
    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class, 'taxonomy_id');
    }

    /**
     * Get forum posts through threads (for forum_cat type).
     */
    public function forumPosts(): HasManyThrough
    {
        return $this->hasManyThrough(ForumPost::class, ForumThread::class, 'taxonomy_id', 'thread_id');
    }

    /**
     * An example for a related posts model.
     *
     * @return MorphToMany
     */
    public function posts()
    {
        return $this->morphedByMany('App\Models\Post', 'taxable', 'taxables');
    }

    /**
     * An example for a related posts model.
     *
     * @return MorphToMany
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
     * @param string|array  $categories
     * @param string        $taxonomy
     * @param Taxonomy|null $parent
     * @param int|null      $sort
     *
     * @return Collection
     */
    public static function createCategories($categories, string $taxonomy, ?Taxonomy $parent = null, ?int $sort = null): ?Collection
    {
        if (is_string($categories)) {
            $categories = explode('|', $categories);
        }

        $terms = collect();
        $taxonomies = collect();

        if (count($categories) > 0) {
            foreach ($categories as $category) {
                if (is_string($category)) {
                    $term = Term::firstOrCreate(['title' => $category]);
                } else {
                    $term = Term::firstOrCreate(['title' => $category['title']]);
                    if (isset($category['color'])) {
                        $term->color = $category['color'];
                    }
                }

                $term->save();
                $terms->push($term);
            }
        }

        foreach ($terms as $term) {
            $tax = Taxonomy::firstOrNew([
                'term_id'  => $term->id,
                'taxonomy' => $taxonomy,
            ]);

            if ($tax) {
                if ($parent instanceof Taxonomy && $tax->parent_id !== $parent->id) {
                    $tax->parent_id = $parent->id;
                }

                if (is_integer($sort) && $tax->sort !== $sort) {
                    $tax->sort = $sort;
                }

                $tax->save();

                $taxonomies->push($tax);
            }
        }

        return $taxonomies;
    }
}
