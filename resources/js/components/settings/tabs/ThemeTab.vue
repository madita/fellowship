<template>
    <div>
        <!-- Theme Mode -->
        <settings-card icon="mdi-theme-light-dark" title="Theme Mode">
            <v-select
                v-model="settings.theme_mode"
                label="Default Theme Mode"
                :items="themeModes"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-theme-light-dark"
                variant="outlined"
                :error-messages="errors.theme_mode"
                hint="Default theme appearance for users"
                persistent-hint
            ></v-select>
        </settings-card>

        <!-- Reset Colors Button -->
        <v-alert type="info" variant="tonal" class="mb-4">
            <div class="d-flex align-center justify-space-between">
                <div>
                    <div class="text-subtitle-2 font-weight-bold mb-1">Reset Theme Colors</div>
                    <div class="text-caption">Restore all light and dark theme colors to their default values</div>
                </div>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-restore"
                    @click="resetToDefaults"
                >
                    Reset to Defaults
                </v-btn>
            </div>
        </v-alert>

        <!-- Light Theme Colors -->
        <settings-card icon="mdi-white-balance-sunny" title="Light Theme Colors">
            <v-row>
                <v-col cols="12" md="6">
                    <v-slider
                        v-model="settings.background_opacity_light"
                        label="Background Opacity"
                        prepend-icon="mdi-opacity"
                        :min="0"
                        :max="100"
                        :step="5"
                        thumb-label
                        hint="Higher values = more opaque (less background image visible)"
                        persistent-hint
                    >
                        <template v-slot:append>
                            <v-text-field
                                v-model="settings.background_opacity_light"
                                type="number"
                                style="width: 80px"
                                density="compact"
                                hide-details
                                suffix="%"
                                variant="outlined"
                            ></v-text-field>
                        </template>
                    </v-slider>
                </v-col>

                <v-col cols="12" md="6">
                    <v-slider
                        v-model="settings.surface_opacity_light"
                        label="Surface Opacity"
                        prepend-icon="mdi-texture-box"
                        :min="0"
                        :max="100"
                        :step="5"
                        thumb-label
                        hint="Controls opacity of cards, sheets, and panels"
                        persistent-hint
                    >
                        <template v-slot:append>
                            <v-text-field
                                v-model="settings.surface_opacity_light"
                                type="number"
                                style="width: 80px"
                                density="compact"
                                hide-details
                                suffix="%"
                                variant="outlined"
                            ></v-text-field>
                        </template>
                    </v-slider>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.primary_color_light"
                        label="Primary Color"
                        prepend-inner-icon="mdi-palette"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.primary_color_light"
                        hint="Main brand color"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.secondary_color_light"
                        label="Secondary Color"
                        prepend-inner-icon="mdi-palette-outline"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.secondary_color_light"
                        hint="Secondary accent"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.accent_color_light"
                        label="Accent Color"
                        prepend-inner-icon="mdi-invert-colors"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.accent_color_light"
                        hint="Accent highlights"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.background_color_light"
                        label="Background Color"
                        prepend-inner-icon="mdi-format-color-fill"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.background_color_light"
                        hint="Main background"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.surface_color_light"
                        label="Surface Color"
                        prepend-inner-icon="mdi-texture-box"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.surface_color_light"
                        hint="Cards, sheets"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.error_color_light"
                        label="Error Color"
                        prepend-inner-icon="mdi-alert-circle"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.error_color_light"
                        hint="Error states"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.warning_color_light"
                        label="Warning Color"
                        prepend-inner-icon="mdi-alert"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.warning_color_light"
                        hint="Warning states"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.info_color_light"
                        label="Info Color"
                        prepend-inner-icon="mdi-information"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.info_color_light"
                        hint="Info states"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.success_color_light"
                        label="Success Color"
                        prepend-inner-icon="mdi-check-circle"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.success_color_light"
                        hint="Success states"
                    ></v-text-field>
                </v-col>
            </v-row>
        </settings-card>

        <!-- Dark Theme Colors -->
        <settings-card icon="mdi-weather-night" title="Dark Theme Colors">
            <v-row>
                <v-col cols="12" md="6">
                    <v-slider
                        v-model="settings.background_opacity_dark"
                        label="Background Opacity"
                        prepend-icon="mdi-opacity"
                        :min="0"
                        :max="100"
                        :step="5"
                        thumb-label
                        hint="Higher values = more opaque (less background image visible)"
                        persistent-hint
                    >
                        <template v-slot:append>
                            <v-text-field
                                v-model="settings.background_opacity_dark"
                                type="number"
                                style="width: 80px"
                                density="compact"
                                hide-details
                                suffix="%"
                                variant="outlined"
                            ></v-text-field>
                        </template>
                    </v-slider>
                </v-col>

                <v-col cols="12" md="6">
                    <v-slider
                        v-model="settings.surface_opacity_dark"
                        label="Surface Opacity"
                        prepend-icon="mdi-texture-box"
                        :min="0"
                        :max="100"
                        :step="5"
                        thumb-label
                        hint="Controls opacity of cards, sheets, and panels"
                        persistent-hint
                    >
                        <template v-slot:append>
                            <v-text-field
                                v-model="settings.surface_opacity_dark"
                                type="number"
                                style="width: 80px"
                                density="compact"
                                hide-details
                                suffix="%"
                                variant="outlined"
                            ></v-text-field>
                        </template>
                    </v-slider>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.primary_color_dark"
                        label="Primary Color"
                        prepend-inner-icon="mdi-palette"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.primary_color_dark"
                        hint="Main brand color"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.secondary_color_dark"
                        label="Secondary Color"
                        prepend-inner-icon="mdi-palette-outline"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.secondary_color_dark"
                        hint="Secondary accent"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.accent_color_dark"
                        label="Accent Color"
                        prepend-inner-icon="mdi-invert-colors"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.accent_color_dark"
                        hint="Accent highlights"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.background_color_dark"
                        label="Background Color"
                        prepend-inner-icon="mdi-format-color-fill"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.background_color_dark"
                        hint="Main background"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.surface_color_dark"
                        label="Surface Color"
                        prepend-inner-icon="mdi-texture-box"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.surface_color_dark"
                        hint="Cards, sheets"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.error_color_dark"
                        label="Error Color"
                        prepend-inner-icon="mdi-alert-circle"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.error_color_dark"
                        hint="Error states"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.warning_color_dark"
                        label="Warning Color"
                        prepend-inner-icon="mdi-alert"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.warning_color_dark"
                        hint="Warning states"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.info_color_dark"
                        label="Info Color"
                        prepend-inner-icon="mdi-information"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.info_color_dark"
                        hint="Info states"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.success_color_dark"
                        label="Success Color"
                        prepend-inner-icon="mdi-check-circle"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.success_color_dark"
                        hint="Success states"
                    ></v-text-field>
                </v-col>
            </v-row>
        </settings-card>

        <!-- Background Images -->
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

        <!-- Typography & Custom Styles -->
        <settings-card icon="mdi-format-font" title="Typography & Custom Styles">
            <!-- Font Preview -->
            <v-card
                v-if="settings.font_family"
                :key="settings.font_family"
                variant="outlined"
                class="pa-4 mb-4 font-preview-card"
                :style="previewStyle"
            >
                <div class="text-subtitle-2 font-weight-bold mb-3 preview-label">Font Preview</div>
                <div class="text-h5 mb-3 preview-text">The quick brown fox jumps over the lazy dog</div>
                <div class="text-h6 mb-3 preview-text">The quick brown fox jumps over the lazy dog</div>
                <div class="text-body-1 mb-2 preview-text">
                    AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz
                </div>
                <div class="text-body-2 mb-2 preview-text">0123456789 !@#$%^&*()</div>
                <div class="text-caption text-medium-emphasis preview-label">
                    Current selection: {{ fontFamilies.find(f => f.value === settings.font_family)?.label || settings.font_family }}
                </div>
            </v-card>

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
            >
                <template v-slot:item="{ props, item }">
                    <v-list-item
                        v-bind="props"
                        :style="{ fontFamily: item.value }"
                    >
                        <v-list-item-title>{{ item.raw.label }}</v-list-item-title>
                    </v-list-item>
                </template>
            </v-select>

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
import { computed } from 'vue';
import SettingsCard from '../SettingsCard.vue';
import ImageUpload from '../ImageUpload.vue';
import { themeModes, fontFamilies } from '../../../composables/settingsConstants';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

