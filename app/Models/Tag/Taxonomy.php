<?php namespace App\Models\Tag;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Cviebrock\EloquentSluggable\Sluggable;

class Taxonomy extends Model
{
    use SoftDeletes;

    protected $table = 'taxonomies';
    /**
     * @inheritdoc
     */
    protected $fillable = [
        'term_id',
        'taxonomy',
        'desc',
        'parent_id',
        'archived',
        'sort',
    ];

    protected $hidden = [''];

    /**
     * @inheritdoc
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the term this taxonomy belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term() {
        return $this->belongsTo(Term::class);
    }
    /**
     * Get the parent taxonomy.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Taxonomy::class, 'parent_id');
    }
    /**
     * Get the children taxonomies.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Taxonomy::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }
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
        return $this->morphedByMany('App\Models\Page', 'taxable', 'taxables');
    }
    /**
     * Scope taxonomies.
     *
     * @param  object  $query
     * @param  string  $taxonomy
     * @return mixed
     */
    public function scopeTaxonomy($query, $taxonomy)
    {
        return $query->where('taxonomy', $taxonomy);
    }
    /**
     * Scope terms.
     *
     * @param  object  $query
     * @param  string  $term
     * @param  string  $taxonomy
     * @return mixed
     */
    public function scopeTerm($query, $term, $taxonomy = 'major')
    {
        return $query->whereHas('term', function($q) use($term, $taxonomy) {
            $q->where('name', $term);
        });
    }
    /**
     * A simple search scope.
     *
     * @param  object  $query
     * @param  string  $searchTerm
     * @param  string  $taxonomy
     * @return mixed
     */
    public function scopeSearch($query, $searchTerm, $taxonomy = 'major')
    {
        return $query->whereHas('term', function($q) use($searchTerm, $taxonomy) {
            $q->where('name', 'like', '%'. $searchTerm .'%');
        });
    }
}
