<?php

namespace App\Models\Ticket;

use App\Models\User;
use App\Notifications\TicketActivityNotification;
use App\Notifications\TicketMentionNotification;
use App\Services\DiscordWebhookService;
use App\Services\MentionService;
use App\Support\DiscordEvents;
use App\Traits\Revisionable;
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
    use HasFactory, Revisionable, SoftDeletes;

    public const STATUSES = ['open', 'in_progress', 'pending', 'resolved', 'closed'];

    public const OPEN_STATUSES = ['open', 'in_progress', 'pending'];

    /**
     * Ticket types listed on the public feedback pages.
     */
    public const FEEDBACK_TYPES = ['bug', 'feature'];

    /**
     * Fields whose changes make up the ticket history (who changed what, when).
     */
    protected $revisionable = [
        'ticket_type_id',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to_user_id',
        'due_date',
        'is_public',
        'duplicate_of_ticket_id',
    ];

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
    public function notifyWatchers(Notification $notification, int|array|null $exceptUserIds = null): void
    {
        $except = array_filter((array) $exceptUserIds);

        $recipients = User::query()
            ->whereIn('id', $this->watchers()
                ->when($except, fn ($q) => $q->whereNotIn('user_id', $except))
                ->select('user_id'))
            ->get()
            ->filter(fn (User $user) => $this->isVisibleTo($user));

        NotificationFacade::send($recipients, $notification);
    }

    /**
     * Whether the member can open the ticket on any page: the ticket admin,
     * their own tickets (created or assigned) or the public feedback page.
     */
    public function canBeOpenedBy(User $user): bool
    {
        return $user->isAdmin()
            || (int) $this->created_by_user_id === (int) $user->id
            || (int) $this->assigned_to_user_id === (int) $user->id
            || ($this->is_public && $this->isFeedback());
    }

    /**
     * The page on which the member opens the ticket.
     */
    public function urlFor(User $user): string
    {
        if ($user->isAdmin()) {
            return "/admin/tickets/{$this->id}";
        }

        if ((int) $this->created_by_user_id === (int) $user->id || (int) $this->assigned_to_user_id === (int) $user->id) {
            return "/account/tickets/{$this->id}";
        }

        return "/feedback/{$this->id}";
    }

    /**
     * Notify members newly @mentioned in a description or comment. Mentions
     * already in the previous version, the author and members who cannot
     * open the ticket are skipped; internal notes only reach admins.
     * Returns the ids of the notified members.
     */
    public function notifyMentions(?string $html, ?string $previous, ?User $author, ?TicketComment $comment = null): array
    {
        if ( ! $author || blank($html)) {
            return [];
        }

        $recipients = app(MentionService::class)
            ->newMentions($html, $previous, $author)
            ->filter(fn (User $user) => $comment?->is_internal ? $user->isAdmin() : $this->canBeOpenedBy($user));

        NotificationFacade::send($recipients, new TicketMentionNotification($this, $author, $comment));

        return $recipients->pluck('id')->all();
    }

    /**
     * Tell the Discord channels about a new ticket. Public feedback links to
     * its own page; everything else to the ticket admin.
     */
    public function announceOnDiscord(): void
    {
        $feedback = $this->isFeedback() && $this->is_public;

        app(DiscordWebhookService::class)->announce(
            $feedback ? DiscordEvents::FEEDBACK_CREATED : DiscordEvents::TICKET_CREATED,
            [
                'title'       => $this->title,
                'description' => $this->description,
                'url'         => $feedback ? "/feedback/{$this->id}" : "/admin/tickets/{$this->id}",
                'author'      => $this->creator?->username,
                'fields'      => [
                    'Type'     => $this->ticketType?->name,
                    'Priority' => $this->priority_label,
                ],
            ]
        );
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

        static::created(function (Ticket $ticket): void {
            $ticket->notifyMentions($ticket->description, null, $ticket->creator);
            $ticket->announceOnDiscord();
        });

        static::updated(function (Ticket $ticket): void {
            if ($ticket->wasChanged('status')) {
                $ticket->notifyWatchers(new TicketActivityNotification($ticket, 'status'), Auth::id());
            }

            if ($ticket->wasChanged('description')) {
                $ticket->notifyMentions($ticket->description, $ticket->getOriginal('description'), Auth::user());
            }
        });
    }
}
