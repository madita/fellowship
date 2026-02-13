<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'comment',
        'is_internal',
        'is_official',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_official' => 'boolean',
    ];

    protected $with = ['user'];

    /**
     * Get the ticket this comment belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who wrote the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user can edit this comment.
     */
    public function canEdit(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Allow editing within 15 minutes or if admin
        $editWindow = now()->subMinutes(15);
        return ($user->id === $this->user_id && $this->created_at->gt($editWindow)) || $user->isAdmin();
    }

    /**
     * Check if user can delete this comment.
     */
    public function canDelete(User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isAdmin();
    }

    /**
     * Check if this is an official developer response.
     */
    public function isOfficial(): bool
    {
        return $this->is_official === true;
    }

    /**
     * Mark comment as official developer response.
     */
    public function markAsOfficial(): void
    {
        $this->update(['is_official' => true]);
    }

    /**
     * Scope: Official comments only.
     */
    public function scopeOfficial($query)
    {
        return $query->where('is_official', true);
    }
}
