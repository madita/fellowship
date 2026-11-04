<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Migration\MigrationLink;
use App\Models\Page;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Services\Migration\LinkAudit;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The link report: what fifteen years of imported content points at, and
 * which of it still answers.
 */
class MigrationLinkAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        EventType::create(['name' => 'Imported', 'color' => '#000000', 'options' => '{}']);

        // The site this content now lives on, and the site it came from
        config(['app.url' => 'https://fellowship.test']);
        $this->audit()->setLegacyHosts(['oldsite.example']);
    }

    public function test_it_finds_the_links_in_imported_content(): void
    {
        $this->page('Meeting notes', implode(' ', [
            '<p>See the <a href="https://external.example/report">report</a>.</p>',
            '<p><img src="https://images.example/photo.jpg" alt="A photo"></p>',
            '<p>Written up at https://bare.example/story.html</p>',
            '<p><a href="mailto:someone@oldsite.example">Mail us</a>',
            '<a href="#top">Back to top</a></p>',
        ]));

        $result = $this->audit()->scan();

        // The taxonomies a fresh install comes with are read too, so the
        // count is "at least the page", not "only the page"
        $this->assertGreaterThanOrEqual(1, $result['records']);
        $this->assertSame(3, $result['links']);

        $urls = MigrationLink::pluck('url')->all();
        $this->assertContains('https://external.example/report', $urls);
        $this->assertContains('https://images.example/photo.jpg', $urls);
        $this->assertContains('https://bare.example/story.html', $urls);

        // An address to write to and a jump down the page are not pages
        // anybody can go and check
        $this->assertCount(3, $urls);

        $image = MigrationLink::where('url', 'https://images.example/photo.jpg')->first();
        $this->assertSame(MigrationLink::KIND_IMAGE, $image->kind);

        $link = MigrationLink::where('url', 'https://external.example/report')->first();
        $this->assertSame(MigrationLink::KIND_LINK, $link->kind);
        $this->assertSame('report', $link->occurrences[0]['text']);
        $this->assertSame('Meeting notes', $link->occurrences[0]['title']);
        $this->assertSame('wiki_pages', $link->occurrences[0]['place']);
    }

    public function test_it_tells_the_old_site_apart_from_the_wider_web(): void
    {
        $this->page('Links', implode(' ', [
            '<a href="https://www.oldsite.example/wiki/Hanau">the old wiki</a>',
            '<a href="https://forum.oldsite.example/viewtopic.php?t=12">the old forum</a>',
            '<a href="https://fellowship.test/wiki/hanau">here</a>',
            '<a href="/events/4">an event here</a>',
            '<a href="https://somewhere.example/">elsewhere</a>',
        ]));

        $this->audit()->scan();

        $scopeOf = fn (string $url) => MigrationLink::where('url', $url)->value('scope');

        // The old site, and anything underneath it, is the old site
        $this->assertSame(MigrationLink::SCOPE_LEGACY, $scopeOf('https://www.oldsite.example/wiki/Hanau'));
        $this->assertSame(MigrationLink::SCOPE_LEGACY, $scopeOf('https://forum.oldsite.example/viewtopic.php?t=12'));

        $this->assertSame(MigrationLink::SCOPE_INTERNAL, $scopeOf('https://fellowship.test/wiki/hanau'));
        // A path on its own is a path on this site
        $this->assertSame(MigrationLink::SCOPE_INTERNAL, $scopeOf('https://fellowship.test/events/4'));

        $this->assertSame(MigrationLink::SCOPE_EXTERNAL, $scopeOf('https://somewhere.example/'));
    }

    public function test_it_reads_every_kind_of_imported_content(): void
    {
        $this->page('A page', '<a href="https://one.example/">one</a>');
        $thread = $this->thread('A thread', '<a href="https://two.example/">two</a>');
        ForumPost::create([
            'taxonomy_id' => $thread->taxonomy_id,
            'thread_id'   => $thread->id,
            'user_id'     => $this->admin->id,
            'body'        => '<a href="https://three.example/">three</a>',
        ]);

        $event = Event::create([
            'user_id'       => $this->admin->id,
            'event_type_id' => 1,
            'title'         => 'A meetup',
            'description'   => '<a href="https://four.example/">four</a>',
            'startDate'     => '2026-12-24',
        ]);

        $term     = Term::firstOrCreateByTitle('Reports');
        $taxonomy = Taxonomy::create([
            'taxonomy'    => 'wiki',
            'term_id'     => $term->id,
            'description' => '<a href="https://five.example/">five</a>',
        ]);

        $this->audit()->scan();

        $places = MigrationLink::query()->get()->mapWithKeys(fn (MigrationLink $link) => [
            $link->host => $link->occurrences[0]['place'] ?? null,
        ]);

        $this->assertSame('wiki_pages', $places['one.example']);
        $this->assertSame('forum_threads', $places['two.example']);
        $this->assertSame('forum_posts', $places['three.example']);
        $this->assertSame('events', $places['four.example']);
        $this->assertSame('taxonomies', $places['five.example']);

        // And each one says where to go and look
        $this->assertSame(
            '/events/' . $event->id,
            MigrationLink::where('host', 'four.example')->first()->occurrences[0]['url']
        );
        $this->assertSame(
            '/wiki/category/' . $taxonomy->term->slug,
            MigrationLink::where('host', 'five.example')->first()->occurrences[0]['url']
        );
    }

    public function test_the_same_link_on_several_pages_is_one_row_with_both_pages(): void
    {
        $this->page('First', '<a href="https://external.example/shared">shared</a>');
        $this->page('Second', '<a href="https://external.example/shared">shared</a>');
        $this->page('Third', '<a href="https://external.example/shared#part-two">shared, further down</a>');

        $this->audit()->scan();

        $links = MigrationLink::all();
        $this->assertCount(1, $links);

        // The anchor is part of the page, not a page of its own
        $this->assertSame('https://external.example/shared', $links->first()->url);
        $this->assertSame(3, $links->first()->occurrence_count);
        $this->assertEqualsCanonicalizing(
            ['First', 'Second', 'Third'],
            collect($links->first()->occurrences)->pluck('title')->all()
        );
    }

    public function test_it_calls_a_404_gone_and_a_200_fine(): void
    {
        Http::fake([
            'gone.example/*'  => Http::response('Not here', 404),
            'alive.example/*' => Http::response('Here', 200),
        ]);

        $this->page('Links', implode(' ', [
            '<a href="https://gone.example/page">gone</a>',
            '<a href="https://alive.example/page">alive</a>',
        ]));

        $this->audit()->scan();
        $result = $this->audit()->check();

        $this->assertSame(2, $result['checked']);
        $this->assertSame(1, $result['broken']);
        $this->assertSame(0, $result['remaining']);

        $gone = MigrationLink::where('host', 'gone.example')->first();
        $this->assertSame(MigrationLink::STATUS_BROKEN, $gone->status);
        $this->assertSame(404, $gone->http_status);
        $this->assertNotNull($gone->checked_at);

        $alive = MigrationLink::where('host', 'alive.example')->first();
        $this->assertSame(MigrationLink::STATUS_OK, $alive->status);
        $this->assertSame(200, $alive->http_status);
    }

    public function test_a_host_that_no_longer_exists_is_gone(): void
    {
        Http::fake(fn () => throw new ConnectException(
            'cURL error 6: Could not resolve host: lapsed.example',
            new PsrRequest('HEAD', 'https://lapsed.example/page')
        ));

        $this->page('Links', '<a href="https://lapsed.example/page">a dead domain</a>');

        $this->audit()->scan();
        $this->audit()->check();

        $link = MigrationLink::first();
        $this->assertSame(MigrationLink::STATUS_BROKEN, $link->status);
        $this->assertNull($link->http_status);
        $this->assertStringContainsString('Could not resolve host', $link->reason);
    }

    public function test_a_whole_curl_error_is_written_down(): void
    {
        // What cURL says about a host that no longer resolves names the
        // host, the URL it was asked for and a page to read about it,
        // which is longer than a string column in this schema holds -
        // this is the message that first overflowed it
        $url     = 'http://wachetreffen.goddy-online.example/uploads/Hanau%20Juni%202004/305.jpg';
        $message = 'cURL error 6: Could not resolve host: wachetreffen.goddy-online.example'
            . ' (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for ' . $url;

        $this->assertGreaterThan(191, strlen($message));

        Http::fake(fn () => throw new ConnectException($message, new PsrRequest('HEAD', $url)));

        $this->page('Links', '<a href="' . $url . '">a photo of Hanau</a>');

        $this->audit()->scan();
        $this->audit()->check();

        $link = MigrationLink::first();
        $this->assertSame(MigrationLink::STATUS_BROKEN, $link->status);
        // Whole, because the useful half is at the end of it
        $this->assertSame($message, $link->reason);

        // Asserted against the schema as well: these tests run on
        // sqlite, which lets a varchar hold whatever it is given, so
        // storing the message proves nothing on its own about the
        // column it has to fit into on MySQL
        $this->assertSame('text', Schema::getColumnType('migration_links', 'reason'));
    }

    public function test_a_host_too_long_to_store_does_not_stop_the_scan(): void
    {
        $host = str_repeat('a', MigrationLink::MAX_HOST + 20) . '.example';

        $this->page('Links', '<a href="https://' . $host . '/page">nonsense</a>');

        $this->audit()->scan();

        // The address is still reported - it just does not group under a
        // host, because what is in front of the slash is not one
        $link = MigrationLink::first();
        $this->assertNotNull($link);
        $this->assertSame('https://' . $host . '/page', $link->url);
        $this->assertNull($link->host);
        $this->assertSame(MigrationLink::SCOPE_EXTERNAL, $link->scope);
    }

    public function test_a_timeout_is_not_a_verdict(): void
    {
        Http::fake(fn () => throw new ConnectException(
            'cURL error 28: Operation timed out after 10000 milliseconds',
            new PsrRequest('HEAD', 'https://slow.example/page')
        ));

        $this->page('Links', '<a href="https://slow.example/page">a slow server</a>');

        $this->audit()->scan();
        $result = $this->audit()->check();

        // Nobody can tell whether the page is there, and filing it as
        // gone would bury the ones that really are
        $this->assertSame(0, $result['broken']);
        $this->assertSame(MigrationLink::STATUS_UNSURE, MigrationLink::first()->status);

        // …and it is not asked again by itself, or the report would
        // never finish
        $this->assertSame(0, $result['remaining']);
    }

    public function test_a_login_wall_is_not_a_verdict_either(): void
    {
        Http::fake(['walled.example/*' => Http::response('Members only', 403)]);

        $this->page('Links', '<a href="https://walled.example/page">members only</a>');

        $this->audit()->scan();
        $this->audit()->check();

        $link = MigrationLink::first();
        $this->assertSame(MigrationLink::STATUS_UNSURE, $link->status);
        $this->assertSame(403, $link->http_status);
    }

    public function test_it_asks_properly_when_head_is_refused(): void
    {
        // Plenty of servers answer a HEAD with "method not allowed" and
        // the same page with a GET
        Http::fake(['fussy.example/*' => Http::sequence()
            ->push('Method not allowed', 405)
            ->push('The page', 200), ]);

        $this->page('Links', '<a href="https://fussy.example/page">a fussy server</a>');

        $this->audit()->scan();
        $this->audit()->check();

        $link = MigrationLink::first();
        $this->assertSame(MigrationLink::STATUS_OK, $link->status);
        $this->assertSame(200, $link->http_status);
    }

    public function test_a_rescan_keeps_the_verdicts_and_drops_what_is_gone_from_the_content(): void
    {
        Http::fake(['*' => Http::response('Not here', 404)]);

        $page = $this->page('Links', implode(' ', [
            '<a href="https://kept.example/page">kept</a>',
            '<a href="https://removed.example/page">removed</a>',
        ]));

        $this->audit()->scan();
        $this->audit()->check();

        $this->assertSame(2, MigrationLink::broken()->count());
        $checkedAt = MigrationLink::where('host', 'kept.example')->value('checked_at');

        // Somebody edits the page and takes one of the links out
        $page->translateOrNew('en')->content = '<a href="https://kept.example/page">kept</a>';
        $page->save();

        $result = $this->audit()->scan();

        $this->assertSame(1, $result['links']);
        $this->assertSame(1, $result['removed']);
        $this->assertNull(MigrationLink::where('host', 'removed.example')->first());

        // The verdict already in hand is not thrown away by a rescan:
        // it cost a request to somebody else's server
        $kept = MigrationLink::where('host', 'kept.example')->first();
        $this->assertSame(MigrationLink::STATUS_BROKEN, $kept->status);
        $this->assertSame($checkedAt->toDateTimeString(), $kept->checked_at->toDateTimeString());
    }

    public function test_naming_the_old_site_later_asks_its_links_again(): void
    {
        Http::fake(['*' => Http::response('Here', 200)]);

        $this->page('Links', '<a href="https://notyetknown.example/page">the old site</a>');

        $this->audit()->scan();
        $this->audit()->check();

        $this->assertSame(MigrationLink::SCOPE_EXTERNAL, MigrationLink::first()->scope);
        $this->assertSame(MigrationLink::STATUS_OK, MigrationLink::first()->status);

        // The admin works out where the content came from and says so
        $this->audit()->setLegacyHosts(['oldsite.example', 'notyetknown.example']);
        $this->audit()->scan();

        $link = MigrationLink::first();
        $this->assertSame(MigrationLink::SCOPE_LEGACY, $link->scope);
        // A different question about the same address: these are the
        // links that will break the day the old domain lapses
        $this->assertSame(MigrationLink::STATUS_PENDING, $link->status);
        $this->assertNull($link->checked_at);
    }

    public function test_the_report_counts_what_the_content_points_at(): void
    {
        Http::fake(['gone.example/*' => Http::response('', 404), '*' => Http::response('', 200)]);

        $this->page('Links', implode(' ', [
            '<a href="https://gone.example/a">a</a>',
            '<a href="https://alive.example/b">b</a>',
            '<a href="https://oldsite.example/c">c</a>',
            '<a href="/wiki/here">here</a>',
        ]));

        $this->audit()->scan();
        $this->audit()->check();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/migrations/links')
            ->assertOk();

        $summary = $response->json('summary');

        $this->assertSame(4, $summary['links']);
        $this->assertSame(1, $summary['statuses'][MigrationLink::STATUS_BROKEN]);
        $this->assertSame(1, $summary['scopes'][MigrationLink::SCOPE_LEGACY]['links']);
        $this->assertSame(1, $summary['scopes'][MigrationLink::SCOPE_INTERNAL]['links']);

        // Links to this site are listed but not asked about: this report
        // is about the web the content came from
        $this->assertSame(
            1,
            $summary['scopes'][MigrationLink::SCOPE_INTERNAL]['statuses'][MigrationLink::STATUS_PENDING]
        );
        $this->assertSame(0, $summary['pending']);

        // Worst first, so the report reads from the top
        $this->assertSame('https://gone.example/a', $response->json('links.0.url'));
        $this->assertSame(['oldsite.example'], $response->json('legacy_hosts'));

        $hosts = collect($response->json('hosts'));
        $this->assertSame(1, $hosts->firstWhere('host', 'gone.example')['broken']);
    }

    public function test_the_report_can_be_narrowed_to_what_is_broken(): void
    {
        Http::fake(['gone.example/*' => Http::response('', 404), '*' => Http::response('', 200)]);

        $this->page('Links', implode(' ', [
            '<a href="https://gone.example/a">a</a>',
            '<a href="https://alive.example/b">b</a>',
        ]));

        $this->audit()->scan();
        $this->audit()->check();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/migrations/links?status=' . MigrationLink::STATUS_BROKEN)
            ->assertOk();

        $this->assertSame(1, $response->json('matching'));
        $this->assertCount(1, $response->json('links'));
        $this->assertSame('https://gone.example/a', $response->json('links.0.url'));

        // The numbers above the table stay the numbers for everything
        $this->assertSame(2, $response->json('summary.links'));
    }

    public function test_the_dashboard_scans_and_then_works_through_the_links_in_batches(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->page('Links', collect(range(1, 5))
            ->map(fn (int $n) => '<a href="https://external.example/' . $n . '">' . $n . '</a>')
            ->implode(' '));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/links/scan', ['legacy_hosts' => ['oldsite.example', 'www.second.example/path']])
            ->assertOk()
            ->assertJsonPath('result.links', 5)
            // Pasted as a whole address as often as as a bare domain
            ->assertJsonPath('legacy_hosts', ['oldsite.example', 'second.example']);

        $first = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/links/check', ['limit' => 2, 'reset' => true])
            ->assertOk();

        $this->assertSame(2, $first->json('result.checked'));
        $this->assertSame(3, $first->json('result.remaining'));

        // The browser keeps going until nothing is left, which is what
        // keeps each request short enough to come back
        $rounds = 0;

        while ($rounds < 10) {
            $rounds++;
            $next = $this->actingAs($this->admin, 'sanctum')
                ->postJson('/api/admin/migrations/links/check', ['limit' => 2])
                ->assertOk();

            if ($next->json('result.remaining') === 0) {
                break;
            }
        }

        $this->assertSame(5, MigrationLink::broken()->count());
        $this->assertLessThan(10, $rounds);
    }

    public function test_the_report_can_be_thrown_away(): void
    {
        $this->page('Links', '<a href="https://external.example/a">a</a>');
        $this->audit()->scan();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/admin/migrations/links')
            ->assertOk();

        $this->assertSame(0, MigrationLink::count());
    }

    public function test_a_member_may_not_read_the_report(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/admin/migrations/links')->assertForbidden();
        $this->actingAs($member, 'sanctum')->postJson('/api/admin/migrations/links/scan')->assertForbidden();
        $this->actingAs($member, 'sanctum')->postJson('/api/admin/migrations/links/check')->assertForbidden();
        $this->actingAs($member, 'sanctum')->deleteJson('/api/admin/migrations/links')->assertForbidden();
    }

    public function test_an_unknown_scope_is_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/links/check', ['scopes' => ['everywhere']])
            ->assertStatus(422);
    }

    private function audit(): LinkAudit
    {
        return app(LinkAudit::class);
    }

    private function page(string $title, string $content): Page
    {
        $page                 = new Page(['user_id' => $this->admin->id, 'slug' => str()->slug($title)]);
        $translation          = $page->translateOrNew('en');
        $translation->title   = $title;
        $translation->content = $content;
        $page->save();

        return $page;
    }

    private function thread(string $title, string $body): ForumThread
    {
        $term     = Term::firstOrCreateByTitle('Imported');
        $taxonomy = Taxonomy::create(['taxonomy' => 'forum_cat', 'term_id' => $term->id]);

        return ForumThread::create([
            'taxonomy_id' => $taxonomy->id,
            'user_id'     => $this->admin->id,
            'title'       => $title,
            'slug'        => str()->slug($title),
            'body'        => $body,
        ]);
    }
}
