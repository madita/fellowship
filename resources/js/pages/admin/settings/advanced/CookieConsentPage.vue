<template>
    <settings-page-layout
        :title="$t('settings.advanced.cookieConsent.title')"
        :description="$t('settings.advanced.cookieConsent.description')"
        icon="mdi-cookie-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-cookie" :title="$t('settings.advanced.cookieConsent.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.cookieConsent.gdprCompliance') }}</strong> {{ $t('settings.advanced.cookieConsent.gdprComplianceText') }}
                </div>
            </v-alert>

            <v-switch
                v-model="settings.cookie_consent_enabled"
                :label="$t('settings.advanced.cookieConsent.enableBanner')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.cookieConsent.enableBannerHint')"
                persistent-hint
            ></v-switch>

            <template v-if="settings.cookie_consent_enabled">
                <v-text-field
                    v-model="settings.cookie_banner_title"
                    :label="$t('settings.advanced.cookieConsent.bannerTitle')"
                    prepend-inner-icon="mdi-format-title"
                    variant="outlined"
                    class="mb-4"
                    :hint="$t('settings.advanced.cookieConsent.bannerTitleHint')"
                    persistent-hint
                    placeholder="Cookie Consent"
                ></v-text-field>

                <v-textarea
                    v-model="settings.cookie_banner_text"
                    :label="$t('settings.advanced.cookieConsent.bannerText')"
                    prepend-inner-icon="mdi-text"
                    variant="outlined"
                    class="mb-4"
                    rows="3"
                    :hint="$t('settings.advanced.cookieConsent.bannerTextHint')"
                    persistent-hint
                ></v-textarea>

                <v-textarea
                    v-model="settings.cookie_preferences_text"
                    :label="$t('settings.advanced.cookieConsent.preferencesDescription')"
                    prepend-inner-icon="mdi-text"
                    variant="outlined"
                    class="mb-4"
                    rows="2"
                    :hint="$t('settings.advanced.cookieConsent.preferencesDescriptionHint')"
                    persistent-hint
                ></v-textarea>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-3">
                    <v-icon size="small" class="mr-1">mdi-toggle-switch</v-icon>
                    {{ $t('settings.advanced.cookieConsent.defaultCookieStates') }}
                </div>

                <v-alert type="warning" variant="tonal" class="mb-4" density="compact">
                    <div class="text-caption">
                        <strong>{{ $t('settings.advanced.cookieConsent.note') }}</strong> {{ $t('settings.advanced.cookieConsent.gdprNote') }}
                    </div>
                </v-alert>

                <v-switch
                    v-model="settings.cookie_analytics_default"
                    :label="$t('settings.advanced.cookieConsent.analyticsDefault')"
                    color="primary"
                    class="mb-2"
                    :hint="$t('settings.advanced.cookieConsent.analyticsDefaultHint')"
                    persistent-hint
                ></v-switch>

                <v-switch
                    v-model="settings.cookie_marketing_default"
                    :label="$t('settings.advanced.cookieConsent.marketingDefault')"
                    color="primary"
                    class="mb-2"
                    :hint="$t('settings.advanced.cookieConsent.marketingDefaultHint')"
                    persistent-hint
                ></v-switch>

                <v-switch
                    v-model="settings.cookie_functional_default"
                    :label="$t('settings.advanced.cookieConsent.functionalDefault')"
                    color="primary"
                    class="mb-4"
                    :hint="$t('settings.advanced.cookieConsent.functionalDefaultHint')"
                    persistent-hint
                ></v-switch>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-3">
                    <v-icon size="small" class="mr-1">mdi-link</v-icon>
                    {{ $t('settings.advanced.cookieConsent.relatedPages') }}
                </div>

                <v-text-field
                    v-model="settings.cookie_policy_url"
                    :label="$t('settings.advanced.cookieConsent.policyUrl')"
                    prepend-inner-icon="mdi-cookie"
                    variant="outlined"
                    class="mb-4"
                    :error-messages="errors.cookie_policy_url"
                    :hint="$t('settings.advanced.cookieConsent.policyUrlHint')"
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
            {{ $t('settings.saveSettings') }}
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
