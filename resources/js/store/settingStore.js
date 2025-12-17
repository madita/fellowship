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
        },
        settingsLoaded: false,
    }),

    getters: {
        appName: (state) => state.appSettings.app_name || 'Fellowship',
        appLogo: (state) => state.appSettings.app_logo ? `/storage/${state.appSettings.app_logo}` : null,
        appCopyright: (state) => state.appSettings.app_copyright || '© Fellowship 2021',
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

                // Settings are returned as a key-value object
                this.appSettings = { ...this.appSettings, ...settings };

                this.settingsLoaded = true;
            } catch (error) {
                console.error('Failed to fetch app settings:', error);
                this.settingsLoaded = true; // Still mark as loaded to prevent infinite retries
            }
        },
    }
});

