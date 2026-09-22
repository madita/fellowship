<?php

namespace App\Models;

use App\Models\Translations\WikiTranslation;
use App\Services\DiscordWebhookService;
use App\Support\Achievements;
use App\Support\DiscordEvents;
use App\Traits\Approvable;
use App\Traits\HasCache;
use App\Traits\HasRelateableContent;
use App\Traits\HasTickets;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Wiki extends Model implements TranslatableContract
{
    use Approvable;
    use HasCache;
    use HasRelateableContent;
    use HasTickets;
    use Sluggable;
    use Translatable;
    //    protected $guard_name = 'api';

    public $translatedAttributes = ['title'];

    public $translationForeignKey = 'wiki_id';

    public $translationModel = WikiTranslation::class;

    protected $table = 'wikiables';

    protected $cacheTag = 'wikiables';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug', 'status', 'parent_id', 'wikiable_type', 'wikiable_id',
    ];

    /**
     * A pending wiki page cannot be opened, so mentions in its text wait
     * for the approval. The text lives on the wikiable (a Page).
     */
    public function afterApproved(): void
    {
        // The morph relation is not declared here; the controllers resolve it the same way
        $model    = $this->wikiable_type;
        $wikiable = $model ? $model::find($this->wikiable_id) : null;

        if ($wikiable && method_exists($wikiable, 'notifyMentionedMembers')) {
            // Credited to whoever wrote the page, not to the admin approving it
            $wikiable->notifyMentionedMembers($wikiable->user ?? null);
        }

        // An approved page is the one worth counting: a draft nobody has
        // read yet is not an achievement. Credited to its author.
        Achievements::record($wikiable?->user ?? null, 'wiki.page.approved');

        app(DiscordWebhookService::class)->announce(DiscordEvents::WIKI_PAGE_APPROVED, [
            'title'       => $this->title,
            'description' => $wikiable?->content,
            'url'         => "/wiki/{$this->slug}",
            'author'      => $wikiable?->user?->username,
        ]);
    }

    /**
     * A page waiting for review: the admins can hear about it in Discord.
     */
    public function announceSubmission(): void
    {
        $model    = $this->wikiable_type;
        $wikiable = $model ? $model::find($this->wikiable_id) : null;

        app(DiscordWebhookService::class)->announce(DiscordEvents::WIKI_PAGE_SUBMITTED, [
            'title'       => $this->title,
            'description' => $wikiable?->content,
            'url'         => "/wiki/{$this->slug}",
            'author'      => $wikiable?->user?->username,
        ]);
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Wiki::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Wiki::class, 'parent_id');
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

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($wiki) {
            // If creator has an auto-approve role, approve immediately (skip ticket)
            if ($wiki->shouldAutoApprove()) {
                $wiki->approve(auth()->user());

                return;
            }

            // Otherwise create approval ticket as before
            $wiki->autoCreateTicket('wiki_approval', [
                'title'       => "New Wiki Page: {$wiki->title}",
                'description' => 'A new wiki page has been created and needs review.',
                'priority'    => 'normal',
            ]);
        });
    }
    //
    //    public function wikiable() {
    //        return $this->morphTo();
    //    }
}
