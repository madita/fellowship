<?php

namespace App\Notifications;

use App\Models\Rank;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent when a member's points carry them into a rank they had not reached
 * before.
 */
class RankReached extends Notification
{
    use Queueable;

    public function __construct(protected Rank $rank, protected int $points)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return array_filter([
            'type'      => 'rank_reached',
            'rank_id'   => $this->rank->id,
            'rank_name' => $this->rank->name,
            'icon'      => $this->rank->icon,
            'image'     => $this->rank->image_url,
            'color'     => $this->rank->color,
            'points'    => $this->points,
            'url'       => '/achievements',
        ], fn ($value) => $value !== null);
    }
}
