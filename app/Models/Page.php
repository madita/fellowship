<?php

namespace App\Models;

use App\Contracts\CanHaveTaxonomies;
use App\Traits\HasCache;
use App\Traits\HasPolls;
use App\Traits\HasRelateableContent;
use App\Traits\HasTaxonomies;
use App\Traits\NotifiesMentions;
use App\Traits\Publishable;
use App\Traits\Revisionable;
// use Lecturize\Taxonomies\Traits\HasCategories;
use App\Traits\Wikiable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements CanHaveTaxonomies, HasMedia, TranslatableContract
{
    use HasCache;
    use HasRelateableContent;
    use HasPolls;
    use HasTaxonomies;
    use InteractsWithMedia;
    use NotifiesMentions;
    use Publishable;
    use Revisionable;
    use Sluggable;
    use Translatable;
    use Wikiable;

    public $translatedAttributes = ['title', 'content'];

    /**
     * A page carries the text of a wiki page or of a standalone page.
     */
    protected array $mentionFields = ['content'];

    protected $fillable = [
        'title',
        'sign_in_only',
        'slug',
        'parent_id',
        'user_id',
        'created_at',
        'updated_at',
    ];

    protected $taxable_title = 'title';

    protected $primaryKey = 'id';

    protected $table = 'pages';

    protected $wikiable = [
        'title' => 'title',
        'slug'  => 'slug',
    ];

    protected $revisionable = [
        'title',
        'slug',
        'content',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    public function getParentsAttribute()
    {
        $parents = collect([]);

        $parent = $this->parent;

        while ( ! is_null($parent)) {
            $parents->push($parent);
            $parent = $parent->parent;
        }

        return $parents;
    }

    // ── @mentions in the page text ──────────────────────────────────

    public function mentionContext(): string
    {
        return $this->asWiki() ? 'wiki' : 'page';
    }

    public function mentionTitle(): string
    {
        return (string) $this->title;
    }

    public function mentionUrl(): string
    {
        $wiki = $this->asWiki();

        return $wiki ? "/wiki/{$wiki->slug}" : "/{$this->slug}";
    }

    /**
     * A wiki page waiting for approval, and an unpublished page, cannot be
     * opened yet — those mentions are sent once the page is approved
     * (see Wiki::afterApproved()).
     */
    public function mentionableBy(User $user): bool
    {
        $wiki = $this->asWiki();

        if ($wiki) {
            return $wiki->isApproved();
        }

        return $this->isPublished() || $user->isAdmin();
    }

    /**
     * A new wiki page has no address until its wiki row is attached, right
     * after this save; WikiController::store notifies once it has one.
     */
    public function shouldNotifyMentions(): bool
    {
        return ! $this->wasRecentlyCreated;
    }

    /**
     * The wiki page this text belongs to, if it is one.
     */
    private function asWiki(): ?Wiki
    {
        return $this->relationLoaded('wikiable')
            ? $this->getRelation('wikiable')->first()
            : $this->wikiable()->first();
    }
}
