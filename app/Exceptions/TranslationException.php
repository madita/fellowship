<?php

namespace App\Exceptions;

use Exception;

class TranslationException extends Exception
{
    protected string $sourceLocale;

    protected string $targetLocale;

    protected ?string $provider;

    public function __construct(
        string $message,
        string $sourceLocale = 'en',
        string $targetLocale = '',
        ?string $provider = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->sourceLocale = $sourceLocale;
        $this->targetLocale = $targetLocale;
        $this->provider = $provider;
    }

    public function getSourceLocale(): string
    {
        return $this->sourceLocale;
    }

    public function getTargetLocale(): string
    {
        return $this->targetLocale;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public static function serviceNotConfigured(): self
    {
        return new self('Translation service is not configured. Please configure your API key and provider.');
    }

    public static function quotaExceeded(): self
    {
        return new self('Translation API quota exceeded. Please wait or upgrade your plan.');
    }

    public static function unsupportedLocale(string $locale): self
    {
        return new self("Locale '{$locale}' is not supported by the translation service.");
    }

    public static function apiError(string $message, ?string $provider = null): self
    {
        return new self("Translation API error: {$message}", 'en', '', $provider);
    }
}
