<?php

namespace App\Console\Commands;

use App\Jobs\Migrations\GenericImportJob;
use App\Models\MigrationLog;
use App\Models\MigrationMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Runs a mapping import synchronously in the CLI. Large imports (e.g. the
 * gallery images) outlive any web request; on the sync queue driver the
 * dashboard's "Run" button executes inside the HTTP request and gets killed
 * by the web server timeout. Re-running resumes: targets skip rows that were
 * already imported.
 */
class RunMigrationMapping extends Command
{
    protected $signature = 'migration:run-mapping {mapping : Mapping id or name}';

    protected $description = 'Run a migration tool mapping import in the CLI (no web timeouts; safe to re-run/resume)';

    public function handle(): int
    {
        $key = $this->argument('mapping');
        $mapping = MigrationMapping::query()
            ->when(
                is_numeric($key),
                fn ($query) => $query->whereKey((int) $key),
                fn ($query) => $query->where('name', $key)
            )
            ->first();

        if (!$mapping) {
            $this->error("Mapping \"{$key}\" not found. Available:");
            MigrationMapping::orderBy('id')->get(['id', 'name'])
                ->each(fn ($m) => $this->line("  {$m->id}: {$m->name}"));

            return self::FAILURE;
        }

        $batchId = Str::uuid()->toString();
        $log = MigrationLog::create([
            'batch_id' => $batchId,
            'migration_key' => GenericImportJob::migrationKeyFor($mapping->id),
            'migration_name' => $mapping->name,
            'status' => 'pending',
            'logs' => [['type' => 'info', 'message' => 'Started from CLI', 'timestamp' => now()->toIso8601String()]],
        ]);

        $this->info("Running \"{$mapping->name}\" (batch {$batchId}) — progress is visible on the dashboard's Runs tab.");
        $start = microtime(true);

        (new GenericImportJob($batchId, $log->id, $mapping->id))->handle();

        $log->refresh();
        $minutes = round((microtime(true) - $start) / 60, 1);

        $this->line("Status: {$log->status} — {$log->processed_items}/{$log->total_items} processed, {$log->error_count} errors, {$minutes} min");
        if ($log->last_error) {
            $this->warn("Last error: {$log->last_error}");
        }

        return $log->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
