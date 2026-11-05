<?php

namespace App\Support;

/**
 * One permission per feature, and what each of them opens.
 *
 * Before this there were five: manage-page and manage-post between them
 * stood for the wiki, the forum, the gallery, the tickets, the menus,
 * the translations, the migration tool and the whole of the settings.
 * Handing somebody the forum meant handing them everything manage-page
 * covered, so in practice nothing was ever handed over and everything
 * was done by an admin.
 *
 * So each feature that has something to manage has a permission of its
 * own now, and the two old ones have shrunk to the feature they are
 * named after. `inherits` records which of them used to cover the work:
 * the migration reads it to give the new permission to every role that
 * already held the old one, so nobody loses access on the day this
 * lands.
 *
 * A feature with nothing to manage - chat, the timeline - has no
 * permission here. One would read as an offer to delegate something
 * that does not exist.
 *
 * The names are the whole set. They are what the API guards with
 * permission.any, what the router checks (resources/js/router/
 * middleware/permission.js) and what the admin menu hides itself
 * behind, so a name here that nothing checks is decoration, and a check
 * for a name that is not here is a door nobody but an admin can open.
 */
class FeaturePermissions
{
    /**
     * Whoever can hand out roles and permissions.
     *
     * Kept here rather than written out at each call: it is the answer
     * to "who may change this", and the escalation rules in
     * UserAccessController read the same constant.
     */
    public const ASSIGN = 'manage-user';

    /**
     * name => [label, category, inherits, description].
     *
     * `inherits` is the coarse permission that used to cover this work,
     * or null where it was an admin's alone.
     */
    private const PERMISSIONS = [
        // ── Who may do what ──────────────────────────────────────────
        'manage-user' => [
            'Members', 'access', null,
            'Edit members, and give them roles and permissions',
        ],
        'manage-role' => [
            'Roles and permissions', 'access', null,
            'Create roles and decide what each of them may do',
        ],
        'manage-permission' => [
            'Permissions', 'access', null,
            'Edit the permissions themselves',
        ],

        // ── Written content ──────────────────────────────────────────
        'manage-page' => [
            'Pages', 'content', null,
            'Write and publish the standalone pages',
        ],
        'manage-post' => [
            'Posts', 'content', null,
            'Write and publish posts',
        ],
        'manage-wiki' => [
            'Wiki', 'content', 'manage-page',
            'Write wiki articles and the categories they sit in',
        ],
        'manage-announcement' => [
            'Announcements', 'content', 'manage-post',
            'Put an announcement in front of every member',
        ],
        'manage-taxonomy' => [
            'Taxonomies and terms', 'content', 'manage-page',
            'The vocabularies content is filed under, and their entries',
        ],
        'manage-relation' => [
            'Related content', 'content', 'manage-page',
            'What the site offers to read next',
        ],

        // ── Pictures and files ───────────────────────────────────────
        'manage-media' => [
            'Media library', 'media', 'manage-page',
            'Everything uploaded to the site, whoever uploaded it',
        ],
        'manage-gallery' => [
            'Gallery', 'media', 'manage-post',
            'The albums and the pictures in them',
        ],

        // ── The community ────────────────────────────────────────────
        'manage-forum' => [
            'Forum', 'community', 'manage-page',
            'The forum categories, and moderating what is in them',
        ],
        'manage-event' => [
            'Events', 'community', 'manage-post',
            'The calendar, the kinds of event and the sign-up forms',
        ],
        'manage-poll' => [
            'Polls', 'community', 'manage-post',
            'Close, reopen and remove the polls members have made',
        ],
        'manage-ticket' => [
            'Tickets and feedback', 'community', 'manage-page',
            'The support queue, and the bug reports members file',
        ],
        'manage-achievement' => [
            'Achievements', 'community', null,
            'Set up what members can earn, and what it is worth',
        ],
        'award-achievements' => [
            'Award achievements', 'community', null,
            'Hand achievements to members for things that happened away from the site',
        ],
        'manage-rank' => [
            'Ranks', 'community', null,
            'The ladder members climb with their points',
        ],
        'manage-podcast' => [
            'Podcast', 'community', null,
            'Create shows, write episodes and upload their audio, without the admin panel',
        ],
        'manage-mailings' => [
            'Mailings', 'community', null,
            'Write and send mail to the members, and edit the templates the site sends',
        ],
        'manage-irc' => [
            'IRC', 'community', null,
            'The servers, the channels and the daemon that holds them',
        ],

        // ── The site itself ──────────────────────────────────────────
        'manage-setting' => [
            'Site settings', 'site', 'manage-post',
            'The name, the look, the addresses and what is switched on',
        ],
        'manage-menu' => [
            'Menus', 'site', 'manage-page',
            'The navigation, and what each entry of it points at',
        ],
        'manage-homepage' => [
            'Homepage and footer', 'site', null,
            'The sections and widgets the front page and the footer are built from',
        ],
        'manage-translation' => [
            'Translations', 'site', 'manage-page',
            'The wording of the interface in every language',
        ],
        'manage-help' => [
            'Tours and hints', 'site', null,
            'Walk members through a page, or put a hint beside the things on it',
        ],
        'manage-discord' => [
            'Discord', 'site', null,
            'The webhooks the site announces itself through',
        ],

        // ── Tools ────────────────────────────────────────────────────
        'manage-migration' => [
            'Migration tool', 'tools', 'manage-page',
            'Import an old community, and the reports that come after',
        ],
    ];

    /**
     * Which permissions a new role is offered by default.
     *
     * The three that were marked this way before this list existed -
     * nothing else is given away without being asked for.
     */
    private const DEFAULTS = [
        'manage-permission',
        'manage-role',
        'manage-user',
        'manage-page',
        'manage-post',
    ];

    /**
     * What each category is called, in the order the screens show them.
     */
    private const CATEGORIES = [
        'access'    => 'Who may do what',
        'content'   => 'Content',
        'media'     => 'Pictures and files',
        'community' => 'Community',
        'site'      => 'The site itself',
        'tools'     => 'Tools',
    ];

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::PERMISSIONS);
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::PERMISSIONS);
    }

    /**
     * Every permission as a row, ready to be written to the database or
     * handed to a screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $rows = [];

        foreach (self::PERMISSIONS as $name => [$label, $category, $inherits, $description]) {
            $rows[] = [
                'name'         => $name,
                'display_name' => $label,
                'category'     => $category,
                'description'  => $description,
                'inherits'     => $inherits,
                'is_default'   => in_array($name, self::DEFAULTS, true),
            ];
        }

        return $rows;
    }

    /**
     * The coarse permission a feature's work used to belong to, for
     * every permission that was carved out of one.
     *
     * @return array<string, string>
     */
    public static function inherited(): array
    {
        $map = [];

        foreach (self::PERMISSIONS as $name => [, , $inherits]) {
            if ($inherits !== null) {
                $map[$name] = $inherits;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }
}
