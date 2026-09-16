<?php

namespace App\Models\Ticket;

use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;

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
        'is_public',
        'duplicate_of_ticket_id',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'due_date'    => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at'   => 'datetime',
        'is_public'   => 'boolean',
    ];

    protected $appends = ['status_label', 'priority_label'];

    public const STATUSES = ['open', 'in_progress', 'pending', 'resolved', 'closed'];

    public const OPEN_STATUSES = ['open', 'in_progress', 'pending'];

    /**
     * Ticket types listed on the public feedback pages.
     */
    public const FEEDBACK_TYPES = ['bug', 'feature'];

    protected static function booted(): void
    {
        // resolved_at / closed_at follow the status, whichever way it is changed
        static::saving(function (Ticket $ticket): void {
            if ( ! $ticket->isDirty('status')) {
                return;
            }

            if ($ticket->status === 'resolved') {
                $ticket->resolved_at ??= now();
            } elseif ($ticket->status === 'closed') {
                $ticket->closed_at ??= now();
            } elseif (in_array($ticket->status, self::OPEN_STATUSES, true)) {
                $ticket->resolved_at = null;
                $ticket->closed_at   = null;
            }
        });

        static::updated(function (Ticket $ticket): void {
            if ($ticket->wasChanged('status')) {
                $ticket->notifyWatchers(new TicketActivityNotification($ticket, 'status'), Auth::id());
            }
        });
    }

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
        return match ($this->status) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'pending'     => 'Pending',
            'resolved'    => 'Resolved',
            'closed'      => 'Closed',
            default       => ucfirst($this->status),
        };
    }

    /**
     * Get priority label for UI.
     */
    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low'    => 'Low',
            'normal' => 'Normal',
            'high'   => 'High',
            'urgent' => 'Urgent',
            default  => ucfirst($this->priority),
        };
    }

    /**
     * Check if ticket is open (not closed).
     */
    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
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
            'status'              => $this->status === 'open' ? 'in_progress' : $this->status,
        ]);
    }

    /**
     * Unassign ticket.
     */
    public function unassign(): void
    {
        $this->update([
            'assigned_to_user_id' => null,
            'status'              => $this->status === 'in_progress' ? 'open' : $this->status,
        ]);
    }

    /**
     * Mark ticket as resolved.
     */
    public function resolve(): void
    {
        $this->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    /**
     * Close ticket.
     */
    public function close(): void
    {
        $this->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Reopen ticket.
     */
    public function reopen(): void
    {
        $this->update([
            'status'      => 'open',
            'resolved_at' => null,
            'closed_at'   => null,
        ]);
    }

    /**
     * Scope: Open tickets.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
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

    // ── Public feedback (bug reports and feature requests) ──────────

    /**
     * Get all votes for this ticket.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(TicketVote::class);
    }

    /**
     * Get the users watching this ticket.
     */
    public function watchers(): HasMany
    {
        return $this->hasMany(TicketWatcher::class);
    }

    /**
     * Get the tags of this ticket.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TicketTag::class);
    }

    /**
     * Get the original ticket if this one is a duplicate.
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'duplicate_of_ticket_id');
    }

    /**
     * Get the tickets marked as duplicates of this one.
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(Ticket::class, 'duplicate_of_ticket_id');
    }

    /**
     * Whether this is a bug report or feature request.
     */
    public function isFeedback(): bool
    {
        return in_array($this->ticketType?->slug, self::FEEDBACK_TYPES, true);
    }

    /**
     * Public tickets are visible to everyone, private ones to their creator and admins.
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->is_public
            || ($user && ((int) $this->created_by_user_id === (int) $user->id || $user->isAdmin()));
    }

    /**
     * Add or remove the user's vote; returns whether the user has voted now.
     */
    public function toggleVote(User $user): bool
    {
        if ($this->votes()->where('user_id', $user->id)->delete()) {
            return false;
        }

        $this->votes()->createOrFirst(['user_id' => $user->id]);

        return true;
    }

    /**
     * Start or stop watching; returns whether the user is watching now.
     */
    public function toggleWatch(User $user): bool
    {
        if ($this->watchers()->where('user_id', $user->id)->delete()) {
            return false;
        }

        $this->watch($user);

        return true;
    }

    /**
     * Watch the ticket (no-op when already watching).
     */
    public function watch(User $user): void
    {
        $this->watchers()->createOrFirst(['user_id' => $user->id]);
    }

    /**
     * Notify everyone watching the ticket, except the member who caused it
     * and watchers who can no longer see the ticket.
     */
    public function notifyWatchers(Notification $notification, ?int $exceptUserId = null): void
    {
        $recipients = User::query()
            ->whereIn('id', $this->watchers()
                ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
                ->select('user_id'))
            ->get()
            ->filter(fn (User $user) => $this->isVisibleTo($user));

        NotificationFacade::send($recipients, $notification);
    }

    /**
     * Scope: Bug reports and feature requests.
     */
    public function scopeFeedback($query)
    {
        return $query->whereHas('ticketType', function ($q) {
            $q->whereIn('slug', self::FEEDBACK_TYPES);
        });
    }

    /**
     * Scope: Tickets the user may see (see isVisibleTo()).
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('is_public', true)
                ->when($user, fn ($q) => $q->orWhere('created_by_user_id', $user->id));
        });
    }
}
