<?php

namespace App\Services\Migration;

use App\Models\Collection;
use App\Models\Event\Event;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\MigrationAttribution;
use App\Models\MigrationIdMap;
use App\Models\MigrationLegacyUser;
use App\Models\Page;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Registry of features external data can be mapped onto: the fields each
 * target accepts (shown in the mapping editor) and how a mapped row is
 * persisted. Adding a target means adding a definition + an import method.
 */
class MigrationTargets
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            'users' => [
                'label' => 'Users',
                'description' => 'Import user accounts. Existing emails/usernames are skipped.',
                'fields' => [
                    ['key' => 'username', 'label' => 'Username', 'required' => true],
                    ['key' => 'email', 'label' => 'E-mail', 'required' => true],
                    ['key' => 'name', 'label' => 'Display name', 'required' => false],
                    ['key' => 'password', 'label' => 'Password (plain — will be hashed; random if empty)', 'required' => false],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform'],
                ],
            ],
            'legacy_users' => [
                'label' => 'Legacy Users',
                'description' => 'Import the user roster of a legacy system into the legacy-user directory (no accounts are created). Shows up on the Legacy Users tab with e-mails, used to verify claims and suggest matches with registered users. Re-runs update existing entries.',
                'fields' => [
                    ['key' => 'username', 'label' => 'Username', 'required' => true],
                    ['key' => 'legacy_source', 'label' => 'Legacy system', 'required' => true, 'hint' => 'set a default naming the old system (e.g. "wiki", "forum") — must match the legacy system used by the content mappings'],
                    ['key' => 'email', 'label' => 'E-mail', 'required' => false, 'hint' => 'used to verify claims / suggest registered users'],
                    ['key' => 'legacy_user_id', 'label' => 'Old user id', 'required' => false],
                    ['key' => 'real_name', 'label' => 'Real name', 'required' => false],
                    ['key' => 'registered_at', 'label' => 'Registered at', 'required' => false, 'hint' => 'datetime transform — MediaWiki format: YmdHis'],
                ],
            ],
            'events' => [
                'label' => 'Events',
                'description' => 'Import calendar events (with optional coordinates into event details).',
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'required' => true],
                    ['key' => 'description', 'label' => 'Description', 'required' => false],
                    ['key' => 'startDate', 'label' => 'Start date', 'required' => true, 'hint' => 'date transform'],
                    ['key' => 'endDate', 'label' => 'End date', 'required' => false, 'hint' => 'date transform'],
                    ['key' => 'startTime', 'label' => 'Start time', 'required' => false, 'hint' => 'time transform'],
                    ['key' => 'endTime', 'label' => 'End time', 'required' => false, 'hint' => 'time transform'],
                    ['key' => 'lat', 'label' => 'Latitude', 'required' => false],
                    ['key' => 'lng', 'label' => 'Longitude', 'required' => false],
                    ['key' => 'album', 'label' => 'Gallery album name', 'required' => false, 'hint' => 'stored in the event details; "Link Gallery" uses it to attach the collection'],
                    ['key' => 'max_participants', 'label' => 'Max participants', 'required' => false, 'hint' => 'stored in the event details options'],
                    ['key' => 'creator', 'label' => 'Original creator (name)', 'required' => false, 'hint' => 'stored in the event details options'],
                    ['key' => 'legacy_owner', 'label' => 'Legacy owner (username)', 'required' => false, 'hint' => 'recorded for later assignment to a registered user; falls back to creator'],
                    ['key' => 'legacy_source', 'label' => 'Legacy system', 'required' => false, 'hint' => 'set a default naming the old system (e.g. "treffen") — same usernames from different systems stay distinct'],
                    ['key' => 'last_edited_by', 'label' => 'Last edited by (name)', 'required' => false, 'hint' => 'stored in the event details options'],
                    ['key' => 'user_id', 'label' => 'Owner user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'event_type_id', 'label' => 'Event type id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform'],
                ],
            ],
            'forum_threads' => [
                'label' => 'Forum Threads',
                'description' => 'Import forum threads; the category is created (forum_cat taxonomy) if missing. For phpBB: map phpbb3_topics, join phpbb3_forums for the category and phpbb3_posts (on topic_first_post_id) for the body.',
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'required' => true, 'hint' => 'phpBB: topic_title, html_decode transform'],
                    ['key' => 'body', 'label' => 'Body', 'required' => true, 'hint' => 'phpBB: post_text of the first post'],
                    ['key' => 'convert_bbcode', 'label' => 'Convert BBCode', 'required' => false, 'hint' => 'default: 1 — converts phpBB post markup to HTML'],
                    ['key' => 'category', 'label' => 'Category name', 'required' => true, 'hint' => 'phpBB: forum_name'],
                    ['key' => 'legacy_id', 'label' => 'Legacy thread id', 'required' => false, 'hint' => 'phpBB: topic_id — lets posts link up and makes re-runs skip imported threads'],
                    ['key' => 'is_locked', 'label' => 'Locked', 'required' => false, 'hint' => 'phpBB: topic_status, bool transform'],
                    ['key' => 'is_pinned', 'label' => 'Pinned', 'required' => false, 'hint' => 'phpBB: topic_type (any non-zero value pins)'],
                    ['key' => 'view_count', 'label' => 'Views', 'required' => false, 'hint' => 'phpBB: topic_views, int transform'],
                    ['key' => 'legacy_owner', 'label' => 'Legacy owner (username)', 'required' => false, 'hint' => 'phpBB: topic_first_poster_name — recorded for later assignment'],
                    ['key' => 'legacy_source', 'label' => 'Legacy system', 'required' => false, 'hint' => 'e.g. default "forum"'],
                    ['key' => 'user_id', 'label' => 'Author user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'phpBB: topic_time — datetime transform, format U'],
                ],
            ],
            'forum_posts' => [
                'label' => 'Forum Posts',
                'description' => 'Import forum replies into threads imported earlier (matched via the legacy thread id). Import the threads first. Filter out each topic\'s first post — it already became the thread body.',
                'fields' => [
                    ['key' => 'thread_legacy_id', 'label' => 'Legacy thread id', 'required' => true, 'hint' => 'phpBB: topic_id — must match the threads\' "Legacy thread id"'],
                    ['key' => 'body', 'label' => 'Body', 'required' => true, 'hint' => 'phpBB: post_text'],
                    ['key' => 'convert_bbcode', 'label' => 'Convert BBCode', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'legacy_id', 'label' => 'Legacy post id', 'required' => false, 'hint' => 'phpBB: post_id — makes re-runs skip imported posts'],
                    ['key' => 'legacy_owner', 'label' => 'Legacy owner (username)', 'required' => false, 'hint' => 'phpBB: join phpbb3_users on poster_id → username'],
                    ['key' => 'legacy_source', 'label' => 'Legacy system', 'required' => false, 'hint' => 'e.g. default "forum"'],
                    ['key' => 'user_id', 'label' => 'Author user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'phpBB: post_time — datetime transform, format U'],
                ],
            ],
            'wiki_pages' => [
                'label' => 'Wiki Pages',
                'description' => 'Import wiki pages (e.g. from MediaWiki: map the page table and join revision + text for the content). Wikitext is converted to HTML, categories in the text become wiki terms, and pages are approved automatically. Existing slugs are skipped.',
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'required' => true, 'hint' => 'MediaWiki page_title → transform underscores_to_spaces'],
                    ['key' => 'content', 'label' => 'Content', 'required' => true, 'hint' => 'MediaWiki text.old_text (join revision on page_latest, text on rev_text_id)'],
                    ['key' => 'convert_wikitext', 'label' => 'Convert wikitext', 'required' => false, 'hint' => 'default: 1 — set default 0 when content is already HTML'],
                    ['key' => 'status', 'label' => 'Status', 'required' => false, 'hint' => 'MediaWiki page_is_redirect → bool transform ("redirect" when true)'],
                    ['key' => 'locale', 'label' => 'Language', 'required' => false, 'hint' => 'locale the title/content are written to (e.g. default "de") — falls back to the app locale'],
                    ['key' => 'legacy_owner', 'label' => 'Legacy owner (username)', 'required' => false, 'hint' => 'MediaWiki rev_user_text — recorded for later assignment to a registered user'],
                    ['key' => 'legacy_source', 'label' => 'Legacy system', 'required' => false, 'hint' => 'set a default naming the old system (e.g. "wiki")'],
                    ['key' => 'user_id', 'label' => 'Owner user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform — MediaWiki format: YmdHis'],
                    ['key' => 'updated_at', 'label' => 'Updated at', 'required' => false, 'hint' => 'datetime transform — MediaWiki format: YmdHis'],
                ],
            ],
            'gallery_collections' => [
                'label' => 'Gallery Collections',
                'description' => 'Import gallery collections (albums). One collection per distinct name — duplicates are skipped, so a per-image table can be mapped directly. Use the Gallery Images target for the files.',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'taxonomy_id', 'label' => 'Taxonomy id', 'required' => false, 'hint' => 'e.g. default 1'],
                    ['key' => 'user_id', 'label' => 'Owner user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'legacy_owner', 'label' => 'Legacy owner (username)', 'required' => false, 'hint' => 'recorded for later assignment to a registered user'],
                    ['key' => 'legacy_source', 'label' => 'Legacy system', 'required' => false, 'hint' => 'set a default naming the old system (e.g. "gallery")'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform'],
                ],
            ],
            'gallery_images' => [
                'label' => 'Gallery Images',
                'description' => 'Attach image files from a folder on this server to existing gallery collections (import the collections first). One row per image; already-attached file names are skipped.',
                'fields' => [
                    ['key' => 'collection', 'label' => 'Collection name', 'required' => true],
                    ['key' => 'base_path', 'label' => 'Image folder on this server', 'required' => true, 'hint' => 'set as default, e.g. C:\\archive\\uploads'],
                    ['key' => 'file', 'label' => 'File (relative to folder)', 'required' => true, 'hint' => 'template e.g. {topic|fold}/{id}.jpg'],
                    ['key' => 'caption', 'label' => 'Caption', 'required' => false],
                    ['key' => 'uploader', 'label' => 'Uploader (name)', 'required' => false, 'hint' => 'also recorded as the legacy owner for later user assignment'],
                    ['key' => 'legacy_source', 'label' => 'Legacy system', 'required' => false, 'hint' => 'set a default naming the old system (e.g. "gallery")'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform'],
                ],
            ],
            'wiki_terms' => [
                'label' => 'Wiki Terms',
                'description' => 'Import wiki categories as terms/taxonomies, with optional parent relations (e.g. MediaWiki: map namespace-14 pages joined with revision + text for descriptions, or categorylinks rows for the hierarchy).',
                'fields' => [
                    ['key' => 'name', 'label' => 'Category name', 'required' => true, 'hint' => 'underscores become spaces'],
                    ['key' => 'description', 'label' => 'Description', 'required' => false],
                    ['key' => 'convert_wikitext', 'label' => 'Convert wikitext', 'required' => false, 'hint' => 'default: 1 — set default 0 when the description is already HTML'],
                    ['key' => 'parent', 'label' => 'Parent category name', 'required' => false, 'hint' => 'created as wiki taxonomy if missing'],
                ],
            ],
        ];
    }

    /**
     * @return string[] validation errors for a mapped row (empty = ok)
     */
    public static function validateRow(string $target, array $mapped): array
    {
        $errors = [];
        foreach (self::all()[$target]['fields'] ?? [] as $field) {
            if (($field['required'] ?? false) && ($mapped[$field['key']] ?? null) === null) {
                $errors[] = "Missing required field \"{$field['key']}\"";
            }
        }

        return $errors;
    }

    /**
     * Persist one mapped row. Returns a short label for progress display,
     * or null when the row was deliberately skipped (e.g. duplicate user).
     *
     * @param array<string,mixed> $mapped
     */
    public static function import(string $target, array $mapped): ?string
    {
        return match ($target) {
            'users' => self::importUser($mapped),
            'legacy_users' => self::importLegacyUser($mapped),
            'events' => self::importEvent($mapped),
            'forum_threads' => self::importForumThread($mapped),
            'forum_posts' => self::importForumPost($mapped),
            'wiki_pages' => self::importWikiPage($mapped),
            'wiki_terms' => self::importWikiTerm($mapped),
            'gallery_collections' => self::importCollection($mapped),
            'gallery_images' => self::importGalleryImage($mapped),
            default => throw new \InvalidArgumentException("Unknown migration target: {$target}"),
        };
    }

    private static function importUser(array $mapped): ?string
    {
        if (User::where('email', $mapped['email'])->orWhere('username', $mapped['username'])->exists()) {
            return null; // duplicate — skip
        }

        $user = new User([
            'username' => $mapped['username'],
            'email' => $mapped['email'],
            'name' => $mapped['name'] ?? $mapped['username'],
            'password' => Hash::make(($mapped['password'] ?? null) ?: Str::random(32)),
        ]);
        if (!empty($mapped['created_at'])) {
            $user->created_at = $mapped['created_at'];
        }
        $user->save();

        return $user->username;
    }

    private static function importLegacyUser(array $mapped): ?string
    {
        $username = trim((string) $mapped['username']);
        if ($username === '') {
            return null;
        }

        MigrationLegacyUser::updateOrCreate(
            [
                'legacy_source' => trim((string) $mapped['legacy_source']),
                'username' => $username,
            ],
            array_filter([
                'email' => trim((string) ($mapped['email'] ?? '')) ?: null,
                'legacy_user_id' => $mapped['legacy_user_id'] ?? null,
                'real_name' => trim((string) ($mapped['real_name'] ?? '')) ?: null,
                'registered_at' => $mapped['registered_at'] ?? null,
            ], fn ($value) => $value !== null)
        );

        return $username;
    }

    private static function importEvent(array $mapped): string
    {
        $event = new Event();
        $event->title = $mapped['title'];
        $event->description = $mapped['description'] ?? null;
        $event->user_id = $mapped['user_id'] ?? 1;
        $event->event_type_id = $mapped['event_type_id'] ?? 1;
        $event->startDate = $mapped['startDate'];
        $event->endDate = $mapped['endDate'] ?? $mapped['startDate'];
        $event->startTime = $mapped['startTime'] ?? null;
        $event->endTime = $mapped['endTime'] ?? null;
        if (!empty($mapped['created_at'])) {
            $event->created_at = $mapped['created_at'];
        }
        $event->save();

        // Extra metadata lives in the event details options (the same keys
        // the "Link Gallery" post-import step reads).
        $options = array_filter([
            'max' => $mapped['max_participants'] ?? null,
            'creator' => $mapped['creator'] ?? null,
            'lastEditedBy' => $mapped['last_edited_by'] ?? null,
            'albumName' => $mapped['album'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (isset($mapped['lat'], $mapped['lng']) || $options) {
            $event->details()->create([
                'lat' => $mapped['lat'] ?? null,
                'lng' => $mapped['lng'] ?? null,
                'options' => json_encode($options),
            ]);
        }

        MigrationAttribution::record($event, $mapped['legacy_owner'] ?? $mapped['creator'] ?? null, $mapped['legacy_source'] ?? null);

        return (string) $event->title;
    }

    private static function importForumThread(array $mapped): ?string
    {
        if (MigrationIdMap::exists_for('forum_topic', $mapped['legacy_id'] ?? null)) {
            return null; // already imported — skip
        }

        $term = Term::firstOrCreateByTitle($mapped['category']);
        $taxonomy = Taxonomy::firstOrCreate(
            ['term_id' => $term->id, 'taxonomy' => 'forum_cat'],
            ['sort' => 0, 'visible' => true, 'searchable' => true, 'properties' => []]
        );

        $body = (string) $mapped['body'];
        $convert = filter_var($mapped['convert_bbcode'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        if ($convert) {
            $body = (new BbcodeConverter())->toHtml($body);
        }

        $legacyOwner = trim((string) ($mapped['legacy_owner'] ?? ''));

        $thread = ForumThread::create([
            'taxonomy_id' => $taxonomy->id,
            'user_id' => $mapped['user_id'] ?? 1,
            'title' => $mapped['title'],
            'body' => $body,
            'is_locked' => (bool) ($mapped['is_locked'] ?? false),
            // phpBB topic_type: 0 normal, 1 sticky, 2/3 announcement.
            'is_pinned' => !empty($mapped['is_pinned']),
            'view_count' => (int) ($mapped['view_count'] ?? 0),
            // Shown as the author until the legacy account is assigned.
            'meta' => $legacyOwner !== '' ? ['legacy_author' => $legacyOwner] : null,
        ]);
        if (!empty($mapped['created_at'])) {
            ForumThread::whereKey($thread->id)->update([
                'created_at' => $mapped['created_at'],
                'last_post_at' => $mapped['created_at'],
            ]);
        }

        MigrationIdMap::remember('forum_topic', $mapped['legacy_id'] ?? null, $thread);
        MigrationAttribution::record($thread, $mapped['legacy_owner'] ?? null, $mapped['legacy_source'] ?? null);

        return (string) $thread->title;
    }

    private static function importForumPost(array $mapped): ?string
    {
        if (MigrationIdMap::exists_for('forum_post', $mapped['legacy_id'] ?? null)) {
            return null; // already imported — skip
        }

        $thread = MigrationIdMap::lookup('forum_topic', $mapped['thread_legacy_id']);
        if (!$thread instanceof ForumThread) {
            throw new \RuntimeException("No imported thread for legacy topic id \"{$mapped['thread_legacy_id']}\" — import the threads first");
        }

        $body = (string) $mapped['body'];
        $convert = filter_var($mapped['convert_bbcode'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        if ($convert) {
            $body = (new BbcodeConverter())->toHtml($body);
        }

        $legacyOwner = trim((string) ($mapped['legacy_owner'] ?? ''));

        $post = ForumPost::create([
            'thread_id' => $thread->id,
            'user_id' => $mapped['user_id'] ?? 1,
            'body' => $body,
            // Shown as the author until the legacy account is assigned.
            'meta' => $legacyOwner !== '' ? ['legacy_author' => $legacyOwner] : null,
        ]);
        if (!empty($mapped['created_at'])) {
            ForumPost::whereKey($post->id)->update(['created_at' => $mapped['created_at']]);
            // The created-hook stamped last_post_at with now() — correct it
            // to the real newest post time.
            ForumThread::whereKey($thread->id)->update([
                'last_post_at' => ForumPost::where('thread_id', $thread->id)->max('created_at'),
            ]);
        }

        MigrationIdMap::remember('forum_post', $mapped['legacy_id'] ?? null, $post);
        MigrationAttribution::record($post, $mapped['legacy_owner'] ?? null, $mapped['legacy_source'] ?? null);

        return Str::limit(strip_tags($body), 40);
    }

    private static function importWikiPage(array $mapped): ?string
    {
        $title = trim((string) $mapped['title']);
        $slug = Str::slug($title);

        if ($slug === '' || Wiki::where('slug', $slug)->exists()) {
            return null; // already imported (or unusable title) — skip
        }

        $content = (string) $mapped['content'];
        $categories = [];

        $convert = filter_var($mapped['convert_wikitext'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        if ($convert) {
            $converted = (new WikitextConverter())->convert($content);
            $content = $converted['html'];
            $categories = $converted['categories'];
        }

        $userId = (int) ($mapped['user_id'] ?? 1);

        // Title/content live in the *_translations tables — write them to an
        // explicit locale so imports don't depend on the queue worker's locale.
        $locale = trim((string) ($mapped['locale'] ?? '')) ?: app()->getLocale();

        $page = new Page(['user_id' => $userId, 'slug' => $slug]);
        $translation = $page->translateOrNew($locale);
        $translation->title = $title;
        $translation->content = $content;
        $page->save();

        // MediaWiki's page_is_redirect maps to the wiki "redirect" status;
        // any other non-empty string is stored as-is.
        $status = $mapped['status'] ?? null;
        if (is_bool($status) || in_array($status, [0, 1, '0', '1'], true)) {
            $status = filter_var($status, FILTER_VALIDATE_BOOLEAN) ? 'redirect' : null;
        }

        $wiki = new Wiki(['slug' => $page->slug, 'status' => $status]);
        $wiki->translateOrNew($locale)->title = $title;
        $page->wikiable()->save($wiki);

        // Imported pages should not queue up for approval: approve them as
        // the owning user and drop the auto-created approval ticket.
        $owner = User::find($userId) ?? User::query()->orderBy('id')->first();
        if ($owner && !$wiki->isApproved()) {
            $wiki->approve($owner);
        }
        $wiki->tickets()->delete();

        foreach ($categories as $category) {
            if (!$page->hasCategory($category, 'wiki')) {
                $page->addCategory($category, 'wiki');
            }
        }

        MigrationAttribution::record($page, $mapped['legacy_owner'] ?? null, $mapped['legacy_source'] ?? null);

        // Preserve original timestamps without the model touching them again.
        $timestamps = array_filter([
            'created_at' => $mapped['created_at'] ?? null,
            'updated_at' => $mapped['updated_at'] ?? $mapped['created_at'] ?? null,
        ]);
        if ($timestamps) {
            Page::whereKey($page->id)->update($timestamps);
        }

        return $title;
    }

    private static function importCollection(array $mapped): ?string
    {
        $name = trim((string) $mapped['name']);

        if ($name === '' || Collection::whereTranslation('name', $name)->exists()) {
            return null; // one collection per distinct name — skip duplicates
        }

        $collection = new Collection(array_filter([
            'user_id' => $mapped['user_id'] ?? 1,
            'taxonomy_id' => $mapped['taxonomy_id'] ?? null,
        ]));
        $collection->name = $name;
        $collection->save();

        if (!empty($mapped['created_at'])) {
            Collection::whereKey($collection->id)->update(['created_at' => $mapped['created_at']]);
        }

        MigrationAttribution::record($collection, $mapped['legacy_owner'] ?? null, $mapped['legacy_source'] ?? null);

        return $name;
    }

    private static function importGalleryImage(array $mapped): ?string
    {
        $name = trim((string) $mapped['collection']);
        $collection = Collection::whereTranslation('name', $name)->first();
        if (!$collection) {
            throw new \RuntimeException("Collection \"{$name}\" not found — import the collections first");
        }

        $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim((string) $mapped['file']));
        $path = rtrim((string) $mapped['base_path'], '/\\') . DIRECTORY_SEPARATOR . $relative;
        if (!is_file($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $fileName = basename($relative);
        $alreadyAttached = $collection->media()
            ->where('collection_name', 'gallery')
            ->where('file_name', $fileName)
            ->exists();
        if ($alreadyAttached) {
            return null; // re-runs skip files that are already attached
        }

        $properties = array_filter([
            'album' => $name,
            'uploader' => $mapped['uploader'] ?? null,
            'caption' => $mapped['caption'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $media = $collection->addMedia($path)
            ->preservingOriginal()
            ->usingFileName($fileName)
            ->withCustomProperties($properties)
            ->toMediaCollection('gallery');

        if (!empty($mapped['created_at'])) {
            $media->created_at = $mapped['created_at'];
            $media->save();
        }

        MigrationAttribution::record($media, $mapped['uploader'] ?? null, $mapped['legacy_source'] ?? null);

        return $fileName;
    }

    private static function importWikiTerm(array $mapped): string
    {
        $name = trim(str_replace('_', ' ', (string) $mapped['name']));

        $term = Term::firstOrCreateByTitle($name);
        $taxonomy = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);

        $description = $mapped['description'] ?? null;
        if ($description !== null && $description !== '') {
            $convert = filter_var($mapped['convert_wikitext'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            $taxonomy->description = $convert
                ? (new WikitextConverter())->toHtml((string) $description)
                : (string) $description;
        }

        $parentName = trim(str_replace('_', ' ', (string) ($mapped['parent'] ?? '')));
        if ($parentName !== '') {
            $parentTerm = Term::firstOrCreateByTitle($parentName);
            $parent = Taxonomy::firstOrCreate(['taxonomy' => 'wiki', 'term_id' => $parentTerm->id]);
            $taxonomy->parent_id = $parent->id;
        }

        $taxonomy->save();

        return $name;
    }
}
