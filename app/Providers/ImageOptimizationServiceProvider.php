<?php

namespace App\Providers;

use App\Services\ImageOptimizationService;
use Illuminate\Support\ServiceProvider;

class ImageOptimizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageOptimizationService::class, function ($app) {
            return new ImageOptimizationService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure optimizers based on database setting
        // We do this after the app is booted to ensure database is available
        $this->app->booted(function () {
            try {
                ImageOptimizationService::configureOptimizers();
            } catch (\Exception $e) {
                // Database might not be available during migrations/install
                \Log::debug('Could not configure image optimizers: ' . $e->getMessage());
            }
        });
    }
}
