<template>
    <settings-page-layout
        :title="$t('settings.oauth.general.title')"
        :description="$t('settings.oauth.general.description')"
        icon="mdi-account-cog-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'oauth' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-cog" :title="$t('settings.oauth.general.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4">
                <div class="text-body-2">
                    {{ $t('settings.oauth.general.infoText') }}
                </div>
            </v-alert>

            <v-checkbox
                v-model="settings.oauth_allow_registration"
                :label="$t('settings.oauth.general.allowRegistration')"
                :hint="$t('settings.oauth.general.allowRegistrationHint')"
                persistent-hint
                density="compact"
                class="mb-3"
            ></v-checkbox>

            <v-checkbox
                v-model="settings.oauth_auto_verify_email"
                :label="$t('settings.oauth.general.autoVerifyEmail')"
                :hint="$t('settings.oauth.general.autoVerifyEmailHint')"
                persistent-hint
                density="compact"
            ></v-checkbox>
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
