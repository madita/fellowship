<?php

namespace Tests\Feature;

use App\Jobs\Migrations\GenericImportJob;
use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\MigrationLog;
use App\Models\MigrationMapping;
use App\Models\MigrationSource;
use App\Models\Page;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end test of the generic migration tool against a real (temporary
 * sqlite) source database: source CRUD + introspection, mapping with
 * transforms, dry-run preview and an actual import run (sync queue).
 */
class MigrationToolTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $sourceDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Import targets default to user_id 1 / event_type_id 1.
        EventType::create(['name' => 'Imported', 'color' => '#000000', 'options' => '{}']);

        // Build a legacy-style source database.
        $this->sourceDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migration_source_' . uniqid() . '.sqlite';
        $pdo = new PDO('sqlite:' . $this->sourceDbPath);
        $pdo->exec('CREATE TABLE treffen (location TEXT, bericht TEXT, starttag TEXT, startzeit TEXT, x REAL, y REAL)');
        $pdo->exec("INSERT INTO treffen VALUES ('Town Hall &amp; Garden', 'First meetup', '20240315', '1830', 52.5, 13.4)");
        $pdo->exec("INSERT INTO treffen VALUES ('Old Docks', 'Second meetup', '20240401', '1900', NULL, NULL)");
        $pdo->exec("INSERT INTO treffen VALUES ('Broken Row', 'No date at all', NULL, NULL, NULL, NULL)");
        unset($pdo);
    }

    protected function tearDown(): void
    {
        @unlink($this->sourceDbPath);
        parent::tearDown();
    }

    private function createSource(): MigrationSource
    {
        return MigrationSource::create([
            'name' => 'Legacy DB',
            'driver' => 'sqlite',
            'database' => $this->sourceDbPath,
        ]);
    }

    private function createEventsMapping(MigrationSource $source): MigrationMapping
    {
        return MigrationMapping::create([
            'migration_source_id' => $source->id,
            'name' => 'Legacy events',
            'target' => 'events',
            'source_table' => 'treffen',
            'field_map' => [
                'title' => ['source' => 'location', 'transform' => 'html_decode'],
                'description' => ['source' => 'bericht'],
                'startDate' => ['source' => 'starttag', 'transform' => 'date', 'format' => 'Ymd'],
                'startTime' => ['source' => 'startzeit', 'transform' => 'time', 'format' => 'Hi'],
                'lat' => ['source' => 'x', 'transform' => 'float'],
                'lng' => ['source' => 'y', 'transform' => 'float'],
                'user_id' => ['default' => 1],
                'event_type_id' => ['default' => 1],
            ],
        ]);
    }

    public function test_source_can_be_tested_and_introspected(): void
    {
        $source = $this->createSource();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/migrations/sources/{$source->id}/test")
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'tables' => 1]);

        $tables = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/migrations/sources/{$source->id}/tables")
            ->assertStatus(200)
            ->json('tables');
        $this->assertContains('treffen', $tables);

        $columns = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/migrations/sources/{$source->id}/tables/treffen/columns")
            ->assertStatus(200)
            ->json();
        $this->assertSame(3, $columns['rowCount']);
        $this->assertContains('starttag', array_column($columns['columns'], 'name'));
        $this->assertSame('Town Hall &amp; Garden', $columns['sample']['location']);
    }

    public function test_unknown_tables_are_rejected(): void
    {
        $source = $this->createSource();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/migrations/sources/{$source->id}/tables/sqlite_master; DROP TABLE x/columns")
            ->assertStatus(404);
    }

    public function test_preview_maps_rows_without_writing(): void
    {
        $mapping = $this->createEventsMapping($this->createSource());

        $preview = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/migrations/mappings/{$mapping->id}/preview")
            ->assertStatus(200)
            ->json();

        $this->assertSame(3, $preview['total']);
        $this->assertSame('Town Hall & Garden', $preview['rows'][0]['mapped']['title']);
        $this->assertSame('2024-03-15', $preview['rows'][0]['mapped']['startDate']);
        $this->assertSame('18:30:00', $preview['rows'][0]['mapped']['startTime']);
        $this->assertSame([], $preview['rows'][0]['errors']);

        // The row without a date fails the required-field validation.
        $this->assertNotEmpty($preview['rows'][2]['errors']);

        $this->assertSame(0, Event::count());
    }

    public function test_run_imports_valid_rows_and_reports_progress(): void
    {
        $mapping = $this->createEventsMapping($this->createSource());

        $batchId = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/migrations/mappings/{$mapping->id}/run")
            ->assertStatus(200)
            ->json('batchId');

        // sync queue: the import already ran.
        $this->assertSame(2, Event::count());

        $event = Event::whereDate('startDate', '2024-03-15')->firstOrFail();
        $this->assertSame('Town Hall & Garden', $event->title);
        $details = $event->details()->first();
        $this->assertEquals(52.5, (float) $details->lat);

        $status = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/migrations/status/{$batchId}")
            ->assertStatus(200)
            ->json();

        $this->assertSame('completed', $status['status']);
        $this->assertSame(1, $status['migrations'][0]['errors']); // the broken row
    }

    /**
     * MediaWiki spreads a page over page → revision → text; categories live
     * in the wikitext. This exercises the join + filter options and the
     * wiki_pages target end to end.
     */
    public function test_mediawiki_wiki_pages_import_with_joins_and_filters(): void
    {
        $dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mediawiki_' . uniqid() . '.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE page (page_id INTEGER, page_namespace INTEGER, page_title TEXT, page_is_redirect INTEGER, page_latest INTEGER)');
        $pdo->exec('CREATE TABLE revision (rev_id INTEGER, rev_page INTEGER, rev_timestamp TEXT, rev_text_id INTEGER)');
        $pdo->exec('CREATE TABLE "text" (old_id INTEGER, old_text TEXT)');

        $pdo->exec("INSERT INTO page VALUES (1, 0, 'Night_Watch', 0, 10)");
        $pdo->exec("INSERT INTO page VALUES (2, 1, 'Diskussion_Seite', 0, 11)"); // talk namespace — filtered out
        $pdo->exec("INSERT INTO page VALUES (3, 0, 'Old_Name', 1, 12)");         // redirect

        $pdo->exec("INSERT INTO revision VALUES (10, 1, '20240115120000', 100)");
        $pdo->exec("INSERT INTO revision VALUES (11, 2, '20240116120000', 101)");
        $pdo->exec("INSERT INTO revision VALUES (12, 3, '20240117120000', 102)");

        $wikitext = "== History ==\nThe '''watch''' patrols [[Ankh-Morpork|the city]].\n* Vimes\n* Carrot\n[[Kategorie:Orga]]";
        $insert = $pdo->prepare('INSERT INTO "text" VALUES (?, ?)');
        $insert->execute([100, $wikitext]);
        $insert->execute([101, 'talk page text']);
        $insert->execute([102, '#REDIRECT [[Night Watch]]']);
        unset($insert, $pdo);

        try {
            $source = MigrationSource::create([
                'name' => 'MediaWiki',
                'driver' => 'sqlite',
                'database' => $dbPath,
            ]);

            $mapping = MigrationMapping::create([
                'migration_source_id' => $source->id,
                'name' => 'MediaWiki pages',
                'target' => 'wiki_pages',
                'source_table' => 'page',
                'field_map' => [
                    'title' => ['source' => 'page_title', 'transform' => 'underscores_to_spaces'],
                    'content' => ['source' => 'old_text'],
                    'status' => ['source' => 'page_is_redirect', 'transform' => 'bool'],
                    'locale' => ['default' => 'de'],
                    'user_id' => ['default' => $this->admin->id],
                    'created_at' => ['source' => 'rev_timestamp', 'transform' => 'datetime', 'format' => 'YmdHis'],
                ],
                'options' => [
                    'joins' => [
                        ['table' => 'revision', 'type' => 'inner', 'first' => 'revision.rev_id', 'operator' => '=', 'second' => 'page.page_latest'],
                        ['table' => 'text', 'type' => 'inner', 'first' => 'text.old_id', 'operator' => '=', 'second' => 'revision.rev_text_id'],
                    ],
                    'wheres' => [
                        ['column' => 'page_namespace', 'operator' => '=', 'value' => '0'],
                    ],
                ],
            ]);

            // Preview resolves the joins and honours the namespace filter.
            $preview = $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/admin/migrations/mappings/{$mapping->id}/preview")
                ->assertStatus(200)
                ->json();

            $this->assertSame(2, $preview['total']);
            $this->assertSame('Night Watch', $preview['rows'][0]['mapped']['title']);
            $this->assertStringContainsString('watch', $preview['rows'][0]['mapped']['content']);
            $this->assertSame('2024-01-15 12:00:00', $preview['rows'][0]['mapped']['created_at']);
            $this->assertSame(0, Page::count());

            // Actual import (sync queue).
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/admin/migrations/mappings/{$mapping->id}/run")
                ->assertStatus(200);

            $this->assertSame(2, Page::count());

            // title/content land in the translation tables, under the mapped locale.
            $page = Page::whereTranslation('title', 'Night Watch')->firstOrFail();
            $content = $page->translate('de')->content;
            $this->assertSame('Night Watch', $page->translate('de')->title);
            $this->assertStringContainsString('<h2', $content);
            $this->assertStringContainsString('<strong class="highlight">watch</strong>', $content);
            $this->assertStringContainsString('<a href="/wiki/ankh-morpork">the city</a>', $content);
            $this->assertStringContainsString('<li>Vimes</li>', $content);
            $this->assertStringNotContainsString('[[Kategorie', $content);
            $this->assertTrue($page->hasCategory('Orga', 'wiki'));
            $this->assertSame('2024-01-15 12:00:00', $page->created_at->format('Y-m-d H:i:s'));

            $wiki = Wiki::where('slug', 'night-watch')->firstOrFail();
            $this->assertSame('Night Watch', $wiki->translate('de')->title);
            $this->assertNull($wiki->status);
            $this->assertTrue($wiki->isApproved());

            $redirect = Wiki::where('slug', 'old-name')->firstOrFail();
            $this->assertSame('redirect', $redirect->status);

            // Talk-namespace page was filtered out.
            $this->assertSame(0, Wiki::where('slug', 'diskussion-seite')->count());

            // Re-running skips already imported slugs instead of duplicating.
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/admin/migrations/mappings/{$mapping->id}/run")
                ->assertStatus(200);
            $this->assertSame(2, Page::count());
        } finally {
            @unlink($dbPath);
        }
    }

    public function test_cancel_stops_pending_and_running_migrations(): void
    {
        $mapping = $this->createEventsMapping($this->createSource());
        $batchId = (string) Str::uuid();

        $running = MigrationLog::create([
            'batch_id' => $batchId,
            'migration_key' => GenericImportJob::migrationKeyFor($mapping->id),
            'migration_name' => 'Legacy events',
            'status' => 'running',
        ]);
        $pending = MigrationLog::create([
            'batch_id' => $batchId,
            'migration_key' => 'events',
            'migration_name' => 'Events',
            'status' => 'pending',
        ]);

        // Cancel marks BOTH pending and running logs.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/migrations/cancel/{$batchId}")
            ->assertStatus(200)
            ->assertJson(['cancelled' => 2]);

        $this->assertSame('failed', $running->fresh()->status);
        $this->assertSame('failed', $pending->fresh()->status);

        // A job whose log was cancelled while still queued must not import.
        dispatch(new GenericImportJob($batchId, $running->id, $mapping->id));

        $this->assertSame(0, Event::count());
        $this->assertSame('failed', $running->fresh()->status);
        $logMessages = array_column($running->fresh()->logs ?? [], 'message');
        $this->assertContains('Skipped: migration was cancelled before it started', $logMessages);
    }

    public function test_non_admins_cannot_use_the_tool(): void
    {
        $source = $this->createSource();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/migrations/sources')
            ->assertStatus(403);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/migrations/sources/{$source->id}/test")
            ->assertStatus(403);
    }
}
