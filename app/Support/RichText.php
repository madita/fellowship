<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Stevebauman\Purify\Facades\Purify;

/**
 * HTML from the simple editor (bold, italic, lists, @mentions), cleaned
 * with the same rules as forum posts and timeline statuses.
 */
class RichText
{
    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $clean = Purify::config('sandbox')->clean($html);

        return static::isBlank($clean) ? null : $clean;
    }

    /**
     * Cleans a required field; fails validation when nothing but markup is left.
     */
    public static function cleanRequired(string $html, string $attribute): string
    {
        $clean = static::clean($html);

        if ($clean === null) {
            throw ValidationException::withMessages([
                $attribute => __('validation.required', ['attribute' => $attribute]),
            ]);
        }

        return $clean;
    }

    public static function isBlank(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '';
    }
}
