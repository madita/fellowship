<?php

namespace App\Notifications;

use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ForumReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected ForumThread $thread,
        protected ForumPost $post
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'forum_reply',
            'thread_id' => $this->thread->id,
            'thread_title' => $this->thread->title,
            'thread_url' => $this->thread->url,
            'post_id' => $this->post->id,
            'post_author' => $this->post->author->username,
            'post_excerpt' => Str::limit(strip_tags($this->post->body), 100),
        ];
    }
}
