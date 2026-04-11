<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_type_id',
        'ticketable_type',
        'ticketable_id',
        'created_by_user_id',
        'assigned_to_user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'due_date' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'priority_label'];

    /**
     * Get the ticket type.
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * Get the ticketable entity (polymorphic).
     */
    public function ticketable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created the ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the assigned user.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get all comments for this ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }

    /**
     * Get public comments only.
     */
    public function publicComments(): HasMany
    {
        return $this->hasMany(TicketComment::class)
            ->where('is_internal', false)
            ->orderBy('created_at');
    }

    /**
     * Get internal comments only.
     */
    public function internalComments(): HasMany
    {
        return $this->hasMany(TicketComment::class)
            ->where('is_internal', true)
            ->orderBy('created_at');
    }

    /**
     * Get status label for UI.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get priority label for UI.
     */
    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
            default => ucfirst($this->priority),
        };
    }

    /**
     * Check if ticket is open (not closed).
     */
    public function isOpen(): bool
    {
        return !in_array($this->status, ['resolved', 'closed']);
    }

    /**
     * Check if ticket is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->assigned_to_user_id !== null;
    }

    /**
     * Assign ticket to a user.
     */
    public function assignTo(User $user): void
    {
        $this->update([
            'assigned_to_user_id' => $user->id,
            'status' => $this->status === 'open' ? 'in_progress' : $this->status,
        ]);
    }

    /**
     * Unassign ticket.
     */
    public function unassign(): void
    {
        $this->update([
            'assigned_to_user_id' => null,
            'status' => $this->status === 'in_progress' ? 'open' : $this->status,
        ]);
    }

    /**
     * Mark ticket as resolved.
     */
    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    /**
     * Close ticket.
     */
    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Reopen ticket.
     */
    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Scope: Open tickets.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress', 'pending']);
    }

    /**
     * Scope: Assigned to user.
     */
    public function scopeAssignedTo($query, User $user)
    {
        return $query->where('assigned_to_user_id', $user->id);
    }

    /**
     * Scope: Created by user.
     */
    public function scopeCreatedBy($query, User $user)
    {
        return $query->where('created_by_user_id', $user->id);
    }

    /**
     * Scope: By ticket type.
     */
    public function scopeOfType($query, string $typeSlug)
    {
        return $query->whereHas('ticketType', function ($q) use ($typeSlug) {
            $q->where('slug', $typeSlug);
        });
    }
}
