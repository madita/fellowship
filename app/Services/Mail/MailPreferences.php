<?php

namespace App\Services\Mail;

use App\Models\Mail\MailCategory;
use App\Models\Mail\MailPreference;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Whether a particular member wants a particular kind of mail.
 *
 * One place answers that question, for the newsletter and for the mail
 * about an achievement alike, so there is no second route by which mail
 * reaches somebody who said no. Everything that sends goes through
 * `allows()`.
 *
 * Two things beat a category answer:
 *
 *   - a mandatory category always passes. Letting somebody switch off the
 *     mail that resets their password would lock them out of their own
 *     account with one click.
 *
 *   - `mail_unsubscribed_at` on the member stops everything else. That is
 *     the one click at the bottom of a mail, and it should not need six
 *     more to take effect.
 */
class MailPreferences
{
    /**
     * May we mail this member about this kind of thing?
     */
    public function allows(User $user, MailCategory|string|null $category): bool
    {
        if (blank($user->email)) {
            return false;
        }

        $category = $this->resolve($category);

        // A kind of mail this install has deleted reaches nobody, rather
        // than reaching everybody
        if ($category === null) {
            return false;
        }

        if ($category->is_mandatory) {
            return true;
        }

        if ($user->mail_unsubscribed_at !== null) {
            return false;
        }

        $answer = MailPreference::where('user_id', $user->id)
            ->where('mail_category_id', $category->id)
            ->value('opted_in');

        // Nobody has asked them yet, so the category decides
        return $answer === null ? (bool) $category->default_opt_in : (bool) $answer;
    }

    /**
     * Every category with this member's answer beside it - what the
     * preferences page draws.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(User $user): array
    {
        $answers = MailPreference::where('user_id', $user->id)
            ->pluck('opted_in', 'mail_category_id');

        return MailCategory::with('translations')->ordered()->get()
            ->map(function (MailCategory $category) use ($answers, $user) {
                $chosen = $answers[$category->id] ?? null;

                return [
                    'id'           => $category->id,
                    'key'          => $category->key,
                    'name'         => $category->name,
                    'description'  => $category->description,
                    'icon'         => $category->icon,
                    'color'        => $category->color,
                    'is_mandatory' => $category->is_mandatory,
                    // What the switch shows: their own answer, or the
                    // default they have silently been living with
                    'opted_in' => $category->is_mandatory
                        ? true
                        : ($chosen === null ? (bool) $category->default_opt_in : (bool) $chosen),
                    // Whether that is their answer or just the default,
                    // so the page can say which
                    'is_default' => $chosen === null,
                    // Switched off by the one click, not by this row
                    'blocked_by_unsubscribe_all' => ! $category->is_mandatory && $user->mail_unsubscribed_at !== null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Save what a member chose, as category key => yes or no.
     *
     * Answering anything at all cancels a blanket unsubscribe: somebody
     * who comes back to this page and switches the newsletter on means to
     * hear from us again, and leaving the blanket flag set would quietly
     * ignore them.
     *
     * @param  array<string, bool>  $answers
     */
    public function update(User $user, array $answers): void
    {
        $categories = MailCategory::whereIn('key', array_keys($answers))->get();

        foreach ($categories as $category) {
            // Nothing to store: it cannot be turned off, so an answer
            // about it would only be misleading
            if ($category->is_mandatory) {
                continue;
            }

            MailPreference::updateOrCreate(
                ['user_id' => $user->id, 'mail_category_id' => $category->id],
                ['opted_in' => (bool) $answers[$category->key]]
            );
        }

        if ($user->mail_unsubscribed_at !== null && collect($answers)->contains(true)) {
            $user->forceFill(['mail_unsubscribed_at' => null])->save();
        }
    }

    /**
     * Everything off. What the link at the bottom of a mail does when it
     * names no category.
     */
    public function unsubscribeAll(User $user): void
    {
        $user->forceFill(['mail_unsubscribed_at' => now()])->save();
    }

    /**
     * One kind off, the rest as they were - the link at the bottom of a
     * mail when it does name its category.
     */
    public function unsubscribeFrom(User $user, MailCategory $category): bool
    {
        if ($category->is_mandatory) {
            return false;
        }

        MailPreference::updateOrCreate(
            ['user_id' => $user->id, 'mail_category_id' => $category->id],
            ['opted_in' => false]
        );

        return true;
    }

    /**
     * Back on: the blanket flag cleared and every category returned to
     * its default. For the member who unsubscribed by accident.
     */
    public function resubscribe(User $user): void
    {
        $user->forceFill(['mail_unsubscribed_at' => null])->save();

        MailPreference::where('user_id', $user->id)->delete();
    }

    /**
     * The member's unsubscribe token, made the first time one is needed.
     *
     * Not handed out at registration: most accounts never need one, and a
     * column full of secrets nobody uses is a column worth stealing.
     */
    public function token(User $user): string
    {
        if (filled($user->mail_token)) {
            return $user->mail_token;
        }

        do {
            $token = Str::random(48);
        } while (User::where('mail_token', $token)->exists());

        $user->forceFill(['mail_token' => $token])->save();

        return $token;
    }

    /**
     * Where the link in the footer of a mail points: the page that says
     * what is about to happen and asks. With a category, it offers to
     * turn off that one kind; without, everything.
     */
    public function unsubscribeUrl(User $user, ?MailCategory $category = null): string
    {
        return $this->url('/mail/unsubscribe/', $user, $category);
    }

    /**
     * Where the List-Unsubscribe header points, which is not the same
     * address.
     *
     * A mail client that offers its own unsubscribe button posts to that
     * header without a person seeing anything, so it has to reach
     * something that acts rather than a page that asks - and the page
     * that asks has to be reachable by a human with a browser. Two
     * addresses for two different callers.
     */
    public function unsubscribePostUrl(User $user, ?MailCategory $category = null): string
    {
        return $this->url('/api/mail/unsubscribe/', $user, $category);
    }

    private function url(string $path, User $user, ?MailCategory $category): string
    {
        $url = rtrim((string) config('app.url'), '/') . $path . $this->token($user);

        return $category && ! $category->is_mandatory
            ? $url . '?category=' . urlencode($category->key)
            : $url;
    }

    /**
     * The member a token belongs to, or null - which is what an expired
     * bookmark or a guess gets.
     */
    public function byToken(string $token): ?User
    {
        if (strlen($token) < 20) {
            return null;
        }

        return User::where('mail_token', $token)->first();
    }

    private function resolve(MailCategory|string|null $category): ?MailCategory
    {
        if ($category instanceof MailCategory) {
            return $category;
        }

        return is_string($category) ? MailCategory::findByKey($category) : null;
    }
}
