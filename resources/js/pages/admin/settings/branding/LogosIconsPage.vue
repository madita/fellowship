<template>
    <settings-page-layout
        title="Logos & Icons"
        description="Configure your site's logos, favicon, and app icon"
        icon="mdi-image-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'branding' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-image-multiple" title="Logos & Icons">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>Logo Usage:</strong> The logo automatically switches based on theme.
                    <ul class="mt-1 ml-4">
                        <li><strong>Light Mode:</strong> Uses Logo (Light Theme)</li>
                        <li><strong>Dark Mode:</strong> Uses Logo (Dark Theme) if available, otherwise Light Logo</li>
                    </ul>
                </div>
            </v-alert>

            <v-row>
                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-white-balance-sunny</v-icon>
                        Logo (Light Theme)
                    </div>
                    <image-upload
                        image-key="logo_light"
                        label="Upload Light Logo"
                        :current-image="settings.logo_light"
                        :max-height="300"
                        :max-width="600"
                        placeholder-size="large"
                        image-class="bg-grey-lighten-4"
                        hint="Displayed when light theme is active"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-weather-night</v-icon>
                        Logo (Dark Theme)
                    </div>
                    <image-upload
                        image-key="logo_dark"
                        label="Upload Dark Logo"
                        :current-image="settings.logo_dark"
                        :max-height="300"
                        :max-width="600"
                        placeholder-size="large"
                        image-class="bg-grey-darken-4"
                        hint="Displayed when dark theme is active"
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
                        Favicon
                    </div>
                    <image-upload
                        image-key="favicon"
                        label="Upload Favicon"
                        accept="image/*,.ico"
                        icon="mdi-star-circle"
                        :current-image="settings.favicon"
                        :max-height="120"
                        :max-width="120"
                        placeholder-size="small"
                        hint="Browser tab icon (16x16 or 32x32 recommended)"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">
                        <v-icon size="small" class="mr-1">mdi-cellphone</v-icon>
                        App Icon (PWA)
                    </div>
                    <image-upload
                        image-key="app_icon"
                        label="Upload App Icon"
                        icon="mdi-cellphone"
                        accept="image/png,image/webp,image/svg+xml"
                        :current-image="settings.app_icon"
                        :max-height="200"
                        :max-width="200"
                        placeholder-size="small"
                        hint="PWA icon: PNG/WebP/SVG only, 192x192+ pixels, square shape required"
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
