<?php

namespace Tests\Feature;

use App\Models\Migration\MigrationMapping;
use App\Models\Migration\MigrationSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A field map entry may carry only a `default` — that is how a mapping sets a
 * fixed value such as legacy_source => "wiki". The save rules listed source,
 * transform, format and template but not default, and Laravel returns only the
 * keys it validated, so those entries were silently stored empty. Every
 * imported row then failed its required-field check and nothing was imported.
 */
class MigrationMappingDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected MigrationSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->source = MigrationSource::create([
            'name'     => 'Legacy DB',
            'driver'   => 'sqlite',
            'database' => database_path('database.sqlite'),
        ]);
    }

    private function fieldMap(): array
    {
        return [
            'username'      => ['source' => 'user_name', 'transform' => 'underscores_to_spaces'],
            'legacy_source' => ['default' => 'wiki'],
            'user_id'       => ['default' => 1],
        ];
    }

    public function test_importing_a_pack_keeps_fields_that_only_carry_a_default(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/migrations/mappings/import', [
            'mappings' => [[
                'source'       => 'Legacy DB',
                'name'         => 'Legacy Users (wiki)',
                'target'       => 'legacy_users',
                'source_table' => 'ww_user',
                'field_map'    => $this->fieldMap(),
            ]],
        ])->assertOk()->assertJsonPath('created', 1);

        $stored = MigrationMapping::where('name', 'Legacy Users (wiki)')->firstOrFail();

        $this->assertSame('wiki', $stored->field_map['legacy_source']['default'] ?? null);
        $this->assertSame(1, $stored->field_map['user_id']['default'] ?? null);
        $this->assertSame('user_name', $stored->field_map['username']['source'] ?? null);
    }

    public function test_saving_a_mapping_keeps_a_default_only_field(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/migrations/mappings', [
            'migration_source_id' => $this->source->id,
            'name'                => 'Saved by hand',
            'target'              => 'legacy_users',
            'source_table'        => 'ww_user',
            'field_map'           => $this->fieldMap(),
        ])->assertSuccessful();

        $stored = MigrationMapping::where('name', 'Saved by hand')->firstOrFail();

        $this->assertSame('wiki', $stored->field_map['legacy_source']['default'] ?? null);
    }

    public function test_a_row_maps_to_the_default_so_required_fields_pass(): void
    {
        $mapping = MigrationMapping::create([
            'migration_source_id' => $this->source->id,
            'name'                => 'Legacy Users',
            'target'              => 'legacy_users',
            'source_table'        => 'ww_user',
            'field_map'           => $this->fieldMap(),
        ]);

        $mapped = (new \App\Services\Migration\RowMapper($mapping->field_map))
            ->map(['user_name' => 'Sam_Vimes']);

        $this->assertSame('Sam Vimes', $mapped['username']);
        $this->assertSame('wiki', $mapped['legacy_source']);
        $this->assertSame(
            [],
            \App\Services\Migration\MigrationTargets::validateRow('legacy_users', $mapped),
            'a default-only field satisfies the required check'
        );
    }
}
