<?php

namespace App\Providers;

use App\Services\Translation\NullTranslationService;
use App\Services\Translation\TranslationServiceInterface;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranslationServiceInterface::class, function ($app) {
            // Check if translation API is enabled via settings
            $enabled = config('services.translation.enabled', false);
            $provider = config('services.translation.provider', null);
            $apiKey = config('services.translation.api_key', null);

            if (!$enabled || !$apiKey) {
                return new NullTranslationService();
            }

            // Future: Add provider-specific implementations
            // switch ($provider) {
            //     case 'google':
            //         return new GoogleTranslationService($apiKey);
            //     case 'deepl':
            //         return new DeepLTranslationService($apiKey);
            //     default:
            //         return new NullTranslationService();
            // }

            return new NullTranslationService();
        });
    }

    public function boot(): void
    {
        //
    }
}
