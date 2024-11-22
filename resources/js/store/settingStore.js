import { defineStore } from 'pinia';

export const useSettingsStore = defineStore({
    id: 'settings',

    state: () => ({
        locale: 'de', // default locale
        maxFileSize: 2 * 1024 * 1024, // Default: 2MB
        maxBatchSize: 5, // Default: 5 files per batch
        batchUpload: true, // Default: batch upload
    }),

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
    }
});

