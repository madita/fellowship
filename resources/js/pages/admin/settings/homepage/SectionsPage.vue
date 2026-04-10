<template>
    <settings-page-layout
        :title="$t('settings.homepage.sections.title')"
        :description="$t('settings.homepage.sections.description')"
        icon="mdi-view-grid-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'homepage' } }"
        :show-save-button="false"
    >
        <section-builder
            @edit-widget="editWidget"
            @add-widget="handleAddWidgetToSection"
        />

        <!-- Widget Editor Dialog -->
        <widget-editor
            v-model="showEditor"
            :widget="selectedWidget"
            @save="saveWidget"
        />

        <!-- Widget Library Dialog -->
        <widget-library
            v-model="showWidgetLibrary"
            @select="addWidget"
        />

        <!-- Success Snackbar -->
        <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="3000">
            {{ snackbarMessage }}
        </v-snackbar>
    </settings-page-layout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useHomepageStore } from '@/store/homepageStore';
import { getWidgetDefinition } from '@/configs/widgetTypes';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SectionBuilder from '@/components/settings/homepage/SectionBuilder.vue';
import WidgetEditor from '@/components/settings/homepage/WidgetEditor.vue';
import WidgetLibrary from '@/components/settings/homepage/WidgetLibrary.vue';

const { t } = useI18n();

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const homepageStore = useHomepageStore();

const showEditor = ref(false);
const showWidgetLibrary = ref(false);
const selectedWidget = ref(null);
const widgetSectionContext = ref(null);

const snackbar = ref(false);
const snackbarMessage = ref('');
const snackbarColor = ref('success');

const sections = computed(() => homepageStore.orderedSections || []);
const widgets = computed(() => homepageStore.orderedWidgets || []);

function editWidget(widget) {
    selectedWidget.value = { ...widget };
    showEditor.value = true;
}

async function saveWidget(updatedWidget) {
    try {
        await homepageStore.updateWidget(updatedWidget.id, updatedWidget);
        showSnackbar(t('settings.homepage.sections.widgetUpdated'), 'success');
        showEditor.value = false;
    } catch (error) {
        console.error('Failed to save widget:', error);
        showSnackbar(t('settings.homepage.sections.failedToUpdateWidget'), 'error');
    }
}

function handleAddWidgetToSection({ sectionId, column }) {
    widgetSectionContext.value = { sectionId, column };
    showWidgetLibrary.value = true;
}

async function addWidget(widgetType) {
    try {
        const definition = getWidgetDefinition(widgetType);
        const newWidget = {
            type: widgetType,
            title: definition.name,
            enabled: true,
            order: widgets.value.length + 1,
            content: definition.defaultContent,
            config: {},
            anchor_id: widgetType.replace('_', '-')
        };

        if (widgetSectionContext.value) {
            newWidget.section_id = widgetSectionContext.value.sectionId;
            newWidget.column = widgetSectionContext.value.column;
            newWidget.order = 1;
        }

        await homepageStore.createWidget(newWidget);
        showSnackbar(t('settings.homepage.sections.widgetAdded'), 'success');
        showWidgetLibrary.value = false;
        widgetSectionContext.value = null;

        if (newWidget.section_id) {
            await homepageStore.fetchSections();
        }
    } catch (error) {
        showSnackbar(t('settings.homepage.sections.failedToAddWidget'), 'error');
    }
}

function showSnackbar(message, color = 'success') {
    snackbarMessage.value = message;
    snackbarColor.value = color;
    snackbar.value = true;
}

onMounted(async () => {
    await homepageStore.fetchSections();
});
</script>
