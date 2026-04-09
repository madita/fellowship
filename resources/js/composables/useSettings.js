import { ref, reactive } from 'vue';
import { useApi } from '@/api/useAPI.js';

const api = useApi('api');

export function useSettings() {
    const isSaving = ref(false);
    const message = ref('');
    const alertType = ref('success');
    const errors = reactive({});

    const settings = reactive({
        // General / Site Settings
        app_name: '',
        app_logo: '',
        app_copyright: '',
        site_tagline: '',
        site_url: '',
        maintenance_mode: false,
        maintenance_message: '',
        admin_email: '',
        support_email: '',

        // Contact & Social
        contact_address: '',
        contact_phone: '',
        contact_email: '',
        social_twitter: '',
        social_facebook: '',
        social_instagram: '',

        // Localization
        default_language: 'en',
        default_timezone: 'UTC',
        date_format: 'Y-m-d',
        time_format: 'H:i:s',
        language_change_enabled: true,
        locale_auto_detect: false,
        translation_fallback_language: 'en',

        // Branding & Appearance
        logo_light: null,
        logo_dark: null,
        favicon: null,
        app_icon: null,
        primary_color: '#1976D2',
        secondary_color: '#424242',
        font_family: 'Roboto, sans-serif',
        custom_css: '',
        theme_mode: 'system',
        login_branding_enabled: true,
        background_light: null,
        background_dark: null,
        background_style: 'cover',

        // Light Theme Colors
        primary_color_light: '#115571',
        secondary_color_light: '#a0b9c8',
        accent_color_light: '#048ba8',
        background_color_light: '#ffffff',
        surface_color_light: '#f2f5f8',
        error_color_light: '#ef476f',
        warning_color_light: '#ffd166',
        info_color_light: '#2196F3',
        success_color_light: '#06d6a0',

        // Dark Theme Colors
        primary_color_dark: '#115571',
        secondary_color_dark: '#a0b9c8',
        accent_color_dark: '#26c4da',
        background_color_dark: '#121212',
        surface_color_dark: '#1e1e1e',
        error_color_dark: '#ef476f',
        warning_color_dark: '#ffd166',
        info_color_dark: '#2196F3',
        success_color_dark: '#06d6a0',

        // Opacity Settings
        background_opacity_light: 95,
        background_opacity_dark: 95,
        surface_opacity_light: 100,
        surface_opacity_dark: 100,

        // SEO & Metadata
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
        og_title: '',
        og_description: '',
        og_image: null,
        twitter_card_type: 'summary_large_image',
        twitter_site: '',
        canonical_url: '',
        indexing_enabled: true,
        sitemap_enabled: true,
        robots_txt_custom: '',

        // Analytics & Tracking
        google_analytics_id: '',
        google_tag_manager_id: '',
        analytics_script_header: '',
        analytics_script_body: '',
        analytics_script_footer: '',
        anonymize_ip: true,
        tracking_production_only: true,

        // Cookie Consent / GDPR
        cookie_consent_enabled: true,
        cookie_banner_title: '',
        cookie_banner_text: '',
        cookie_preferences_text: '',
        cookie_analytics_default: false,
        cookie_marketing_default: false,
        cookie_functional_default: false,

        // Scripts & Integrations
        custom_head_scripts: '',
        custom_body_scripts: '',
        custom_footer_scripts: '',
        third_party_embeds_enabled: true,
        chat_widget_script: '',
        newsletter_provider: 'none',
        newsletter_api_key: '',

        // Content Settings
        homepage_type: 'default',
        homepage_builder_enabled: true,
        homepage_menu_enabled: true,
        blog_enabled: false,
        posts_per_page: 10,
        comments_enabled: false,
        comments_moderation_required: true,
        media_max_upload_size: 10240,
        allowed_file_types: 'jpg,jpeg,png,gif,pdf,doc,docx',

        // User & Access Settings
        user_registration_enabled: true,
        default_user_role: 'user',
        email_verification_required: true,
        password_min_length: 8,
        password_require_special_char: true,
        password_require_number: true,
        password_require_uppercase: true,
        session_timeout_minutes: 120,
        login_rate_limit_attempts: 5,
        login_rate_limit_minutes: 15,
        two_factor_enabled: false,

        // OAuth / Social Login Settings
        oauth_google_enabled: false,
        oauth_google_client_id: '',
        oauth_google_client_secret: '',
        oauth_discord_enabled: false,
        oauth_discord_client_id: '',
        oauth_discord_client_secret: '',
        oauth_github_enabled: false,
        oauth_github_client_id: '',
        oauth_github_client_secret: '',
        oauth_facebook_enabled: false,
        oauth_facebook_client_id: '',
        oauth_facebook_client_secret: '',
        oauth_allow_registration: true,
        oauth_auto_verify_email: true,

        // Email & Notifications
        email_sender_name: '',
        email_sender_address: '',
        smtp_host: '',
        smtp_port: 587,
        smtp_username: '',
        smtp_password: '',
        smtp_encryption: 'tls',
        admin_notifications_enabled: true,

        // Performance & Technical
        cache_enabled: true,
        cache_lifetime_minutes: 60,
        cdn_enabled: false,
        cdn_url: '',
        image_optimization_enabled: true,
        lazy_loading_enabled: true,
        debug_mode: false,

        // Legal & Compliance
        privacy_policy_url: '',
        terms_conditions_url: '',
        cookie_policy_url: '',
        gdpr_consent_text: '',
        right_to_be_forgotten_enabled: true,
        age_confirmation_required: false,
        age_minimum: 13,

        // Sandbox Settings
        sandbox_enabled: false,
        sandbox_public_enabled: false,
        sandbox_collaboration_enabled: false,
        sandbox_autosave_interval: 30,
        sandbox_role_limits: null,

        // Advanced / Developer Settings
        environment: 'production',
        api_rate_limit_per_minute: 60,
        api_keys_enabled: false,
        background_jobs_enabled: true,

        // Footer Settings
        custom_footer_enabled: false,
        custom_footer_html: '',
        footer_quicklinks: '[]',
    });

    async function fetchSettings() {
        try {
            const response = await api.get('/admin/settings');
            const fetchedSettings = response.data.settings;
            Object.assign(settings, fetchedSettings);
        } catch (error) {
            console.error('Failed to fetch settings:', error);
            showMessage('Failed to load settings', 'error');
        }
    }

    async function saveSettings() {
        isSaving.value = true;
        resetErrors();

        try {
            const settingsArray = Object.entries(settings)
                .filter(([key]) => key !== 'app_logo')
                .filter(([key, value]) => value !== null && value !== undefined) // Filter out null/undefined only
                .map(([key, value]) => {
                    let type = 'string';
                    if (typeof value === 'boolean') {
                        type = 'boolean';
                    } else if (typeof value === 'number') {
                        type = Number.isInteger(value) ? 'integer' : 'float';
                    }
                    return { key, value, type };
                });

            console.log('Saving settings:', settingsArray.find(s => s.key === 'font_family'));

            await api.post('/admin/settings', { settings: settingsArray });

            // Update the settings store after saving (this will update localStorage)
            const { useSettingsStore } = await import('@/store/settingStore.js');
            const settingsStore = useSettingsStore();
            await settingsStore.fetchAppSettings();

            showMessage('Settings saved successfully', 'success');
        } catch (error) {
            console.error('Failed to save settings:', error);
            handleErrors(error);

            // Build detailed error message
            let errorMessage = 'Failed to save settings';
            if (error.response?.data?.errors) {
                const errorDetails = Object.values(error.response.data.errors)
                    .flat()
                    .join('\n• ');
                errorMessage += ':\n• ' + errorDetails;
            }
            showMessage(errorMessage, 'error');
        } finally {
            isSaving.value = false;
        }
    }

    function showMessage(msg, type = 'success') {
        message.value = msg;
        alertType.value = type;

        // Scroll to top to show the message
        window.scrollTo({ top: 0, behavior: 'smooth' });

        setTimeout(() => {
            message.value = '';
        }, 10000); // Increased to 10 seconds for error messages
    }

    function resetErrors() {
        Object.keys(errors).forEach(key => delete errors[key]);
    }

    function handleErrors(error) {
        if (error.response?.data?.errors) {
            Object.assign(errors, error.response.data.errors);
        }
    }

    return {
        settings,
        isSaving,
        message,
        alertType,
        errors,
        fetchSettings,
        saveSettings,
        showMessage,
        resetErrors,
        handleErrors,
    };
}
