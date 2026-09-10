<?php

namespace App\Models\Status;

use App\Models\Concerns\HasPolls;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Status extends Model implements HasMedia
{
    use HasFactory, HasPolls, InteractsWithMedia, SoftDeletes;

    /**
     * Relations needed to build the `poll` attribute without extra queries.
     */
    public const POLL_RELATIONS = ['latestPoll.creator', 'latestPoll.options', 'latestPoll.votes'];

    protected $fillable = [
        'user_id',
        'content',
        'feeling',
        'likes_count',
        'comments_count',
    ];

    protected $casts = [
        'likes_count'    => 'integer',
        'comments_count' => 'integer',
    ];

    protected $with = ['user', 'media'];

    // The raw relation is only a data source for the `poll` attribute
    protected $hidden = ['latestPoll'];

    protected $appends = ['is_liked_by_me', 'time_ago', 'media_urls', 'poll'];

    /**
     * The status' latest poll formatted for the current user (null when
     * there is none). Controllers eager load POLL_RELATIONS on lists so
     * this does not query per status.
     */
    public function getPollAttribute(): ?array
    {
        if ( ! $this->exists) {
            return null;
        }

        $this->loadMissing(self::POLL_RELATIONS);

        return $this->latestPoll?->toPayload(auth()->user());
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400);
    }

    /**
     * Get array of media URLs for the frontend.
     */
    public function getMediaUrlsAttribute(): array
    {
        return $this->getMedia('images')->map(fn ($media) => $media->getUrl())->toArray();
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
        if ( ! auth()->check()) {
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
            'user_id'       => $user->id,
            'reaction_type' => $reactionType,
        ]);
        $this->increment('likes_count');

        return true;
    }

    /**
     * Add comment to status.
     */
    public function addComment(User $user, string $content, ?int $parentId = null): StatusComment
    {
        $comment = $this->comments()->create([
            'user_id'   => $user->id,
            'content'   => $content,
            'parent_id' => $parentId,
        ]);

        $this->increment('comments_count');

        return $comment;
    }

    /**
     * Check if user can edit this status.
     */
    public function canEdit(?User $user = null): bool
    {
        if ( ! $user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
    }

    /**
     * Check if user can delete this status.
     */
    public function canDelete(?User $user = null): bool
    {
        if ( ! $user) {
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
}
