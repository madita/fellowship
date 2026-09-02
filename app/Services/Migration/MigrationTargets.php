<?php

namespace App\Services\Migration;

use App\Models\Collection;
use App\Models\Event\Event;
use App\Models\Forum\ForumThread;
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
                    ['key' => 'user_id', 'label' => 'Owner user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'event_type_id', 'label' => 'Event type id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform'],
                ],
            ],
            'forum_threads' => [
                'label' => 'Forum Threads',
                'description' => 'Import forum threads; the category is created (forum_cat taxonomy) if missing.',
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'required' => true],
                    ['key' => 'body', 'label' => 'Body', 'required' => true],
                    ['key' => 'category', 'label' => 'Category name', 'required' => true],
                    ['key' => 'user_id', 'label' => 'Author user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform'],
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
                    ['key' => 'user_id', 'label' => 'Owner user id', 'required' => false, 'hint' => 'default: 1'],
                    ['key' => 'created_at', 'label' => 'Created at', 'required' => false, 'hint' => 'datetime transform — MediaWiki format: YmdHis'],
                    ['key' => 'updated_at', 'label' => 'Updated at', 'required' => false, 'hint' => 'datetime transform — MediaWiki format: YmdHis'],
                ],
            ],
            'gallery_collections' => [
                'label' => 'Gallery Collections',
                'description' => 'Import gallery collections (albums). Images are not transferred.',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'user_id', 'label' => 'Owner user id', 'required' => false, 'hint' => 'default: 1'],
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
            'events' => self::importEvent($mapped),
            'forum_threads' => self::importForumThread($mapped),
            'wiki_pages' => self::importWikiPage($mapped),
            'gallery_collections' => self::importCollection($mapped),
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

        if (isset($mapped['lat'], $mapped['lng'])) {
            $event->details()->create([
                'lat' => $mapped['lat'],
                'lng' => $mapped['lng'],
                'options' => json_encode([]),
            ]);
        }

        return (string) $event->title;
    }

    private static function importForumThread(array $mapped): string
    {
        $term = Term::firstOrCreateByTitle($mapped['category']);
        $taxonomy = Taxonomy::firstOrCreate(
            ['term_id' => $term->id, 'taxonomy' => 'forum_cat'],
            ['sort' => 0, 'visible' => true, 'searchable' => true, 'properties' => []]
        );

        $thread = ForumThread::create([
            'taxonomy_id' => $taxonomy->id,
            'user_id' => $mapped['user_id'] ?? 1,
            'title' => $mapped['title'],
            'body' => $mapped['body'],
        ]);
        if (!empty($mapped['created_at'])) {
            $thread->created_at = $mapped['created_at'];
            $thread->save();
        }

        return (string) $thread->title;
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

    private static function importCollection(array $mapped): string
    {
        $collection = new Collection(['user_id' => $mapped['user_id'] ?? 1]);
        $collection->name = $mapped['name'];
        $collection->save();

        return (string) $mapped['name'];
    }
}
