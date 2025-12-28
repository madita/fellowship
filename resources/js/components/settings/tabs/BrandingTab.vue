<template>
    <div>
        <!-- Logos & Icons -->
        <settings-card icon="mdi-image-multiple" title="Logos & Icons">
            <v-alert type="info" variant="tonal" class="mb-4">
                <strong>Note:</strong> The Logo (Light Theme) is used on the landing page header. Upload both light and dark versions for different themes.
            </v-alert>
            <v-row>
                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">Logo (Light Theme)</div>
                    <image-upload
                        image-key="logo_light"
                        label="Upload Light Logo"
                        :current-image="settings.logo_light"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">Logo (Dark Theme)</div>
                    <image-upload
                        image-key="logo_dark"
                        label="Upload Dark Logo"
                        :current-image="settings.logo_dark"
                        image-class="pa-2 bg-grey-darken-4 rounded"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">Favicon</div>
                    <image-upload
                        image-key="favicon"
                        label="Upload Favicon"
                        accept="image/*,.ico"
                        icon="mdi-star-circle"
                        :current-image="settings.favicon"
                        :max-height="64"
                        :max-width="64"
                        placeholder-size="small"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <div class="text-subtitle-2 mb-2">App Icon</div>
                    <image-upload
                        image-key="app_icon"
                        label="Upload App Icon"
                        icon="mdi-cellphone"
                        :current-image="settings.app_icon"
                        :max-height="64"
                        :max-width="64"
                        placeholder-size="small"
                        @uploaded="handleImageUploaded"
                        @deleted="handleImageDeleted"
                        @error="handleImageError"
                    />
                </v-col>
            </v-row>
        </settings-card>

        <!-- Colors & Theme -->
        <settings-card icon="mdi-palette" title="Colors & Theme">
            <v-row>
                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="settings.primary_color"
                        label="Primary Color"
                        prepend-inner-icon="mdi-palette"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.primary_color"
                        hint="Main brand color used throughout the application"
                        persistent-hint
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="settings.secondary_color"
                        label="Secondary Color"
                        prepend-inner-icon="mdi-palette-outline"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.secondary_color"
                        hint="Secondary accent color"
                        persistent-hint
                    ></v-text-field>
                </v-col>

                <v-col cols="12">
                    <v-select
                        v-model="settings.theme_mode"
                        label="Theme Mode"
                        :items="themeModes"
                        item-title="label"
                        item-value="value"
                        prepend-inner-icon="mdi-theme-light-dark"
                        variant="outlined"
                        :error-messages="errors.theme_mode"
                        hint="Default theme appearance for users"
                        persistent-hint
                    ></v-select>
                </v-col>
            </v-row>
        </settings-card>

        <!-- Typography & Custom Styles -->
        <settings-card icon="mdi-format-font" title="Typography & Custom Styles">
            <v-select
                v-model="settings.font_family"
                label="Font Family"
                :items="fontFamilies"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-format-font"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.font_family"
            ></v-select>

            <v-textarea
                v-model="settings.custom_css"
                label="Custom CSS"
                prepend-inner-icon="mdi-language-css3"
                variant="outlined"
                rows="6"
                :error-messages="errors.custom_css"
                hint="Add custom CSS styles (advanced users only)"
                persistent-hint
                placeholder=".my-custom-class { color: red; }"
            ></v-textarea>
        </settings-card>

        <!-- Additional Options -->
        <settings-card icon="mdi-cog-outline" title="Additional Options">
            <v-switch
                v-model="settings.login_branding_enabled"
                label="Enable Login Page Branding"
                color="primary"
                :hint="settings.login_branding_enabled ? 'Show custom branding on login page' : 'Use default login page appearance'"
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
            Save Settings
        </v-btn>
    </div>
</template>

<script setup>
import SettingsCard from '../SettingsCard.vue';
import ImageUpload from '../ImageUpload.vue';
import { themeModes, fontFamilies } from '../../../composables/settingsConstants';

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
