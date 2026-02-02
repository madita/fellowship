import { defineStore } from 'pinia';
import axios from 'axios';
import { debug } from '@/utils/debug.js';

const log = debug.module('SettingsStore');

export const useSettingsStore = defineStore({
    id: 'settings',

    state: () => ({
        locale: 'de', // default locale
        maxFileSize: 2 * 1024 * 1024, // Default: 2MB
        maxBatchSize: 5, // Default: 5 files per batch
        batchUpload: true, // Default: batch upload
        appSettings: {
            app_name: 'Fellowship',
            app_logo: '',
            app_copyright: '© Fellowship 2021',
            site_tagline: '',
            contact_address: '',
            contact_phone: '',
            contact_email: '',
            social_twitter: '',
            social_facebook: '',
            social_instagram: '',
            social_linkedin: '',
            social_youtube: '',
            social_discord: '',
            default_language: 'en',
            default_timezone: 'UTC',
            date_format: 'Y-m-d',
            time_format: 'H:i:s',
            language_change_enabled: true,
            logo_light: null,
            logo_dark: null,
            theme_mode: 'system',
            font_family: 'Roboto, sans-serif',
            custom_footer_enabled: false,
            custom_footer_html: '',
            maintenance_mode: false,
            maintenance_message: '',
            primary_color: '#115571',
            secondary_color: '#a0b9c8',
            background_light: null,
            background_dark: null,
            background_style: 'cover',
            // Light theme colors
            primary_color_light: '#115571',
            secondary_color_light: '#a0b9c8',
            accent_color_light: '#048ba8',
            background_color_light: '#ffffff',
            surface_color_light: '#f2f5f8',
            error_color_light: '#ef476f',
            warning_color_light: '#ffd166',
            info_color_light: '#2196F3',
            success_color_light: '#06d6a0',
            // Dark theme colors
            primary_color_dark: '#115571',
            secondary_color_dark: '#a0b9c8',
            accent_color_dark: '#26c4da',
            background_color_dark: '#121212',
            surface_color_dark: '#1e1e1e',
            error_color_dark: '#ef476f',
            warning_color_dark: '#ffd166',
            info_color_dark: '#2196F3',
            success_color_dark: '#06d6a0',
            // Opacity settings
            background_opacity_light: 95,
            background_opacity_dark: 95,
            surface_opacity_light: 100,
            surface_opacity_dark: 100,
            // Cookie Consent / GDPR
            cookie_consent_enabled: true,
            cookie_banner_title: '',
            cookie_banner_text: '',
            cookie_preferences_text: '',
            cookie_analytics_default: false,
            cookie_marketing_default: false,
            cookie_functional_default: false,
            cookie_policy_url: '',
            // Login branding
            login_branding_enabled: false,
            // Custom CSS
            custom_css: '',
            // Age confirmation
            age_confirmation_required: false,
            age_minimum: 18,
            // Performance settings
            lazy_loading_enabled: true,
            image_optimization_enabled: true,
        },
        settingsLoaded: false,
    }),

    getters: {
        appName: (state) => state.appSettings.app_name || 'Fellowship',
        appLogo: (state) => state.appSettings.app_logo ? `/storage/${state.appSettings.app_logo}` : null,
        logoLight: (state) => state.appSettings.logo_light || null,
        logoDark: (state) => state.appSettings.logo_dark || null,
        appCopyright: (state) => state.appSettings.app_copyright || '© Fellowship 2021',
        siteTagline: (state) => state.appSettings.site_tagline || '',
        contactAddress: (state) => state.appSettings.contact_address || '',
        contactPhone: (state) => state.appSettings.contact_phone || '',
        contactEmail: (state) => state.appSettings.contact_email || '',
        socialTwitter: (state) => state.appSettings.social_twitter || '',
        socialFacebook: (state) => state.appSettings.social_facebook || '',
        socialInstagram: (state) => state.appSettings.social_instagram || '',
        socialLinkedin: (state) => state.appSettings.social_linkedin || '',
        socialYoutube: (state) => state.appSettings.social_youtube || '',
        socialDiscord: (state) => state.appSettings.social_discord || '',
        languageChangeEnabled: (state) => {
            // Convert string 'true'/'false' to boolean if needed
            const value = state.appSettings.language_change_enabled;
            if (typeof value === 'string') {
                return value === 'true' || value === '1';
            }
            return value !== false;
        },
        defaultTimezone: (state) => state.appSettings.default_timezone || 'UTC',
        defaultDateFormat: (state) => state.appSettings.date_format || 'Y-m-d',
        defaultTimeFormat: (state) => state.appSettings.time_format || 'H:i:s',
        customFooterEnabled: (state) => {
            const value = state.appSettings.custom_footer_enabled;
            if (typeof value === 'string') {
                return value === 'true' || value === '1';
            }
            return value === true;
        },
        customFooterHtml: (state) => state.appSettings.custom_footer_html || '',
        themeMode: (state) => state.appSettings.theme_mode || 'system',
        fontFamily: (state) => state.appSettings.font_family || 'Roboto, sans-serif',
        maintenanceMode: (state) => {
            const value = state.appSettings.maintenance_mode;
            if (typeof value === 'string') {
                return value === 'true' || value === '1';
            }
            return value === true;
        },
        maintenanceMessage: (state) => state.appSettings.maintenance_message || 'We are currently performing scheduled maintenance. Please check back soon.',
        primaryColor: (state) => state.appSettings.primary_color || '#115571',
        secondaryColor: (state) => state.appSettings.secondary_color || '#a0b9c8',
        favicon: (state) => state.appSettings.favicon || null,
        appIcon: (state) => state.appSettings.app_icon || null,
        loginBrandingEnabled: (state) => {
            const value = state.appSettings.login_branding_enabled;
            if (typeof value === 'string') {
                return value === 'true' || value === '1';
            }
            return value === true;
        },
        customCss: (state) => state.appSettings.custom_css || '',
        ageConfirmationRequired: (state) => {
            const value = state.appSettings.age_confirmation_required;
            if (typeof value === 'string') {
                return value === 'true' || value === '1';
            }
            return value === true;
        },
        ageMinimum: (state) => {
            const value = state.appSettings.age_minimum;
            return parseInt(value) || 18;
        },
        lazyLoadingEnabled: (state) => {
            const value = state.appSettings.lazy_loading_enabled;
            if (typeof value === 'string') {
                return value === 'true' || value === '1';
            }
            return value !== false;
        },
        imageOptimizationEnabled: (state) => {
            const value = state.appSettings.image_optimization_enabled;
            if (typeof value === 'string') {
                return value === 'true' || value === '1';
            }
            return value !== false;
        },
        // Alias for components that use settings.xxx
        settings: (state) => state.appSettings,
    },

    actions: {
        setLocale(locale) {
            this.locale = locale;
        },
        setMaxFileSize(size) {
            this.maxFileSize = size;
        },
        setMaxBatchSize(batchSize) {
            this.maxBatchSize = batchSize;
        },
        setBatchUpload(enabled) {
            this.batchUpload = enabled;
        },
        applyBackgroundImages() {
            try {
                // Dynamically import vuetify to check current theme
                import('@/plugins/vuetify.js').then(({ default: vuetify }) => {
                    const isDark = vuetify.theme.global.current.value.dark;
                    const backgroundImage = isDark
                        ? this.appSettings.background_dark
                        : this.appSettings.background_light;
                    const backgroundStyle = this.appSettings.background_style || 'cover';

                    // Get or create the background element
                    let bgElement = document.getElementById('app-background');
                    if (!bgElement) {
                        bgElement = document.createElement('div');
                        bgElement.id = 'app-background';
                        bgElement.style.position = 'fixed';
                        bgElement.style.top = '0';
                        bgElement.style.left = '0';
                        bgElement.style.width = '100%';
                        bgElement.style.height = '100%';
                        bgElement.style.zIndex = '-1';
                        bgElement.style.pointerEvents = 'none';
                        document.body.prepend(bgElement);
                    }

                    // Apply background image or remove it
                    if (backgroundImage && backgroundImage.trim() !== '') {
                        const imageUrl = `/storage/${backgroundImage}`;
                        bgElement.style.backgroundImage = `url('${imageUrl}')`;
                        bgElement.style.backgroundPosition = 'center';
                        bgElement.style.backgroundAttachment = 'fixed';

                        if (backgroundStyle === 'repeat') {
                            bgElement.style.backgroundSize = 'auto';
                            bgElement.style.backgroundRepeat = 'repeat';
                        } else {
                            bgElement.style.backgroundSize = 'cover';
                            bgElement.style.backgroundRepeat = 'no-repeat';
                        }
                    } else {
                        // Remove background image if none is set
                        bgElement.style.backgroundImage = '';
                    }

                    // Make sure body background is transparent to show the background element
                    document.body.style.background = 'transparent';
                }).catch(e => {
                    log.warn('Failed to apply background images:', e);
                });
            } catch (e) {
                log.warn('Failed to apply background images:', e);
            }
        },
        applyCustomCss() {
            try {
                const customCss = this.appSettings.custom_css || '';

                // Get or create the custom CSS style element
                let styleEl = document.getElementById('custom-css-style');
                if (!styleEl) {
                    styleEl = document.createElement('style');
                    styleEl.id = 'custom-css-style';
                    document.head.appendChild(styleEl);
                }

                // Apply custom CSS
                styleEl.textContent = customCss;
            } catch (e) {
                log.warn('Failed to apply custom CSS:', e);
            }
        },
        applyOpacitySettings() {
            try {
                import('@/plugins/vuetify.js').then(({ default: vuetify }) => {
                    const isDark = vuetify.theme.global.current.value.dark;

                    // Get opacity values (0-100) and convert to decimal (0-1)
                    const backgroundOpacity = isDark
                        ? (this.appSettings.background_opacity_dark || 95) / 100
                        : (this.appSettings.background_opacity_light || 95) / 100;

                    const surfaceOpacity = isDark
                        ? (this.appSettings.surface_opacity_dark || 100) / 100
                        : (this.appSettings.surface_opacity_light || 100) / 100;

                    // Get or create the opacity style element
                    let styleEl = document.getElementById('dynamic-opacity-style');
                    if (!styleEl) {
                        styleEl = document.createElement('style');
                        styleEl.id = 'dynamic-opacity-style';
                        document.head.appendChild(styleEl);
                    }

                    // Apply opacity via CSS custom properties
                    styleEl.textContent = `
                        :root {
                            --app-background-opacity: ${backgroundOpacity};
                            --app-surface-opacity: ${surfaceOpacity};
                        }

                        .v-main {
                            background: rgba(var(--v-theme-background), var(--app-background-opacity)) !important;
                        }

                        .v-card,
                        .v-sheet,
                        .v-toolbar,
                        .v-app-bar {
                            background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
                        }

                        .v-navigation-drawer,
                        .v-dialog .v-overlay__content,
                        .v-menu .v-overlay__content {
                            background: rgb(var(--v-theme-surface)) !important;
                        }
                    `;
                }).catch(e => {
                    log.warn('Failed to apply opacity settings:', e);
                });
            } catch (e) {
                log.warn('Failed to apply opacity settings:', e);
            }
        },
        async fetchAppSettings() {
            try {
                const response = await axios.get('/api/settings/public');
                const settings = response.data.settings;

                log.log('Fetched settings from API:', { font_family: settings.font_family });

                // Settings are returned as a key-value object
                this.appSettings = { ...this.appSettings, ...settings };

                log.log('Current font_family in store:', this.fontFamily);

                // Cache settings to localStorage for faster initial load
                try {
                    localStorage.setItem('app_settings', JSON.stringify(this.appSettings));
                } catch (e) {
                    log.warn('Failed to cache settings to localStorage:', e);
                }

                // Apply theme colors dynamically
                try {
                    const { updateThemeColors } = await import('@/plugins/vuetify.js');
                    const lightColors = {
                        primary: this.appSettings.primary_color_light || this.appSettings.primary_color || '#115571',
                        secondary: this.appSettings.secondary_color_light || this.appSettings.secondary_color || '#a0b9c8',
                        accent: this.appSettings.accent_color_light || '#048ba8',
                        background: this.appSettings.background_color_light || '#ffffff',
                        surface: this.appSettings.surface_color_light || '#f2f5f8',
                        error: this.appSettings.error_color_light || '#ef476f',
                        info: this.appSettings.info_color_light || '#2196F3',
                        success: this.appSettings.success_color_light || '#06d6a0',
                        warning: this.appSettings.warning_color_light || '#ffd166',
                    };
                    const darkColors = {
                        primary: this.appSettings.primary_color_dark || this.appSettings.primary_color || '#115571',
                        secondary: this.appSettings.secondary_color_dark || this.appSettings.secondary_color || '#a0b9c8',
                        accent: this.appSettings.accent_color_dark || '#26c4da',
                        background: this.appSettings.background_color_dark || '#121212',
                        surface: this.appSettings.surface_color_dark || '#1e1e1e',
                        error: this.appSettings.error_color_dark || '#ef476f',
                        info: this.appSettings.info_color_dark || '#2196F3',
                        success: this.appSettings.success_color_dark || '#06d6a0',
                        warning: this.appSettings.warning_color_dark || '#ffd166',
                    };
                    updateThemeColors(lightColors, darkColors);
                } catch (e) {
                    log.warn('Failed to update theme colors:', e);
                }

                // Apply font family dynamically
                try {
                    const fontFamily = this.fontFamily;
                    log.log('Applying font family:', fontFamily);
                    if (fontFamily) {
                        const { updateFontFamily } = await import('@/plugins/vuetify.js');
                        updateFontFamily(fontFamily);
                    }
                } catch (e) {
                    log.warn('Failed to apply font family:', e);
                }

                // Update favicon and app icons dynamically
                try {
                    if (this.favicon) {
                        const faviconLink = document.getElementById('favicon');
                        if (faviconLink) {
                            faviconLink.href = `/storage/${this.favicon}`;
                        }
                    }

                    if (this.appIcon) {
                        const appIcon192 = document.getElementById('app-icon-192');
                        const appIcon512 = document.getElementById('app-icon-512');
                        const iconUrl = `/storage/${this.appIcon}`;

                        if (appIcon192) appIcon192.href = iconUrl;
                        if (appIcon512) appIcon512.href = iconUrl;
                    }
                } catch (e) {
                    log.warn('Failed to update favicon/app icons:', e);
                }

                // Apply background images based on current theme
                this.applyBackgroundImages();

                // Apply opacity settings
                this.applyOpacitySettings();

                // Apply custom CSS
                this.applyCustomCss();

                this.settingsLoaded = true;
            } catch (error) {
                log.error('Failed to fetch app settings:', error);
                this.settingsLoaded = true; // Still mark as loaded to prevent infinite retries
            }
        },
    }
});

