<template>
    <settings-page-layout
        :title="$t('settings.typography.title')"
        :description="$t('settings.typography.description')"
        icon="mdi-format-font"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'theme' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-format-font" :title="$t('settings.typography.cardTitle')">
            <!-- Font Preview -->
            <v-card
                v-if="settings.font_family"
                :key="settings.font_family"
                variant="outlined"
                class="pa-4 mb-4 font-preview-card"
                :style="previewStyle"
            >
                <div class="text-subtitle-2 font-weight-bold mb-3 preview-label">{{ $t('settings.typography.fontPreview') }}</div>
                <div class="text-h5 mb-3 preview-text">{{ $t('settings.typography.sampleText') }}</div>
                <div class="text-h6 mb-3 preview-text">{{ $t('settings.typography.sampleText') }}</div>
                <div class="text-body-1 mb-2 preview-text">
                    AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz
                </div>
                <div class="text-body-2 mb-2 preview-text">0123456789 !@#$%^&*()</div>
                <div class="text-caption text-medium-emphasis preview-label">
                    {{ $t('settings.typography.currentSelection') }}: {{ fontFamilies.find(f => f.value === settings.font_family)?.label || settings.font_family }}
                </div>
            </v-card>

            <v-select
                v-model="settings.font_family"
                :label="$t('settings.typography.fontFamily')"
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
                :label="$t('settings.typography.customCss')"
                prepend-inner-icon="mdi-language-css3"
                variant="outlined"
                rows="6"
                :error-messages="errors.custom_css"
                :hint="$t('settings.typography.customCssHint')"
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
import { fontFamilies } from '@/composables/settingsConstants';

const { t } = useI18n();

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');

const previewStyle = computed(() => {
    return {
        fontFamily: props.settings.font_family || 'Roboto, sans-serif',
        fontDisplay: 'swap',
    };
});
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
