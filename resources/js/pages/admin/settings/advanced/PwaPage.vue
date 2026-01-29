<template>
    <settings-page-layout
        title="Progressive Web App"
        description="PWA configuration and status"
        icon="mdi-cellphone-link"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-cellphone-check" title="Progressive Web App (PWA)">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>What is PWA?</strong> Progressive Web Apps allow users to install your site as an app on their device. Features include:
                    <ul class="mt-1 ml-4">
                        <li>Install to home screen (mobile & desktop)</li>
                        <li>Offline access with service workers</li>
                        <li>App-like experience in standalone mode</li>
                        <li>Fast loading with caching</li>
                    </ul>
                </div>
            </v-alert>

            <div class="mb-4">
                <div class="text-subtitle-2 mb-2">
                    <v-icon size="small" class="mr-1">mdi-information</v-icon>
                    PWA Status
                </div>
                <v-alert type="success" variant="tonal" density="compact">
                    <div class="d-flex align-center">
                        <v-icon class="mr-2">mdi-check-circle</v-icon>
                        <div>
                            <div class="font-weight-medium">PWA is enabled and configured</div>
                            <div class="text-caption mt-1">
                                Your app is ready to be installed by users. The manifest is dynamically generated from your settings.
                            </div>
                        </div>
                    </div>
                </v-alert>
            </div>

            <div class="mb-4">
                <div class="text-subtitle-2 mb-2">
                    <v-icon size="small" class="mr-1">mdi-cog</v-icon>
                    PWA Configuration
                </div>
                <v-card variant="outlined" class="pa-3">
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">App Name:</span>
                        <span class="text-body-2 font-weight-medium">{{ settings.app_name || 'Fellowship' }}</span>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">Theme Color:</span>
                        <v-chip :color="settings.primary_color || '#1976D2'" size="small">
                            {{ settings.primary_color || '#1976D2' }}
                        </v-chip>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">App Icon:</span>
                        <span class="text-body-2 font-weight-medium">
                            {{ settings.app_icon ? 'Uploaded' : 'Using default' }}
                        </span>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center">
                        <span class="text-body-2">Favicon:</span>
                        <span class="text-body-2 font-weight-medium">
                            {{ settings.favicon ? 'Uploaded' : 'Using default' }}
                        </span>
                    </div>
                </v-card>
            </div>

            <v-alert type="warning" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-alert</v-icon>
                    <strong>Important:</strong> For PWA installation to work, you must upload an App Icon that meets these requirements:
                    <ul class="mt-1 ml-4">
                        <li><strong>Format:</strong> PNG, WebP, or SVG (NOT JPEG)</li>
                        <li><strong>Size:</strong> At least 192x192 pixels (512x512 recommended)</li>
                        <li><strong>Shape:</strong> Square (equal width and height)</li>
                        <li><strong>Purpose:</strong> Used for home screen icon and app icon</li>
                    </ul>
                    <div class="mt-2">
                        Go to <strong>Branding</strong> > <strong>Logos & Icons</strong> to upload a suitable icon.
                    </div>
                </div>
            </v-alert>

            <div class="d-flex gap-2">
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-open-in-new"
                    href="/manifest.json"
                    target="_blank"
                >
                    View Manifest
                </v-btn>
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-refresh"
                    @click="testServiceWorker"
                >
                    Test Service Worker
                </v-btn>
            </div>
        </settings-card>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const emit = defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');

function testServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration('/').then((registration) => {
            if (registration) {
                emit('message', {
                    text: 'Service Worker is active and registered! Check browser console for details.',
                    type: 'success'
                });
                console.log('Service Worker Registration:', registration);
                console.log('Service Worker State:', registration.active?.state);
            } else {
                emit('message', {
                    text: 'Service Worker is not registered yet. It will be registered on production build.',
                    type: 'info'
                });
            }
        });
    } else {
        emit('message', {
            text: 'Service Workers are not supported in this browser.',
            type: 'error'
        });
    }
}
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
