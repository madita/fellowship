<?php

namespace App\Services\Migration;

use App\Models\Event\Event;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Migration\MigrationLink;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Tag\Taxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * What the imported content links to, and whether any of it still exists.
 *
 * An import brings along a decade of links to a web that has moved on:
 * forums that closed, image hosts that shut down, and - most of all - the
 * old site's own address, which was fine to link to while that site was
 * the site. Those last ones are the interesting half of the report: they
 * still work today and will break the day the old domain lapses, and they
 * point members away from the content that was just imported for them.
 *
 * Two steps, deliberately separate. Scanning reads content out of the
 * database and costs nothing; checking asks a few hundred strangers'
 * servers whether a page is still there, which is slow and can only be
 * done in batches. Scanning keeps whatever was already checked, so
 * rescanning after an edit does not throw the answers away.
 */
class LinkAudit
{
    /**
     * Where the old site lived, so links to it can be told apart from
     * links to the wider web. Set from the dashboard; several domains
     * are allowed, because old sites collect them.
     */
    public const HOSTS_SETTING = 'migration_legacy_hosts';

    /** Scopes worth asking a server about by default. */
    public const CHECKED_SCOPES = [MigrationLink::SCOPE_EXTERNAL, MigrationLink::SCOPE_LEGACY];

    /**
     * A link copied onto every page needs no complete list of pages -
     * enough of them to go and look.
     */
    public const MAX_OCCURRENCES = 25;

    /**
     * A ceiling on the whole report. Reached only by content generating
     * links programmatically, and then the report is noise anyway.
     */
    public const MAX_LINKS = 20000;

    private const TIMEOUT         = 10;
    private const CONNECT_TIMEOUT = 5;

    /**
     * Servers that refuse a HEAD request - they answer one of these and
     * mean "ask properly" rather than "not here".
     */
    private const RETRY_WITH_GET = [400, 403, 405, 409, 501];

    /**
     * The content kinds an import writes to, with the text to read and
     * where to go and look at it.
     *
     * Not limited to the imported rows: a dead link is dead whoever
     * wrote it, and a report that hid the ones a member typed last week
     * would be a worse report.
     *
     * @return array<string, array<string, mixed>>
     */
    public function places(): array
    {
        return [
            'wiki_pages' => [
                'label' => 'Wiki pages',
                'query' => fn () => Page::query()->with(['translations', 'wikiable']),
                'text'  => fn (Page $page) => $page->translations->pluck('content')->all(),
                'title' => fn (Page $page) => $page->title ?: $page->slug,
                // A page is a wiki page or a standalone one, and they
                // live at different addresses
                'url' => fn (Page $page) => ($page->wikiable->isNotEmpty() ? '/wiki/' : '/') . $page->slug,
            ],
            'forum_threads' => [
                'label' => 'Forum threads',
                'query' => fn () => ForumThread::query()->with('category.term'),
                'text'  => fn (ForumThread $thread) => [$thread->body],
                'title' => fn (ForumThread $thread) => $thread->title,
                'url'   => fn (ForumThread $thread) => $thread->category?->term?->slug
                    ? '/forum/' . $thread->category->term->slug . '/thread/' . $thread->slug
                    : null,
            ],
            'forum_posts' => [
                'label' => 'Forum replies',
                'query' => fn () => ForumPost::query()->with('thread.category.term'),
                'text'  => fn (ForumPost $post) => [$post->body],
                'title' => fn (ForumPost $post) => $post->thread?->title ?: ('#' . $post->id),
                'url'   => fn (ForumPost $post) => $post->thread?->category?->term?->slug
                    ? '/forum/' . $post->thread->category->term->slug . '/thread/' . $post->thread->slug
                    : null,
            ],
            'events' => [
                'label' => 'Events',
                'query' => fn () => Event::query()->with('translations'),
                'text'  => fn (Event $event) => $event->translations->pluck('description')->all(),
                'title' => fn (Event $event) => $event->title,
                'url'   => fn (Event $event) => '/events/' . $event->id,
            ],
            'taxonomies' => [
                'label' => 'Categories and terms',
                'query' => fn () => Taxonomy::query()->with(['translations', 'term']),
                'text'  => fn (Taxonomy $taxonomy) => $taxonomy->translations
                    ->flatMap(fn ($translation) => [$translation->description, $translation->content, $translation->lead])
                    ->all(),
                'title' => fn (Taxonomy $taxonomy) => $taxonomy->term?->title ?: ('#' . $taxonomy->id),
                'url'   => fn (Taxonomy $taxonomy) => match ($taxonomy->taxonomy) {
                    'wiki'      => '/wiki/category/' . $taxonomy->term?->slug,
                    'forum_cat' => '/forum/' . $taxonomy->term?->slug,
                    default     => null,
                },
            ],
        ];
    }

