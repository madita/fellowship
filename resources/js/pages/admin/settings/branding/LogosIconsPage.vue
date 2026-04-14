<template>
    <settings-page-layout
        :title="$t('settings.branding.logosIcons.title')"
        :description="$t('settings.branding.logosIcons.description')"
        icon="mdi-image-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'branding' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-image-multiple" :title="$t('settings.branding.logosIcons.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.branding.logosIcons.logoUsage') }}</strong> {{ $t('settings.branding.logosIcons.logoUsageDescription') }}
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.branding.logosIcons.lightMode') }}</strong> {{ $t('settings.branding.logosIcons.lightModeDescription') }}</li>
                        <li><strong>{{ $t('settings.branding.logosIcons.darkMode') }}</strong> {{ $t('settings.branding.logosIcons.darkModeDescription') }}</li>
                    </ul>
                </div>
            </v-alert>

            <v-row>
                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-white-balance-sunny</v-icon>
                        {{ $t('settings.branding.logosIcons.logoLight') }}
                    </div>
                    <image-upload
                        image-key="logo_light"
                        :label="$t('settings.branding.logosIcons.uploadLightLogo')"
                        :current-image="settings.logo_light"
                        :max-height="300"
                        :max-width="600"
                        placeholder-size="large"
                        image-class="bg-grey-lighten-4"
                        :hint="$t('settings.branding.logosIcons.lightLogoHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-weather-night</v-icon>
                        {{ $t('settings.branding.logosIcons.logoDark') }}
                    </div>
                    <image-upload
                        image-key="logo_dark"
                        :label="$t('settings.branding.logosIcons.uploadDarkLogo')"
                        :current-image="settings.logo_dark"
                        :max-height="300"
                        :max-width="600"
                        placeholder-size="large"
                        image-class="bg-grey-darken-4"
                        :hint="$t('settings.branding.logosIcons.darkLogoHint')"
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
                        {{ $t('settings.branding.logosIcons.favicon') }}
                    </div>
                    <image-upload
                        image-key="favicon"
                        :label="$t('settings.branding.logosIcons.uploadFavicon')"
                        accept="image/*,.ico"
                        icon="mdi-star-circle"
                        :current-image="settings.favicon"
                        :max-height="120"
                        :max-width="120"
                        placeholder-size="small"
                        :hint="$t('settings.branding.logosIcons.faviconHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-cellphone</v-icon>
                        {{ $t('settings.branding.logosIcons.appIcon') }}
                    </div>
                    <image-upload
                        image-key="app_icon"
                        :label="$t('settings.branding.logosIcons.uploadAppIcon')"
                        icon="mdi-cellphone"
                        accept="image/png,image/webp,image/svg+xml"
                        :current-image="settings.app_icon"
                        :max-height="200"
                        :max-width="200"
                        placeholder-size="small"
                        :hint="$t('settings.branding.logosIcons.appIconHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>
            </v-row>
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
import { useI18n } from 'vue-i18n';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import ImageUpload from '@/components/settings/ImageUpload.vue';

const { t } = useI18n();

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const emit = defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');

function handleImageUploaded({ key, path }) {
    props.settings[key] = path;
    emit('message', { text: t('settings.branding.logosIcons.uploadSuccess'), type: 'success' });
}

function handleImageDeleted(key) {
    props.settings[key] = null;
    emit('message', { text: t('settings.branding.logosIcons.deleteSuccess'), type: 'success' });
}

function handleImageError(errorMessage) {
    emit('message', { text: errorMessage, type: 'error' });
}
</script>
