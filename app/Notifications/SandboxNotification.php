<?php

namespace App\Notifications;

use App\Models\Sandbox\Sandbox;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SandboxNotification extends Notification
{
    use Queueable;

    /**
     * Supported actions:
     * - shared: sandbox shared with user (collaborator added)
     * - removed: user removed from sandbox
     * - comment: new comment thread on sandbox
     * - reply: reply to a comment thread
     * - resolved: comment thread resolved
     * - invite_accepted: collaborator accepted invite
     */
    public function __construct(
        protected Sandbox $sandbox,
        protected string $action,
        protected User $actor,
        protected array $extra = []
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        $data = [
            'type' => 'sandbox_' . $this->action,
            'sandbox_id' => $this->sandbox->id,
            'sandbox_uuid' => $this->sandbox->uuid,
            'sandbox_title' => $this->sandbox->title,
            'actor_id' => $this->actor->id,
            'actor_username' => $this->actor->username,
            'subject' => $this->getSubject(),
            'body' => $this->getBody(),
            'url' => '/sandbox/' . $this->sandbox->uuid,
        ];

        return array_merge($data, $this->extra);
    }

    protected function getSubject(): string
    {
        return match ($this->action) {
            'shared' => 'Sandbox shared with you',
            'removed' => 'Removed from sandbox',
            'comment' => 'New comment on sandbox',
            'reply' => 'New reply on your comment',
            'resolved' => 'Comment thread resolved',
            'invite_accepted' => 'Invite accepted',
            default => 'Sandbox notification',
        };
    }

    protected function getBody(): string
    {
        $actor = $this->actor->username;
        $title = $this->sandbox->title;

        return match ($this->action) {
            'shared' => "{$actor} shared \"{$title}\" with you" . (isset($this->extra['role']) ? " as {$this->extra['role']}" : ''),
            'removed' => "{$actor} removed you from \"{$title}\"",
            'comment' => "{$actor} commented on \"{$title}\"" . (isset($this->extra['quote']) ? ": \"{$this->extra['quote']}\"" : ''),
            'reply' => "{$actor} replied to your comment on \"{$title}\"" . (isset($this->extra['excerpt']) ? ": \"{$this->extra['excerpt']}\"" : ''),
            'resolved' => "{$actor} resolved a comment thread on \"{$title}\"",
            'invite_accepted' => "{$actor} accepted your invitation to \"{$title}\"",
            default => "{$actor} updated \"{$title}\"",
        };
    }
}
