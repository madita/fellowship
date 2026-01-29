<template>
    <settings-page-layout
        title="Background Images"
        description="Configure background images for light and dark themes"
        icon="mdi-image-multiple-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'theme' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-image-area" title="Background Images">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    Upload background tiles or images for light and dark themes.
                    Choose between full cover or repeating tile pattern.
                </div>
            </v-alert>

            <v-row>
                <v-col cols="12">
                    <v-select
                        v-model="settings.background_style"
                        label="Background Style"
                        :items="backgroundStyles"
                        item-title="label"
                        item-value="value"
                        prepend-inner-icon="mdi-image-size-select-large"
                        variant="outlined"
                        hint="Choose how background images should be displayed"
                        persistent-hint
                    ></v-select>
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-white-balance-sunny</v-icon>
                        Light Theme Background
                    </div>
                    <image-upload
                        image-key="background_light"
                        label="Upload Light Background"
                        :current-image="settings.background_light"
                        :max-height="1080"
                        :max-width="1920"
                        placeholder-size="large"
                        image-class="bg-grey-lighten-4"
                        hint="Background image for light theme"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-weather-night</v-icon>
                        Dark Theme Background
                    </div>
                    <image-upload
                        image-key="background_dark"
                        label="Upload Dark Background"
                        :current-image="settings.background_dark"
                        :max-height="1080"
                        :max-width="1920"
                        placeholder-size="large"
                        image-class="bg-grey-darken-4"
                        hint="Background image for dark theme"
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
            Save Settings
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import ImageUpload from '@/components/settings/ImageUpload.vue';

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

const backgroundStyles = [
    { label: 'Full Cover (Stretch to fit)', value: 'cover' },
    { label: 'Repeating Tile (Pattern)', value: 'repeat' },
];

function handleImageUploaded({ key, path }) {
    props.settings[key] = path;
    emit('message', { text: `${key.replace(/_/g, ' ')} uploaded successfully`, type: 'success' });
}

function handleImageDeleted(key) {
    props.settings[key] = null;
    emit('message', { text: `${key.replace(/_/g, ' ')} deleted successfully`, type: 'success' });
}

function handleImageError(errorMessage) {
    emit('message', { text: errorMessage, type: 'error' });
}
</script>
