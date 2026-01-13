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

        <!-- Custom Footer -->
        <settings-card icon="mdi-page-layout-footer" title="Custom Footer">
            <v-switch
                v-model="settings.custom_footer_enabled"
                label="Enable Custom Footer HTML"
                color="primary"
                class="mb-4"
                hint="Replace the default footer with custom HTML"
                persistent-hint
            ></v-switch>

            <v-textarea
                v-if="settings.custom_footer_enabled"
                v-model="settings.custom_footer_html"
                label="Custom Footer HTML"
                prepend-inner-icon="mdi-code-tags"
                variant="outlined"
                rows="12"
                :error-messages="errors.custom_footer_html"
                hint="Custom HTML for the footer section"
                persistent-hint
                placeholder="Enter your custom HTML here..."
                class="mb-4"
            ></v-textarea>

            <div v-if="settings.custom_footer_enabled" class="d-flex gap-2 mb-4">
                <v-btn
                    color="primary"
                    variant="outlined"
                    @click="loadSimpleFooterTemplate"
                    prepend-icon="mdi-code-braces"
                >
                    Load Simple Template
                </v-btn>
                <v-btn
                    color="primary"
                    variant="outlined"
                    @click="loadComplexFooterTemplate"
                    prepend-icon="mdi-code-tags"
                >
                    Load Complex Template
                </v-btn>
            </div>

            <v-alert v-if="settings.custom_footer_enabled" type="info" variant="tonal" class="mb-4">
                <div class="text-body-2">
                    <strong>Available Variables:</strong>
                    <ul class="mt-2">
                        <li><code v-pre>{{appName}}</code> - Application name</li>
                        <li><code v-pre>{{appCopyright}}</code> - Copyright text</li>
                        <li><code v-pre>{{contactEmail}}</code> - Contact email</li>
                        <li><code v-pre>{{contactPhone}}</code> - Contact phone</li>
                        <li><code v-pre>{{contactAddress}}</code> - Contact address</li>
                        <li><code v-pre>{{socialTwitter}}</code> - Twitter URL</li>
                        <li><code v-pre>{{socialFacebook}}</code> - Facebook URL</li>
                        <li><code v-pre>{{socialInstagram}}</code> - Instagram URL</li>
                    </ul>
                    <div class="mt-3">
                        <strong>Note:</strong> This custom footer will only appear on the Landing Layout (public pages).
                    </div>
                </div>
            </v-alert>
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
                hint="Link to your privacy policy page"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.terms_conditions_url"
                label="Terms & Conditions URL"
                prepend-inner-icon="mdi-file-document"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.terms_conditions_url"
                hint="Link to your terms and conditions page"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.cookie_policy_url"
                label="Cookie Policy URL"
                prepend-inner-icon="mdi-cookie"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cookie_policy_url"
                hint="Link to your cookie policy page"
                persistent-hint
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

function loadSimpleFooterTemplate() {
    props.settings.custom_footer_html = `<v-footer inset>
    <v-container>
        <v-row>
            <v-col cols="12" class="text-center">
                <div class="mb-2">
                    <strong>{{appName}}</strong>
                </div>
                <div class="text-caption mb-2">
                    {{appCopyright}}
                </div>
                <div v-if="{{contactEmail}}" class="text-caption">
                    Contact: <a :href="\`mailto:{{contactEmail}}\`">{{contactEmail}}</a>
                </div>
                <!-- Social Links -->
                <div v-if="{{socialTwitter}} || {{socialFacebook}} || {{socialInstagram}}" class="mt-3">
                    <v-btn v-if="{{socialTwitter}}" :href="{{socialTwitter}}" target="_blank" icon size="small" variant="text">
                        <v-icon>mdi-twitter</v-icon>
                    </v-btn>
                    <v-btn v-if="{{socialFacebook}}" :href="{{socialFacebook}}" target="_blank" icon size="small" variant="text">
                        <v-icon>mdi-facebook</v-icon>
                    </v-btn>
                    <v-btn v-if="{{socialInstagram}}" :href="{{socialInstagram}}" target="_blank" icon size="small" variant="text">
                        <v-icon>mdi-instagram</v-icon>
                    </v-btn>
                </div>
            </v-col>
        </v-row>
    </v-container>
</v-footer>`;
}

function loadComplexFooterTemplate() {
    props.settings.custom_footer_html = `<v-footer color="transparent">
    <v-container class="py-5">
        <v-row>
            <!-- Navigation Section -->
            <v-col cols="12" md="4">
                <div class="text-h6 text-lg-h5 font-weight-bold">Navigation</div>
                <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"></div>
                <div class="d-flex flex-wrap">
                    <div class="w-half body-1 mb-1">
                        <router-link class="text-decoration-none text-primary" to="/">Home</router-link>
                    </div>
                    <div class="w-half body-1 mb-1">
                        <router-link class="text-decoration-none text-primary" to="/about">About</router-link>
                    </div>
                    <div class="w-half body-1 mb-1">
                        <router-link class="text-decoration-none text-primary" to="/contact">Contact</router-link>
                    </div>
                </div>
            </v-col>

            <!-- Contact Information Section -->
            <v-col cols="12" md="4">
                <div class="text-h6 text-lg-h5 font-weight-bold">Contact Information</div>
                <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"></div>
                <div v-if="{{contactAddress}}" class="d-flex mb-2 font-weight-bold">
                    <v-icon color="primary lighten-1" class="mr-2">mdi-map-marker-outline</v-icon>
                    {{contactAddress}}
                </div>
                <div v-if="{{contactPhone}}" class="d-flex mb-2">
                    <v-icon color="primary lighten-1" class="mr-2">mdi-phone-outline</v-icon>
                    <a :href="\`tel:{{contactPhone}}\`" class="text-decoration-none text-primary">{{contactPhone}}</a>
                </div>
                <div v-if="{{contactEmail}}" class="d-flex mb-2">
                    <v-icon color="primary lighten-1" class="mr-2">mdi-email-outline</v-icon>
                    <a :href="\`mailto:{{contactEmail}}\`" class="text-decoration-none text-primary">{{contactEmail}}</a>
                </div>
            </v-col>

            <!-- Newsletter & Social Section -->
            <v-col cols="12" md="4">
                <div class="text-h6 text-lg-h5 font-weight-bold">Newsletter</div>
                <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"></div>
                <div class="d-flex flex-column flex-lg-row w-full">
                    <v-text-field
                        variant="outlined"
                        label="Your email"
                        dense
                        height="44"
                        class="mr-lg-2"
                    ></v-text-field>
                    <v-btn size="large" color="primary">Subscribe</v-btn>
                </div>
                <!-- Social Media Links -->
                <div class="text-center text-md-right mt-4 mt-lg-2">
                    Connect
                    <v-btn v-if="{{socialTwitter}}" :href="{{socialTwitter}}" target="_blank" fab small color="primary" class="ml-2">
                        <v-icon>mdi-twitter</v-icon>
                    </v-btn>
                    <v-btn v-if="{{socialFacebook}}" :href="{{socialFacebook}}" target="_blank" fab small color="primary" class="ml-2">
                        <v-icon>mdi-facebook</v-icon>
                    </v-btn>
                    <v-btn v-if="{{socialInstagram}}" :href="{{socialInstagram}}" target="_blank" fab small color="primary" class="ml-2">
                        <v-icon>mdi-instagram</v-icon>
                    </v-btn>
                </div>
            </v-col>
        </v-row>

        <!-- Footer Bottom -->
        <v-divider class="my-3"></v-divider>
        <div class="text-center caption">
            {{appCopyright}}
        </div>
    </v-container>
</v-footer>`;
}
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
