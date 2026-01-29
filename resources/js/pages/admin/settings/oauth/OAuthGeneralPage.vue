<template>
    <settings-page-layout
        title="OAuth Settings"
        description="Configure general OAuth registration and verification settings"
        icon="mdi-account-cog-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'oauth' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-cog" title="General OAuth Settings">
            <v-alert type="info" variant="tonal" class="mb-4">
                <div class="text-body-2">
                    These settings apply to all OAuth providers and control how users can register and authenticate.
                </div>
            </v-alert>

            <v-checkbox
                v-model="settings.oauth_allow_registration"
                label="Allow new user registration via OAuth"
                hint="When disabled, only existing users can link OAuth accounts"
                persistent-hint
                density="compact"
                class="mb-3"
            ></v-checkbox>

            <v-checkbox
                v-model="settings.oauth_auto_verify_email"
                label="Automatically verify email from OAuth providers"
                hint="Trust OAuth providers' email verification status"
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
