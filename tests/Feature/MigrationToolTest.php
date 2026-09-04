<?php

namespace Tests\Feature;

use App\Jobs\Migrations\GenericImportJob;
use App\Jobs\Migrations\MigrateLinkGalleryJob;
use App\Models\Collection;
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
        $pdo->exec('CREATE TABLE treffen (location TEXT, bericht TEXT, starttag TEXT, startzeit TEXT, x REAL, y REAL, foto_topic TEXT)');
        $pdo->exec("INSERT INTO treffen VALUES ('Town Hall &amp; Garden', 'First meetup', '20240315', '1830', 52.5, 13.4, 'Hanau Juni 2004')");
        $pdo->exec("INSERT INTO treffen VALUES ('Old Docks', 'Second meetup', '20240401', '1900', NULL, NULL, NULL)");
        $pdo->exec("INSERT INTO treffen VALUES ('Broken Row', 'No date at all', NULL, NULL, NULL, NULL, NULL)");
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
                'album' => ['source' => 'foto_topic'],
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
        // Album name lands in the details options for the Link Gallery step.
        $this->assertSame('Hanau Juni 2004', json_decode($details->options)->albumName);

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

    /**
     * Regression: collections keep their name in collection_translations —
     * linking galleries to events must match via the translation, not a
     * (removed) collections.name column.
     */
    public function test_link_gallery_job_matches_collections_via_translation(): void
    {
        $event = new Event();
        $event->title = 'Sommerfest';
        $event->user_id = $this->admin->id;
        $event->event_type_id = 1;
        $event->startDate = '2004-06-01';
        $event->endDate = '2004-06-01';
        $event->save();
        $event->details()->create([
            'lat' => 0,
            'lng' => 0,
            'options' => json_encode(['albumName' => 'Hanau Juni 2004']),
        ]);

        $collection = new Collection(['user_id' => $this->admin->id]);
        $collection->name = 'Hanau Juni 2004';
        $collection->save();

        $batchId = (string) Str::uuid();
        $log = MigrationLog::create([
            'batch_id' => $batchId,
            'migration_key' => 'linkGallery',
            'migration_name' => 'Link Gallery to Events',
            'status' => 'pending',
        ]);

        dispatch(new MigrateLinkGalleryJob($batchId, $log->id));

        $log = $log->fresh();
        $this->assertSame('completed', $log->status);
        $messages = array_column($log->logs ?? [], 'message');
        $this->assertContains('Link Gallery complete: 1 linked, 0 not found', $messages);
    }

    public function test_wiki_terms_import_builds_the_category_hierarchy(): void
    {
        $dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wiki_terms_' . uniqid() . '.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE kategorien (name TEXT, beschreibung TEXT, parent TEXT)');
        $insert = $pdo->prepare('INSERT INTO kategorien VALUES (?, ?, ?)');
        $insert->execute(['Orga', "== Info ==\nDie '''Organisation'''.", null]);
        $insert->execute(['Treffen_Archiv', null, 'Orga']);
        unset($insert, $pdo);

        try {
            $source = MigrationSource::create(['name' => 'Wiki cats', 'driver' => 'sqlite', 'database' => $dbPath]);
            $mapping = MigrationMapping::create([
                'migration_source_id' => $source->id,
                'name' => 'Wiki categories',
                'target' => 'wiki_terms',
                'source_table' => 'kategorien',
                'field_map' => [
                    'name' => ['source' => 'name'],
                    'description' => ['source' => 'beschreibung'],
                    'parent' => ['source' => 'parent'],
                ],
            ]);

            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/admin/migrations/mappings/{$mapping->id}/run")
                ->assertStatus(200);

            $orga = \App\Models\Tag\Term::whereTranslation('title', 'Orga')->firstOrFail();
            $orgaTax = \App\Models\Tag\Taxonomy::where('taxonomy', 'wiki')->where('term_id', $orga->id)->firstOrFail();
            $this->assertStringContainsString('<h2', $orgaTax->description);
            $this->assertStringContainsString('<strong class="highlight">Organisation</strong>', $orgaTax->description);

            // Child hangs below its parent, underscores become spaces.
            $child = \App\Models\Tag\Term::whereTranslation('title', 'Treffen Archiv')->firstOrFail();
            $childTax = \App\Models\Tag\Taxonomy::where('taxonomy', 'wiki')->where('term_id', $child->id)->firstOrFail();
            $this->assertSame($orgaTax->id, $childTax->parent_id);
        } finally {
            @unlink($dbPath);
        }
    }

    public function test_gallery_collections_and_images_import_from_folder(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // Image archive on "this server": {folded topic}/{id}.jpg
        $archive = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gallery_' . uniqid();
        mkdir($archive . DIRECTORY_SEPARATOR . 'Gruene Wiese', 0777, true);
        file_put_contents($archive . DIRECTORY_SEPARATOR . 'Gruene Wiese' . DIRECTORY_SEPARATOR . '1.jpg', 'jpg-bytes-1');
        file_put_contents($archive . DIRECTORY_SEPARATOR . 'Gruene Wiese' . DIRECTORY_SEPARATOR . '2.jpg', 'jpg-bytes-2');

        $dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bilder_' . uniqid() . '.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE bilder (id INTEGER, topic TEXT, info TEXT, uploader TEXT, datum TEXT)');
        $pdo->exec("INSERT INTO bilder VALUES (1, 'Grüne Wiese', 'Erstes Bild', 'madita', '2004-06-01 12:00:00')");
        $pdo->exec("INSERT INTO bilder VALUES (2, 'Grüne Wiese', NULL, 'madita', '2004-06-01 12:05:00')");
        $pdo->exec("INSERT INTO bilder VALUES (3, 'Grüne Wiese', 'Fehlt', 'madita', '2004-06-01 12:10:00')"); // file missing
        unset($pdo);

        try {
            $source = MigrationSource::create(['name' => 'Bilder', 'driver' => 'sqlite', 'database' => $dbPath]);

            // Collections first: one per distinct topic (duplicates skipped).
            $collections = MigrationMapping::create([
                'migration_source_id' => $source->id,
                'name' => 'Albums',
                'target' => 'gallery_collections',
                'source_table' => 'bilder',
                'field_map' => [
                    'name' => ['source' => 'topic'],
                    'user_id' => ['default' => 1],
                    'created_at' => ['source' => 'datum', 'transform' => 'datetime'],
                ],
            ]);
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/admin/migrations/mappings/{$collections->id}/run")
                ->assertStatus(200);

            $this->assertSame(1, Collection::count());
            $collection = Collection::whereTranslation('name', 'Grüne Wiese')->firstOrFail();

            // Then the files, resolved via a path template.
            $images = MigrationMapping::create([
                'migration_source_id' => $source->id,
                'name' => 'Album images',
                'target' => 'gallery_images',
                'source_table' => 'bilder',
                'field_map' => [
                    'collection' => ['source' => 'topic'],
                    'base_path' => ['default' => $archive],
                    'file' => ['template' => '{topic|fold}/{id}.jpg'],
                    'caption' => ['source' => 'info'],
                    'uploader' => ['source' => 'uploader'],
                    'created_at' => ['source' => 'datum', 'transform' => 'datetime'],
                ],
            ]);
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/admin/migrations/mappings/{$images->id}/run")
                ->assertStatus(200);

            $media = $collection->getMedia('gallery');
            $this->assertCount(2, $media); // the third file does not exist → row error
            $this->assertSame('Erstes Bild', $media[0]->getCustomProperty('caption'));
            $this->assertSame('madita', $media[0]->getCustomProperty('uploader'));

            // Re-running skips already-attached files instead of duplicating.
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/admin/migrations/mappings/{$images->id}/run")
                ->assertStatus(200);
            $this->assertCount(2, $collection->fresh()->getMedia('gallery'));
        } finally {
            @unlink($dbPath);
            @unlink($archive . DIRECTORY_SEPARATOR . 'Gruene Wiese' . DIRECTORY_SEPARATOR . '1.jpg');
            @unlink($archive . DIRECTORY_SEPARATOR . 'Gruene Wiese' . DIRECTORY_SEPARATOR . '2.jpg');
            @rmdir($archive . DIRECTORY_SEPARATOR . 'Gruene Wiese');
            @rmdir($archive);
        }
    }

    public function test_import_writes_translations_to_the_mapping_locale(): void
    {
        $mapping = $this->createEventsMapping($this->createSource());
        $mapping->update(['options' => ['locale' => 'de']]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/migrations/mappings/{$mapping->id}/run")
            ->assertStatus(200);

        $event = Event::whereDate('startDate', '2024-03-15')->firstOrFail();
        $this->assertNotNull($event->translate('de'));
        $this->assertSame('Town Hall & Garden', $event->translate('de')->title);

        // The worker's locale is restored after the run.
        $this->assertNotSame('de', app()->getLocale());
    }

    public function test_mappings_can_be_imported_and_exported_as_json(): void
    {
        $this->createSource(); // named "Legacy DB"

        $payload = [
            'mappings' => [
                [
                    'source' => 'Legacy DB',
                    'name' => 'Imported events',
                    'target' => 'events',
                    'source_table' => 'treffen',
                    'field_map' => [
                        'title' => ['source' => 'location', 'transform' => 'html_decode'],
                        'startDate' => ['source' => 'starttag', 'transform' => 'date', 'format' => 'Ymd'],
                        'user_id' => ['default' => 1],
                        'event_type_id' => ['default' => 1],
                    ],
                    'options' => [
                        'wheres' => [['column' => 'starttag', 'operator' => '!=', 'value' => '']],
                    ],
                ],
                [
                    'source' => 'No Such Source',
                    'name' => 'Broken mapping',
                    'target' => 'events',
                    'source_table' => 'x',
                    'field_map' => ['title' => ['source' => 'y']],
                ],
            ],
        ];

        $result = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/mappings/import', $payload)
            ->assertStatus(200)
            ->json();

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('No Such Source', $result['errors'][0]);

        $mapping = MigrationMapping::where('name', 'Imported events')->firstOrFail();
        $this->assertSame('events', $mapping->target);
        $this->assertSame('html_decode', $mapping->field_map['title']['transform']);

        // Re-importing the same JSON updates instead of duplicating.
        $payload['mappings'][0]['field_map']['description'] = ['source' => 'bericht'];
        $again = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/mappings/import', $payload)
            ->assertStatus(200)
            ->json();
        $this->assertSame(0, $again['created']);
        $this->assertSame(1, $again['updated']);
        $this->assertSame(1, MigrationMapping::where('name', 'Imported events')->count());
        $this->assertSame('bericht', $mapping->fresh()->field_map['description']['source']);

        // Export round-trips the same shape (source referenced by name).
        $export = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/migrations/mappings/export')
            ->assertStatus(200)
            ->json();
        $this->assertSame('Legacy DB', $export['mappings'][0]['source']);
        $this->assertSame('Imported events', $export['mappings'][0]['name']);
        $this->assertArrayHasKey('wheres', $export['mappings'][0]['options']);

        // The repo ships a ready-made JSON for the old hardcoded jobs — keep it valid.
        $shipped = json_decode(file_get_contents(base_path('docs/migration-mappings.stadtwache.json')), true);
        $this->assertIsArray($shipped['mappings']);
        $this->assertCount(8, $shipped['mappings']);
    }

    public function test_cli_command_runs_a_mapping_by_name(): void
    {
        $this->createEventsMapping($this->createSource());

        // Completes despite the one broken source row (logged as row error).
        $this->artisan('migration:run-mapping', ['mapping' => 'Legacy events'])
            ->assertExitCode(0);

        $this->assertSame(2, Event::count());

        $log = MigrationLog::orderByDesc('id')->first();
        $this->assertSame('completed', $log->status);
    }

    /**
     * Full legacy-account cycle: imports record the old owner's username;
     * a registered user files a claim (ticket); the admin assigns the
     * legacy name — content ownership moves and the ticket is resolved.
     */
    public function test_legacy_owner_claim_and_assignment_cycle(): void
    {
        // Import events whose mapping records the legacy owner.
        $source = $this->createSource();
        $mapping = $this->createEventsMapping($source);
        $fieldMap = $mapping->field_map;
        $fieldMap['legacy_owner'] = ['default' => 'OldVimes'];
        $fieldMap['legacy_source'] = ['default' => 'treffen'];
        $mapping->update(['field_map' => $fieldMap]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/migrations/mappings/{$mapping->id}/run")
            ->assertStatus(200);

        $this->assertSame(2, \App\Models\MigrationAttribution::where('legacy_username', 'OldVimes')->where('legacy_source', 'treffen')->count());

        // The same username in a DIFFERENT legacy system is a separate identity.
        $otherSystemEvent = new Event();
        $otherSystemEvent->title = 'Other system event';
        $otherSystemEvent->user_id = 1;
        $otherSystemEvent->event_type_id = 1;
        $otherSystemEvent->startDate = '2020-01-01';
        $otherSystemEvent->endDate = '2020-01-01';
        $otherSystemEvent->save();
        \App\Models\MigrationAttribution::record($otherSystemEvent, 'OldVimes', 'forum');

        // The returning user checks and claims their legacy account.
        $vimes = User::factory()->create(['username' => 'vimes']);

        $preview = $this->actingAs($vimes, 'sanctum')
            ->postJson('/api/account/legacy-claim/preview', ['legacy_username' => 'OldVimes'])
            ->assertStatus(200)
            ->json();
        $this->assertTrue($preview['found']);
        $this->assertSame(3, $preview['total']);
        // …with a per-system breakdown.
        $this->assertSame(2, $preview['sources']['treffen']['total']);
        $this->assertSame(1, $preview['sources']['forum']['total']);

        $this->actingAs($vimes, 'sanctum')
            ->postJson('/api/account/legacy-claim', ['legacy_username' => 'OldVimes', 'message' => 'That was me!'])
            ->assertStatus(201);

        // Duplicate claims are rejected.
        $this->actingAs($vimes, 'sanctum')
            ->postJson('/api/account/legacy-claim', ['legacy_username' => 'OldVimes'])
            ->assertStatus(409);

        // A legacy-user directory entry backs the treffen identity: it
        // carries the old e-mail, which matches vimes' registered e-mail —
        // so the listing marks the claim as e-mail-verified and suggests
        // the match even without a claim.
        \App\Models\MigrationLegacyUser::create([
            'legacy_source' => 'treffen',
            'username' => 'OldVimes',
            'email' => $vimes->email,
        ]);

        // The admin sees one row PER SYSTEM, both showing the open claim…
        $listing = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/migrations/legacy-users')
            ->assertStatus(200)
            ->json('legacyUsers');
        $rows = collect($listing)->where('legacy_username', 'OldVimes');
        $this->assertCount(2, $rows);
        $treffenRow = $rows->firstWhere('legacy_source', 'treffen');
        $this->assertSame(2, $treffenRow['items']);
        $this->assertSame('vimes', $treffenRow['claim']['user']['username']);
        $this->assertNull($treffenRow['assigned_user']);
        // Directory data: e-mail shown, claim verified, match suggested.
        $this->assertSame($vimes->email, $treffenRow['email']);
        $this->assertTrue($treffenRow['claim']['email_verified']);
        $this->assertSame('vimes', $treffenRow['suggested_user']['username']);
        // The forum identity has no directory entry — nothing verified there.
        $forumRow = $rows->firstWhere('legacy_source', 'forum');
        $this->assertNull($forumRow['email']);
        $this->assertFalse($forumRow['claim']['email_verified']);

        // …and assigns only the treffen identity.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/legacy-users/assign', [
                'legacy_username' => 'OldVimes',
                'legacy_source' => 'treffen',
                'user' => 'vimes',
            ])
            ->assertStatus(200)
            ->assertJsonPath('reassigned.Event', 2);

        // treffen content moved; the forum identity stayed untouched.
        $this->assertSame(2, Event::where('user_id', $vimes->id)->count());
        $this->assertNotSame($vimes->id, $otherSystemEvent->fresh()->user_id);

        // The ticket stays open while the forum part is unassigned…
        $ticket = \App\Models\Ticket\Ticket::whereHas('ticketType', fn ($q) => $q->where('slug', 'legacy-account-claim'))->first();
        $this->assertSame('open', $ticket->status);

        // …and resolves after the last system is assigned.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/legacy-users/assign', [
                'legacy_username' => 'OldVimes',
                'legacy_source' => 'forum',
                'user' => 'vimes',
            ])
            ->assertStatus(200);
        $this->assertSame('resolved', $ticket->fresh()->status);

        $listing = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/migrations/legacy-users')
            ->json('legacyUsers');
        $this->assertSame(
            'vimes',
            collect($listing)->where('legacy_username', 'OldVimes')->firstWhere('legacy_source', 'treffen')['assigned_user']['username']
        );

        // Unknown target user is a clean 422.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/migrations/legacy-users/assign', [
                'legacy_username' => 'OldVimes',
                'legacy_source' => 'treffen',
                'user' => 'nobody-here',
            ])
            ->assertStatus(422);
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
