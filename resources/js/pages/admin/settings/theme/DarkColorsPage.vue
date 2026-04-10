<template>
    <settings-page-layout
        :title="$t('settings.darkColors.title')"
        :description="$t('settings.darkColors.description')"
        icon="mdi-moon-waning-crescent"
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
                    <div class="text-subtitle-2 font-weight-bold mb-1">{{ $t('settings.darkColors.resetTitle') }}</div>
                    <div class="text-caption">{{ $t('settings.darkColors.resetDescription') }}</div>
                </div>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-restore"
                    @click="resetToDefaults"
                >
                    {{ $t('settings.colors.resetToDefaults') }}
                </v-btn>
            </div>
        </v-alert>

        <settings-card icon="mdi-weather-night" :title="$t('settings.darkColors.cardTitle')">
            <v-row>
                <v-col cols="12" md="6">
                    <v-slider
                        v-model="settings.background_opacity_dark"
                        :label="$t('settings.colors.backgroundOpacity')"
                        prepend-icon="mdi-opacity"
                        :min="0"
                        :max="100"
                        :step="5"
                        thumb-label
                        :hint="$t('settings.colors.backgroundOpacityHint')"
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
                        :label="$t('settings.colors.surfaceOpacity')"
                        prepend-icon="mdi-texture-box"
                        :min="0"
                        :max="100"
                        :step="5"
                        thumb-label
                        :hint="$t('settings.colors.surfaceOpacityHint')"
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
                        :label="$t('settings.colors.primaryColor')"
                        prepend-inner-icon="mdi-palette"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.primary_color_dark"
                        :hint="$t('settings.colors.primaryColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.secondary_color_dark"
                        :label="$t('settings.colors.secondaryColor')"
                        prepend-inner-icon="mdi-palette-outline"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.secondary_color_dark"
                        :hint="$t('settings.colors.secondaryColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.accent_color_dark"
                        :label="$t('settings.colors.accentColor')"
                        prepend-inner-icon="mdi-invert-colors"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.accent_color_dark"
                        :hint="$t('settings.colors.accentColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.background_color_dark"
                        :label="$t('settings.colors.backgroundColor')"
                        prepend-inner-icon="mdi-format-color-fill"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.background_color_dark"
                        :hint="$t('settings.colors.backgroundColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.surface_color_dark"
                        :label="$t('settings.colors.surfaceColor')"
                        prepend-inner-icon="mdi-texture-box"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.surface_color_dark"
                        :hint="$t('settings.colors.surfaceColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.error_color_dark"
                        :label="$t('settings.colors.errorColor')"
                        prepend-inner-icon="mdi-alert-circle"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.error_color_dark"
                        :hint="$t('settings.colors.errorColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.warning_color_dark"
                        :label="$t('settings.colors.warningColor')"
                        prepend-inner-icon="mdi-alert"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.warning_color_dark"
                        :hint="$t('settings.colors.warningColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.info_color_dark"
                        :label="$t('settings.colors.infoColor')"
                        prepend-inner-icon="mdi-information"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.info_color_dark"
                        :hint="$t('settings.colors.infoColorHint')"
                    ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="settings.success_color_dark"
                        :label="$t('settings.colors.successColor')"
                        prepend-inner-icon="mdi-check-circle"
                        variant="outlined"
                        type="color"
                        :error-messages="errors.success_color_dark"
                        :hint="$t('settings.colors.successColorHint')"
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
            {{ $t('settings.saveSettings') }}
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

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

const defaultColors = {
    primary_color_dark: '#115571',
    secondary_color_dark: '#a0b9c8',
    accent_color_dark: '#26c4da',
    background_color_dark: '#121212',
    surface_color_dark: '#1e1e1e',
    error_color_dark: '#ef476f',
    warning_color_dark: '#ffd166',
    info_color_dark: '#2196F3',
    success_color_dark: '#06d6a0',
};

function resetToDefaults() {
    if (confirm(t('settings.darkColors.confirmReset'))) {
        Object.assign(props.settings, defaultColors);
        emit('message', { text: t('settings.darkColors.resetSuccess'), type: 'info' });
    }
}
</script>
