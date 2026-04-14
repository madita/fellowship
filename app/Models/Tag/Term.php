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
 * @property int                   $id
 * @property string                $title
 * @property string|null           $slug
 * @property string|null           $content
 * @property string|null           $lead
 * @property Collection|Taxonomy[] $taxonomies
 */
class Term extends Model implements TranslatableContract
{
    use Sluggable;
    use SoftDeletes;
    use HasCache;
    use Translatable;

    public $translatedAttributes = ['title', 'content', 'lead'];

    /** @inheritdoc */
    protected $fillable = [
        'slug',
    ];

    /** @inheritdoc */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /** @inheritdoc */
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

    /** @inheritdoc */
    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title']];
    }

    /**
     * Get the taxonomies (categories) this term belongs to.
     *
     * @return HasMany
     */
    public function taxonomies(): HasMany
    {
        return $this->hasMany(Taxonomy::class);
    }

    /**
     * Get display title.
     *
     * @param int $limit
     *
     * @return string
     */
    public function getDisplayTitle(int $limit = 0): string
    {
        return $limit > 0
            ? Str::limit($this->title, $limit)
            : $this->title;
    }
}
