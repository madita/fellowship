<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Filling {{ placeholders }} in a mail subject or body.
 *
 * Deliberately not Blade. A template is written in the admin screens and
 * stored in the database, and nothing a browser can edit should end up
 * being executed on the way out - so this does one thing: it swaps a
 * known name for a known string.
 *
 * A placeholder nobody passed a value for becomes nothing rather than
 * staying on the page as braces, and a paragraph left empty by that is
 * dropped, so a template with an optional line reads properly when the
 * line is missing.
 */
class MailVariables
{
    /**
     * The names every mail is filled with, whatever it is about.
     *
     * Anything a particular mail adds - an achievement, an event - is
     * named in that template's own `variables`.
     */
    public const BASE = [
        'name',
        'username',
        'email',
        'site_name',
        'site_url',
        'unsubscribe_url',
        'preferences_url',
    ];

    /**
     * What one recipient's mail is filled with, before whatever the
     * sender adds on top.
     *
     * @return array<string, string>
     */
    public static function forUser(User $user, array $extra = []): array
    {
        return array_merge([
            // Members are not obliged to give a name, and "Hello ," reads
            // badly - the username is always there
            'name'            => $user->name ?: $user->username,
            'username'        => (string) $user->username,
            'email'           => (string) $user->email,
            'site_name'       => static::siteName(),
            'site_url'        => rtrim((string) config('app.url'), '/'),
            'unsubscribe_url' => '',
            'preferences_url' => rtrim((string) config('app.url'), '/') . '/users/edit',
        ], $extra);
    }

    /**
     * Swap the placeholders in a piece of HTML.
     *
     * Values are escaped: a member whose display name is `<b>` should see
     * their name, not bold text, and certainly not a tag of their
     * choosing in everybody else's mail.
     */
    public static function html(?string $body, array $variables): string
    {
        $rendered = static::replace($body, $variables, true);

        // A line that was only a placeholder - the optional note under an
        // awarded achievement - should not leave a blank paragraph behind
        $rendered = (string) preg_replace('/<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $rendered);

        return trim($rendered);
    }

    /**
     * Swap the placeholders in a subject line, which is plain text: the
     * mailer encodes the header itself, so escaping here would show the
     * entities.
     */
    public static function text(?string $subject, array $variables): string
    {
        return trim(static::replace($subject, $variables, false));
    }

    /**
     * Swap only the placeholders there is a value for, and leave every
     * other one standing exactly as it was written.
     *
     * This is the one used while a mailing is still being written. An
     * event's title can go in now - it is the same for everybody - but
     * `{{ name }}` has to stay a placeholder until the mail is actually
     * addressed to somebody, and blanking it here would quietly turn a
     * greeting into "Hello ,".
     */
    public static function fill(?string $text, array $variables, bool $escape = true): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $lookup = [];

        foreach ($variables as $key => $value) {
            $lookup[strtolower((string) $key)] = static::stringify($value);
        }

        return (string) preg_replace_callback(
            static::PATTERN,
            function (array $match) use ($lookup, $escape): string {
                $name = strtolower($match[1]);

                // Nothing to put here yet - hand back the placeholder
                if ( ! array_key_exists($name, $lookup)) {
                    return $match[0];
                }

                return $escape ? e($lookup[$name]) : $lookup[$name];
            },
            $text
        );
    }

    /**
     * Every placeholder a piece of text asks for, whether or not anybody
     * can fill it. What the editor uses to warn about a typo.
     *
     * @return array<int, string>
     */
    public static function used(?string ...$texts): array
    {
        $found = [];

        foreach ($texts as $text) {
            preg_match_all(static::PATTERN, (string) $text, $matches);
            $found = array_merge($found, $matches[1] ?? []);
        }

        return array_values(array_unique(array_map('strtolower', $found)));
    }

    /**
     * `{{ name }}`, `{{name}}` and `{{  NAME  }}` are the same thing -
     * whoever writes a template should not have to count spaces.
     */
    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/';

    private static function replace(?string $text, array $variables, bool $escape): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Lower-cased once, so {{ Name }} finds `name`
        $lookup = [];

        foreach ($variables as $key => $value) {
            $lookup[strtolower((string) $key)] = static::stringify($value);
        }

        return (string) preg_replace_callback(
            static::PATTERN,
            function (array $match) use ($lookup, $escape): string {
                $value = $lookup[strtolower($match[1])] ?? '';

                return $escape ? e($value) : $value;
            },
            $text
        );
    }

    private static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if ($value === null || is_array($value) || is_object($value)) {
            return is_object($value) && method_exists($value, '__toString') ? (string) $value : '';
        }

        return (string) $value;
    }

    /**
     * What the mail calls the site. The admin setting wins, since it is
     * the one somebody edits in the browser.
     */
    private static function siteName(): string
    {
        try {
            $name = Setting::get('app_name');
        } catch (\Throwable $e) {
            $name = null;
        }

        return (string) ($name ?: config('app.name', 'Fellowship'));
    }
}
