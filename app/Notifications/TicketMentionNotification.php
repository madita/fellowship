<?php

namespace App\Notifications;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone wrote @username in a ticket description or comment. The link
 * leads to the page the member can open: the ticket admin, their own
 * tickets or the public feedback page.
 */
class TicketMentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected User $mentionedBy,
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
        return 'ticket_mention';
    }

    public function toArray($notifiable): array
    {
        $source = $this->comment?->comment ?? $this->ticket->description ?? '';

        return [
            'type'         => 'ticket_mention',
            'ticket_id'    => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'comment_id'   => $this->comment?->id,
            'url'          => $this->ticket->urlFor($notifiable),
            'mentioned_by' => $this->mentionedBy->username,
            'excerpt'      => Str::limit(trim(html_entity_decode(strip_tags($source))), 100),
        ];
    }
}
