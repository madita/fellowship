<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Migrations\MigrateEventsJob;
use App\Jobs\Migrations\MigrateGalleryJob;
use App\Jobs\Migrations\MigrateLinkGalleryJob;
use App\Jobs\Migrations\MigrateWikiLinkingJob;
use App\Jobs\Migrations\MigrateWikiPagesJob;
use App\Jobs\Migrations\MigrateWikiTermsJob;
use App\Jobs\Migrations\MigrateWikiTermsLinkingJob;
use App\Models\MigrationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MigrationController extends Controller
{
    /**
     * Available migrations with their job classes.
     */
    protected array $migrations = [
        'events' => [
            'name' => 'Events',
            'description' => 'Migrate events from the old database',
            'group' => 'events',
            'job' => MigrateEventsJob::class,
        ],
        'gallery' => [
            'name' => 'Gallery Collections',
            'description' => 'Migrate gallery collections and images',
            'group' => 'events',
            'job' => MigrateGalleryJob::class,
        ],
        'linkGallery' => [
            'name' => 'Link Gallery to Events',
            'description' => 'Create relationships between galleries and events',
            'group' => 'events',
            'job' => MigrateLinkGalleryJob::class,
        ],
        'wikiTerms' => [
            'name' => 'Wiki Terms',
            'description' => 'Migrate wiki categories and terms',
            'group' => 'wiki',
            'job' => MigrateWikiTermsJob::class,
        ],
        'wikiPages' => [
            'name' => 'Wiki Pages',
            'description' => 'Migrate wiki pages with content transformation',
            'group' => 'wiki',
            'job' => MigrateWikiPagesJob::class,
        ],
        'wikiLinking' => [
            'name' => 'Wiki Internal Links',
            'description' => 'Update internal wiki page links',
            'group' => 'wiki',
            'job' => MigrateWikiLinkingJob::class,
        ],
        'wikiTermsLinking' => [
            'name' => 'Wiki Terms Linking',
            'description' => 'Update wiki term links in descriptions',
            'group' => 'wiki',
            'job' => MigrateWikiTermsLinkingJob::class,
        ],
    ];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    /**
     * Get list of available migrations.
     */
    public function index(): JsonResponse
    {
        $migrations = [];
        foreach ($this->migrations as $key => $migration) {
            $migrations[] = [
                'key' => $key,
                'name' => $migration['name'],
                'description' => $migration['description'],
                'group' => $migration['group'],
            ];
        }

        // Get any active batches
        $activeBatches = MigrationLog::whereIn('status', ['pending', 'running'])
            ->select('batch_id')
            ->distinct()
            ->pluck('batch_id');

        return response()->json([
            'migrations' => $migrations,
            'groups' => [
                ['key' => 'events', 'name' => 'Events & Gallery'],
                ['key' => 'wiki', 'name' => 'Wiki'],
            ],
            'activeBatches' => $activeBatches,
        ]);
    }

    /**
     * Start migrations (dispatches jobs to queue).
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'migrations' => 'required|array|min:1',
            'migrations.*' => 'string',
        ]);

        $requestedMigrations = $request->input('migrations');
        $batchId = Str::uuid()->toString();

        // Validate all requested migrations exist
        foreach ($requestedMigrations as $key) {
            if (!array_key_exists($key, $this->migrations)) {
                return response()->json([
                    'error' => __('messages.migrations.unknown', ['key' => $key]),
                ], 400);
            }
        }

        // Create log entries and dispatch jobs
        foreach ($requestedMigrations as $index => $key) {
            $migration = $this->migrations[$key];

            // Create log entry
            $log = MigrationLog::create([
                'batch_id' => $batchId,
                'migration_key' => $key,
                'migration_name' => $migration['name'],
                'status' => 'pending',
                'logs' => [['type' => 'info', 'message' => __('messages.migrations.queued'), 'timestamp' => now()->toIso8601String()]],
            ]);

            // Dispatch job with delay to ensure sequential processing
            $jobClass = $migration['job'];
            $job = new $jobClass($batchId, $log->id);

            // Add delay for sequential processing (each job starts 1 second after previous)
            dispatch($job)->delay(now()->addSeconds($index));
        }

        return response()->json([
            'message' => __('messages.migrations.queued_all'),
            'batchId' => $batchId,
            'count' => count($requestedMigrations),
        ]);
    }

    /**
     * Get status of a batch.
     */
    public function status(string $batchId): JsonResponse
    {
        $logs = MigrationLog::where('batch_id', $batchId)
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            return response()->json(['error' => __('messages.migrations.batch_not_found')], 404);
        }

        $migrations = $logs->map(function ($log) {
            return [
                'key' => $log->migration_key,
                'name' => $log->migration_name,
                'status' => $log->status,
                'total' => $log->total_items,
                'processed' => $log->processed_items,
                'errors' => $log->error_count,
                'percentage' => $log->getProgressPercentage(),
                'currentItem' => $log->current_item,
                'lastError' => $log->last_error,
                'startedAt' => $log->started_at?->toIso8601String(),
                'completedAt' => $log->completed_at?->toIso8601String(),
            ];
        });

        // Calculate overall status
        $pending = $logs->where('status', 'pending')->count();
        $running = $logs->where('status', 'running')->count();
        $completed = $logs->where('status', 'completed')->count();
        $failed = $logs->where('status', 'failed')->count();
        $total = $logs->count();

        $overallStatus = 'pending';
        if ($running > 0) {
            $overallStatus = 'running';
        } elseif ($completed + $failed === $total) {
            $overallStatus = $failed > 0 ? 'completed_with_errors' : 'completed';
        }

        return response()->json([
            'batchId' => $batchId,
            'status' => $overallStatus,
            'summary' => [
                'pending' => $pending,
                'running' => $running,
                'completed' => $completed,
                'failed' => $failed,
                'total' => $total,
            ],
            'migrations' => $migrations,
        ]);
    }

    /**
     * Get logs for a specific migration in a batch.
     */
    public function logs(string $batchId, string $migrationKey): JsonResponse
    {
        $log = MigrationLog::where('batch_id', $batchId)
            ->where('migration_key', $migrationKey)
            ->first();

        if (!$log) {
            return response()->json(['error' => __('messages.migrations.log_not_found')], 404);
        }

        return response()->json([
            'migration' => $migrationKey,
            'name' => $log->migration_name,
            'status' => $log->status,
            'logs' => $log->logs ?? [],
            'total' => $log->total_items,
            'processed' => $log->processed_items,
            'errors' => $log->error_count,
            'percentage' => $log->getProgressPercentage(),
        ]);
    }

    /**
     * Cancel a running batch (marks pending as cancelled).
     */
    public function cancel(string $batchId): JsonResponse
    {
        $updated = MigrationLog::where('batch_id', $batchId)
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
                'last_error' => __('messages.migrations.cancelled'),
                'completed_at' => now(),
            ]);

        return response()->json([
            'message' => __('messages.migrations.cancelled_count', ['count' => $updated]),
            'cancelled' => $updated,
        ]);
    }

    /**
     * Get recent batches.
     */
    public function history(): JsonResponse
    {
        $batches = MigrationLog::selectRaw('
                batch_id,
                MIN(created_at) as started_at,
                MAX(completed_at) as completed_at,
                COUNT(*) as total_migrations,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->groupBy('batch_id')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        return response()->json([
            'batches' => $batches->map(function ($batch) {
                return [
                    'batchId' => $batch->batch_id,
                    'startedAt' => $batch->started_at,
                    'completedAt' => $batch->completed_at,
                    'totalMigrations' => $batch->total_migrations,
                    'completed' => $batch->completed,
                    'failed' => $batch->failed,
                    'status' => $batch->failed > 0 ? 'completed_with_errors' :
                        ($batch->completed === $batch->total_migrations ? 'completed' : 'running'),
                ];
            }),
        ]);
    }

    /**
     * Clear old migration logs.
     */
    public function clearHistory(): JsonResponse
    {
        $deleted = MigrationLog::where('created_at', '<', now()->subDays(7))->delete();

        return response()->json([
            'message' => __('messages.migrations.deleted_count', ['count' => $deleted]),
            'deleted' => $deleted,
        ]);
    }
}
