<template>
    <settings-page-layout
        :title="$t('settings.homepage.widgets.title')"
        :description="$t('settings.homepage.widgets.description')"
        icon="mdi-widgets-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'homepage' } }"
        :show-save-button="false"
    >
        <!-- Action Buttons -->
        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <v-btn color="primary" prepend-icon="mdi-plus" @click="showWidgetLibrary = true">
                    {{ $t('settings.homepage.widgets.addWidget') }}
                </v-btn>
                <v-btn class="ml-2" prepend-icon="mdi-refresh" @click="loadWidgets" :loading="isLoading">
                    {{ $t('settings.homepage.widgets.refresh') }}
                </v-btn>
            </div>
            <v-chip v-if="hasChanges" color="warning">
                {{ $t('settings.homepage.widgets.unsavedChanges') }}
            </v-chip>
        </div>

        <!-- Quick Stats -->
        <v-row class="mb-4">
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="text-center">
                        <div class="text-h4">{{ widgets.length }}</div>
                        <div class="text-caption text-grey">{{ $t('settings.homepage.widgets.totalWidgets') }}</div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="text-center">
                        <div class="text-h4 text-success">{{ enabledCount }}</div>
                        <div class="text-caption text-grey">{{ $t('settings.homepage.widgets.enabled') }}</div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Widgets List with Drag and Drop -->
        <v-card>
            <v-card-title>
                <v-icon class="mr-2">mdi-drag</v-icon>
                {{ $t('settings.homepage.widgets.dragToReorder') }}
            </v-card-title>
            <v-divider></v-divider>

            <v-card-text v-if="isLoading" class="text-center py-8">
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
                <div class="mt-2">{{ $t('settings.homepage.widgets.loadingWidgets') }}</div>
            </v-card-text>

            <v-card-text v-else-if="widgets.length === 0" class="text-center py-8">
                <v-icon size="64" color="grey">mdi-widgets-outline</v-icon>
                <div class="text-h6 mt-4">{{ $t('settings.homepage.widgets.noWidgetsYet') }}</div>
                <div class="text-caption text-grey mb-4">{{ $t('settings.homepage.widgets.clickAddWidget') }}</div>
                <v-btn color="primary" @click="showWidgetLibrary = true">{{ $t('settings.homepage.widgets.addFirstWidget') }}</v-btn>
            </v-card-text>

            <draggable
                v-else
                v-model="localWidgets"
                item-key="id"
                handle=".drag-handle"
                @end="onDragEnd"
                class="widget-list"
            >
                <template #item="{ element: widget }">
                    <v-list-item
                        :key="widget.id"
                        class="widget-item mb-2"
                        :class="{ 'widget-disabled': !widget.enabled }"
                    >
                        <template #prepend>
                            <v-icon class="drag-handle mr-3" style="cursor: grab;">mdi-drag-vertical</v-icon>
                            <v-avatar :color="widget.enabled ? 'success' : 'grey'" size="32" class="mr-3">
                                <v-icon size="18" color="white">
                                    {{ getWidgetIcon(widget.type) }}
                                </v-icon>
                            </v-avatar>
                        </template>

                        <v-list-item-title>
                            {{ widget.title || widget.type }}
                            <v-chip size="x-small" class="ml-2" variant="outlined">{{ widget.type }}</v-chip>
                        </v-list-item-title>

                        <v-list-item-subtitle>
                            {{ $t('settings.homepage.widgets.order') }}: {{ widget.order }} | {{ $t('settings.homepage.widgets.anchor') }}: {{ widget.anchor_id || $t('settings.homepage.widgets.none') }}
                        </v-list-item-subtitle>

                        <template #append>
                            <div class="d-flex align-center">
                                <v-switch
                                    v-model="widget.enabled"
                                    hide-details
                                    density="compact"
                                    color="success"
                                    class="mr-2"
                                    :loading="togglingIds.includes(widget.id)"
                                    :disabled="isRowBusy(widget)"
                                    @change="toggleWidget(widget)"
                                ></v-switch>

                                <v-btn
                                    icon="mdi-pencil"
                                    size="small"
                                    variant="text"
                                    :disabled="isRowBusy(widget)"
                                    @click="editWidget(widget)"
                                    :title="$t('settings.homepage.widgets.editWidget')"
                                ></v-btn>

                                <v-btn
                                    icon="mdi-delete"
                                    size="small"
                                    variant="text"
                                    color="error"
                                    :loading="deletingId === widget.id"
                                    :disabled="togglingIds.includes(widget.id)"
                                    @click="confirmDelete(widget)"
                                    :title="$t('settings.homepage.widgets.deleteWidget')"
                                ></v-btn>
                            </div>
                        </template>
                    </v-list-item>
                </template>
            </draggable>
        </v-card>

        <!-- Widget Editor Dialog -->
        <widget-editor
            v-model="showEditor"
            :widget="selectedWidget"
            :saving="savingWidget"
            @save="saveWidget"
        />

        <!-- Widget Library Dialog -->
        <widget-library
            v-model="showWidgetLibrary"
            :adding="addingWidget"
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
import { useDialog } from '@/composables/useDialog.js';
import draggable from 'vuedraggable';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import WidgetEditor from '@/components/settings/homepage/WidgetEditor.vue';
import WidgetLibrary from '@/components/settings/homepage/WidgetLibrary.vue';

