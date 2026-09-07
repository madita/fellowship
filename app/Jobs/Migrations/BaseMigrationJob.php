<?php

namespace App\Jobs\Migrations;

use App\Models\MigrationLog;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class BaseMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout
    public int $tries = 1; // Don't retry failed jobs
    public bool $failOnTimeout = true;

    protected MigrationLog $log;
    protected string $batchId;
    private int $cancellationCounter = 0;

    public function __construct(string $batchId, int $logId)
    {
        $this->batchId = $batchId;
        $this->onQueue('migrations');
    }

    abstract protected function getMigrationKey(): string;
    abstract protected function runMigration(): void;

    public function handle(): void
    {
        $this->log = MigrationLog::where('batch_id', $this->batchId)
            ->where('migration_key', $this->getMigrationKey())
            ->firstOrFail();

        // Cancelled while still queued: don't run at all.
        if ($this->log->status !== 'pending') {
            $this->log->addLog('info', 'Skipped: migration was cancelled before it started');

            return;
        }

        try {
            $this->log->addLog('info', 'Migration started');
            $this->runMigration();
            $this->log->markCompleted();
            $this->log->addLog('success', 'Migration completed successfully');
        } catch (MigrationCancelledException $e) {
            // cancel() already marked the log — record where we stopped.
            $this->log->addLog('warning', "Migration cancelled by user after {$this->log->processed_items} items");
        } catch (Exception $e) {
            $this->log->markFailed($e->getMessage());
            $this->log->addLog('error', 'Migration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        if (isset($this->log)) {
            $this->log->markFailed($exception->getMessage());
            $this->log->addLog('error', 'Job failed: ' . $exception->getMessage());
        }
    }

    protected function log(string $type, string $message): void
    {
        $this->log->addLog($type, $message);
    }

    protected function setTotal(int $total): void
    {
        $this->log->markRunning($total);
    }

    protected function progress(?string $currentItem = null): void
    {
        $this->log->incrementProgress($currentItem);
        $this->checkForCancellation();
    }

    protected function error(string $message): void
    {
        $this->log->incrementErrors($message);
        $this->log->addLog('warning', $message);
        $this->checkForCancellation();
    }

    /**
     * Every few items, re-read the log status: the cancel endpoint marks the
     * row, and the only way to stop a job that is already running is for the
     * job itself to notice and bail out.
     *
     * @throws MigrationCancelledException
     */
    protected function checkForCancellation(): void
    {
        if (++$this->cancellationCounter % 10 !== 0) {
            return;
        }

        $status = MigrationLog::whereKey($this->log->id)->value('status');
        if ($status !== 'running') {
            throw new MigrationCancelledException();
        }
    }
}
