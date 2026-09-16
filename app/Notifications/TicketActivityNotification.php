<?php

namespace App\Notifications;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to the watchers of a feedback ticket when it gets a public comment
 * (`ticket_comment`) or its status changes (`ticket_status`).
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

    public function toArray($notifiable): array
    {
        return array_filter([
            'type'           => 'ticket_' . $this->event,
            'ticket_id'      => $this->ticket->id,
            'ticket_title'   => $this->ticket->title,
            'url'            => '/feedback/' . $this->ticket->id,
            'status'         => $this->event === 'status' ? $this->ticket->status : null,
            'comment_author' => $this->comment?->user?->username,
            'excerpt'        => $this->comment ? Str::limit($this->comment->comment, 100) : null,
        ], fn ($value) => $value !== null);
    }
}
