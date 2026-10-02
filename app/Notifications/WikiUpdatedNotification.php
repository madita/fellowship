<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A wiki page someone is watching has been edited.
 *
 * Watching a page is only worth anything if it says something when the page
 * changes, which is what this is for.
 */
class WikiUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Wiki $wiki,
        protected User $editor
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'wiki_updated',
            'wiki_id'    => $this->wiki->id,
            'wiki_title' => $this->wiki->title,
            'url'        => $this->wiki->watchUrl(),
            'editor'     => $this->editor->username,
        ];
    }
}
