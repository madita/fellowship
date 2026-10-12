<?php

namespace App\Support;

use App\Models\Status\Status;
use App\Models\Wiki;

/**
 * What can be commented on.
 *
 * The API takes a kind — "wiki", "status" — and never a class name. A
 * client able to name the class could hang a comment off any model in the
 * application and read its title back out, so the mapping lives here and
 * anything not listed is refused.
 *
 * Same shape as Watchables and Voteables.
 */
class Commentables
{
    /**
     * kind => model class, English label, icon.
     */
    private const REGISTRY = [
        'wiki'   => ['type' => Wiki::class, 'label' => 'Wiki page', 'icon' => 'mdi-book-open-page-variant'],
        'status' => ['type' => Status::class, 'label' => 'Timeline post', 'icon' => 'mdi-timeline-text-outline'],
    ];

    public static function typeForKind(?string $kind): ?string
    {
        return self::REGISTRY[$kind ?? '']['type'] ?? null;
    }

    public static function kindForType(?string $type): ?string
    {
        if ( ! $type) {
            return null;
        }

        foreach (self::REGISTRY as $kind => $entry) {
            if ($entry['type'] === $type) {
                return $kind;
            }
        }

        return null;
    }

    public static function kinds(): array
    {
        return array_keys(self::REGISTRY);
    }
}