    /**
     * The hosts the old site answered on.
     *
     * @return string[]
     */
    public function legacyHosts(): array
    {
        $stored = Setting::get(self::HOSTS_SETTING, []);

        if (is_string($stored)) {
            $stored = json_decode($stored, true) ?: preg_split('/[\s,;]+/', $stored);
        }

        return $this->cleanHosts(is_array($stored) ? $stored : []);
    }

    /**
     * @param  string[]|string  $hosts
     * @return string[]
     */
    public function setLegacyHosts(array|string $hosts): array
    {
        $hosts = is_string($hosts) ? preg_split('/[\s,;]+/', $hosts) : $hosts;
        $clean = $this->cleanHosts($hosts ?: []);

        Setting::set(self::HOSTS_SETTING, json_encode($clean), 'json');

        return $clean;
    }

    /**
     * Read every link out of the content and write down where it sits.
     *
     * Anything already checked keeps its verdict - rescanning is cheap
     * and happens after every edit, while a verdict costs a request to
     * somebody else's server. A link that is no longer anywhere in the
     * content is dropped: the report is what is there now.
     *
     * @param  callable|null  $progress  called as ($place, $records)
     * @return array<string, mixed>
     */
    public function scan(?callable $progress = null): array
    {
        $legacyHosts = $this->legacyHosts();
        $startedAt   = now();
        $found       = [];
        $records     = 0;
        $truncated   = false;

        foreach ($this->places() as $key => $place) {
            ($place['query'])()->chunkById(100, function ($chunk) use ($key, $place, $legacyHosts, &$found, &$records, &$truncated, $progress) {
                foreach ($chunk as $record) {
                    $records++;

                    foreach (($place['text'])($record) as $text) {
                        foreach ($this->extract((string) $text) as $link) {
                            $normalised = $this->normalise($link['url'], $legacyHosts);

                            if ($normalised === null) {
                                continue;
                            }

                            $hash = MigrationLink::hash($normalised['url']);

                            if ( ! isset($found[$hash])) {
                                if (count($found) >= self::MAX_LINKS) {
                                    $truncated = true;

                                    continue;
                                }

                                $found[$hash] = $normalised + ['uses' => 0, 'kinds' => [], 'occurrences' => []];
                            }

                            $found[$hash]['uses']++;
                            $found[$hash]['kinds'][$link['kind']] = true;

                            if (count($found[$hash]['occurrences']) < self::MAX_OCCURRENCES) {
                                $found[$hash]['occurrences'][] = $this->occurrence($key, $place, $record, $link);
                            }
                        }
                    }
                }

                if ($progress) {
                    $progress($key, $records);
                }
            });
        }

        $written = $this->persist($found, $startedAt);
        $removed = $this->forget($found);

        return [
            'records'   => $records,
            'links'     => count($found),
            'added'     => $written['added'],
            'removed'   => $removed,
            'truncated' => $truncated,
        ];
    }

