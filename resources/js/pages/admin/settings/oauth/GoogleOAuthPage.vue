<template>
    <settings-page-layout
        title="Google OAuth"
        description="Configure Google sign-in for your application"
        icon="mdi-google"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'oauth' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-google" title="Google OAuth">
            <v-switch
                v-model="settings.oauth_google_enabled"
                label="Enable Google Login"
                color="primary"
                density="compact"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_google_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Setup:</strong> Create OAuth credentials at
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-primary">Google Cloud Console</a><br>
                    <strong>Redirect URI:</strong> <code>{{ redirectUrl }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_google_enabled"
                v-model="settings.oauth_google_client_id"
                label="Client ID"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="errors.oauth_google_client_id"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_google_enabled"
                v-model="settings.oauth_google_client_secret"
                label="Client Secret"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="errors.oauth_google_client_secret"
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
import { ref, computed } from 'vue';
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

const redirectUrl = computed(() => {
    const siteUrl = props.settings.site_url || window.location.origin;
    return `${siteUrl}/auth/google/callback`;
});
</script>

<style scoped>
code {
    background-color: rgba(var(--v-theme-on-surface), 0.05);
    border-radius: 4px;
    padding: 2px 6px;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
}
</style>
