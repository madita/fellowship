<template>
    <settings-page-layout
        :title="$t('settings.footer.allWidgets')"
        :description="$t('settings.footer.description')"
        icon="mdi-widgets-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'footer' } }"
        :show-save-button="false"
    >
        <!-- Action Buttons -->
        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <v-btn color="primary" prepend-icon="mdi-plus" @click="showWidgetLibrary = true">
                    {{ $t('settings.footer.addWidget') }}
                </v-btn>
                <v-btn class="ml-2" prepend-icon="mdi-refresh" @click="loadWidgets" :loading="isLoading">
                    {{ $t('settings.footer.refresh') }}
                </v-btn>
            </div>
            <v-chip v-if="hasChanges" color="warning">
                {{ $t('settings.footer.unsavedChanges') }}
            </v-chip>
        </div>

        <!-- Quick Stats -->
        <v-row class="mb-4">
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="text-center">
                        <div class="text-h4">{{ widgets.length }}</div>
                        <div class="text-caption text-grey">{{ $t('settings.footer.totalWidgets') }}</div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="text-center">
                        <div class="text-h4 text-success">{{ enabledCount }}</div>
                        <div class="text-caption text-grey">{{ $t('settings.footer.enabled') }}</div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Widgets List with Drag and Drop -->
        <v-card>
            <v-card-title>
                <v-icon class="mr-2">mdi-drag</v-icon>
                {{ $t('settings.footer.dragToReorder') }}
            </v-card-title>
            <v-divider></v-divider>

            <v-card-text v-if="isLoading" class="text-center py-8">
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
                <div class="mt-2">{{ $t('settings.footer.loadingWidgets') }}</div>
            </v-card-text>

            <v-card-text v-else-if="widgets.length === 0" class="text-center py-8">
                <v-icon size="64" color="grey">mdi-widgets-outline</v-icon>
                <div class="text-h6 mt-4">{{ $t('settings.footer.noWidgetsYet') }}</div>
                <div class="text-caption text-grey mb-4">{{ $t('settings.footer.clickAddWidget') }}</div>
                <v-btn color="primary" @click="showWidgetLibrary = true">{{ $t('settings.footer.addFirstWidget') }}</v-btn>
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
                            {{ $t('settings.footer.order') }}: {{ widget.order }}
                            <span v-if="widget.section_id"> | {{ $t('settings.footer.section') }}: {{ widget.section_id }} | {{ $t('settings.footer.column') }}: {{ widget.column }}</span>
                        </v-list-item-subtitle>

                        <template #append>
                            <div class="d-flex align-center">
                                <v-switch
                                    v-model="widget.enabled"
                                    hide-details
                                    density="compact"
                                    color="success"
                                    class="mr-2"
                                    @change="toggleWidget(widget)"
                                ></v-switch>

                                <v-btn
                                    icon="mdi-pencil"
                                    size="small"
                                    variant="text"
                                    @click="editWidget(widget)"
                                    :title="$t('settings.footer.editWidget')"
                                ></v-btn>

                                <v-btn
                                    icon="mdi-delete"
                                    size="small"
                                    variant="text"
                                    color="error"
                                    @click="confirmDelete(widget)"
                                    :title="$t('settings.footer.deleteWidget')"
                                ></v-btn>
                            </div>
                        </template>
                    </v-list-item>
                </template>
            </draggable>
        </v-card>

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

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="showDeleteDialog" max-width="500">
            <v-card>
                <v-card-title>{{ $t('settings.footer.confirmDelete') }}</v-card-title>
                <v-card-text>
                    {{ $t('settings.footer.deleteConfirmMessage', { name: widgetToDelete?.title || widgetToDelete?.type }) }}
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="showDeleteDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" @click="deleteWidget">{{ $t('common.delete') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

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
import draggable from 'vuedraggable';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
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
const isLoading = ref(true);
const hasChanges = ref(false);

const widgets = computed(() => footerStore.orderedWidgets || []);
const localWidgets = ref([]);
const enabledCount = computed(() => localWidgets.value.filter(w => w.enabled).length);

const showEditor = ref(false);
const showWidgetLibrary = ref(false);
const showDeleteDialog = ref(false);
const selectedWidget = ref(null);
const widgetToDelete = ref(null);

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
        await footerStore.fetchWidgets();
        localWidgets.value = [...widgets.value];
        hasChanges.value = false;
    } catch (error) {
        showSnackbar(t('settings.footer.failedToLoadData'), 'error');
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
        await footerStore.reorderWidgets(newOrder);
        hasChanges.value = false;
        showSnackbar(t('settings.footer.orderUpdated'), 'success');
    } catch (error) {
        showSnackbar(t('settings.footer.failedToUpdateOrder'), 'error');
        await loadWidgets();
    }
}

function editWidget(widget) {
    selectedWidget.value = { ...widget };
    showEditor.value = true;
}

async function saveWidget(updatedWidget) {
    try {
        await footerStore.updateWidget(updatedWidget.id, updatedWidget);
        showSnackbar(t('settings.footer.widgetUpdated'), 'success');
        showEditor.value = false;
        await loadWidgets();
    } catch (error) {
        showSnackbar(t('settings.footer.failedToUpdateWidget'), 'error');
    }
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
            order: localWidgets.value.length + 1,
            config: definition.defaultConfig || {},
        };

        await footerStore.createWidget(newWidget);
        showSnackbar(t('settings.footer.widgetAdded'), 'success');
        showWidgetLibrary.value = false;
        await loadWidgets();
    } catch (error) {
        showSnackbar(t('settings.footer.failedToAddWidget'), 'error');
    }
}

async function toggleWidget(widget) {
    try {
        await footerStore.toggleWidget(widget.id);
        showSnackbar(widget.enabled ? t('settings.footer.widgetEnabled') : t('settings.footer.widgetDisabled'), 'success');
    } catch (error) {
        showSnackbar(t('settings.footer.failedToToggleWidget'), 'error');
        widget.enabled = !widget.enabled;
    }
}

function confirmDelete(widget) {
    widgetToDelete.value = widget;
    showDeleteDialog.value = true;
}

async function deleteWidget() {
    try {
        await footerStore.deleteWidget(widgetToDelete.value.id);
        showSnackbar(t('settings.footer.widgetDeleted'), 'success');
        showDeleteDialog.value = false;
        widgetToDelete.value = null;
        await loadWidgets();
    } catch (error) {
        showSnackbar(t('settings.footer.failedToDeleteWidget'), 'error');
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
