<?php

namespace App\Console\Commands;

use App\Models\Mail\Mailing;
use App\Services\Mail\MailingService;
use Illuminate\Console\Command;

/**
 * Starts the mailings whose time has come.
 *
 * Scheduled every minute, so "send this on Friday at nine" means Friday
 * at about nine. A mailing whose moment passed while nothing was running
 * is still started - a newsletter that is an hour late is better than one
 * that never goes.
 */
class SendDueMailings extends Command
{
    protected $signature = 'mailings:send-due';

    protected $description = 'Start any mailing that was scheduled for now or earlier';

    public function handle(MailingService $mailings): int
    {
        $due = Mailing::with('category')->due()->orderBy('scheduled_for')->get();

        if ($due->isEmpty()) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        foreach ($due as $mailing) {
            // Claimed before anything is prepared, so two overlapping
            // runs of this command cannot both start the same mailing
            $claimed = Mailing::where('id', $mailing->id)
                ->where('status', Mailing::SCHEDULED)
                ->update(['status' => Mailing::SENDING, 'started_at' => now(), 'updated_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            $mailings->start($mailing->refresh());

            $this->info("Started #{$mailing->id}: {$mailing->subject}");
        }

        return self::SUCCESS;
    }
}
