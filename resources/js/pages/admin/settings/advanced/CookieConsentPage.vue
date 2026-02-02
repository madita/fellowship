<template>
    <settings-page-layout
        title="Cookie Consent"
        description="Configure GDPR cookie banner settings"
        icon="mdi-cookie-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
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
            </template>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
        >
            Save Settings
        </v-btn>
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

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');
</script>
