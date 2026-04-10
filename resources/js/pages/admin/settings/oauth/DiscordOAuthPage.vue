<template>
    <settings-page-layout
        :title="$t('settings.oauth.discord.title')"
        :description="$t('settings.oauth.discord.description')"
        icon="mdi-discord"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'oauth' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-discord" :title="$t('settings.oauth.discord.cardTitle')">
            <v-switch
                v-model="settings.oauth_discord_enabled"
                :label="$t('settings.oauth.discord.enableLogin')"
                color="primary"
                density="compact"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_discord_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.oauth.common.setup') }}</strong> {{ $t('settings.oauth.discord.setupText') }}
                    <a href="https://discord.com/developers/applications" target="_blank" class="text-primary">Discord Developer Portal</a><br>
                    <strong>{{ $t('settings.oauth.common.redirectUri') }}</strong> <code>{{ redirectUrl }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_discord_enabled"
                v-model="settings.oauth_discord_client_id"
                :label="$t('settings.oauth.common.clientId')"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="errors.oauth_discord_client_id"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_discord_enabled"
                v-model="settings.oauth_discord_client_secret"
                :label="$t('settings.oauth.common.clientSecret')"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="errors.oauth_discord_client_secret"
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
    return `${siteUrl}/auth/discord/callback`;
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
