<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

/**
 * One mail, already written.
 *
 * Everything that varies - the subject, the body, the button, whose
 * unsubscribe link goes in the footer - has been worked out before this
 * is built, so this class only has to post it. That is deliberate: the
 * body is HTML written by an admin and filled in per recipient, and there
 * is exactly one place it could have come from (MailVariables, after
 * RichText has cleaned it), rather than a template compiled at send time.
 *
 * The footer carries the unsubscribe address twice: as a link somebody can
 * click, and as the List-Unsubscribe header, which is what makes a mail
 * client offer its own unsubscribe button and what keeps a newsletter out
 * of the spam folder.
 */
class TemplatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public ?string $unsubscribeUrl = null,
        public ?string $categoryName = null,
        // Where a mail client's own unsubscribe button posts. Not the
        // same address as the link above - see MailPreferences.
        public ?string $unsubscribePostUrl = null,
    ) {
    }

    public function build(): self
    {
        $this->subject($this->subjectLine)
            ->view('mail.templated')
            ->text('mail.templated-text')
            ->with([
                'bodyHtml'       => $this->bodyHtml,
                'actionLabel'    => $this->actionLabel,
                'actionUrl'      => $this->actionUrl,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'categoryName'   => $this->categoryName,
                'siteName'       => $this->siteName(),
                'siteUrl'        => rtrim((string) config('app.url'), '/'),
                'headerHtml'     => $this->setting('email_template_header'),
                'footerHtml'     => $this->setting('email_template_footer'),
            ]);

        $from = $this->setting('email_sender_address');

        // Only when an admin has filled it in: Laravel's own configured
        // sender is the right fallback, and an empty from() is a bounce
        if ($from) {
            $this->from($from, $this->setting('email_sender_name') ?: $this->siteName());
        }

        $oneClick = $this->unsubscribePostUrl ?: $this->unsubscribeUrl;

        if ($oneClick) {
            $this->withSymfonyMessage(function (Email $message) use ($oneClick) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', '<' . $oneClick . '>');
                // Says the address may be posted to without asking us
                // first, which is what lets a mail client offer its own
                // unsubscribe button and do it in one press
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
        }

        return $this;
    }

    private function siteName(): string
    {
        return $this->setting('app_name') ?: (string) config('app.name', 'Fellowship');
    }

    /**
     * Settings are read through a cache that may not be reachable from a
     * queue worker on a half-configured install, and a mail that cannot
     * be sent is worse than one without a letterhead.
     */
    private function setting(string $key): ?string
    {
        try {
            $value = Setting::get($key);
        } catch (\Throwable $e) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
