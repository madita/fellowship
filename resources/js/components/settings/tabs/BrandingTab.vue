<template>
    <div>
        <!-- Logos & Icons -->
        <settings-card icon="mdi-image-multiple" :title="$t('settings.branding.logosIcons')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.branding.logoUsage') }}:</strong> {{ $t('settings.branding.logoUsageDesc') }}
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.branding.lightMode') }}:</strong> {{ $t('settings.branding.usesLightLogo') }}</li>
                        <li><strong>{{ $t('settings.branding.darkMode') }}:</strong> {{ $t('settings.branding.usesDarkLogo') }}</li>
                    </ul>
                </div>
            </v-alert>

            <v-row>
                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-white-balance-sunny</v-icon>
                        {{ $t('settings.branding.logoLight') }}
                    </div>
                    <image-upload
                        image-key="logo_light"
                        :label="$t('settings.branding.uploadLightLogo')"
                        :current-image="settings.logo_light"
                        :max-height="300"
                        :max-width="600"
                        placeholder-size="large"
                        image-class="bg-grey-lighten-4"
                        :hint="$t('settings.branding.lightLogoHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-weather-night</v-icon>
                        {{ $t('settings.branding.logoDark') }}
                    </div>
                    <image-upload
                        image-key="logo_dark"
                        :label="$t('settings.branding.uploadDarkLogo')"
                        :current-image="settings.logo_dark"
                        :max-height="300"
                        :max-width="600"
                        placeholder-size="large"
                        image-class="bg-grey-darken-4"
                        :hint="$t('settings.branding.darkLogoHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>
            </v-row>

            <v-divider class="my-4"></v-divider>

            <v-row>
                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-star-circle</v-icon>
                        {{ $t('settings.branding.favicon') }}
                    </div>
                    <image-upload
                        image-key="favicon"
                        :label="$t('settings.branding.uploadFavicon')"
                        accept="image/*,.ico"
                        icon="mdi-star-circle"
                        :current-image="settings.favicon"
                        :max-height="120"
                        :max-width="120"
                        placeholder-size="small"
                        :hint="$t('settings.branding.faviconHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-cellphone</v-icon>
                        {{ $t('settings.branding.appIcon') }}
                    </div>
                    <image-upload
                        image-key="app_icon"
                        :label="$t('settings.branding.uploadAppIcon')"
                        icon="mdi-cellphone"
                        accept="image/png,image/webp,image/svg+xml"
                        :current-image="settings.app_icon"
                        :max-height="200"
                        :max-width="200"
                        placeholder-size="small"
                        :hint="$t('settings.branding.appIconHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>
            </v-row>
        </settings-card>

        <!-- Additional Options -->
        <settings-card icon="mdi-cog-outline" :title="$t('settings.branding.additionalOptions')">
            <v-switch
                v-model="settings.login_branding_enabled"
                :label="$t('settings.branding.enableLoginBranding')"
                color="primary"
                :hint="settings.login_branding_enabled ? $t('settings.branding.loginBrandingEnabledHint') : $t('settings.branding.loginBrandingDisabledHint')"
                persistent-hint
            ></v-switch>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
        >
            {{ $t('settings.branding.saveSettings') }}
        </v-btn>
    </div>
</template>

<script setup>
import SettingsCard from '../SettingsCard.vue';
import ImageUpload from '../ImageUpload.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

const emit = defineEmits(['save', 'message']);

function handleImageUploaded({ key, path }) {
    props.settings[key] = path;
    emit('message', { text: `${key.replace(/_/g, ' ')} uploaded successfully`, type: 'success' });
}

function handleImageDeleted(key) {
    props.settings[key] = null;
    emit('message', { text: `${key.replace(/_/g, ' ')} deleted successfully`, type: 'success' });
}

function handleImageError(message) {
    emit('message', { text: message, type: 'error' });
}
</script>