    /**
     * Ask a batch of servers whether their page is still there.
     *
     * One batch per call and sent concurrently, because this runs inside
     * a web request: fifty links one after another outlive any timeout,
     * while fifty at once take as long as the slowest of them.
     *
     * @param  string[]  $scopes
     * @return array<string, mixed>
     */
    public function check(array $scopes = self::CHECKED_SCOPES, int $limit = 10): array
    {
        $links = MigrationLink::query()
            ->whereIn('scope', $scopes)
            ->checkable()
            // The most used links first: they are worth the most fixed
            ->orderByDesc('occurrence_count')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        if ($links->isEmpty()) {
            return ['checked' => 0, 'remaining' => 0, 'broken' => 0];
        }

        $answers = $this->ask($links, 'head');

        // A server that will not answer a HEAD is not saying the page is
        // gone, only that we asked the lazy way
        $retry = $links->filter(function (MigrationLink $link) use ($answers) {
            $answer = $answers[$link->id] ?? null;

            return $answer instanceof Response && in_array($answer->status(), self::RETRY_WITH_GET, true);
        });

        if ($retry->isNotEmpty()) {
            $answers = array_replace($answers, $this->ask($retry, 'get'));
        }

        $broken = 0;

        foreach ($links as $link) {
            $verdict = $this->verdict($answers[$link->id] ?? null, $link->url);

            $link->update($verdict + ['checked_at' => now()]);

            if ($verdict['status'] === MigrationLink::STATUS_BROKEN) {
                $broken++;
            }
        }

        return [
            'checked'   => $links->count(),
            'broken'    => $broken,
            'remaining' => MigrationLink::query()->whereIn('scope', $scopes)->checkable()->count(),
        ];
    }

    /**
     * Put a set of links back in the queue, so they are asked again.
     *
     * @param  string[]  $scopes
     */
    public function reset(array $scopes = self::CHECKED_SCOPES, ?string $status = null): int
    {
        return MigrationLink::query()
            ->whereIn('scope', $scopes)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->update([
                'status'       => MigrationLink::STATUS_PENDING,
                'http_status'  => null,
                'redirect_url' => null,
                'reason'       => null,
                'checked_at'   => null,
            ]);
    }

