<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;

class OAuthConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * Load OAuth configuration from database settings.
     */
    public function boot(): void
    {
        // Only load OAuth settings if not in console (migrations, etc.)
        // or if we're specifically running the application
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        try {
            // Check if settings table exists
            if (! \Schema::hasTable('settings')) {
                return;
            }

            // Get site URL from settings or fallback to APP_URL
            $siteUrl = rtrim(Setting::get('site_url', config('app.url')), '/');

            // Load OAuth settings for each provider
            $providers = ['google', 'discord', 'github', 'facebook'];

            foreach ($providers as $provider) {
                $enabled = Setting::get("oauth_{$provider}_enabled", false);

                // Only configure if provider is enabled
                if ($enabled) {
                    $clientId = Setting::get("oauth_{$provider}_client_id");
                    $clientSecret = Setting::get("oauth_{$provider}_client_secret");

                    // Only set config if credentials exist
                    if ($clientId && $clientSecret) {
                        $redirectUrl = "{$siteUrl}/auth/{$provider}/callback";
                        config([
                            "services.{$provider}.client_id" => $clientId,
                            "services.{$provider}.client_secret" => $clientSecret,
                            "services.{$provider}.redirect" => $redirectUrl,
                        ]);
                        \Log::debug("OAuth {$provider} redirect URL configured: {$redirectUrl}");
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if database isn't ready yet
            // This prevents errors during initial setup/migrations
            \Log::debug('OAuth config loading skipped: '.$e->getMessage());
        }
    }
}
