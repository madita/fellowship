<template>
    <settings-page-layout
        :title="$t('settings.advanced.newsletter.title')"
        :description="$t('settings.advanced.newsletter.description')"
        icon="mdi-email-newsletter"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-email-newsletter" :title="$t('settings.advanced.newsletter.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.advanced.newsletter.infoText') }}
                </div>
            </v-alert>

            <v-switch
                v-model="settings.newsletter_enabled"
                :label="$t('settings.advanced.newsletter.enableNewsletter')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.newsletter.enableNewsletterHint')"
                persistent-hint
            ></v-switch>

            <v-select
                v-if="settings.newsletter_enabled"
                v-model="settings.newsletter_provider"
                :label="$t('settings.advanced.newsletter.provider')"
                :items="newsletterProviders"
                prepend-inner-icon="mdi-email-variant"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_provider"
                :hint="$t('settings.advanced.newsletter.providerHint')"
                persistent-hint
            ></v-select>

            <v-text-field
                v-if="settings.newsletter_enabled && settings.newsletter_provider"
                v-model="settings.newsletter_api_key"
                :label="$t('settings.advanced.newsletter.apiKey')"
                prepend-inner-icon="mdi-key"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_api_key"
                :hint="$t('settings.advanced.newsletter.apiKeyHint')"
                persistent-hint
                type="password"
            ></v-text-field>

            <v-text-field
                v-if="settings.newsletter_enabled && settings.newsletter_provider"
                v-model="settings.newsletter_list_id"
                :label="$t('settings.advanced.newsletter.listId')"
                prepend-inner-icon="mdi-format-list-bulleted"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_list_id"
                :hint="$t('settings.advanced.newsletter.listIdHint')"
                persistent-hint
            ></v-text-field>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'mailchimp'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Mailchimp Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to your Mailchimp account > Profile > Extras > API keys</li>
                        <li>Create a new API key and paste it above</li>
                        <li>Go to Audience > Settings > Audience name and defaults</li>
                        <li>Copy the Audience ID and paste it above</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'mailerlite'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>MailerLite Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to your MailerLite account > Integrations > Developer API</li>
                        <li>Generate a new API key and paste it above</li>
                        <li>Go to Subscribers > Groups</li>
                        <li>Copy the Group ID and paste it above</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'sendgrid'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>SendGrid Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to Settings > API Keys > Create API Key</li>
                        <li>Give it full access to Marketing permissions</li>
                        <li>Copy the API key and paste it above</li>
                        <li>Go to Marketing > Contacts > Lists</li>
                        <li>Copy the List ID and paste it above</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'convertkit'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>ConvertKit Setup:</strong>
                    <ol class="mt-1 ml-4">
                        <li>Go to Settings > Advanced > API Key</li>
                        <li>Copy your API Secret (not API Key) and paste it above</li>
                        <li>Go to Grow > Landing Pages & Forms</li>
                        <li>Find your form and copy the Form ID from the URL</li>
                    </ol>
                </div>
            </v-alert>
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
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const { t } = useI18n();

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

const newsletterProviders = computed(() => [
    { title: 'Mailchimp', value: 'mailchimp' },
    { title: 'MailerLite', value: 'mailerlite' },
    { title: 'SendGrid', value: 'sendgrid' },
    { title: 'ConvertKit', value: 'convertkit' },
    { title: 'ActiveCampaign', value: 'activecampaign' },
    { title: 'Sendinblue (Brevo)', value: 'sendinblue' },
    { title: t('settings.advanced.newsletter.customApi'), value: 'custom' },
]);
</script>
