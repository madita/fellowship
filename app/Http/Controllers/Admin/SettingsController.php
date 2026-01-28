<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum'])->except(['public', 'getEnabledOAuthProviders']);
        $this->middleware(['admin'])->except(['public', 'getEnabledOAuthProviders']);
    }

    /**
     * Get enabled OAuth providers (public, no authentication required)
     */
    public function getEnabledOAuthProviders(): JsonResponse
    {
        $providers = [];
        $providerList = ['google', 'discord', 'github', 'facebook'];

        foreach ($providerList as $provider) {
            $enabled = Setting::get("oauth_{$provider}_enabled", false);
            if ($enabled) {
                // Only include if credentials are configured
                $clientId = Setting::get("oauth_{$provider}_client_id");
                $clientSecret = Setting::get("oauth_{$provider}_client_secret");

                if ($clientId && $clientSecret) {
                    $providers[] = $provider;
                }
            }
        }

        return response()->json([
            'providers' => $providers,
            'allow_registration' => Setting::get('oauth_allow_registration', true),
        ]);
    }

    /**
     * Get public settings (no authentication required)
     */
    public function public(): JsonResponse
    {
        $publicKeys = [
            // General
            'app_name',
            'app_logo',
            'app_copyright',
            'site_tagline',
            'site_url',
            'contact_address',
            'contact_phone',
            'contact_email',
            'social_twitter',
            'social_facebook',
            'social_instagram',
            'maintenance_mode',
            'maintenance_message',

            // OAuth/Social Login (only enabled status is public, not credentials)
            'oauth_google_enabled',
            'oauth_discord_enabled',
            'oauth_github_enabled',
            'oauth_facebook_enabled',
            'oauth_allow_registration',
            'oauth_auto_verify_email',

            // Localization
            'default_language',
            'default_timezone',
            'date_format',
            'time_format',
            'available_languages',
            'language_change_enabled',
            'currency',
            'currency_symbol',
            'currency_symbol_position',
            'rtl_support',

            // Branding
            'logo_light',
            'logo_dark',
            'favicon',
            'app_icon',
            'primary_color',
            'secondary_color',
            'font_family',
            'custom_css',
            'theme_mode',
            'login_branding_enabled',
            'background_light',
            'background_dark',
            'background_style',

            // Light Theme Colors
            'primary_color_light',
            'secondary_color_light',
            'accent_color_light',
            'background_color_light',
            'surface_color_light',
            'error_color_light',
            'warning_color_light',
            'info_color_light',
            'success_color_light',

            // Dark Theme Colors
            'primary_color_dark',
            'secondary_color_dark',
            'accent_color_dark',
            'background_color_dark',
            'surface_color_dark',
            'error_color_dark',
            'warning_color_dark',
            'info_color_dark',
            'success_color_dark',

            // Opacity Settings
            'background_opacity_light',
            'background_opacity_dark',
            'surface_opacity_light',
            'surface_opacity_dark',

            // SEO
            'meta_title',
            'meta_description',
            'meta_keywords',
            'og_title',
            'og_description',
            'og_image',
            'twitter_card_type',
            'twitter_site',

            // Analytics
            'google_analytics_id',
            'google_tag_manager_id',

            // Cookie Consent / GDPR
            'cookie_consent_enabled',
            'cookie_banner_title',
            'cookie_banner_text',
            'cookie_preferences_text',
            'cookie_analytics_default',
            'cookie_marketing_default',
            'cookie_functional_default',
            'cookie_categories',

            // Legal
            'privacy_policy_url',
            'terms_conditions_url',
            'cookie_policy_url',
            'gdpr_consent_text',

            // Footer
            'custom_footer_enabled',
            'custom_footer_html',
            'footer_quicklinks',
        ];

        $settings = [];

        foreach ($publicKeys as $key) {
            $value = Setting::get($key);
            if ($value !== null) {
                $settings[$key] = $value;
            }
        }

        // Ensure proper types for JSON response
        $settings = $this->ensureProperTypes($settings);

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Get all settings
     */
    public function index(): JsonResponse
    {
        $settings = Setting::getAllSettings();

        // Ensure all values are properly typed for JSON response
        $settings = $this->ensureProperTypes($settings);

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Ensure settings have proper PHP types for JSON encoding
     */
    private function ensureProperTypes(array $settings): array
    {
        // Known boolean settings
        $booleanKeys = [
            'maintenance_mode', 'language_change_enabled', 'locale_auto_detect',
            'login_branding_enabled', 'indexing_enabled', 'sitemap_enabled',
            'cookie_consent_enabled', 'cookie_analytics_default', 'cookie_marketing_default',
            'cookie_functional_default', 'anonymize_ip', 'tracking_production_only',
            'third_party_embeds_enabled', 'homepage_builder_enabled', 'homepage_menu_enabled',
            'blog_enabled', 'comments_enabled', 'comments_moderation_required',
            'user_registration_enabled', 'email_verification_required',
            'password_require_special_char', 'password_require_number',
            'password_require_uppercase', 'two_factor_enabled',
            'admin_notifications_enabled', 'cache_enabled', 'cdn_enabled',
            'image_optimization_enabled', 'lazy_loading_enabled', 'debug_mode',
            'right_to_be_forgotten_enabled', 'age_confirmation_required',
            'api_keys_enabled', 'background_jobs_enabled', 'custom_footer_enabled',
            'oauth_google_enabled', 'oauth_discord_enabled', 'oauth_github_enabled',
            'oauth_facebook_enabled', 'oauth_allow_registration', 'oauth_auto_verify_email',
        ];

        foreach ($booleanKeys as $key) {
            if (isset($settings[$key])) {
                // Convert to actual boolean
                $settings[$key] = (bool) filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        return $settings;
    }

    /**
     * Update settings
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'sometimes|string|in:string,boolean,integer,float,file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Additional validation for specific setting values
        $settings = $request->input('settings', []);
        $valueErrors = [];

        foreach ($settings as $index => $setting) {
            $key = $setting['key'];
            $value = $setting['value'];

            // Skip null/empty values - no validation needed for empty fields
            if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                continue;
            }

            // Email validation (only if not empty)
            if (in_array($key, ['contact_email', 'admin_email', 'support_email', 'email_sender_address'])) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $valueErrors["settings.{$index}.value"] = ["The {$key} must be a valid email address."];
                }
            }

            // URL validation for external URLs that must be full URLs
            $requiresFullUrl = ['site_url', 'cdn_url', 'canonical_url'];
            if (in_array($key, $requiresFullUrl)) {
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $valueErrors["settings.{$index}.value"] = ["The {$key} must be a valid URL (e.g., https://example.com)."];
                }
            }

            // Flexible URL/Path validation for internal pages (accepts /path or full URLs)
            $allowsRelativePath = ['privacy_policy_url', 'terms_conditions_url', 'cookie_policy_url'];
            if (in_array($key, $allowsRelativePath)) {
                // Accept relative paths starting with / or full URLs
                $isRelativePath = str_starts_with($value, '/');
                $isFullUrl = filter_var($value, FILTER_VALIDATE_URL);

                if (!$isRelativePath && !$isFullUrl) {
                    $valueErrors["settings.{$index}.value"] = ["The {$key} must be a valid URL (https://example.com/privacy) or path (/privacy-policy)."];
                }
            }

            // Color validation (hex format)
            $colorFields = [
                'primary_color', 'secondary_color',
                'primary_color_light', 'secondary_color_light', 'accent_color_light',
                'background_color_light', 'surface_color_light', 'error_color_light',
                'warning_color_light', 'info_color_light', 'success_color_light',
                'primary_color_dark', 'secondary_color_dark', 'accent_color_dark',
                'background_color_dark', 'surface_color_dark', 'error_color_dark',
                'warning_color_dark', 'info_color_dark', 'success_color_dark',
            ];
            if (in_array($key, $colorFields)) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    $valueErrors["settings.{$index}.value"] = ["The {$key} must be a valid hex color (e.g., #1976D2)."];
                }
            }

            // Positive integer validation
            if (in_array($key, [
                'posts_per_page', 'media_max_upload_size', 'password_min_length',
                'session_timeout_minutes', 'login_rate_limit_attempts', 'login_rate_limit_minutes',
                'cache_lifetime_minutes', 'api_rate_limit_per_minute', 'smtp_port', 'age_minimum'
            ])) {
                if (!is_numeric($value) || $value < 0) {
                    $valueErrors["settings.{$index}.value"] = ["The {$key} must be a positive number."];
                }
            }
        }

        if (!empty($valueErrors)) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $valueErrors,
            ], 422);
        }

        foreach ($request->input('settings', []) as $setting) {
            $type = $setting['type'] ?? 'string';
            $value = $setting['value'];

            // Handle different value types
            if ($type === 'boolean') {
                // Convert boolean to string "1" or "0" for consistent storage
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                // Handle array/json values
                $value = json_encode($value);
                $type = 'json';
            } elseif ($type === 'integer') {
                $value = (string) (int) $value;
            } elseif ($type === 'float') {
                $value = (string) (float) $value;
            }

            Setting::set($setting['key'], $value, $type);
        }

        Setting::clearCache();

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => Setting::getAllSettings(),
        ]);
    }

    /**
     * Upload logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('logo');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file upload',
                ], 422);
            }

            // Delete old logo if exists
            $oldLogo = Setting::get('app_logo');
            if ($oldLogo && is_string($oldLogo) && trim($oldLogo) !== '' && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            // Store new logo with original extension using file contents (Windows compatible)
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('logo_') . '.' . $extension;
            $path = 'logos/' . $filename;

            // Read file contents and put to storage (bypasses path issues on Windows)
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath() ?: $file->getPathname()));

            // Save to settings
            Setting::set('app_logo', $path, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Logo uploaded successfully',
                'logo_url' => Storage::url($path),
                'logo_path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload logo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete logo
     */
    public function deleteLogo(): JsonResponse
    {
        try {
            $oldLogo = Setting::get('app_logo');

            if ($oldLogo && is_string($oldLogo) && trim($oldLogo) !== '' && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            Setting::set('app_logo', null, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Logo deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete logo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload image file (favicon, logos, icons, etc.)
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,ico,webp|max:2048',
            'key' => 'required|string|in:logo_light,logo_dark,favicon,app_icon,og_image,background_light,background_dark',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('image');
            $key = $request->input('key');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file upload',
                ], 422);
            }

            // Special validation for PWA app icon
            if ($key === 'app_icon') {
                $mimeType = $file->getMimeType();
                $validMimes = ['image/png', 'image/webp', 'image/svg+xml'];

                if (!in_array($mimeType, $validMimes)) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => [
                            'image' => ['PWA app icon must be PNG, WebP, or SVG format. JPEG is not supported.']
                        ],
                    ], 422);
                }

                // Get image dimensions (skip for SVG)
                if ($mimeType !== 'image/svg+xml') {
                    $imagePath = $file->getRealPath() ?: $file->getPathname();
                    $imageInfo = @getimagesize($imagePath);

                    if (!$imageInfo) {
                        return response()->json([
                            'message' => 'Validation failed',
                            'errors' => [
                                'image' => ['Unable to read image dimensions.']
                            ],
                        ], 422);
                    }

                    $width = $imageInfo[0];
                    $height = $imageInfo[1];

                    // Check minimum dimensions
                    if ($width < 144 || $height < 144) {
                        return response()->json([
                            'message' => 'Validation failed',
                            'errors' => [
                                'image' => ["PWA app icon must be at least 144×144 pixels. Your image is {$width}×{$height}."]
                            ],
                        ], 422);
                    }

                    // Check if square (or nearly square - within 10% tolerance)
                    $aspectRatio = max($width, $height) / min($width, $height);
                    if ($aspectRatio > 1.1) {
                        return response()->json([
                            'message' => 'Validation failed',
                            'errors' => [
                                'image' => ["PWA app icon should be square. Your image is {$width}×{$height}. Please upload a square image."]
                            ],
                        ], 422);
                    }

                    // Recommend larger size if below optimal
                    if ($width < 192 || $height < 192) {
                        // This is a warning, not an error - we'll still accept it
                        // You could add a warning field to the response if desired
                    }
                }
            }

            // Delete old file if exists
            $oldFile = Setting::get($key);
            if ($oldFile && is_string($oldFile) && trim($oldFile) !== '' && Storage::disk('public')->exists($oldFile)) {
                Storage::disk('public')->delete($oldFile);
            }

            // Store new file
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid($key . '_') . '.' . $extension;
            $path = 'images/' . $filename;

            Storage::disk('public')->put($path, file_get_contents($file->getRealPath() ?: $file->getPathname()));

            // Save to settings
            Setting::set($key, $path, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Image uploaded successfully',
                'url' => Storage::url($path),
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete image file
     */
    public function deleteImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|in:logo_light,logo_dark,favicon,app_icon,og_image,background_light,background_dark',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $key = $request->input('key');
            $oldFile = Setting::get($key);

            if ($oldFile && is_string($oldFile) && trim($oldFile) !== '' && Storage::disk('public')->exists($oldFile)) {
                Storage::disk('public')->delete($oldFile);
            }

            Setting::set($key, null, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Image deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test email configuration
     */
    public function testEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipient' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $recipient = $request->input('recipient');
            $appName = Setting::get('app_name', config('app.name'));

            \Illuminate\Support\Facades\Mail::raw(
                "This is a test email from {$appName}.\n\n" .
                "If you received this email, your email configuration is working correctly.\n\n" .
                "Sent at: " . now()->format('Y-m-d H:i:s'),
                function ($message) use ($recipient, $appName) {
                    $message->to($recipient)
                        ->subject("Test Email from {$appName}");
                }
            );

            return response()->json([
                'message' => 'Test email sent successfully to ' . $recipient,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache status and statistics
     */
    public function cacheStatus(): JsonResponse
    {
        $cacheEnabled = Setting::isCacheEnabled();
        $cacheLifetime = Setting::getCacheLifetime();

        // Get cache driver info
        $cacheDriver = config('cache.default');
        $cacheStore = config("cache.stores.{$cacheDriver}.driver", $cacheDriver);

        // Check if cache driver supports tags
        $supportsTags = false;
        try {
            $supportsTags = \Illuminate\Support\Facades\Cache::supportsTags();
        } catch (\Exception $e) {
            // Driver doesn't support tags
        }

        return response()->json([
            'enabled' => $cacheEnabled,
            'lifetime_seconds' => $cacheLifetime,
            'lifetime_minutes' => $cacheLifetime / 60,
            'driver' => $cacheStore,
            'supports_tags' => $supportsTags,
            'cached_types' => [
                'settings' => 'Application settings',
                'pages' => 'Static pages',
                'wiki' => 'Wiki articles',
                'posts' => 'Blog posts',
                'widgets' => 'Homepage & footer widgets',
                'http' => 'API responses',
            ],
        ]);
    }

    /**
     * Clear all application cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all');

        try {
            $cleared = [];
            $cacheService = new \App\Services\CacheService();

            switch ($type) {
                case 'settings':
                    Setting::clearCache();
                    Setting::resetCacheConfig();
                    $cacheService::flushTag('settings');
                    $cleared[] = 'settings';
                    break;

                case 'views':
                    \Artisan::call('view:clear');
                    $cleared[] = 'views';
                    break;

                case 'routes':
                    \Artisan::call('route:clear');
                    $cleared[] = 'routes';
                    break;

                case 'config':
                    \Artisan::call('config:clear');
                    $cleared[] = 'config';
                    break;

                case 'application':
                    \Illuminate\Support\Facades\Cache::flush();
                    $cleared[] = 'application cache';
                    break;

                case 'pages':
                    $cacheService::flushTag('pages');
                    $cleared[] = 'pages';
                    break;

                case 'wiki':
                    $cacheService::flushTag('wiki');
                    $cleared[] = 'wiki';
                    break;

                case 'posts':
                    $cacheService::flushTag('posts');
                    $cleared[] = 'posts';
                    break;

                case 'widgets':
                    $cacheService::flushTag('widgets');
                    $cleared[] = 'widgets';
                    break;

                case 'http':
                    // Clear HTTP response cache
                    $this->clearHttpCache();
                    $cleared[] = 'http responses';
                    break;

                case 'all':
                default:
                    Setting::clearCache();
                    Setting::resetCacheConfig();
                    \Artisan::call('view:clear');
                    \Artisan::call('route:clear');
                    \Artisan::call('config:clear');
                    \Illuminate\Support\Facades\Cache::flush();
                    $cleared = ['settings', 'views', 'routes', 'config', 'application cache', 'http responses'];
                    break;
            }

            return response()->json([
                'message' => 'Cache cleared successfully',
                'cleared' => $cleared,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear HTTP response cache
     */
    protected function clearHttpCache(): void
    {
        // Clear all http_cache keys
        $cache = \Illuminate\Support\Facades\Cache::getStore();

        // If using Redis or similar, we can use pattern matching
        if (method_exists($cache, 'connection')) {
            try {
                $redis = $cache->connection();
                $prefix = config('cache.prefix', 'laravel_cache');
                $keys = $redis->keys("{$prefix}:http_cache:*");
                foreach ($keys as $key) {
                    $redis->del($key);
                }
            } catch (\Exception $e) {
                // Fallback: the full cache flush will handle it
            }
        }
    }
}
