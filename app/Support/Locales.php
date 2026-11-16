<?php

namespace App\Support;

/**
 * The languages the site is translated into, read from the language
 * directories so adding one needs no code change.
 */
class Locales
{
    public static function all(): array
    {
        return collect(glob(lang_path('*'), GLOB_ONLYDIR))
            ->map(fn (string $path) => basename($path))
            ->values()
            ->all();
    }

    public static function fallback(): string
    {
        return config('app.fallback_locale', 'en');
    }
}
