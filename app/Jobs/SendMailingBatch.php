<?php

namespace App\Jobs;

use App\Models\Mail\Mailing;
use App\Services\Mail\MailingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Posts one hundred-odd copies of a mailing.
 *
 * It carries ids rather than models: a job that had serialised a mailing
 * and its recipients would be holding the mailing as it looked when the
 * send started, and one holding thousands of users would be a queue
 * payload nobody wants. The rows are read when the job runs.
 *
 * Retried twice, because the usual reason a batch dies is a mail server
 * refusing a connection for a moment. A retry is safe: every recipient
 * row is claimed with a conditional update before anything is posted, so
 * the copies already sent are not sent again.
 */
class SendMailingBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public int $mailingId,
        public array $recipientIds
    ) {
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(MailingService $mailings): void
    {
        $mailing = Mailing::with('category')->find($this->mailingId);

        // Deleted, or cancelled while this was waiting in the queue
        if ($mailing === null || $mailing->status === Mailing::CANCELLED) {
            return;
        }

        $mailings->sendBatch($mailing, $this->recipientIds);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Mailing batch gave up', [
            'mailing_id' => $this->mailingId,
            'recipients' => count($this->recipientIds),
            'error'      => $e->getMessage(),
        ]);
    }
}
