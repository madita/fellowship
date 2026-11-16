<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Status\Status;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone wrote @username in a timeline post or in a comment on one.
 */
class StatusMentionNotification extends Notification
{
    use Queueable;

    /**
     * The payload keys below are matched on by the frontend presenter and
     * its tests, so they stay exactly as they were when comments had a
     * table of their own.
     */
    public function __construct(
        protected Status $status,
        protected ?Comment $comment = null
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
        return $this->comment ? 'status_comment_mention' : 'status_mention';
    }

    public function toArray($notifiable): array
    {
        $source = $this->comment ?? $this->status;

        return [
            'type'         => $this->comment ? 'status_comment_mention' : 'status_mention',
            'status_id'    => $this->status->id,
            'comment_id'   => $this->comment?->id,
            'url'          => '/timeline',
            'mentioned_by' => $source->user->username,
            'excerpt'      => Str::limit(trim(strip_tags($source->content)), 100),
        ];
    }
}
