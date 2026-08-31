<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\MigrationMapping;
use App\Models\MigrationSource;
use App\Models\User;
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
