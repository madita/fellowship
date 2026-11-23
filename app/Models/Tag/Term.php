<?php

namespace App\Models\Tag;

use App\Traits\HasCache;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Class Term.
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $content
 * @property string|null $lead
 * @property Collection|Taxonomy[] $taxonomies
 */
class Term extends Model implements TranslatableContract
{
    use HasCache;
    use Sluggable;
    use SoftDeletes;
    use Translatable;

    public $translatedAttributes = ['title', 'content', 'lead'];

    /** {@inheritdoc} */
    protected $fillable = [
        'slug',
    ];

    /** {@inheritdoc} */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /** {@inheritdoc} */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = 'terms';
    }

    /**
     * Find a term by translated title, or create one.
     */
    public static function firstOrCreateByTitle(string $title): static
    {
        return static::whereTranslation('title', $title)->first()
            ?? static::create(['title' => $title]);
    }

    /** {@inheritdoc} */
    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title']];
    }

    /**
     * Get the taxonomies (categories) this term belongs to.
     */
    public function taxonomies(): HasMany
    {
        return $this->hasMany(Taxonomy::class);
    }

    /**
     * The vocabularies this word is in use as, e.g. "achievement_type,
     * forum_cat". Appended on demand by the admin table rather than
     * globally, so nothing else has to carry the taxonomies relation.
     */
    public function getVocabulariesAttribute(): string
    {
        return $this->taxonomies
            ->pluck('taxonomy')
            ->filter()
            ->unique()
            ->sort()
            ->implode(', ');
    }

    /**
     * Where this word is actually used, as "forum_cat: Help & Support ›
     * Technical Issues". The name of the vocabulary alone cannot answer
     * "which one?" once that vocabulary has a tree in it, so the whole
     * branch is written out. Appended on demand, like the vocabularies
     * above.
     */
    public function getUsedAsAttribute(): string
    {
        return $this->taxonomies
            ->sortBy([['taxonomy', 'asc'], ['id', 'asc']])
            ->map(fn (Taxonomy $taxonomy) => $taxonomy->taxonomy . ': ' . implode(' › ', $taxonomy->getTitlePath()))
            ->implode('; ');
    }

    /**
     * Get display title.
     */
    public function getDisplayTitle(int $limit = 0): string
    {
        return $limit > 0
            ? Str::limit($this->title, $limit)
            : $this->title;
    }
}
