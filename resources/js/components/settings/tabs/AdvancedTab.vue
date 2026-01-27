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

        <!-- Newsletter Integration -->
        <settings-card icon="mdi-email-newsletter" title="Newsletter Integration">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    Connect your newsletter service to enable email subscriptions through your website.
                </div>
            </v-alert>

            <v-switch
                v-model="settings.newsletter_enabled"
                label="Enable Newsletter"
                color="primary"
                class="mb-4"
                hint="Enable newsletter subscription functionality"
                persistent-hint
            ></v-switch>

            <v-select
                v-if="settings.newsletter_enabled"
                v-model="settings.newsletter_provider"
                label="Newsletter Provider"
                :items="newsletterProviders"
                prepend-inner-icon="mdi-email-variant"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_provider"
                hint="Select your newsletter service provider"
                persistent-hint
            ></v-select>

            <v-text-field
                v-if="settings.newsletter_enabled && settings.newsletter_provider"
                v-model="settings.newsletter_api_key"
                label="API Key"
                prepend-inner-icon="mdi-key"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_api_key"
                hint="Your newsletter provider API key"
                persistent-hint
                type="password"
            ></v-text-field>

            <v-text-field
                v-if="settings.newsletter_enabled && settings.newsletter_provider"
                v-model="settings.newsletter_list_id"
                label="List/Audience ID"
                prepend-inner-icon="mdi-format-list-bulleted"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_list_id"
                hint="The ID of your newsletter list or audience"
                persistent-hint
            ></v-text-field>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'mailchimp'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Mailchimp Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to your Mailchimp account → Profile → Extras → API keys</li>
                        <li>Create a new API key and paste it above</li>
                        <li>Go to Audience → Settings → Audience name and defaults</li>
                        <li>Copy the Audience ID and paste it above</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'mailerlite'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>MailerLite Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to your MailerLite account → Integrations → Developer API</li>
                        <li>Generate a new API key and paste it above</li>
                        <li>Go to Subscribers → Groups</li>
                        <li>Copy the Group ID and paste it above</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'sendgrid'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>SendGrid Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to Settings → API Keys → Create API Key</li>
                        <li>Give it full access to Marketing permissions</li>
                        <li>Copy the API key and paste it above</li>
                        <li>Go to Marketing → Contacts → Lists</li>
                        <li>Copy the List ID and paste it above</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'convertkit'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>ConvertKit Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to Settings → Advanced → API Key</li>
                        <li>Copy your API Secret (not API Key) and paste it above</li>
                        <li>Go to Grow → Landing Pages & Forms</li>
                        <li>Find your form and copy the Form ID from the URL</li>
                    </ol>
                </div>
            </v-alert>
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

        <!-- Cookie Consent / GDPR -->
        <settings-card icon="mdi-cookie" title="Cookie Consent (GDPR)">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>GDPR Compliance:</strong> This cookie banner allows visitors to manage their cookie preferences.
                    It's required by EU law (GDPR) and similar regulations worldwide.
                </div>
            </v-alert>

            <v-switch
                v-model="settings.cookie_consent_enabled"
                label="Enable Cookie Consent Banner"
                color="primary"
                class="mb-4"
                hint="Show GDPR-compliant cookie consent banner to visitors"
                persistent-hint
            ></v-switch>

            <template v-if="settings.cookie_consent_enabled">
                <v-text-field
                    v-model="settings.cookie_banner_title"
                    label="Banner Title"
                    prepend-inner-icon="mdi-format-title"
                    variant="outlined"
                    class="mb-4"
                    hint="Title shown on the cookie banner (default: 'Cookie Consent')"
                    persistent-hint
                    placeholder="Cookie Consent"
                ></v-text-field>

                <v-textarea
                    v-model="settings.cookie_banner_text"
                    label="Banner Text"
                    prepend-inner-icon="mdi-text"
                    variant="outlined"
                    class="mb-4"
                    rows="3"
                    hint="Main message shown on the cookie consent banner"
                    persistent-hint
                    placeholder="We use cookies to enhance your browsing experience, analyze site traffic, and personalize content."
                ></v-textarea>

                <v-textarea
                    v-model="settings.cookie_preferences_text"
                    label="Preferences Description"
                    prepend-inner-icon="mdi-text"
                    variant="outlined"
                    class="mb-4"
                    rows="2"
                    hint="Text shown when user opens cookie preferences"
                    persistent-hint
                    placeholder="Manage your cookie preferences below. You can enable or disable different types of cookies."
                ></v-textarea>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-3">
                    <v-icon size="small" class="mr-1">mdi-toggle-switch</v-icon>
                    Default Cookie States
                </div>

                <v-alert type="warning" variant="tonal" class="mb-4" density="compact">
                    <div class="text-caption">
                        <strong>Note:</strong> For GDPR compliance, non-essential cookies should be disabled by default.
                        Users must actively opt-in to analytics and marketing cookies.
                    </div>
                </v-alert>

                <v-switch
                    v-model="settings.cookie_analytics_default"
                    label="Analytics cookies enabled by default"
                    color="primary"
                    class="mb-2"
                    hint="Pre-enable analytics cookies (not GDPR compliant if enabled)"
                    persistent-hint
                ></v-switch>

                <v-switch
                    v-model="settings.cookie_marketing_default"
                    label="Marketing cookies enabled by default"
                    color="primary"
                    class="mb-2"
                    hint="Pre-enable marketing cookies (not GDPR compliant if enabled)"
                    persistent-hint
                ></v-switch>

                <v-switch
                    v-model="settings.cookie_functional_default"
                    label="Functional cookies enabled by default"
                    color="primary"
                    class="mb-4"
                    hint="Pre-enable functional cookies for enhanced features"
                    persistent-hint
                ></v-switch>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-3">
                    <v-icon size="small" class="mr-1">mdi-link</v-icon>
                    Related Pages
                </div>

                <v-text-field
                    v-model="settings.cookie_policy_url"
                    label="Cookie Policy URL"
                    prepend-inner-icon="mdi-cookie"
                    variant="outlined"
                    class="mb-4"
                    :error-messages="errors.cookie_policy_url"
                    hint="Link to your cookie policy page (shown in banner)"
                    persistent-hint
                    placeholder="/cookies or https://example.com/cookies"
                ></v-text-field>

                <v-text-field
                    v-model="settings.privacy_policy_url"
                    label="Privacy Policy URL"
                    prepend-inner-icon="mdi-shield-account"
                    variant="outlined"
                    :error-messages="errors.privacy_policy_url"
                    hint="Link to your privacy policy page"
                    persistent-hint
                    placeholder="/privacy or https://example.com/privacy"
                ></v-text-field>
            </template>
        </settings-card>

        <!-- Custom Scripts -->
        <settings-card icon="mdi-code-tags" title="Custom Scripts">
            <v-alert type="warning" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-alert</v-icon>
                    <strong>Security Warning:</strong> Only add scripts from trusted sources. Malicious scripts can compromise your site and user data.
                </div>
            </v-alert>

            <v-textarea
                v-model="settings.custom_head_scripts"
                label="Head Scripts"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_head_scripts"
                hint="Scripts to insert in the <head> section. Include full <script> tags. Great for analytics, meta pixels, etc."
                persistent-hint
                placeholder="<script>
  // Your head scripts here
  // e.g., Google Tag Manager, Meta Pixel
