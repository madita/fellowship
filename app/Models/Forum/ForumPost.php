<?php

namespace App\Models\Forum;

use App\Models\User;
use App\Traits\Likeable;
use App\Traits\SafeSearchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumPost extends Model
{
    use HasFactory, Likeable, SafeSearchable, SoftDeletes;

    protected $fillable = [
        'thread_id',
        'user_id',
        'parent_id',
        'body',
        'is_solution',
        'like_count',
        'meta',
    ];

    protected $casts = [
        'is_solution' => 'boolean',
        'like_count'  => 'integer',
        'meta'        => 'array',
    ];

    protected $appends = ['display_author'];

    protected $with = ['author'];

    /**
     * Name to show as the author: imported posts keep their original
     * poster's name (meta.legacy_author) until the legacy account is
     * assigned to a registered user.
     */
    public function getDisplayAuthorAttribute(): ?string
    {
        return $this->meta['legacy_author'] ?? $this->author?->username;
    }

    /**
     * Get the thread this post belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    /**
     * Whether the member may read this post — which is entirely a question
     * about the thread it is in, and so about that thread's category.
     *
     * The activity feed asks subjects this before listing them, so without
     * it a post in a private category would have its thread title shown to
     * anyone.
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->thread?->isVisibleTo($user) ?? false;
    }

    /**
     * Get the author of the post.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent post (for nested replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'parent_id');
    }

    /**
     * Get child replies to this post.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * Check if user can edit this post.
     */
    public function canEdit(?User $user = null): bool
    {
        if ( ! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Allow editing within 1 hour for the author
        $editWindow = now()->subHour();
        if ($user->id === $this->user_id && $this->created_at->gt($editWindow)) {
            return true;
        }

        // Check forum-level moderate roles
        $moderateRoles = $this->thread->category->properties['moderate_roles'] ?? [];

        return ! empty($moderateRoles) && $user->hasAnyRole($moderateRoles);
    }

    /**
     * Check if user can delete this post.
     */
    public function canDelete(?User $user = null): bool
    {
        if ( ! $user) {
            return false;
        }

        if ($user->id === $this->user_id || $user->isAdmin()) {
            return true;
        }

        // Check forum-level delete roles
        $deleteRoles = $this->thread->category->properties['delete_roles'] ?? [];

        return ! empty($deleteRoles) && $user->hasAnyRole($deleteRoles);
    }

    /**
     * Mark this post as the solution.
     */
    public function markAsSolution(): void
    {
        // Unmark other solutions in this thread
        $this->thread->posts()->where('id', '!=', $this->id)->update(['is_solution' => false]);

        $this->update(['is_solution' => true]);
    }

    public function searchableAs(): string
    {
        return 'forum_posts';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['author', 'thread.category.term']);

        return [
            'id'            => $this->id,
            'body'          => strip_tags($this->body),
            'author_name'   => $this->author?->username ?? '',
            'thread_id'     => $this->thread_id,
            'thread_title'  => $this->thread?->title ?? '',
            'thread_slug'   => $this->thread?->slug ?? '',
            'category_name' => $this->thread?->category?->term?->title ?? '',
            'category_slug' => $this->thread?->category?->term?->slug ?? '',
            'taxonomy_id'   => $this->thread?->taxonomy_id,
            'is_solution'   => $this->is_solution,
            'created_at'    => $this->created_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }

    // likes(), likers(), isLikedBy(), like(), unlike() and toggleLike()
    // all come from the Likeable trait.

    /**
     * A forum post keeps its total in like_count — singular, unlike a
     * status, which is why the trait is told rather than guessing.
     */
    protected function likeCounterColumn(): ?string
    {
        return 'like_count';
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($post) {
            $post->thread->updateAfterNewPost($post);
        });

        static::deleted(function ($post) {
            $post->thread->update([
                'reply_count' => $post->thread->posts()->count(),
            ]);
        });
    }
}
