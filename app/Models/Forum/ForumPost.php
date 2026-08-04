<?php

namespace App\Models\Forum;

use App\Models\Concerns\SafeSearchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumPost extends Model
{
    use HasFactory, SafeSearchable, SoftDeletes;

    protected $fillable = [
        'thread_id',
        'user_id',
        'parent_id',
        'body',
        'is_solution',
        'like_count',
    ];

    protected $casts = [
        'is_solution' => 'boolean',
        'like_count' => 'integer',
    ];

    protected $with = ['author'];

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

    /**
     * Get the thread this post belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
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
        if (! $user) {
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
        if (! $user) {
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
     * Get likes for this post.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(ForumPostLike::class, 'post_id');
    }

    /**
     * Check if a user has liked this post.
     */
    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Like this post as a user.
     */
    public function like(User $user): void
    {
        if (! $this->isLikedBy($user)) {
            $this->likes()->create(['user_id' => $user->id]);
            $this->increment('like_count');
        }
    }

    /**
     * Unlike this post as a user.
     */
    public function unlike(User $user): void
    {
        $deleted = $this->likes()->where('user_id', $user->id)->delete();
        if ($deleted) {
            $this->decrement('like_count');
        }
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
            'id' => $this->id,
            'body' => strip_tags($this->body),
            'author_name' => $this->author?->username ?? '',
            'thread_id' => $this->thread_id,
            'thread_title' => $this->thread?->title ?? '',
            'thread_slug' => $this->thread?->slug ?? '',
            'category_name' => $this->thread?->category?->term?->title ?? '',
            'category_slug' => $this->thread?->category?->term?->slug ?? '',
            'taxonomy_id' => $this->thread?->taxonomy_id,
            'is_solution' => $this->is_solution,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }
}
