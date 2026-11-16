<?php

namespace App\Support;

use App\Models\Forum\ForumThread;
use App\Models\Ticket\Ticket;
use App\Models\Wiki;

/**
 * What can be watched.
 *
 * The API takes a kind — "ticket", "forum-thread" — and never a class name.
 * A client that could name the class could point a watch row at any model in
 * the application, so the mapping lives here and anything not listed is
 * refused.
 *
 * Shaped after RelateableHelper, which solves the same problem for links.
 */
class Watchables
{
    /**
     * kind => model class, English label, icon.
     */
    private const REGISTRY = [
        'forum-thread' => ['type' => ForumThread::class, 'label' => 'Forum thread', 'icon' => 'mdi-forum-outline'],
        'ticket'       => ['type' => Ticket::class, 'label' => 'Ticket', 'icon' => 'mdi-ticket-outline'],
        'wiki'         => ['type' => Wiki::class, 'label' => 'Wiki page', 'icon' => 'mdi-book-open-page-variant'],
    ];

    /**
     * The model class behind a kind, or null when the kind is not watchable.
     */
    public static function typeForKind(?string $kind): ?string
    {
        return self::REGISTRY[$kind ?? '']['type'] ?? null;
    }

    /**
     * The kind for a model class, for handing a watch back to the client.
     */
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

    /**
     * Every kind that may be watched, for validation.
     */
    public static function kinds(): array
    {
        return array_keys(self::REGISTRY);
    }

    public static function iconForType(?string $type): ?string
    {
        $kind = self::kindForType($type);

        return $kind ? self::REGISTRY[$kind]['icon'] : null;
    }

    public static function labelForType(?string $type): ?string
    {
        $kind = self::kindForType($type);

        return $kind ? self::REGISTRY[$kind]['label'] : null;
    }
}
