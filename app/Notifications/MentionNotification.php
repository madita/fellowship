<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone wrote @username in content written with one of the editors: a
 * wiki page, a page, an event, a forum thread and so on. Timeline posts,
 * forum posts and tickets carry their own notification with more context.
 */
class MentionNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $context  what was written in: wiki, page, post, event …
     * @param  string  $title  what the reader will recognise it by
     * @param  string  $url  where the mention can be read
     */
    public function __construct(
        protected string $context,
        protected string $title,
        protected string $url,
        protected User $mentionedBy,
        protected ?string $excerpt = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return array_filter([
            'type'         => 'mention',
            'context'      => $this->context,
            'subject'      => $this->title,
            'url'          => $this->url,
            'mentioned_by' => $this->mentionedBy->username,
            'excerpt'      => $this->excerpt ? Str::limit(trim(html_entity_decode(strip_tags($this->excerpt))), 120) : null,
        ], fn ($value) => $value !== null);
    }
}