    /**
     * The report in numbers: how many links there are, where they point
     * and how many of them still work.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $rows = MigrationLink::query()
            ->selectRaw('scope, status, count(*) as links, sum(occurrence_count) as uses')
            ->groupBy('scope', 'status')
            ->get();

        $scopes   = [];
        $statuses = [];
        $links    = 0;
        $uses     = 0;

        foreach ($rows as $row) {
            $count = (int) $row->links;
            $used  = (int) $row->uses;

            $scopes[$row->scope]['links']                  = ($scopes[$row->scope]['links'] ?? 0) + $count;
            $scopes[$row->scope]['uses']                   = ($scopes[$row->scope]['uses'] ?? 0) + $used;
            $scopes[$row->scope]['statuses'][$row->status] = $count;

            $statuses[$row->status] = ($statuses[$row->status] ?? 0) + $count;
            $links += $count;
            $uses += $used;
        }

        return [
            'links'    => $links,
            'uses'     => $uses,
            'scopes'   => $scopes,
            'statuses' => $statuses,
            // What is left to ask about, which is what the check button
            // counts down
            'pending' => MigrationLink::query()
                ->whereIn('scope', self::CHECKED_SCOPES)
                ->checkable()
                ->count(),
            'scanned_at' => MigrationLink::max('last_seen_at'),
            'checked_at' => MigrationLink::max('checked_at'),
        ];
    }

    /**
     * The hosts the content points at, worst first - one dead server
     * usually accounts for a whole column of broken links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hosts(int $limit = 50): array
    {
        return MigrationLink::query()
            ->selectRaw("host, scope, count(*) as links, sum(occurrence_count) as uses, sum(case when status = '" . MigrationLink::STATUS_BROKEN . "' then 1 else 0 end) as broken")
            ->whereNotNull('host')
            ->groupBy('host', 'scope')
            ->orderByDesc('broken')
            ->orderByDesc('links')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'host'   => $row->host,
                'scope'  => $row->scope,
                'links'  => (int) $row->links,
                'uses'   => (int) $row->uses,
                'broken' => (int) $row->broken,
            ])
            ->all();
    }

    /**
     * Every link in one piece of text, with the words it was written as.
     *
     * Three kinds, in the order they can be told apart: anchors, images,
     * and bare addresses left in the text by a converter that did not
     * recognise them as links.
     *
     * @return array<int, array{url: string, text: string, kind: string}>
     */
    public function extract(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $links = [];
        $seen  = [];

        $add = function (string $url, string $label, string $kind) use (&$links, &$seen): void {
            $url = trim($url);
            $key = $kind . '|' . $url;

            if ($url === '' || isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $links[]    = ['url' => $url, 'text' => Str::limit(trim($label), 120, ''), 'kind' => $kind];
        };

        if (preg_match_all('/<a\b[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)<\/a>/is', $text, $anchors, PREG_SET_ORDER)) {
            foreach ($anchors as $anchor) {
                $add($this->attribute($anchor), $this->plain($anchor[4] ?? ''), MigrationLink::KIND_LINK);
            }
        }

        if (preg_match_all('/<img\b[^>]*?src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>/is', $text, $images, PREG_SET_ORDER)) {
            foreach ($images as $image) {
                $add($this->attribute($image), '', MigrationLink::KIND_IMAGE);
            }
        }

        // Bare addresses: looked for in the text with the tags taken out,
        // so an address already read out of an attribute is not found a
        // second time as part of the markup around it
        $bare = preg_replace('/<[^>]*>/', ' ', $text);

        if (preg_match_all('#\bhttps?://[^\s<>"\'\\\\]+#i', (string) $bare, $plain)) {
            foreach ($plain[0] as $url) {
                // A sentence ending in a link puts its full stop on the end
                $add(rtrim($url, '.,;:!?)]}\'"'), '', MigrationLink::KIND_LINK);
            }
        }

        return $links;
    }

    /**
     * One address, in the form it will be looked up and asked for - or
     * null when it is not an address that can be checked at all.
     *
     * @param  string[]  $legacyHosts
     * @return array{url: string, host: string|null, scope: string}|null
     */
    public function normalise(string $raw, array $legacyHosts = []): ?array
    {
        // Imported markup carries entities in attributes (&amp; in a
        // query string), and a line break inside an href is a leftover
        // of the old editor rather than part of the address
        $url = preg_replace('/\s+/', '', html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === null || $url === '' || strlen($url) > 2000) {
            return null;
        }

        // The same page, further down it
        if (Str::startsWith($url, '#')) {
            return null;
        }

        // Written without a scheme because the old site had both
        if (Str::startsWith($url, '//')) {
            $url = 'https:' . $url;
        }

        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));

        // mailto:, tel:, javascript:, data: - nothing to go and fetch
        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // An anchor is part of the page, not a page of its own
        if (($hash = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $hash);
        }

        if (trim($url) === '') {
            return null;
        }

        if ($scheme === '') {
            // A path on this site. Wikitext leftovers like "Image:Foo.jpg"
            // are not paths at all, and nothing can be done about them
            // here, so they stay out of the report.
            if ( ! Str::startsWith($url, '/')) {
                return null;
            }

            return [
                'url'   => rtrim($this->siteUrl(), '/') . $url,
                'host'  => $this->siteHost(),
                'scope' => MigrationLink::SCOPE_INTERNAL,
            ];
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        return [
            'url' => $url,
            // A name longer than the column holds is not a hostname at
            // all - the link is still worth reporting, it just does not
            // group under a host
            'host'  => strlen($host) > MigrationLink::MAX_HOST ? null : $host,
            'scope' => match (true) {
                $this->hostMatches($host, [$this->siteHost()]) => MigrationLink::SCOPE_INTERNAL,
                $this->hostMatches($host, $legacyHosts)        => MigrationLink::SCOPE_LEGACY,
                default                                        => MigrationLink::SCOPE_EXTERNAL,
            },
        ];
    }

