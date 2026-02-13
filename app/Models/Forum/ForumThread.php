<?php

namespace App\Models\Forum;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $forum_id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property string $body
 */

class ForumThread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'forum_id',
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
        });

        static::created(function ($thread) {
            $thread->forum->incrementThreadCount();
            $thread->forum->updateStatistics();
        });

        static::deleted(function ($thread) {
            $thread->forum->decrementThreadCount();
            $thread->forum->updateStatistics();
        });
    }

    /**
     * Get the forum this thread belongs to.
     */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
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
        return "/forum/{$this->forum->slug}/{$this->slug}";
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

        return $user->id === $this->user_id || $user->isAdmin();
    }

    /**
     * Check if user can delete this thread.
     */
    public function canDelete(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
    }

    /**
     * Get subscriptions for this thread.
     */
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

        $this->forum->updateStatistics();
    }
}
