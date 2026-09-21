<?php

namespace App\Notifications;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent the moment a member earns an achievement — whether the site counted
 * it for them or an admin handed it over.
 */
class AchievementEarned extends Notification
{
    use Queueable;

    public function __construct(
        protected Achievement $achievement,
        protected ?User $awardedBy = null,
        protected ?string $note = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return array_filter([
            'type'             => 'achievement_earned',
            'achievement_id'   => $this->achievement->id,
            'achievement_name' => $this->achievement->name,
            'icon'             => $this->achievement->icon,
            'image'            => $this->achievement->image_url,
            'color'            => $this->achievement->color,
            'points'           => $this->achievement->points,
            'url'              => '/achievements',
            // Only set when someone handed it over rather than the site
            // counting it, so the member knows it came from a person
            'awarded_by' => $this->awardedBy?->username,
            'note'       => $this->note,
        ], fn ($value) => $value !== null);
    }
}
