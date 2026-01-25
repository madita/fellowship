<template>
    <div>
        <v-alert type="info" variant="tonal" class="mb-4">
            <div class="text-body-2">
                Configure OAuth providers to allow users to sign in with their social media accounts.
                Users can link multiple providers to a single account.
            </div>
        </v-alert>

        <!-- Google OAuth -->
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
                    <strong>Redirect URI:</strong> <code>{{ redirectUrl('google') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_google_enabled"
                v-model="settings.oauth_google_client_id"
                label="Client ID"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_google_client_id')"
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
                :error-messages="getError('oauth_google_client_secret')"
            ></v-text-field>
        </settings-card>

        <!-- Discord OAuth -->
        <settings-card icon="mdi-discord" title="Discord OAuth">
            <v-switch
                v-model="settings.oauth_discord_enabled"
                label="Enable Discord Login"
                color="primary"
                density="compact"
                @update:model-value="markChanged"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_discord_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Setup:</strong> Create application at
                    <a href="https://discord.com/developers/applications" target="_blank" class="text-primary">Discord Developer Portal</a><br>
                    <strong>Redirect URI:</strong> <code>{{ redirectUrl('discord') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_discord_enabled"
                v-model="settings.oauth_discord_client_id"
                label="Client ID"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_discord_client_id')"
                @input="markChanged"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_discord_enabled"
                v-model="settings.oauth_discord_client_secret"
                label="Client Secret"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="getError('oauth_discord_client_secret')"
                @input="markChanged"
            ></v-text-field>
        </settings-card>

        <!-- GitHub OAuth -->
        <settings-card icon="mdi-github" title="GitHub OAuth">
            <v-switch
                v-model="settings.oauth_github_enabled"
                label="Enable GitHub Login"
                color="primary"
                density="compact"
                @update:model-value="markChanged"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_github_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Setup:</strong> Create OAuth App at
                    <a href="https://github.com/settings/developers" target="_blank" class="text-primary">GitHub Developer Settings</a><br>
                    <strong>Callback URL:</strong> <code>{{ redirectUrl('github') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_github_enabled"
                v-model="settings.oauth_github_client_id"
                label="Client ID"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_github_client_id')"
                @input="markChanged"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_github_enabled"
                v-model="settings.oauth_github_client_secret"
                label="Client Secret"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="getError('oauth_github_client_secret')"
                @input="markChanged"
            ></v-text-field>
        </settings-card>

        <!-- Facebook OAuth -->
        <settings-card icon="mdi-facebook" title="Facebook OAuth">
            <v-switch
                v-model="settings.oauth_facebook_enabled"
                label="Enable Facebook Login"
                color="primary"
                density="compact"
                @update:model-value="markChanged"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_facebook_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Setup:</strong> Create app at
                    <a href="https://developers.facebook.com/apps/" target="_blank" class="text-primary">Facebook Developers</a><br>
                    <strong>Redirect URI:</strong> <code>{{ redirectUrl('facebook') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_facebook_enabled"
                v-model="settings.oauth_facebook_client_id"
                label="App ID"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_facebook_client_id')"
                @input="markChanged"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_facebook_enabled"
                v-model="settings.oauth_facebook_client_secret"
                label="App Secret"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="getError('oauth_facebook_client_secret')"
                @input="markChanged"
            ></v-text-field>
        </settings-card>

        <!-- General OAuth Settings -->
        <settings-card icon="mdi-cog" title="General OAuth Settings">
            <v-checkbox
                v-model="settings.oauth_allow_registration"
                label="Allow new user registration via OAuth"
                hint="When disabled, only existing users can link OAuth accounts"
                persistent-hint
                density="compact"
                @update:model-value="markChanged"
                class="mb-3"
            ></v-checkbox>

            <v-checkbox
                v-model="settings.oauth_auto_verify_email"
                label="Automatically verify email from OAuth providers"
                hint="Trust OAuth providers' email verification status"
                persistent-hint
                density="compact"
                @update:model-value="markChanged"
            ></v-checkbox>
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
    settings: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['save']);

const redirectUrl = (provider) => {
    const siteUrl = props.settings.site_url || window.location.origin;
    return `${siteUrl}/auth/${provider}/callback`;
};

const getError = (field) => {
    return props.errors[field] || [];
};
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
