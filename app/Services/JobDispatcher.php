<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;

class JobDispatcher
{
    /**
     * Dispatch a job, either to the queue or synchronously based on settings.
     *
     * @param ShouldQueue $job   The job to dispatch
     * @param string|null $queue Optional queue name
     *
     * @return mixed
     */
    public static function dispatch(ShouldQueue $job, ?string $queue = null)
    {
        $backgroundJobsEnabled = static::isBackgroundJobsEnabled();

        if ($backgroundJobsEnabled) {
            // Dispatch to queue
            if ($queue) {
                return dispatch($job)->onQueue($queue);
            }

            return dispatch($job);
        }

        // Run synchronously
        return dispatch_sync($job);
    }

    /**
     * Dispatch a job to run after the response is sent.
     *
     * @param ShouldQueue $job The job to dispatch
     *
     * @return mixed
     */
    public static function dispatchAfterResponse(ShouldQueue $job)
    {
        $backgroundJobsEnabled = static::isBackgroundJobsEnabled();

        if ($backgroundJobsEnabled) {
            return dispatch($job)->afterResponse();
        }

        // Run synchronously
        return dispatch_sync($job);
    }

    /**
     * Dispatch a job with a delay.
     *
     * @param ShouldQueue                          $job   The job to dispatch
     * @param \DateTimeInterface|\DateInterval|int $delay The delay
     *
     * @return mixed
     */
    public static function dispatchWithDelay(ShouldQueue $job, $delay)
    {
        $backgroundJobsEnabled = static::isBackgroundJobsEnabled();

        if ($backgroundJobsEnabled) {
            return dispatch($job)->delay($delay);
        }

        // Run synchronously (ignore delay)
        return dispatch_sync($job);
    }

    /**
     * Check if background jobs are enabled.
     *
     * @return bool
     */
    public static function isBackgroundJobsEnabled(): bool
    {
        // Check database setting
        $setting = Setting::get('background_jobs_enabled', true);

        // Convert to boolean
        if (is_string($setting)) {
            return $setting === 'true' || $setting === '1';
        }

        return (bool) $setting;
    }

    /**
     * Get the queue connection to use.
     *
     * @return string
     */
    public static function getQueueConnection(): string
    {
        if (!static::isBackgroundJobsEnabled()) {
            return 'sync';
        }

        return config('queue.default', 'sync');
    }
}
