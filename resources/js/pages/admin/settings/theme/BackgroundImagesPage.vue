<template>
    <settings-page-layout
        :title="$t('settings.backgroundImages.title')"
        :description="$t('settings.backgroundImages.description')"
        icon="mdi-image-multiple-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'theme' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-image-area" :title="$t('settings.backgroundImages.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.backgroundImages.infoText') }}
                </div>
            </v-alert>

            <v-row>
                <v-col cols="12">
                    <v-select
                        v-model="settings.background_style"
                        :label="$t('settings.backgroundImages.backgroundStyle')"
                        :items="backgroundStyles"
                        item-title="label"
                        item-value="value"
                        prepend-inner-icon="mdi-image-size-select-large"
                        variant="outlined"
                        :hint="$t('settings.backgroundImages.backgroundStyleHint')"
                        persistent-hint
                    ></v-select>
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-white-balance-sunny</v-icon>
                        {{ $t('settings.backgroundImages.lightThemeBackground') }}
                    </div>
                    <image-upload
                        image-key="background_light"
                        :label="$t('settings.backgroundImages.uploadLightBackground')"
                        :current-image="settings.background_light"
                        :max-height="1080"
                        :max-width="1920"
                        placeholder-size="large"
                        image-class="bg-grey-lighten-4"
                        :hint="$t('settings.backgroundImages.lightBackgroundHint')"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-weather-night</v-icon>
                        {{ $t('settings.backgroundImages.darkThemeBackground') }}
                    </div>
                    <image-upload
                        image-key="background_dark"
                        :label="$t('settings.backgroundImages.uploadDarkBackground')"
                        :current-image="settings.background_dark"
                        :max-height="1080"
                        :max-width="1920"
                        placeholder-size="large"
                        image-class="bg-grey-darken-4"
                        :hint="$t('settings.backgroundImages.darkBackgroundHint')"
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
import { ref, computed } from 'vue';
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

const backgroundStyles = computed(() => [
    { label: t('settings.backgroundImages.styleCover'), value: 'cover' },
    { label: t('settings.backgroundImages.styleRepeat'), value: 'repeat' },
]);

function handleImageUploaded({ key, path }) {
    props.settings[key] = path;
    emit('message', { text: t('settings.backgroundImages.uploadSuccess'), type: 'success' });
}

function handleImageDeleted(key) {
    props.settings[key] = null;
    emit('message', { text: t('settings.backgroundImages.deleteSuccess'), type: 'success' });
}

function handleImageError(errorMessage) {
    emit('message', { text: errorMessage, type: 'error' });
}
</script>
