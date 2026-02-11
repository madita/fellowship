<template>
    <settings-page-layout
        :title="$t('settings.advanced.legal.title')"
        :description="$t('settings.advanced.legal.description')"
        icon="mdi-scale-balance"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-gavel" :title="$t('settings.advanced.legal.cardTitle')">
            <v-text-field
                v-model="settings.privacy_policy_url"
                :label="$t('settings.advanced.legal.privacyPolicyUrl')"
                prepend-inner-icon="mdi-shield-check"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.privacy_policy_url"
                :hint="$t('settings.advanced.legal.urlHint')"
                persistent-hint
                placeholder="/privacy-policy"
            ></v-text-field>

            <v-text-field
                v-model="settings.terms_conditions_url"
                :label="$t('settings.advanced.legal.termsUrl')"
                prepend-inner-icon="mdi-file-document"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.terms_conditions_url"
                :hint="$t('settings.advanced.legal.urlHint')"
                persistent-hint
                placeholder="/terms"
            ></v-text-field>

            <v-text-field
                v-model="settings.cookie_policy_url"
                :label="$t('settings.advanced.legal.cookiePolicyUrl')"
                prepend-inner-icon="mdi-cookie"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cookie_policy_url"
                :hint="$t('settings.advanced.legal.urlHint')"
                persistent-hint
                placeholder="/cookies"
            ></v-text-field>

            <v-switch
                v-model="settings.right_to_be_forgotten_enabled"
                :label="$t('settings.advanced.legal.rightToBeForgotten')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.legal.rightToBeForgottenHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.age_confirmation_required"
                :label="$t('settings.advanced.legal.ageConfirmation')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.legal.ageConfirmationHint')"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-if="settings.age_confirmation_required"
                v-model.number="settings.age_minimum"
                :label="$t('settings.advanced.legal.minimumAge')"
                prepend-inner-icon="mdi-numeric"
                variant="outlined"
                type="number"
                :error-messages="errors.age_minimum"
                :hint="$t('settings.advanced.legal.minimumAgeHint')"
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
