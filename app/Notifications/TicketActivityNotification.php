<?php

namespace App\Notifications;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Something happened to a ticket somebody is following.
 *
 * `ticket_comment` when it gets a public comment, `ticket_status` when
 * its status changes, `ticket_created` when it is opened — the last of
 * those going to the people who handle tickets rather than to watchers,
 * since nobody can be watching a ticket that did not exist yet.
 */
class TicketActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected string $event,
        protected ?TicketComment $comment = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * What the socket says this is, matching the payload's own `type`.
     *
     * Without it Laravel broadcasts the class name under that key and
     * overwrites it, leaving the browser a notification it cannot read.
     */
    public function broadcastType(): string
    {
        return 'ticket_' . $this->event;
    }

    public function toArray($notifiable): array
    {
        return array_filter([
            'type'           => 'ticket_' . $this->event,
            'ticket_id'      => $this->ticket->id,
            'ticket_title'   => $this->ticket->title,
            // Where this particular recipient opens it. The link used to
            // be /feedback/{id} for everybody, which is the one page an
            // admin handling a support ticket cannot use — and for a
            // ticket that is not feedback at all, nobody could.
            'url'            => $notifiable instanceof User
                ? $this->ticket->urlFor($notifiable)
                : '/feedback/' . $this->ticket->id,
            'status'         => $this->event === 'status' ? $this->ticket->status : null,
            'ticket_type'    => $this->ticket->ticketType?->name,
            'creator'        => $this->event === 'created' ? $this->ticket->creator?->username : null,
            'comment_author' => $this->comment?->user?->username,
            'excerpt'        => $this->comment ? Str::limit($this->comment->comment, 100) : null,
        ], fn ($value) => $value !== null);
    }
}
