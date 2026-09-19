<?php

namespace App\Models;

use App\Contracts\CanHaveTaxonomies;
use App\Traits\HasCache;
use App\Traits\HasRelateableContent;
use App\Traits\HasTaxonomies;
use App\Traits\NotifiesMentions;
use App\Traits\Publishable;
use App\Traits\Revisionable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements CanHaveTaxonomies, TranslatableContract
{
    use HasCache;
    use HasRelateableContent;
    use HasTaxonomies;
    use NotifiesMentions;
    use Publishable;
    use Revisionable;
    use Sluggable;
    use Translatable;

    public $translatedAttributes = ['title', 'body'];

    protected array $mentionFields = ['body'];

    protected $fillable = [
        'slug',
        'user_id',
    ];

    protected $taxable_title = 'title';

    protected $primaryKey = 'id';

    protected $table        = 'posts';
    protected $revisionable = [
        'title',
        'slug',
        'body',
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
        return $this->belongsTo('App\User');
    }

    // ── @mentions in the post text ──────────────────────────────────

    public function mentionContext(): string
    {
        return 'post';
    }

    public function mentionTitle(): string
    {
        return (string) $this->title;
    }

    public function mentionUrl(): string
    {
        return "/blog/{$this->slug}";
    }

    /**
     * A draft is only readable by admins.
     */
    public function mentionableBy(User $user): bool
    {
        return $this->isPublished() || $user->isAdmin();
    }
}
