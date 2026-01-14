import { defineStore } from 'pinia';
import axios from 'axios';

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
        async fetchAppSettings() {
            try {
                const response = await axios.get('/api/settings/public');
                const settings = response.data.settings;

                console.log('Fetched settings from API:', { font_family: settings.font_family });

                // Settings are returned as a key-value object
                this.appSettings = { ...this.appSettings, ...settings };

                console.log('Current font_family in store:', this.fontFamily);

                // Cache settings to localStorage for faster initial load
                try {
                    localStorage.setItem('app_settings', JSON.stringify(this.appSettings));
                } catch (e) {
                    console.warn('Failed to cache settings to localStorage:', e);
                }

                // Apply theme colors dynamically
                try {
                    const { updateThemeColors } = await import('@/plugins/vuetify.js');
                    updateThemeColors(this.primaryColor, this.secondaryColor);
                } catch (e) {
                    console.warn('Failed to update theme colors:', e);
                }

                // Apply font family dynamically
                try {
                    const fontFamily = this.fontFamily;
                    console.log('Applying font family:', fontFamily);
                    if (fontFamily) {
                        const { updateFontFamily } = await import('@/plugins/vuetify.js');
                        updateFontFamily(fontFamily);
                    }
                } catch (e) {
                    console.warn('Failed to apply font family:', e);
                    console.error(e);
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
                    console.warn('Failed to update favicon/app icons:', e);
                }

                this.settingsLoaded = true;
            } catch (error) {
                console.error('Failed to fetch app settings:', error);
                this.settingsLoaded = true; // Still mark as loaded to prevent infinite retries
            }
        },
    }
});

