<template>
    <settings-page-layout
        title="Legal & Compliance"
        description="Configure privacy, terms, and GDPR settings"
        icon="mdi-scale-balance"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
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
