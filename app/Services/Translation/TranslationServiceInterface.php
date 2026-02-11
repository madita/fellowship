<?php

namespace App\Services\Translation;

interface TranslationServiceInterface
{
    /**
     * Translate a single text string to the target locale.
     *
     * @param string $text         The text to translate
     * @param string $targetLocale The locale to translate to (e.g., 'de', 'fr')
     * @param string $sourceLocale The source locale (default: 'en')
     *
     * @return string The translated text
     *
     * @throws \App\Exceptions\TranslationException
     */
    public function translate(string $text, string $targetLocale, string $sourceLocale = 'en'): string;

    /**
     * Translate multiple text strings in a batch operation.
     *
     * @param array  $texts        Array of texts to translate
     * @param string $targetLocale The locale to translate to
     * @param string $sourceLocale The source locale (default: 'en')
     *
     * @return array Array of translated texts in the same order
     *
     * @throws \App\Exceptions\TranslationException
     */
    public function translateBatch(array $texts, string $targetLocale, string $sourceLocale = 'en'): array;

    /**
     * Check if the translation service is configured and available.
     */
    public function isAvailable(): bool;

    /**
     * Get the list of supported locales.
     */
    public function getSupportedLocales(): array;

    /**
     * Get the current usage/quota information.
     *
     * @return array{used: int, limit: int, remaining: int}
     */
    public function getUsageStats(): array;
}