    /**
     * Drop the links this scan did not find: they have been edited out
     * of the content, and the report is what is there now.
     *
     * Walked row by row against what was found rather than asked of the
     * database by timestamp - two scans in the same second write the
     * same timestamp, and the second one would then drop nothing.
     *
     * @param  array<string, array<string, mixed>>  $found
     */
    private function forget(array $found): int
    {
        $removed = 0;

        MigrationLink::query()
            ->select(['id', 'url_hash'])
            ->chunkById(500, function ($rows) use ($found, &$removed) {
                $gone = $rows
                    ->reject(fn (MigrationLink $link) => isset($found[$link->url_hash]))
                    ->pluck('id');

                if ($gone->isNotEmpty()) {
                    $removed += MigrationLink::whereKey($gone)->delete();
                }
            });

        return $removed;
    }

    /**
     * Write the scan's findings, keeping the verdicts already in hand.
     *
     * @param  array<string, array<string, mixed>>  $found
     * @return array{added: int}
     */
    private function persist(array $found, \DateTimeInterface $seenAt): array
    {
        $added = 0;

        foreach ($found as $hash => $link) {
            $row = MigrationLink::firstOrNew(['url_hash' => $hash]);

            // A link that moved between scopes is a different question -
            // typically the old domain was named after it was scanned,
            // and now its verdict has to be asked again
            $rescope = $row->exists && $row->scope !== $link['scope'];

            $row->fill([
                'url'              => $link['url'],
                'host'             => $link['host'],
                'scope'            => $link['scope'],
                'kind'             => $this->kind($link['kinds']),
                'occurrences'      => $link['occurrences'],
                'occurrence_count' => $link['uses'],
                'last_seen_at'     => $seenAt,
            ]);

            if ( ! $row->exists || $rescope) {
                $row->fill([
                    'status'       => MigrationLink::STATUS_PENDING,
                    'http_status'  => null,
                    'redirect_url' => null,
                    'reason'       => null,
                    'checked_at'   => null,
                ]);

                $added += $row->exists ? 0 : 1;
            }

            $row->save();
        }

        return ['added' => $added];
    }

    /**
     * Send one batch and hand back what came of each, keyed by link id.
     *
     * @param  Collection<int, MigrationLink>  $links
     * @return array<int, Response|Throwable>
     */
    private function ask(Collection $links, string $method): array
    {
        try {
            $answers = Http::pool(fn (Pool $pool) => $links
                ->map(fn (MigrationLink $link) => $pool
                    ->as((string) $link->id)
                    ->timeout(self::TIMEOUT)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->withUserAgent($this->userAgent())
                    ->withOptions(['allow_redirects' => ['max' => 5]])
                    ->{$method}($link->url))
                ->all());
        } catch (Throwable $e) {
            // The batch fell over as a whole - no DNS at all, no sockets
            // left. Which link did it cannot be told, so they all get the
            // same inconclusive answer and the run carries on.
            return array_fill_keys($links->pluck('id')->all(), $e);
        }

        $byId = [];

        foreach ($answers as $key => $answer) {
            $byId[(int) $key] = $answer;
        }

        return $byId;
    }

    /**
     * What one answer means.
     *
     * The verdicts a report can honestly carry are three: it is there,
     * it is gone, or nobody can tell. A login wall, a rate limit and a
     * server having a bad day all say nothing about whether the page
     * exists, and filing them as broken would bury the ones that are.
     *
     * @return array<string, mixed>
     */
    private function verdict(Response|Throwable|null $answer, string $url): array
    {
        if ($answer === null) {
            return [
                'status'      => MigrationLink::STATUS_UNSURE,
                'http_status' => null,
                'reason'      => 'No answer',
            ];
        }

        if ($answer instanceof Throwable) {
            $message = $answer->getMessage();

            // A domain that no longer resolves is as gone as a page can
            // be - the whole site it belonged to has left the web
            $gone = Str::contains(Str::lower($message), ['could not resolve host', 'curl error 6', 'name or service not known']);

            return [
                'status'      => $gone ? MigrationLink::STATUS_BROKEN : MigrationLink::STATUS_UNSURE,
                'http_status' => null,
                // Kept whole enough to be read, capped so a server that
                // answers with a wall of text does not fill the report
                'reason' => Str::limit($message, 500, ''),
            ];
        }

        $code     = $answer->status();
        $landedOn = (string) $answer->effectiveUri();

        return [
            'status' => match (true) {
                $code >= 200 && $code < 300       => MigrationLink::STATUS_OK,
                in_array($code, [404, 410], true) => MigrationLink::STATUS_BROKEN,
                default                           => MigrationLink::STATUS_UNSURE,
            },
            'http_status'  => $code,
            'redirect_url' => $landedOn !== '' && $landedOn !== $url ? $landedOn : null,
            'reason'       => null,
        ];
    }

