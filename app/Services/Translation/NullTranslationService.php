<?php

namespace App\Services\Translation;

/**
 * Null implementation of TranslationServiceInterface.
 * Used when no translation API is configured.
 */
class NullTranslationService implements TranslationServiceInterface
{
    public function translate(string $text, string $targetLocale, string $sourceLocale = 'en'): string
    {
        // Return the original text when no service is configured
        return $text;
    }

    public function translateBatch(array $texts, string $targetLocale, string $sourceLocale = 'en'): array
    {
        // Return original texts when no service is configured
        return $texts;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getSupportedLocales(): array
    {
        return config('translatable.locales', ['en', 'de']);
    }

    public function getUsageStats(): array
    {
        return [
            'used' => 0,
            'limit' => 0,
            'remaining' => 0,
        ];
    }
}
