<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;

trait SafeSearchable
{
    use Searchable;

    /**
     * Dispatch the job to make the given models searchable.
     * Silently skips indexing when Meilisearch is unavailable.
     */
    public function searchable(): void
    {
        try {
            parent::searchable();
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            Log::warning('Meilisearch unavailable, skipping index for ' . static::class . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            if ($this->isMeilisearchConnectionError($e)) {
                Log::warning('Meilisearch unavailable, skipping index for ' . static::class . ': ' . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    /**
     * Dispatch the job to make the given models unsearchable.
     * Silently skips removal when Meilisearch is unavailable.
     */
    public function unsearchable(): void
    {
        try {
            parent::unsearchable();
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            Log::warning('Meilisearch unavailable, skipping unsearchable for ' . static::class . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            if ($this->isMeilisearchConnectionError($e)) {
                Log::warning('Meilisearch unavailable, skipping unsearchable for ' . static::class . ': ' . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    /**
     * Check if the exception is caused by a Meilisearch connection issue.
     */
    private function isMeilisearchConnectionError(\Exception $e): bool
    {
        $previous = $e->getPrevious();

        if ($previous instanceof \Meilisearch\Exceptions\CommunicationException) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'meilisearch')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'could not resolve host');
    }
}
