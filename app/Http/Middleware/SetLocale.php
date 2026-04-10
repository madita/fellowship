<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * The locale is determined in this priority order:
     * 1. Query parameter (?locale=de)
     * 2. X-Locale header
     * 3. Cookie (locale)
     * 4. Accept-Language header
     * 5. Default from config
     */
    public function handle(Request $request, Closure $next): Response
    {
        $availableLocales = config('translatable.locales', ['en', 'de']);
        $defaultLocale = config('app.locale', 'en');

        $locale = $this->determineLocale($request, $availableLocales) ?? $defaultLocale;

        app()->setLocale($locale);

        $response = $next($request);

        // Set a cookie with the locale for persistence
        if ($request->has('locale') || $request->hasHeader('X-Locale')) {
            $response->cookie('locale', $locale, 60 * 24 * 365); // 1 year
        }

        return $response;
    }

    /**
     * Determine the locale from the request.
     */
    protected function determineLocale(Request $request, array $availableLocales): ?string
    {
        // 1. Check query parameter
        if ($request->has('locale')) {
            $locale = $request->input('locale');
            if (in_array($locale, $availableLocales)) {
                return $locale;
            }
        }

        // 2. Check X-Locale header
        if ($request->hasHeader('X-Locale')) {
            $locale = $request->header('X-Locale');
            if (in_array($locale, $availableLocales)) {
                return $locale;
            }
        }

        // 3. Check cookie
        if ($request->hasCookie('locale')) {
            $locale = $request->cookie('locale');
            if (in_array($locale, $availableLocales)) {
                return $locale;
            }
        }

        // 4. Check Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            // Parse Accept-Language (e.g., "de-DE,de;q=0.9,en;q=0.8")
            $languages = $this->parseAcceptLanguage($acceptLanguage);
            foreach ($languages as $lang) {
                // Check full locale (e.g., de-DE)
                if (in_array($lang, $availableLocales)) {
                    return $lang;
                }
                // Check base language (e.g., de)
                $baseLang = substr($lang, 0, 2);
                if (in_array($baseLang, $availableLocales)) {
                    return $baseLang;
                }
            }
        }

        return null;
    }

    /**
     * Parse Accept-Language header and return sorted languages.
     */
    protected function parseAcceptLanguage(string $header): array
    {
        $languages = [];

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            $quality = 1.0;

            if (str_contains($part, ';q=')) {
                [$locale, $q] = explode(';q=', $part);
                $quality = (float) $q;
            } else {
                $locale = $part;
            }

            $languages[$locale] = $quality;
        }

        arsort($languages);

        return array_keys($languages);
    }
}