    /**
     * The value of a quoted-or-not attribute: whichever of the three
     * ways of writing it matched.
     *
     * @param  array<int, string>  $match
     */
    private function attribute(array $match): string
    {
        foreach ([1, 2, 3] as $group) {
            if (($match[$group] ?? '') !== '') {
                return $match[$group];
            }
        }

        return '';
    }

    /**
     * @param  array<string, bool>  $kinds
     */
    private function kind(array $kinds): string
    {
        // A link also used as an image is still a link worth clicking;
        // only one that is nothing but an image is filed as an image
        return isset($kinds[MigrationLink::KIND_LINK])
            ? MigrationLink::KIND_LINK
            : MigrationLink::KIND_IMAGE;
    }

    /**
     * @param  array<string, mixed>  $place
     * @param  array{url: string, text: string, kind: string}  $link
     * @return array<string, mixed>
     */
    private function occurrence(string $key, array $place, Model $record, array $link): array
    {
        return array_filter([
            'place' => $key,
            'label' => $place['label'],
            'id'    => $record->getKey(),
            'title' => Str::limit((string) (($place['title'])($record) ?: ''), 120, ''),
            'url'   => ($place['url'])($record),
            'text'  => $link['text'],
            'kind'  => $link['kind'],
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  string[]  $hosts
     * @return string[]
     */
    private function cleanHosts(array $hosts): array
    {
        $clean = [];

        foreach ($hosts as $host) {
            // Pasted as a whole address as often as as a bare domain
            $host = Str::lower(trim((string) $host));
            $host = (string) preg_replace('#^[a-z]+://#', '', $host);
            $host = Str::before($host, '/');
            $host = Str::before($host, ':');
            $host = $this->bareHost(ltrim($host, '.'));

            if ($host !== '' && Str::contains($host, '.')) {
                $clean[] = $host;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Whether a host is one of these, or sits underneath one: the old
     * site's forum lived on forum.example.org, and that is the same old
     * site as example.org.
     *
     * @param  array<int, string|null>  $candidates
     */
    private function hostMatches(string $host, array $candidates): bool
    {
        $host = $this->bareHost($host);

        foreach (array_filter($candidates) as $candidate) {
            $candidate = $this->bareHost((string) $candidate);

            if ($candidate !== '' && ($host === $candidate || Str::endsWith($host, '.' . $candidate))) {
                return true;
            }
        }

        return false;
    }

    /**
     * www.example.org and example.org are the same site.
     */
    private function bareHost(string $host): string
    {
        $host = Str::lower(trim($host));

        return Str::startsWith($host, 'www.') ? Str::after($host, 'www.') : $host;
    }

    private function plain(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function siteUrl(): string
    {
        return (string) config('app.url');
    }

    private function siteHost(): ?string
    {
        $host = parse_url($this->siteUrl(), PHP_URL_HOST);

        return $host ? Str::lower($host) : null;
    }

    /**
     * Say who is asking. A link checker that hides what it is gets
     * blocked, and a blocked request is an answer about nothing.
     */
    private function userAgent(): string
    {
        return (string) config('app.name', 'Fellowship') . ' link check (+' . $this->siteUrl() . ')';
    }
}
