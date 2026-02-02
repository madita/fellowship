<template>
    <settings-page-layout
        title="Light Theme Colors"
        description="Configure the color palette for light theme mode"
        icon="mdi-white-balance-sunny"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'theme' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <!-- Reset Colors Button -->
        <v-alert type="info" variant="tonal" class="mb-4">
            <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                <div>
                    <div class="text-subtitle-2 font-weight-bold mb-1">Reset Light Theme Colors</div>
                    <div class="text-caption">Restore all light theme colors to their default values</div>
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

const defaultColors = {
    primary_color_light: '#115571',
    secondary_color_light: '#a0b9c8',
    accent_color_light: '#048ba8',
    background_color_light: '#ffffff',
    surface_color_light: '#f2f5f8',
    error_color_light: '#ef476f',
    warning_color_light: '#ffd166',
    info_color_light: '#2196F3',
    success_color_light: '#06d6a0',
};

function resetToDefaults() {
    if (confirm('Are you sure you want to reset all light theme colors to their default values?')) {
        Object.assign(props.settings, defaultColors);
        emit('message', { text: 'Light theme colors reset to defaults. Click "Save" to apply changes.', type: 'info' });
    }
}
</script>