</script>"
            ></v-textarea>

            <v-textarea
                v-model="settings.custom_body_scripts"
                label="Body Scripts (before </body>)"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_body_scripts"
                hint="Scripts to insert before the closing </body> tag. Include full <script> tags. Great for chat widgets, tracking scripts, etc."
                persistent-hint
                placeholder="<script>
  // Your body scripts here
  // e.g., Chat widgets, deferred analytics
</script>"
            ></v-textarea>

            <v-alert type="info" variant="tonal" density="compact">
                <div class="text-caption">
                    <strong>Common uses:</strong>
                    <ul class="mt-1 ml-4">
                        <li><strong>Head:</strong> Google Tag Manager, Meta/Facebook Pixel, Google Analytics</li>
                        <li><strong>Body:</strong> Chat widgets (Intercom, Crisp), HotJar, deferred loading scripts</li>
                    </ul>
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

const newsletterProviders = [
    { title: 'Mailchimp', value: 'mailchimp' },
    { title: 'MailerLite', value: 'mailerlite' },
    { title: 'SendGrid', value: 'sendgrid' },
    { title: 'ConvertKit', value: 'convertkit' },
    { title: 'ActiveCampaign', value: 'activecampaign' },
    { title: 'Sendinblue (Brevo)', value: 'sendinblue' },
    { title: 'Custom API', value: 'custom' },
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

.monospace-input :deep(textarea) {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
}
</style>
