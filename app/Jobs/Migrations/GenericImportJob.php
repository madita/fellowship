<?php

namespace App\Jobs\Migrations;

use App\Models\MigrationMapping;
use App\Services\Migration\MigrationTargets;
use App\Services\Migration\RowMapper;
use App\Services\Migration\SourceQuery;
use Exception;

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
        $mapper = new RowMapper($mapping->field_map);

        // Translatable models write to the app locale — imports of legacy
        // content should land in the language the content is written in.
        $previousLocale = app()->getLocale();
        $locale = $mapping->options['locale'] ?? null;
        if ($locale) {
            app()->setLocale($locale);
            $this->log('info', "Importing with content locale \"{$locale}\"");
        }

        try {
            $this->import($mapping, $mapper);
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function import(MigrationMapping $mapping, RowMapper $mapper): void
    {
        $query = SourceQuery::build($mapping);

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
            } catch (MigrationCancelledException $e) {
                throw $e;
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
