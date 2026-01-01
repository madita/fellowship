<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache before seeding
        Setting::clearCache();

        /*$defaultSettings = [
            // Application Settings
            ['key' => 'app_name', 'value' => 'Fellowship', 'type' => 'string'],
            ['key' => 'app_copyright', 'value' => '© Fellowship 2021', 'type' => 'string'],

            // Localization Settings
            ['key' => 'default_language', 'value' => 'en', 'type' => 'string'],
            ['key' => 'default_timezone', 'value' => 'UTC', 'type' => 'string'],
            ['key' => 'date_format', 'value' => 'Y-m-d', 'type' => 'string'],
            ['key' => 'time_format', 'value' => 'H:i:s', 'type' => 'string'],
            ['key' => 'language_change_enabled', 'value' => 'true', 'type' => 'boolean'],

            // Contact Information (empty by default)
            ['key' => 'contact_address', 'value' => '', 'type' => 'string'],
            ['key' => 'contact_phone', 'value' => '', 'type' => 'string'],
            ['key' => 'contact_email', 'value' => '', 'type' => 'string'],

            // Social Media Links (empty by default)
            ['key' => 'social_twitter', 'value' => '', 'type' => 'string'],
            ['key' => 'social_facebook', 'value' => '', 'type' => 'string'],
            ['key' => 'social_instagram', 'value' => '', 'type' => 'string'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ]
            );
        }*/

        $settings = [
            // ===== 1. General / Site Settings =====
            'app_name' => 'Fellowship',
            'app_copyright' => '© Fellowship 2021',

            'site_tagline' => 'Your Adventure Awaits',
            'site_url' => config('app.url'),
            'maintenance_mode' => false,
            'maintenance_message' => 'We are currently performing scheduled maintenance. Please check back soon.',
            'admin_email' => 'admin@fellowship.com',
            'support_email' => 'support@fellowship.com',

            // ===== 2. Localization / Internationalization =====
            'available_languages' => json_encode(['en' => true, 'de' => true]),
            'locale_auto_detect' => false,
//            'currency' => 'USD',
//            'currency_symbol' => '$',
//            'currency_symbol_position' => 'before', // before or after
//            'rtl_support' => false,
            'translation_fallback_language' => 'en',
            'number_format_locale' => 'en_US',

            // ===== 3. Branding & Appearance =====
            'logo_light' => null,
            'logo_dark' => null,
            'favicon' => null,
            'app_icon' => null,
            'primary_color' => '#1976D2',
            'secondary_color' => '#424242',
            'font_family' => 'Roboto, sans-serif',
            'custom_css' => '',
            'theme_mode' => 'system', // light, dark, system
            'login_branding_enabled' => true,

            // ===== 4. SEO & Metadata =====
            'meta_title' => 'Fellowship - Your Adventure Platform',
            'meta_description' => 'Join Fellowship to connect, collaborate, and create amazing experiences.',
            'meta_keywords' => 'fellowship, community, adventure, collaboration',
            'og_title' => 'Fellowship',
            'og_description' => 'Join Fellowship to connect, collaborate, and create amazing experiences.',
            'og_image' => null,
            'twitter_card_type' => 'summary_large_image',
            'twitter_site' => '@fellowship',
            'canonical_url' => config('app.url'),
            'indexing_enabled' => true,
            'robots_txt_custom' => '',
            'sitemap_enabled' => true,

            // ===== 5. Analytics & Tracking =====
            'google_analytics_id' => '',
            'google_tag_manager_id' => '',
            'analytics_script_header' => '',
            'analytics_script_body' => '',
            'analytics_script_footer' => '',
            'cookie_consent_enabled' => true,
            'cookie_categories' => json_encode([
                'necessary' => true,
                'analytics' => false,
                'marketing' => false,
            ]),
            'anonymize_ip' => true,
            'tracking_production_only' => true,

            // ===== 6. Scripts & Integrations =====
            'custom_head_scripts' => '',
            'custom_body_scripts' => '',
            'custom_footer_scripts' => '',
            'third_party_embeds_enabled' => true,
            'chat_widget_script' => '',
            'newsletter_provider' => 'none', // none, mailchimp, sendgrid, etc.
            'newsletter_api_key' => '',
            'crm_integration_enabled' => false,
            'webhook_endpoints' => json_encode([]),

            // ===== 7. Content Settings =====
            'homepage_type' => 'default', // default, custom, wiki, etc.
            'blog_enabled' => false,
            'posts_per_page' => 10,
            'default_author_id' => null,
            'comments_enabled' => false,
            'comments_moderation_required' => true,
            'media_max_upload_size' => 10240, // KB (10MB)
            'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx',
            'wysiwyg_toolbar' => 'full', // full, basic, minimal

            // ===== 8. User & Access Settings =====
            'user_registration_enabled' => true,
            'default_user_role' => 'user',
            'email_verification_required' => true,
            'password_min_length' => 8,
            'password_require_special_char' => true,
            'password_require_number' => true,
            'password_require_uppercase' => true,
            'session_timeout_minutes' => 120,
            'login_rate_limit_attempts' => 5,
            'login_rate_limit_minutes' => 15,
            'two_factor_enabled' => false,
            'admin_ip_whitelist' => '',

            // ===== 9. Email & Notifications =====
            'email_sender_name' => 'Fellowship',
            'email_sender_address' => 'noreply@fellowship.com',
            'smtp_host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
            'smtp_port' => env('MAIL_PORT', 2525),
            'smtp_username' => env('MAIL_USERNAME', ''),
            'smtp_password' => env('MAIL_PASSWORD', ''),
            'smtp_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'email_template_header' => '',
            'email_template_footer' => '',
            'admin_notifications_enabled' => true,
            'user_notification_preferences_enabled' => true,
            'system_alert_emails' => 'admin@fellowship.com',

            // ===== 10. Performance & Technical =====
            'cache_enabled' => true,
            'cache_lifetime_minutes' => 60,
            'cdn_enabled' => false,
            'cdn_url' => '',
            'asset_versioning_enabled' => true,
            'image_optimization_enabled' => true,
            'lazy_loading_enabled' => true,
            'debug_mode' => env('APP_DEBUG', false),
            'error_logging_level' => 'error', // debug, info, warning, error

            // ===== 11. Legal & Compliance =====
            'privacy_policy_url' => '/privacy-policy',
            'terms_conditions_url' => '/terms-conditions',
            'cookie_policy_url' => '/cookie-policy',
            'gdpr_consent_text' => 'I agree to the processing of my personal data in accordance with the Privacy Policy.',
            'data_retention_days' => 365,
            'right_to_be_forgotten_enabled' => true,
            'age_confirmation_required' => false,
            'age_minimum' => 13,

            // ===== 12. Advanced / Developer Settings =====
            'environment' => env('APP_ENV', 'production'),
            'feature_flags' => json_encode([]),
            'api_rate_limit_per_minute' => 60,
            'api_keys_enabled' => false,
            'webhook_retry_attempts' => 3,
            'queue_driver' => env('QUEUE_CONNECTION', 'sync'),
            'background_jobs_enabled' => true,

            // ===== 13. Footer Settings =====
            'custom_footer_enabled' => false,
            'custom_footer_html' => '',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        // Clear cache after seeding
        Setting::clearCache();

        $this->command->info('Settings seeded successfully!');
    }
}
