<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    protected $fillable = [
        'batch_id',
        'migration_key',
        'migration_name',
        'status',
        'total_items',
        'processed_items',
        'error_count',
        'current_item',
        'last_error',
        'logs',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'logs' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function addLog(string $type, string $message): void
    {
        $logs = $this->logs ?? [];
        $logs[] = [
            'type' => $type,
            'message' => mb_substr($message, 0, 1000),
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last 100 logs to prevent memory issues
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        $this->update(['logs' => $logs]);
    }

    public function markRunning(int $totalItems = 0): void
    {
        $this->update([
            'status' => 'running',
            'total_items' => $totalItems,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            // Errors can embed whole SQL statements (with huge bound
            // values) — keep the head, or storing the error fails too.
            'last_error' => mb_substr($error, 0, 1000),
            'completed_at' => now(),
        ]);
    }

    public function incrementProgress(?string $currentItem = null): void
    {
        $this->increment('processed_items');
        if ($currentItem) {
            $this->update(['current_item' => $currentItem]);
        }
    }

    public function incrementErrors(?string $error = null): void
    {
        $this->increment('error_count');
        if ($error) {
            $this->update(['last_error' => mb_substr($error, 0, 1000)]);
        }
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }
        return round(($this->processed_items / $this->total_items) * 100, 1);
    }
}
