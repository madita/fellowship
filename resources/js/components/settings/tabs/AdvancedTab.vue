<template>
    <div>
        <!-- Performance & Technical -->
        <settings-card icon="mdi-speedometer" title="Performance & Caching">
            <v-switch
                v-model="settings.cache_enabled"
                label="Enable Caching"
                color="primary"
                class="mb-4"
                hint="Enable application-wide caching"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-model.number="settings.cache_lifetime_minutes"
                label="Cache Lifetime (minutes)"
                prepend-inner-icon="mdi-clock"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.cache_lifetime_minutes"
                hint="How long to cache data (in minutes)"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.cdn_enabled"
                label="Enable CDN"
                color="primary"
                class="mb-4"
                hint="Use CDN for static assets"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-if="settings.cdn_enabled"
                v-model="settings.cdn_url"
                label="CDN URL"
                prepend-inner-icon="mdi-server-network"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cdn_url"
                hint="CDN base URL for assets"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.image_optimization_enabled"
                label="Enable Image Optimization"
                color="primary"
                class="mb-4"
                hint="Automatically optimize uploaded images"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.lazy_loading_enabled"
                label="Enable Lazy Loading"
                color="primary"
                hint="Lazy load images and components"
                persistent-hint
            ></v-switch>
        </settings-card>

        <!-- PWA Settings -->
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
                            {{ settings.app_icon ? '✓ Uploaded' : '⚠ Using default' }}
                        </span>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center">
                        <span class="text-body-2">Favicon:</span>
                        <span class="text-body-2 font-weight-medium">
                            {{ settings.favicon ? '✓ Uploaded' : '⚠ Using default' }}
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
                        <li><strong>Size:</strong> At least 192×192 pixels (512×512 recommended)</li>
                        <li><strong>Shape:</strong> Square (equal width and height)</li>
                        <li><strong>Purpose:</strong> Used for home screen icon and app icon</li>
                    </ul>
                    <div class="mt-2">
                        Go to <strong>Branding</strong> tab → <strong>App Icon (PWA)</strong> to upload a suitable icon.
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

        <!-- API & Developer Settings -->
        <settings-card icon="mdi-api" title="API & Developer Settings">
            <v-select
                v-model="settings.environment"
                label="Environment"
                :items="environments"
                prepend-inner-icon="mdi-monitor"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.environment"
            ></v-select>

            <v-switch
                v-model="settings.debug_mode"
                label="Enable Debug Mode"
                color="warning"
                class="mb-4"
                hint="Show detailed error messages (disable in production)"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-model.number="settings.api_rate_limit_per_minute"
                label="API Rate Limit (per minute)"
                prepend-inner-icon="mdi-speedometer"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.api_rate_limit_per_minute"
                hint="Maximum API requests per minute per user"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.api_keys_enabled"
                label="Enable API Keys"
                color="primary"
                class="mb-4"
                hint="Allow users to generate API keys"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.background_jobs_enabled"
                label="Enable Background Jobs"
                color="primary"
                hint="Process tasks in background queue"
                persistent-hint
            ></v-switch>
        </settings-card>

        <!-- Legal & Compliance -->
        <settings-card icon="mdi-gavel" title="Legal & Compliance">
            <v-text-field
                v-model="settings.privacy_policy_url"
                label="Privacy Policy URL"
                prepend-inner-icon="mdi-shield-check"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.privacy_policy_url"
                hint="Relative path (/privacy-policy) or full URL (https://example.com/privacy)"
                persistent-hint
                placeholder="/privacy-policy"
            ></v-text-field>

            <v-text-field
                v-model="settings.terms_conditions_url"
                label="Terms & Conditions URL"
                prepend-inner-icon="mdi-file-document"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.terms_conditions_url"
                hint="Relative path (/terms) or full URL (https://example.com/terms)"
                persistent-hint
                placeholder="/terms"
            ></v-text-field>

            <v-text-field
                v-model="settings.cookie_policy_url"
                label="Cookie Policy URL"
                prepend-inner-icon="mdi-cookie"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cookie_policy_url"
                hint="Relative path (/cookies) or full URL (https://example.com/cookies)"
                persistent-hint
                placeholder="/cookies"
            ></v-text-field>

            <v-switch
                v-model="settings.right_to_be_forgotten_enabled"
                label="Enable Right to be Forgotten (GDPR)"
                color="primary"
                class="mb-4"
                hint="Allow users to request data deletion"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.age_confirmation_required"
                label="Require Age Confirmation"
                color="primary"
                class="mb-4"
                hint="Users must confirm their age"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-if="settings.age_confirmation_required"
                v-model.number="settings.age_minimum"
                label="Minimum Age"
                prepend-inner-icon="mdi-numeric"
                variant="outlined"
                type="number"
                :error-messages="errors.age_minimum"
                hint="Minimum age required to use the site"
                persistent-hint
            ></v-text-field>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
        >
            Save Settings
        </v-btn>
    </div>
</template>

<script setup>
import SettingsCard from '../SettingsCard.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

const emit = defineEmits(['save', 'message']);

const environments = [
    'development',
    'staging',
    'production'
];

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
