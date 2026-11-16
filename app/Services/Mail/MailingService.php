<?php

namespace App\Services\Mail;

use App\Jobs\SendMailingBatch;
use App\Mail\TemplatedMail;
use App\Models\Mail\MailCategory;
use App\Models\Mail\Mailing;
use App\Models\Mail\MailingRecipient;
use App\Models\User;
use App\Services\JobDispatcher;
use App\Support\MailVariables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sending one piece of writing to a lot of people.
 *
 * It happens in two halves on purpose. `prepare()` writes one row per
 * recipient and sends nothing; `sendBatch()` then works through those
 * rows a hundred at a time, in a queued job per batch. Everything that
 * makes bulk mail survivable follows from that split:
 *
 *   - a run that dies halfway can be started again, because the rows say
 *     who already has their copy;
 *   - a member cannot get two copies, because a row is claimed with a
 *     conditional update before anything is posted;
 *   - one address the mailer chokes on fails its own row instead of the
 *     whole send;
 *   - the admin screen can show real progress while it happens.
 *
 * A mailing is written in one language. The audience can be narrowed by
 * language for that reason, so a German newsletter and an English one are
 * two mailings to two sets of people rather than one mailing trying to be
 * both. The templates are translated, so each starts from the right
 * wording.
 */
class MailingService
{
    /**
     * How many recipients one queued job takes. Small enough that a lost
     * worker costs little, big enough not to queue ten thousand jobs.
     */
    public const BATCH = 100;

    public function __construct(
        private Audience $audience,
        private MailPreferences $preferences
    ) {
    }

    /**
     * How many people an audience comes to, and how many of them will
     * actually get the mail - what the composer shows before anybody
     * presses send.
     *
     * @param  array<string, mixed>  $audience
     * @return array<string, int>
     */
    public function estimate(array $audience, MailCategory $category): array
    {
        $total    = 0;
        $reached  = 0;

        $this->audience->query($audience)
            ->select(['id', 'email', 'mail_unsubscribed_at'])
            ->chunkById(500, function ($users) use (&$total, &$reached, $category) {
                foreach ($users as $user) {
                    $total++;

                    if ($this->preferences->allows($user, $category)) {
                        $reached++;
                    }
                }
            });

        return [
            'total'        => $total,
            'will_receive' => $reached,
            'opted_out'    => $total - $reached,
        ];
    }

