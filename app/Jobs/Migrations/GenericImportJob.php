<?php

namespace App\Jobs\Migrations;

use App\Models\MigrationMapping;
use App\Services\Migration\MigrationTargets;
use App\Services\Migration\RowMapper;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Imports rows from any configured source database into a feature target,
 * driven entirely by a stored MigrationMapping (source table + field map).
 */
class GenericImportJob extends BaseMigrationJob
{
    public function __construct(string $batchId, int $logId, private int $mappingId)
    {
        parent::__construct($batchId, $logId);
    }

    public static function migrationKeyFor(int $mappingId): string
    {
        return "import_{$mappingId}";
    }

    protected function getMigrationKey(): string
    {
        return self::migrationKeyFor($this->mappingId);
    }

    protected function runMigration(): void
    {
        $mapping = MigrationMapping::with('source')->findOrFail($this->mappingId);
        $connection = $mapping->source->connectionName();
        $mapper = new RowMapper($mapping->field_map);

        $query = DB::connection($connection)->table($mapping->source_table);

        $total = (clone $query)->count();
        $this->setTotal($total);
        $this->log('info', "Importing {$total} rows from \"{$mapping->source_table}\" ({$mapping->source->name}) into \"{$mapping->target}\"");

        $skipped = 0;

        foreach ($query->cursor() as $row) {
            $mapped = $mapper->map((array) $row);

            try {
                $errors = MigrationTargets::validateRow($mapping->target, $mapped);
                if ($errors) {
                    $this->error('Row skipped: ' . implode('; ', $errors));
                    continue;
                }

                $label = MigrationTargets::import($mapping->target, $mapped);
                if ($label === null) {
                    $skipped++;
                    $this->progress('(skipped duplicate)');
                } else {
                    $this->progress($label);
                }
            } catch (Exception $e) {
                $this->error('Row failed: ' . $e->getMessage());
            }

            if ($this->log->processed_items % 100 === 0) {
                gc_collect_cycles();
            }
        }

        $this->log('info', "Import complete: {$this->log->processed_items} processed, {$skipped} duplicates skipped, {$this->log->error_count} errors");
    }
}
