<template>
    <settings-page-layout
        :title="$t('settings.footer.sectionsGrid')"
        :description="$t('settings.footer.description')"
        icon="mdi-view-grid-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'footer' } }"
        :show-save-button="false"
    >
        <footer-section-builder
            @edit-widget="editWidget"
            @add-widget="handleAddWidgetToSection"
        />

        <!-- Widget Editor Dialog -->
        <footer-widget-editor
            v-model="showEditor"
            :widget="selectedWidget"
            @save="saveWidget"
        />

        <!-- Widget Library Dialog -->
        <footer-widget-library
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
import { useFooterStore } from '@/store/footerStore';
import { getWidgetDefinition } from '@/configs/footerWidgetTypes';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import FooterSectionBuilder from '@/components/settings/footer/FooterSectionBuilder.vue';
import FooterWidgetEditor from '@/components/settings/footer/FooterWidgetEditor.vue';
import FooterWidgetLibrary from '@/components/settings/footer/FooterWidgetLibrary.vue';

const { t } = useI18n();

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const footerStore = useFooterStore();

const showEditor = ref(false);
const showWidgetLibrary = ref(false);
const selectedWidget = ref(null);
const widgetSectionContext = ref(null);

const snackbar = ref(false);
const snackbarMessage = ref('');
const snackbarColor = ref('success');

const sections = computed(() => footerStore.orderedSections || []);
const widgets = computed(() => footerStore.orderedWidgets || []);

function editWidget(widget) {
    selectedWidget.value = { ...widget };
    showEditor.value = true;
}

async function saveWidget(updatedWidget) {
    try {
        await footerStore.updateWidget(updatedWidget.id, updatedWidget);
        showSnackbar(t('settings.footer.widgetUpdated'), 'success');
        showEditor.value = false;
        await footerStore.fetchSections();
    } catch (error) {
        console.error('Failed to save widget:', error);
        showSnackbar(t('settings.footer.failedToUpdateWidget'), 'error');
    }
}

function handleAddWidgetToSection({ sectionId, column }) {
    widgetSectionContext.value = { sectionId, column };
    showWidgetLibrary.value = true;
}

async function addWidget(widgetType) {
    try {
        const definition = getWidgetDefinition(widgetType);
        if (!definition) {
            showSnackbar(t('settings.footer.widgetTypeNotFound', { type: widgetType }), 'error');
            return;
        }

        const newWidget = {
            type: widgetType,
            enabled: true,
            config: definition.defaultConfig || {},
        };

        if (widgetSectionContext.value) {
            newWidget.section_id = parseInt(widgetSectionContext.value.sectionId);
            newWidget.column = parseInt(widgetSectionContext.value.column);

            const section = sections.value.find(s => s.id === widgetSectionContext.value.sectionId);
            if (section && section.widgets) {
                const columnWidgets = section.widgets.filter(w => Number(w.column) === newWidget.column);
                newWidget.order = columnWidgets.length + 1;
            } else {
                newWidget.order = 1;
            }
        } else {
            newWidget.order = widgets.value.length + 1;
        }

        await footerStore.createWidget(newWidget);
        showSnackbar(t('settings.footer.widgetAdded'), 'success');
        showWidgetLibrary.value = false;
        widgetSectionContext.value = null;

        if (newWidget.section_id) {
            await footerStore.fetchSections();
        }
    } catch (error) {
        console.error('Failed to add widget:', error);
        showSnackbar(t('settings.footer.failedToAddWidget'), 'error');
    }
}

function showSnackbar(message, color = 'success') {
    snackbarMessage.value = message;
    snackbarColor.value = color;
    snackbar.value = true;
}

onMounted(async () => {
    await footerStore.fetchSections();
});
</script>
