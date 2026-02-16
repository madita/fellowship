<?php

namespace App\Models\Forum;

use App\Models\Tag\Taxonomy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $taxonomy_id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property string $body
 */

class ForumThread extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'taxonomy_id',
        'user_id',
        'title',
        'slug',
        'body',
        'is_pinned',
        'is_locked',
        'view_count',
        'reply_count',
        'last_post_id',
        'last_post_user_id',
        'last_post_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'view_count' => 'integer',
        'reply_count' => 'integer',
        'last_post_at' => 'datetime',
    ];

    protected $appends = ['url'];

    protected $with = ['author'];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($thread) {
            if (empty($thread->slug)) {
                $thread->slug = Str::slug($thread->title);

                // Ensure slug is unique
                $count = 1;
                while (static::where('slug', $thread->slug)->exists()) {
                    $thread->slug = Str::slug($thread->title) . '-' . $count++;
                }
            }

            $thread->last_post_at = now();
            $thread->last_post_user_id = $thread->user_id;
        });
    }

    /**
     * Get the category (taxonomy) this thread belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'taxonomy_id');
    }

    /**
     * Get the author of the thread.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all posts in this thread.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'thread_id')->orderBy('created_at');
    }

    /**
     * Get the last post in this thread.
     */
    public function lastPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'last_post_id');
    }

    /**
     * Get the user who made the last post.
     */
    public function lastPostUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }

    /**
     * Get the URL attribute.
     */
    public function getUrlAttribute(): string
    {
        return "/forum/{$this->category->term->slug}/{$this->slug}";
    }

    /**
     * Increment view count.
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    /**
     * Check if user can reply to this thread.
     */
    public function canReply(User $user = null): bool
    {
        if ($this->is_locked) {
            return $user && $user->isAdmin();
        }

        return $user !== null;
    }

    /**
     * Check if user can edit this thread.
     */
    public function canEdit(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->id === $this->user_id || $user->isAdmin()) {
            return true;
        }

        $moderateRoles = $this->category->properties['moderate_roles'] ?? [];

        return !empty($moderateRoles) && $user->hasAnyRole($moderateRoles);
    }

    /**
     * Check if user can delete this thread.
     */
    public function canDelete(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->id === $this->user_id || $user->isAdmin()) {
            return true;
        }

        $deleteRoles = $this->category->properties['delete_roles'] ?? [];

        return !empty($deleteRoles) && $user->hasAnyRole($deleteRoles);
    }

    /**
     * Get subscriptions for this thread.
     */
    /**
     * Get read records for this thread.
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ForumThreadRead::class, 'thread_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ThreadSubscription::class, 'thread_id');
    }

    /**
     * Check if a user is subscribed to this thread.
     */
    public function isSubscribedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->subscriptions()->where('user_id', $user->id)->exists();
    }

    /**
     * Subscribe a user to this thread.
     */
    public function subscribe(User $user): void
    {
        $this->subscriptions()->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Unsubscribe a user from this thread.
     */
    public function unsubscribe(User $user): void
    {
        $this->subscriptions()->where('user_id', $user->id)->delete();
    }

    /**
     * Update thread statistics after a new post.
     */
    public function updateAfterNewPost(ForumPost $post): void
    {
        $this->update([
            'reply_count' => $this->posts()->count(),
            'last_post_id' => $post->id,
            'last_post_user_id' => $post->user_id,
            'last_post_at' => $post->created_at,
        ]);
    }

    public function searchableAs(): string
    {
        return 'forum_threads';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['author', 'category.term']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => strip_tags($this->body),
            'slug' => $this->slug,
            'author_name' => $this->author?->username ?? '',
            'category_name' => $this->category?->term?->title ?? '',
            'category_slug' => $this->category?->term?->slug ?? '',
            'taxonomy_id' => $this->taxonomy_id,
            'is_pinned' => $this->is_pinned,
            'reply_count' => $this->reply_count ?? 0,
            'view_count' => $this->view_count ?? 0,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return !$this->trashed();
    }
}
