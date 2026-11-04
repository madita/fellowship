<?php

namespace App\Services\Mail;

use App\Mail\TemplatedMail;
use App\Models\Mail\MailTemplate;
use App\Models\User;
use App\Services\JobDispatcher;
use App\Support\MailVariables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sending one of the mails the site writes by itself.
 *
 * Everything the code mails a single member goes through here - the
 * welcome, the handed-over achievement, the rank reached - so all of it
 * gets the same three things without each caller remembering them: the
 * member's own language, their preferences honoured, and an unsubscribe
 * link in the footer that works.
 *
 * It returns whether a mail actually went out rather than throwing. A
 * member who would rather not hear about achievements is not an error,
 * and neither is a template an admin has switched off; the thing that
 * caused the mail - the award itself - must not fail because of either.
 */
class TemplatedMailer
{
    public function __construct(private MailPreferences $preferences)
    {
    }

    /**
     * Mail one member the template under this key.
     *
     * @param  array<string, mixed>  $variables  what the template's own placeholders are filled with
     */
    public function send(User $user, string $templateKey, array $variables = []): bool
    {
        $template = MailTemplate::active($templateKey);

        if ($template === null || $template->category === null) {
            return false;
        }

        if ( ! $this->preferences->allows($user, $template->category)) {
            return false;
        }

        $locale  = $this->localeFor($user);
        $message = $this->render($template, $user, $variables, $locale);

        $mail = new TemplatedMail(
            subjectLine: $message['subject'],
            bodyHtml: $message['body'],
            actionLabel: $message['action_label'],
            actionUrl: $message['action_url'],
            unsubscribeUrl: $message['unsubscribe_url'],
            categoryName: $template->category->translate($locale)?->name ?? $template->category->name,
            unsubscribePostUrl: $template->category->is_mandatory
                ? null
                : $this->preferences->unsubscribePostUrl($user, $template->category),
        );

        try {
            $pending = Mail::to($user->email)->locale($locale);

            // The same switch the rest of the site's background work
            // obeys, so an install with no queue worker still sends
            JobDispatcher::isBackgroundJobsEnabled()
                ? $pending->queue($mail)
                : $pending->send($mail);
        } catch (\Throwable $e) {
            Log::error('Templated mail failed', [
                'template' => $templateKey,
                'user_id'  => $user->id,
                'error'    => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * What this template says to this member, placeholders and all.
     *
     * Also what the admin preview shows, which is why it is public and
     * why it takes the locale rather than reading it: the preview is
     * looked at in whatever language the admin picked.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, string|null>
     */
    public function render(MailTemplate $template, User $user, array $variables = [], ?string $locale = null): array
    {
        $locale      = $locale ?: $this->localeFor($user);
        $translation = $template->translateOrDefault($locale);

        $unsubscribe = $template->category && ! $template->category->is_mandatory
            ? $this->preferences->unsubscribeUrl($user, $template->category)
            : null;

        $filled = MailVariables::forUser($user, array_merge($variables, [
            'unsubscribe_url' => (string) $unsubscribe,
        ]));

        return [
            'subject'         => MailVariables::text($translation?->subject, $filled),
            'body'            => MailVariables::html($translation?->body, $filled),
            'action_label'    => $template->action_label ? MailVariables::text($template->action_label, $filled) : null,
            'action_url'      => $template->action_url ? MailVariables::text($template->action_url, $filled) : null,
            'unsubscribe_url' => $unsubscribe,
        ];
    }

    /**
     * The language the member reads the site in. `language` is an
     * accessor that already falls back to the site default, so an account
     * that never chose one still gets a real locale.
     */
    private function localeFor(User $user): string
    {
        return (string) ($user->language ?: config('app.locale', 'en'));
    }
}
