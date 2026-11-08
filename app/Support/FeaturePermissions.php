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
     * The features whose work is split by what is being done to it,
     * rather than held whole.
     *
     * feature => [plural noun for the wording, category, actions]
     *
     * `manage-<feature>` stays, and stays the umbrella: holding it
     * satisfies every action below it, so nothing that asks for
     * `manage-podcast` today has to change and nobody loses anything.
     * What the split adds is the ability to hand over one action
     * without the others - which is the whole point of it. Somebody
     * trusted enough to write a podcast is given `create-podcast`, and
     * what they write waits for `approve-podcast` to let it out; that
     * is not expressible with one permission per feature.
     *
     * Only the features members themselves put things into are here. A
     * feature nobody but staff touches - the settings, the migration
     * tool, the IRC daemon - is held whole, because splitting it would
     * mint names nothing ever checks.
     *
     * `approve` appears only where something really waits to be
     * approved, which today is the wiki and the podcast: the two models
     * using the Approvable trait, and the two with an approval ticket
     * type. See App\Support\Approvals. An `approve-forum` with no queue
     * behind it would be decoration.
     */
    private const CRUD = [
        'page'    => ['pages', 'content', ['view', 'create', 'edit', 'delete']],
        'post'    => ['posts', 'content', ['view', 'create', 'edit', 'delete']],
        'wiki'    => ['wiki articles', 'content', ['view', 'create', 'edit', 'delete', 'approve']],
        'gallery' => ['albums', 'media', ['view', 'create', 'edit', 'delete']],
        'forum'   => ['forum categories', 'community', ['view', 'create', 'edit', 'delete']],
        'event'   => ['events', 'community', ['view', 'create', 'edit', 'delete']],
        'podcast' => ['podcasts', 'community', ['view', 'create', 'edit', 'delete', 'approve']],
    ];

    /**
     * What each action is called and what it says it does.
     *
     * action => [label, description]. Both take the feature's noun.
     */
    private const ACTIONS = [
        'view' => [
            'See all %s',
            'See %s that are not out yet, including anything waiting to be approved',
        ],
        'create' => [
            'Add %s',
            'Add %s. Where the feature asks for approval, what they add waits for it',
        ],
        'edit' => [
            'Edit %s',
            'Change %s, including the ones somebody else wrote',
        ],
        'delete' => [
            'Remove %s',
            'Delete %s for good, including the ones somebody else wrote',
        ],
        'approve' => [
            'Approve %s',
            'Work through the %s waiting to be approved, and let them be seen',
        ],
    ];

    /**
     * What the `user` role carries - what any member may do.
     *
     * Both of these were open to anybody signed in before each feature
     * was split by action: WikiController let any member write a page
     * and held it back until it was approved, and
     * CollectionPolicy::create() returned true with a comment saying
     * so. They are the same rule with a name on it, and a name is what
     * lets it be taken away from one person, or handed to the `user`
     * role for a feature you want open to everybody.
     *
     * Read by RolesTableSeeder, which defines the role, and by the
     * migration that brings an install already running up to date.
     */
    private const MEMBER = [
        'create-wiki',
        'create-gallery',
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

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $rows = null;

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::all(), 'name');
    }

    public static function has(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /**
     * Every permission as a row, ready to be written to the database or
     * handed to a screen: the ones written out above, and the ones
     * generated for each feature that is split by action.
     *
     * A feature's actions follow the permission they sit under, so a
     * screen listing these shows them together without having to know
     * that they belong together.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }

        $rows = [];

        foreach (self::PERMISSIONS as $name => [$label, $category, $inherits, $description]) {
            $rows[] = [
                'name'         => $name,
                'display_name' => $label,
                'category'     => $category,
                'description'  => $description,
                'inherits'     => $inherits,
                'is_default'   => in_array($name, self::DEFAULTS, true),
                'feature'      => self::featureOf($name),
                'action'       => null,
            ];

            foreach (self::actionsUnder($name) as $row) {
                $rows[] = $row;
            }
        }

        return self::$rows = $rows;
    }

    /**
     * The coarse permission a feature's work used to belong to, for
     * every permission that was carved out of one.
     *
     * This is the migration's map, and it is historical: it carries both
     * the features carved out of manage-page and manage-post, and each
     * action carved out of its own feature.
     *
     * @return array<string, string>
     */
    public static function inherited(): array
    {
        $map = [];

        foreach (self::all() as $row) {
            if ($row['inherits'] !== null) {
                $map[$row['name']] = $row['inherits'];
            }
        }

        return $map;
    }

    /**
     * The permission that stands over each action permission.
     *
     * Not the same as inherited(), and deliberately narrower: that one
     * also says manage-wiki came out of manage-page, and making it live
     * would hand whoever holds manage-page the whole wiki again, which
     * is the thing this set was split up to undo. This map is only ever
     * an action and the one feature it belongs to.
     *
     * @return array<string, string>
     */
    public static function umbrella(): array
    {
        $map = [];

        foreach (self::all() as $row) {
            if ($row['action'] !== null) {
                $map[$row['name']] = 'manage-' . $row['feature'];
            }
        }

        return $map;
    }

    /**
     * Whether a set of held permission names reaches the wanted one.
     *
     * Holding a feature whole satisfies each of its actions, so a route
     * asking for create-podcast lets manage-podcast through without
     * every gate in the file having to name both.
     *
     * @param  array<int, string>  $held
     */
    public static function satisfies(string $wanted, array $held): bool
    {
        if (in_array($wanted, $held, true)) {
            return true;
        }

        $umbrella = self::umbrella()[$wanted] ?? null;

        return $umbrella !== null && in_array($umbrella, $held, true);
    }

    /**
     * feature => the actions it is split into.
     *
     * @return array<string, array<int, string>>
     */
    public static function split(): array
    {
        $map = [];

        foreach (self::CRUD as $feature => [, , $actions]) {
            $map[$feature] = $actions;
        }

        return $map;
    }

    /**
     * What the role every member holds carries.
     *
     * @return array<int, string>
     */
    public static function member(): array
    {
        return self::MEMBER;
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * The generated rows sitting under one umbrella permission.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function actionsUnder(string $umbrella): array
    {
        $feature = self::featureOf($umbrella);

        if ($feature === null) {
            return [];
        }

        [$noun, $category, $actions] = self::CRUD[$feature];

        $rows = [];

        foreach ($actions as $action) {
            [$label, $description] = self::ACTIONS[$action];

            $rows[] = [
                'name'         => "{$action}-{$feature}",
                'display_name' => sprintf($label, $noun),
                'category'     => $category,
                'description'  => sprintf($description, $noun),
                // Whoever could manage the feature yesterday could do
                // every one of these, so the migration hands them over
                'inherits'     => $umbrella,
                'is_default'   => false,
                'feature'      => $feature,
                'action'       => $action,
            ];
        }

        return $rows;
    }

    /**
     * The feature an umbrella permission is split into, or null when it
     * is held whole.
     */
    private static function featureOf(string $name): ?string
    {
        if ( ! str_starts_with($name, 'manage-')) {
            return null;
        }

        $feature = substr($name, strlen('manage-'));

        return isset(self::CRUD[$feature]) ? $feature : null;
    }
}
