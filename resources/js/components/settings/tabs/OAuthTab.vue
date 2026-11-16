<template>
    <div>
        <v-alert type="info" variant="tonal" class="mb-4">
            <div class="text-body-2">
                {{ $t('settings.oauthSettings.description') }}
            </div>
        </v-alert>

        <!-- Google OAuth -->
        <settings-card icon="mdi-google" :title="$t('settings.oauthSettings.googleOAuth')">
            <v-switch
                v-model="settings.oauth_google_enabled"
                :label="$t('settings.oauthSettings.enableGoogleLogin')"
                color="primary"
                density="compact"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_google_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.oauthSettings.setup') }}:</strong> {{ $t('settings.oauthSettings.googleSetupText') }}
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-primary">Google Cloud Console</a><br>
                    <strong>{{ $t('settings.oauthSettings.redirectUri') }}:</strong> <code>{{ redirectUrl('google') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_google_enabled"
                v-model="settings.oauth_google_client_id"
                :label="$t('settings.oauthSettings.clientId')"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_google_client_id')"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_google_enabled"
                v-model="settings.oauth_google_client_secret"
                :label="$t('settings.oauthSettings.clientSecret')"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="getError('oauth_google_client_secret')"
            ></v-text-field>
        </settings-card>

        <!-- Discord OAuth -->
        <settings-card icon="mdi-discord" :title="$t('settings.oauthSettings.discordOAuth')">
            <v-switch
                v-model="settings.oauth_discord_enabled"
                :label="$t('settings.oauthSettings.enableDiscordLogin')"
                color="primary"
                density="compact"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_discord_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.oauthSettings.setup') }}:</strong> {{ $t('settings.oauthSettings.discordSetupText') }}
                    <a href="https://discord.com/developers/applications" target="_blank" class="text-primary">Discord Developer Portal</a><br>
                    <strong>{{ $t('settings.oauthSettings.redirectUri') }}:</strong> <code>{{ redirectUrl('discord') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_discord_enabled"
                v-model="settings.oauth_discord_client_id"
                :label="$t('settings.oauthSettings.clientId')"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_discord_client_id')"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_discord_enabled"
                v-model="settings.oauth_discord_client_secret"
                :label="$t('settings.oauthSettings.clientSecret')"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="getError('oauth_discord_client_secret')"
            ></v-text-field>
        </settings-card>

        <!-- GitHub OAuth -->
        <settings-card icon="mdi-github" :title="$t('settings.oauthSettings.githubOAuth')">
            <v-switch
                v-model="settings.oauth_github_enabled"
                :label="$t('settings.oauthSettings.enableGithubLogin')"
                color="primary"
                density="compact"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_github_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.oauthSettings.setup') }}:</strong> {{ $t('settings.oauthSettings.githubSetupText') }}
                    <a href="https://github.com/settings/developers" target="_blank" class="text-primary">GitHub Developer Settings</a><br>
                    <strong>{{ $t('settings.oauthSettings.callbackUrl') }}:</strong> <code>{{ redirectUrl('github') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_github_enabled"
                v-model="settings.oauth_github_client_id"
                :label="$t('settings.oauthSettings.clientId')"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_github_client_id')"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_github_enabled"
                v-model="settings.oauth_github_client_secret"
                :label="$t('settings.oauthSettings.clientSecret')"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="getError('oauth_github_client_secret')"
            ></v-text-field>
        </settings-card>

        <!-- Facebook OAuth -->
        <settings-card icon="mdi-facebook" :title="$t('settings.oauthSettings.facebookOAuth')">
            <v-switch
                v-model="settings.oauth_facebook_enabled"
                :label="$t('settings.oauthSettings.enableFacebookLogin')"
                color="primary"
                density="compact"
                class="mb-3"
            ></v-switch>

            <v-alert v-if="settings.oauth_facebook_enabled" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.oauthSettings.setup') }}:</strong> {{ $t('settings.oauthSettings.facebookSetupText') }}
                    <a href="https://developers.facebook.com/apps/" target="_blank" class="text-primary">Facebook Developers</a><br>
                    <strong>{{ $t('settings.oauthSettings.redirectUri') }}:</strong> <code>{{ redirectUrl('facebook') }}</code>
                </div>
            </v-alert>

            <v-text-field
                v-if="settings.oauth_facebook_enabled"
                v-model="settings.oauth_facebook_client_id"
                :label="$t('settings.oauthSettings.appId')"
                variant="outlined"
                density="compact"
                prepend-inner-icon="mdi-key"
                :error-messages="getError('oauth_facebook_client_id')"
                class="mb-3"
            ></v-text-field>

            <v-text-field
                v-if="settings.oauth_facebook_enabled"
                v-model="settings.oauth_facebook_client_secret"
                :label="$t('settings.oauthSettings.appSecret')"
                variant="outlined"
                density="compact"
                type="password"
                prepend-inner-icon="mdi-lock"
                :error-messages="getError('oauth_facebook_client_secret')"
            ></v-text-field>
        </settings-card>

        <!-- General OAuth Settings -->
        <settings-card icon="mdi-cog" :title="$t('settings.oauthSettings.generalSettings')">
            <v-checkbox
                v-model="settings.oauth_allow_registration"
                :label="$t('settings.oauthSettings.allowRegistration')"
                :hint="$t('settings.oauthSettings.allowRegistrationHint')"
                persistent-hint
                density="compact"
                class="mb-3"
            ></v-checkbox>

            <v-checkbox
                v-model="settings.oauth_auto_verify_email"
                :label="$t('settings.oauthSettings.autoVerifyEmail')"
                :hint="$t('settings.oauthSettings.autoVerifyEmailHint')"
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
        >
            {{ $t('settings.oauthSettings.saveSettings') }}
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
