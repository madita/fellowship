<?php

namespace App\Models;

use App\Contracts\CanHaveTaxonomies;
use App\Traits\HasCache;
use App\Traits\HasRelateableContent;
use App\Traits\HasTaxonomies;
use App\Services\DiscordWebhookService;
use App\Support\DiscordEvents;
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

    protected static function booted(): void
    {
        // Announced when it goes live, whether that is at once or later
        static::saved(function (Post $post): void {
            if ($post->wasChanged('published_at') && $post->isPublished()) {
                app(DiscordWebhookService::class)->announce(DiscordEvents::POST_PUBLISHED, [
                    'title'       => $post->title,
                    'description' => $post->body,
                    'url'         => "/blog/{$post->slug}",
                    'author'      => $post->user?->username,
                ]);
            }
        });
    }

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
        // 'App\User' does not exist in this app; the relation errored when used
        return $this->belongsTo(User::class);
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
