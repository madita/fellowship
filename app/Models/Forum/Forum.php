<?php

namespace App\Models\Forum;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Forum extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'position',
        'is_private',
        'is_locked',
        'threads_count',
        'posts_count',
        'last_post_id',
        'last_post_at',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_locked' => 'boolean',
        'threads_count' => 'integer',
        'posts_count' => 'integer',
        'last_post_at' => 'datetime',
    ];

    protected $appends = ['url'];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($forum) {
            if (empty($forum->slug)) {
                $forum->slug = Str::slug($forum->name);
            }
        });
    }

    /**
     * Get the parent forum.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Forum::class, 'parent_id');
    }

    /**
     * Get child forums (sub-forums).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Forum::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get all threads in this forum.
     */
    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class)->orderByDesc('is_pinned')->orderByDesc('last_post_at');
    }

    /**
     * Get the last post in this forum.
     */
    public function lastPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'last_post_id');
    }

    /**
     * Get the URL attribute.
     */
    public function getUrlAttribute(): string
    {
        return "/forum/{$this->slug}";
    }

    /**
     * Increment thread count.
     */
    public function incrementThreadCount(): void
    {
        $this->increment('threads_count');
    }

    /**
     * Decrement thread count.
     */
    public function decrementThreadCount(): void
    {
        $this->decrement('threads_count');
    }

    /**
     * Update forum statistics.
     */
    public function updateStatistics(): void
    {
        $lastPost = ForumPost::whereHas('thread', function ($query) {
            $query->where('forum_id', $this->id);
        })
        ->latest()
        ->first();

        $this->update([
            'threads_count' => $this->threads()->count(),
            'posts_count' => ForumPost::whereHas('thread', function ($query) {
                $query->where('forum_id', $this->id);
            })->count(),
            'last_post_id' => $lastPost?->id,
            'last_post_at' => $lastPost?->created_at,
        ]);
    }

    /**
     * Check if user can access this forum.
     */
    public function canAccess(User $user = null): bool
    {
        if (!$this->is_private) {
            return true;
        }

        if (!$user) {
            return false;
        }

        return $user->isAdmin();
    }
}
