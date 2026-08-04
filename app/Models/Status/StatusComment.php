<?php

namespace App\Models\Status;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'status_id',
        'user_id',
        'parent_id',
        'content',
        'likes_count',
    ];

    protected $casts = [
        'likes_count' => 'integer',
    ];

    protected $with = ['user'];

    protected $appends = ['time_ago'];

    /**
     * Get the status this comment belongs to.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the user who commented.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (for nested replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(StatusComment::class, 'parent_id');
    }

    /**
     * Get child replies.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(StatusComment::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * Get human-readable time ago.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if user can edit this comment.
     */
    public function canEdit(?User $user = null): bool
    {
        if (! $user) {
            return false;
        }

        // Allow editing within 15 minutes or if admin
        $editWindow = now()->subMinutes(15);

        return ($user->id === $this->user_id && $this->created_at->gt($editWindow)) || $user->isAdmin();
    }

    /**
     * Check if user can delete this comment.
     */
    public function canDelete(?User $user = null): bool
    {
        if (! $user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
    }
}