const emit = defineEmits(['save', 'message']);

// Background style options
const backgroundStyles = [
    { label: 'Full Cover (Stretch to fit)', value: 'cover' },
    { label: 'Repeating Tile (Pattern)', value: 'repeat' },
];

// Default color values
const defaultColors = {
    light: {
        primary_color_light: '#115571',
        secondary_color_light: '#a0b9c8',
        accent_color_light: '#048ba8',
        background_color_light: '#ffffff',
        surface_color_light: '#f2f5f8',
        error_color_light: '#ef476f',
        warning_color_light: '#ffd166',
        info_color_light: '#2196F3',
        success_color_light: '#06d6a0',
    },
    dark: {
        primary_color_dark: '#115571',
        secondary_color_dark: '#a0b9c8',
        accent_color_dark: '#26c4da',
        background_color_dark: '#121212',
        surface_color_dark: '#1e1e1e',
        error_color_dark: '#ef476f',
        warning_color_dark: '#ffd166',
        info_color_dark: '#2196F3',
        success_color_dark: '#06d6a0',
    },
};

// Computed property for font preview style
const previewStyle = computed(() => {
    return {
        fontFamily: props.settings.font_family || 'Roboto, sans-serif',
        fontDisplay: 'swap',
    };
});

function resetToDefaults() {
    if (confirm('Are you sure you want to reset all theme colors to their default values? This will overwrite your current color settings.')) {
        // Reset light theme colors
        Object.assign(props.settings, defaultColors.light);
        // Reset dark theme colors
        Object.assign(props.settings, defaultColors.dark);

        emit('message', { text: 'Theme colors reset to defaults. Click "Save Settings" to apply changes.', type: 'info' });
    }
}

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

<style scoped>
.font-preview-card {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

/* Preview text uses the selected font */
.font-preview-card .preview-text {
    font-family: inherit !important;
}

/* Labels use default Roboto font */
.font-preview-card .preview-label {
    font-family: Roboto, sans-serif !important;
}

/* Force all child elements of preview text to inherit font */
.font-preview-card .preview-text * {
    font-family: inherit !important;
}
</style>
