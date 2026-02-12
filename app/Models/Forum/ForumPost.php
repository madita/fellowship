<?php

namespace App\Models\Forum;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumPost extends Model
{
    use HasFactory, SoftDeletes;

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
            $post->thread->forum->updateStatistics();
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
    public function canEdit(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Allow editing within 1 hour or if admin
        $editWindow = now()->subHour();
        return ($user->id === $this->user_id && $this->created_at->gt($editWindow)) || $user->isAdmin();
    }

    /**
     * Check if user can delete this post.
     */
    public function canDelete(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
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
}
