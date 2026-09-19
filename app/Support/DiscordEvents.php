<?php

namespace App\Support;

/**
 * What a Discord webhook can announce. The keys are stored on the webhook
 * and named in the admin settings; the colour is the stripe of the embed.
 */
class DiscordEvents
{
    public const WIKI_PAGE_APPROVED = 'wiki_page_approved';

    public const WIKI_PAGE_SUBMITTED = 'wiki_page_submitted';

    public const TICKET_CREATED = 'ticket_created';

    public const FEEDBACK_CREATED = 'feedback_created';

    public const FORUM_THREAD_CREATED = 'forum_thread_created';

    public const POST_PUBLISHED = 'post_published';

    public const EVENT_CREATED = 'event_created';

    public const EVENT_GUEST_JOINED = 'event_guest_joined';

    public const ANNOUNCEMENT_POSTED = 'announcement_posted';

    /**
     * Colour per event, as Discord wants it: a decimal RGB value.
     */
    public const COLORS = [
        self::WIKI_PAGE_APPROVED   => 0x2ECC71,
        self::WIKI_PAGE_SUBMITTED  => 0xF1C40F,
        self::TICKET_CREATED       => 0x3498DB,
        self::FEEDBACK_CREATED     => 0x9B59B6,
        self::FORUM_THREAD_CREATED => 0xE67E22,
        self::POST_PUBLISHED       => 0x1ABC9C,
        self::EVENT_CREATED        => 0xE91E63,
        self::EVENT_GUEST_JOINED   => 0x00BCD4,
        self::ANNOUNCEMENT_POSTED  => 0xFF5722,
    ];

    /**
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::COLORS);
    }

    /**
     * The languages messages can be written in: the ones the site is translated to.
     */
    public static function locales(): array
    {
        return collect(glob(lang_path('*'), GLOB_ONLYDIR))
            ->map(fn (string $path) => basename($path))
            ->values()
            ->all();
    }

    public static function color(string $event): int
    {
        return self::COLORS[$event] ?? 0x5865F2;
    }
}
