<?php

namespace App\Models\Status;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'content',
        'media',
        'feeling',
        'likes_count',
        'comments_count',
    ];

    protected $casts = [
        'media' => 'array',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
    ];

    protected $with = ['user'];
    protected $appends = ['is_liked_by_me', 'time_ago'];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($status) {
            // Could trigger notifications here
        });
    }

    /**
     * Get the user who posted the status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all likes for this status.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(StatusLike::class);
    }

    /**
     * Get all comments for this status.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(StatusComment::class)->whereNull('parent_id')->orderBy('created_at');
    }

    /**
     * Get all comments including replies.
     */
    public function allComments(): HasMany
    {
        return $this->hasMany(StatusComment::class)->orderBy('created_at');
    }

    /**
     * Check if current user has liked this status.
     */
    public function getIsLikedByMeAttribute(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        return $this->likes()
            ->where('user_id', auth()->id())
            ->exists();
    }

    /**
     * Get human-readable time ago.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Toggle like from user.
     */
    public function toggleLike(User $user, string $reactionType = 'like'): bool
    {
        $existingLike = $this->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
            $this->decrement('likes_count');
            return false;
        }

        $this->likes()->create([
            'user_id' => $user->id,
            'reaction_type' => $reactionType,
        ]);
        $this->increment('likes_count');

        return true;
    }

    /**
     * Add comment to status.
     */
    public function addComment(User $user, string $content, int $parentId = null): StatusComment
    {
        $comment = $this->comments()->create([
            'user_id' => $user->id,
            'content' => $content,
            'parent_id' => $parentId,
        ]);

        $this->increment('comments_count');

        return $comment;
    }

    /**
     * Check if user can edit this status.
     */
    public function canEdit(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
    }

    /**
     * Check if user can delete this status.
     */
    public function canDelete(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
    }

    /**
     * Scope: Statuses from specific user.
     */
    public function scopeFromUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Recent statuses (ordered by newest first).
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }
}