    /**
     * Write the recipient rows for a mailing, and nothing else.
     *
     * Safe to run twice: the rows already there are left exactly as they
     * are, so re-preparing a mailing that half-sent does not resurrect
     * the copies that already went out.
     *
     * @return int how many are still to be sent
     */
    public function prepare(Mailing $mailing): int
    {
        $now = now();

        $this->audience->query($mailing->audience)
            ->select(['id', 'email', 'mail_unsubscribed_at'])
            ->chunkById(500, function ($users) use ($mailing, $now) {
                $rows = [];

                foreach ($users as $user) {
                    $allowed = $this->preferences->allows($user, $mailing->category);

                    $rows[] = [
                        'mailing_id' => $mailing->id,
                        'user_id'    => $user->id,
                        'email'      => (string) $user->email,
                        'status'     => $allowed ? MailingRecipient::PENDING : MailingRecipient::SKIPPED,
                        'reason'     => $allowed
                            ? null
                            : ($user->mail_unsubscribed_at !== null ? 'unsubscribed_all' : 'opted_out'),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Ignore rather than upsert: a row that exists already
                // has a history - sent, failed, when - and this must not
                // reset it
                if ($rows) {
                    DB::table('mailing_recipients')->insertOrIgnore($rows);
                }
            });

        $this->recount($mailing);

        return (int) $mailing->recipients()->where('status', MailingRecipient::PENDING)->count();
    }

    /**
     * Prepare if it has not been prepared, then queue the work.
     */
    public function start(Mailing $mailing): Mailing
    {
        $pending = $this->prepare($mailing);

        $mailing->forceFill([
            'status'     => Mailing::SENDING,
            'started_at' => $mailing->started_at ?: now(),
        ])->save();

        // Nobody to write to: finished, not stuck half-way
        if ($pending === 0) {
            return $this->finish($mailing);
        }

        $mailing->recipients()
            ->where('status', MailingRecipient::PENDING)
            ->orderBy('id')
            ->pluck('id')
            ->chunk(self::BATCH)
            ->each(fn ($ids) => JobDispatcher::dispatch(
                new SendMailingBatch($mailing->id, $ids->values()->all())
            ));

        return $mailing->refresh();
    }

    /**
     * Post one batch of a mailing. This is the whole of what the job
     * does; it lives here so the service owns every rule about sending.
     *
     * @param  array<int, int>  $recipientIds
     */
    public function sendBatch(Mailing $mailing, array $recipientIds): void
    {
        foreach ($recipientIds as $id) {
            // Claiming the row before posting is what makes a repeated
            // job - a retry, an overlapping run - harmless: whoever gets
            // the update sends, everybody else moves on
            $claimed = MailingRecipient::where('id', $id)
                ->where('mailing_id', $mailing->id)
                ->where('status', MailingRecipient::PENDING)
                ->update(['status' => MailingRecipient::SENT, 'sent_at' => now(), 'updated_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            /** @var MailingRecipient|null $recipient */
            $recipient = MailingRecipient::with('user')->find($id);

            if ($recipient === null || $recipient->user === null) {
                continue;
            }

            try {
                $this->deliver($mailing, $recipient);
            } catch (\Throwable $e) {
                Log::error('Mailing delivery failed', [
                    'mailing_id' => $mailing->id,
                    'user_id'    => $recipient->user_id,
                    'error'      => $e->getMessage(),
                ]);

                $recipient->forceFill([
                    'status'  => MailingRecipient::FAILED,
                    'sent_at' => null,
                    'reason'  => mb_substr($e->getMessage(), 0, 250),
                ])->save();
            }
        }

        $this->recount($mailing);

        if ($mailing->recipients()->where('status', MailingRecipient::PENDING)->doesntExist()) {
            $this->finish($mailing);
        }
    }

    /**
     * The mailing as it would arrive, to one address, without touching
     * the recipient rows or the counts. What "send me a test" does.
     */
    public function sendTest(Mailing $mailing, User $to): void
    {
        $rendered = $this->renderFor($mailing, $to);

        Mail::to($to->email)->locale($this->localeFor($to))->send(new TemplatedMail(
            subjectLine: '[Test] ' . $rendered['subject'],
            bodyHtml: $rendered['body'],
            actionLabel: $rendered['action_label'],
            actionUrl: $rendered['action_url'],
            // No unsubscribe footer on a test: it is the sender's own
            // address, and one careless click should not take them off
            // the list they are about to write to
            unsubscribeUrl: null,
            categoryName: $mailing->category?->name,
        ));
    }

    /**
     * Stop a send. What has gone out has gone out; the rest never will.
     */
    public function cancel(Mailing $mailing): Mailing
    {
        $mailing->recipients()
            ->where('status', MailingRecipient::PENDING)
            ->update([
                'status'     => MailingRecipient::SKIPPED,
                'reason'     => 'cancelled',
                'updated_at' => now(),
            ]);

        $this->recount($mailing);

        $mailing->forceFill([
            'status'      => Mailing::CANCELLED,
            'finished_at' => now(),
        ])->save();

        return $mailing->refresh();
    }

    /**
     * What this mailing says to this member.
     *
     * @return array<string, string|null>
     */
    public function renderFor(Mailing $mailing, User $user, ?string $unsubscribeUrl = null): array
    {
        $variables = MailVariables::forUser($user, [
            'unsubscribe_url' => (string) $unsubscribeUrl,
        ]);

        return [
            'subject'      => MailVariables::text($mailing->subject, $variables),
            'body'         => MailVariables::html($mailing->body, $variables),
            'action_label' => $mailing->action_label ? MailVariables::text($mailing->action_label, $variables) : null,
            'action_url'   => $mailing->action_url ? MailVariables::text($mailing->action_url, $variables) : null,
        ];
    }

    private function deliver(Mailing $mailing, MailingRecipient $recipient): void
    {
        $user        = $recipient->user;
        $category    = $mailing->category;
        $unsubscribe = $category && ! $category->is_mandatory
            ? $this->preferences->unsubscribeUrl($user, $category)
            : null;

        $rendered = $this->renderFor($mailing, $user, $unsubscribe);

        // Sent rather than queued: this is already running inside a
        // queued batch, and queueing again would hide a failure from the
        // row that is meant to record it
        Mail::to($recipient->email)->locale($this->localeFor($user))->send(new TemplatedMail(
            subjectLine: $rendered['subject'],
            bodyHtml: $rendered['body'],
            actionLabel: $rendered['action_label'],
            actionUrl: $rendered['action_url'],
            unsubscribeUrl: $unsubscribe,
            categoryName: $category?->translate($this->localeFor($user))?->name ?? $category?->name,
            unsubscribePostUrl: $unsubscribe === null
                ? null
                : $this->preferences->unsubscribePostUrl($user, $category),
        ));
    }

    /**
     * Mark a mailing done, and say whether it went well. A send in which
     * every single copy failed is a failure worth seeing in the list,
     * even though the run itself finished.
     */
    private function finish(Mailing $mailing): Mailing
    {
        $this->recount($mailing);
        $mailing->refresh();

        $allFailed = $mailing->sent_count === 0 && $mailing->failed_count > 0;

        $mailing->forceFill([
            'status'      => $allFailed ? Mailing::FAILED : Mailing::SENT,
            'finished_at' => now(),
        ])->save();

        return $mailing->refresh();
    }

    /**
     * Copy the recipient tallies onto the mailing. The rows are the
     * truth; these are what a list of mailings can show without counting
     * them all again.
     */
    private function recount(Mailing $mailing): void
    {
        $counts = $mailing->recipients()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $mailing->forceFill([
            'recipient_count' => (int) $counts->sum(),
            'sent_count'      => (int) ($counts[MailingRecipient::SENT] ?? 0),
            'failed_count'    => (int) ($counts[MailingRecipient::FAILED] ?? 0),
            'skipped_count'   => (int) ($counts[MailingRecipient::SKIPPED] ?? 0),
        ])->save();
    }

    private function localeFor(User $user): string
    {
        return (string) ($user->language ?: config('app.locale', 'en'));
    }
}
