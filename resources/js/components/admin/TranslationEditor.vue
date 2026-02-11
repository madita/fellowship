<template>
    <v-card variant="outlined" class="translation-editor">
        <v-card-title class="d-flex align-center justify-space-between">
            <span>{{ $t('modelTranslations.editTranslations') }}</span>
            <v-chip v-if="itemIdentifier" size="small" color="primary" variant="tonal">
                {{ itemIdentifier }}
            </v-chip>
        </v-card-title>

        <v-divider />

        <template v-if="isInitialized">
            <v-tabs v-model="activeLocale" color="primary" density="compact">
                <v-tab v-for="locale in locales" :key="locale" :value="locale">
                    <v-avatar size="20" class="mr-2">
                        <span class="text-caption font-weight-bold">{{ locale.toUpperCase() }}</span>
                    </v-avatar>
                    {{ getLocaleName(locale) }}
                    <v-icon
                        v-if="hasTranslation(locale)"
                        icon="mdi-check-circle"
                        size="small"
                        color="success"
                        class="ml-2"
                    />
                    <v-icon
                        v-else
                        icon="mdi-alert-circle"
                        size="small"
                        color="warning"
                        class="ml-2"
                    />
                </v-tab>
            </v-tabs>

            <v-divider />

            <v-card-text>
                <v-window v-model="activeLocale">
                    <v-window-item v-for="locale in locales" :key="locale" :value="locale">
                        <v-row>
                            <v-col
                                v-for="field in fields"
                                :key="field"
                                cols="12"
                                :md="getFieldColumnSize(field)"
                            >
                                <component
                                    :is="getFieldComponent(field)"
                                    v-model="localTranslations[locale][field]"
                                    :label="formatFieldLabel(field)"
                                    variant="outlined"
                                    density="comfortable"
                                    :rows="isLongTextField(field) ? 6 : 3"
                                    :auto-grow="isLongTextField(field)"
                                    @update:model-value="onFieldChange(locale, field)"
                                />
                            </v-col>
                        </v-row>

                        <!-- Show source text for reference when not editing default locale -->
                        <v-alert
                            v-if="locale !== defaultLocale && hasTranslation(defaultLocale)"
                            type="info"
                            variant="tonal"
                            density="compact"
                            class="mt-4"
                        >
                            <div class="text-subtitle-2 mb-2">
                                {{ $t('modelTranslations.sourceText') }} ({{ defaultLocale.toUpperCase() }}):
                            </div>
                            <div v-for="field in fields" :key="field" class="mb-2">
                                <strong>{{ formatFieldLabel(field) }}:</strong>
                                <span class="text-body-2 ml-2">
                                    {{ truncateText(localTranslations[defaultLocale]?.[field], 200) || '-' }}
                                </span>
                            </div>
                        </v-alert>
                    </v-window-item>
                </v-window>
            </v-card-text>
        </template>

        <v-card-text v-else class="text-center py-8">
            <v-progress-circular indeterminate color="primary" />
        </v-card-text>

        <v-divider />

        <v-card-actions>
            <v-spacer />
            <v-btn
                variant="text"
                @click="$emit('cancel')"
            >
                {{ $t('common.cancel') }}
            </v-btn>
            <v-btn
                color="primary"
                :loading="saving"
                :disabled="!hasChanges || !isInitialized"
                @click="saveTranslations"
            >
                <v-icon icon="mdi-content-save" start />
                {{ $t('common.save') }}
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelType: {
        type: String,
        required: true,
    },
    itemId: {
        type: [Number, String],
        required: true,
    },
    itemIdentifier: {
        type: String,
        default: '',
    },
    fields: {
        type: Array,
        required: true,
    },
    locales: {
        type: Array,
        required: true,
    },
    translations: {
        type: Object,
        default: () => ({}),
    },
    saving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['save', 'cancel', 'change']);

const activeLocale = ref(props.locales[0] || 'en');
const defaultLocale = ref('en');
const localTranslations = reactive({});
const originalTranslations = ref({});
const hasChanges = ref(false);
const isInitialized = ref(false);

// Initialize translations immediately
const initializeTranslations = () => {
    if (!props.locales.length || !props.fields.length) {
        return;
    }

    props.locales.forEach(locale => {
        localTranslations[locale] = {};
        props.fields.forEach(field => {
            localTranslations[locale][field] = props.translations[locale]?.[field] || '';
        });
    });
    originalTranslations.value = JSON.parse(JSON.stringify(localTranslations));
    hasChanges.value = false;
    isInitialized.value = true;
};

// Watch for translations prop changes and initialize
watch(() => props.translations, () => {
    initializeTranslations();
}, { deep: true, immediate: true });

// Also watch locales and fields in case they change
watch([() => props.locales, () => props.fields], () => {
    initializeTranslations();
}, { deep: true });

const localeNames = {
    en: 'English',
    de: 'Deutsch',
    fr: 'Français',
    es: 'Español',
    it: 'Italiano',
    pt: 'Português',
    nl: 'Nederlands',
    pl: 'Polski',
    ru: 'Русский',
    ja: '日本語',
    zh: '中文',
    ko: '한국어',
};

const getLocaleName = (locale) => {
    return localeNames[locale] || locale.toUpperCase();
};

const hasTranslation = (locale) => {
    if (!localTranslations[locale]) return false;
    return props.fields.some(field => {
        const value = localTranslations[locale]?.[field];
        return value && value.trim() !== '';
    });
};

const formatFieldLabel = (field) => {
    return field
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const getFieldComponent = (field) => {
    const longFields = ['content', 'body', 'description', 'lead', 'meta_desc'];
    return longFields.includes(field) ? 'v-textarea' : 'v-text-field';
};

const getFieldColumnSize = (field) => {
    const fullWidthFields = ['content', 'body', 'description'];
    return fullWidthFields.includes(field) ? 12 : 6;
};

const isLongTextField = (field) => {
    return ['content', 'body'].includes(field);
};

const truncateText = (text, maxLength) => {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
};

const onFieldChange = (locale, field) => {
    hasChanges.value = JSON.stringify(localTranslations) !== JSON.stringify(originalTranslations.value);
    emit('change', { locale, field, value: localTranslations[locale][field] });
};

const saveTranslations = () => {
    emit('save', { ...localTranslations });
};
</script>

<style scoped>
.translation-editor :deep(.v-textarea textarea) {
    font-family: inherit;
    line-height: 1.5;
}
</style>