const { t } = useI18n();
const dialog = useDialog();

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const homepageStore = useHomepageStore();
const isLoading = ref(true);
const hasChanges = ref(false);

const widgets = computed(() => homepageStore.orderedWidgets || []);
const localWidgets = ref([]);
const enabledCount = computed(() => localWidgets.value.filter(w => w.enabled).length);

const showEditor = ref(false);
const showWidgetLibrary = ref(false);
const selectedWidget = ref(null);

// Request state per control
const savingWidget = ref(false);
const addingWidget = ref(false);
const togglingIds = ref([]);
const deletingId = ref(null);

function isRowBusy(widget) {
    return togglingIds.value.includes(widget.id) || deletingId.value === widget.id;
}

const snackbar = ref(false);
const snackbarMessage = ref('');
const snackbarColor = ref('success');

function getWidgetIcon(type) {
    const definition = getWidgetDefinition(type);
    return definition?.icon || 'mdi-widgets';
}

async function loadWidgets() {
    isLoading.value = true;
    try {
        await homepageStore.fetchWidgets();
        localWidgets.value = [...widgets.value];
        hasChanges.value = false;
    } catch (error) {
        showSnackbar(t('settings.homepage.widgets.failedToLoadWidgets'), 'error');
    } finally {
        isLoading.value = false;
    }
}

async function onDragEnd() {
    try {
        const newOrder = localWidgets.value.map((widget, index) => ({
            id: widget.id,
            order: index + 1
        }));
        await homepageStore.reorderWidgets(newOrder);
        hasChanges.value = false;
        showSnackbar(t('settings.homepage.widgets.widgetOrderUpdated'), 'success');
    } catch (error) {
        showSnackbar(t('settings.homepage.widgets.failedToUpdateWidgetOrder'), 'error');
        await loadWidgets();
    }
}

function editWidget(widget) {
    selectedWidget.value = { ...widget };
    showEditor.value = true;
}

async function saveWidget(updatedWidget) {
    if (savingWidget.value) return;
    savingWidget.value = true;
    try {
        await homepageStore.updateWidget(updatedWidget.id, updatedWidget);
        showSnackbar(t('settings.homepage.widgets.widgetUpdated'), 'success');
        showEditor.value = false;
        await loadWidgets();
    } catch (error) {
        showSnackbar(t('settings.homepage.widgets.failedToUpdateWidget'), 'error');
    } finally {
        savingWidget.value = false;
    }
}

async function addWidget(widgetType) {
    if (addingWidget.value) return;
    addingWidget.value = true;
    try {
        const definition = getWidgetDefinition(widgetType);
        const newWidget = {
            type: widgetType,
            title: definition.name,
            enabled: true,
            order: localWidgets.value.length + 1,
            content: definition.defaultContent,
            config: {},
            anchor_id: widgetType.replace('_', '-')
        };

        await homepageStore.createWidget(newWidget);
        showSnackbar(t('settings.homepage.widgets.widgetAdded'), 'success');
        showWidgetLibrary.value = false;
        await loadWidgets();
    } catch (error) {
        showSnackbar(t('settings.homepage.widgets.failedToAddWidget'), 'error');
    } finally {
        addingWidget.value = false;
    }
}

async function toggleWidget(widget) {
    if (togglingIds.value.includes(widget.id)) return;
    togglingIds.value.push(widget.id);
    try {
        await homepageStore.toggleWidget(widget.id);
        showSnackbar(widget.enabled ? t('settings.homepage.widgets.widgetEnabled') : t('settings.homepage.widgets.widgetDisabled'), 'success');
    } catch (error) {
        showSnackbar(t('settings.homepage.widgets.failedToToggleWidget'), 'error');
        widget.enabled = !widget.enabled;
    } finally {
        togglingIds.value = togglingIds.value.filter(id => id !== widget.id);
    }
}

async function confirmDelete(widget) {
    if (deletingId.value !== null) return;
    const ok = await dialog.confirmDelete(
        t('settings.homepage.widgets.deleteConfirmText', { name: widget.title || widget.type }),
        { title: t('settings.homepage.widgets.confirmDelete') }
    );
    if (!ok) return;

    deletingId.value = widget.id;
    try {
        await homepageStore.deleteWidget(widget.id);
        showSnackbar(t('settings.homepage.widgets.widgetDeleted'), 'success');
        await loadWidgets();
    } catch (error) {
        showSnackbar(t('settings.homepage.widgets.failedToDeleteWidget'), 'error');
    } finally {
        deletingId.value = null;
    }
}

function showSnackbar(message, color = 'success') {
    snackbarMessage.value = message;
    snackbarColor.value = color;
    snackbar.value = true;
}

onMounted(async () => {
    await loadWidgets();
});
</script>

<style scoped>
.widget-list {
    padding: 16px;
}

.widget-item {
    background: rgba(var(--v-theme-surface-variant), 0.4);
    border-radius: 8px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    transition: all 0.2s;
}

.widget-item:hover {
    background: rgba(var(--v-theme-surface-variant), 0.6);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.widget-disabled {
    opacity: 0.6;
}

.drag-handle:hover {
    cursor: grab;
}

.drag-handle:active {
    cursor: grabbing;
}
</style>
