<template>
  <div>
    <v-alert type="info" variant="tonal" class="mb-4">
      <div class="text-h6 mb-2">{{ $t('settings.homepage.title') }}</div>
      <div>{{ $t('settings.homepage.description') }}</div>
    </v-alert>

    <!-- Tabs for Sections, Widgets and Menu -->
    <v-tabs v-model="tab" bg-color="transparent" color="primary">
      <v-tab value="sections">
        <v-icon class="mr-2">mdi-view-grid-outline</v-icon>
        {{ $t('settings.homepage.sectionsGrid') }}
      </v-tab>
      <v-tab value="widgets">
        <v-icon class="mr-2">mdi-widgets</v-icon>
        {{ $t('settings.homepage.allWidgets') }}
      </v-tab>
      <v-tab value="menu">
        <v-icon class="mr-2">mdi-menu</v-icon>
        {{ $t('settings.homepage.navigationMenu') }}
      </v-tab>
    </v-tabs>

    <v-divider class="mb-4"></v-divider>

    <v-window v-model="tab">
      <!-- Sections Tab -->
      <v-window-item value="sections">
        <section-builder
          @edit-widget="editWidget"
          @add-widget="handleAddWidgetToSection"
        />
      </v-window-item>

      <!-- Widgets Tab (Legacy - all widgets list) -->
      <v-window-item value="widgets">
        <!-- Action Buttons -->
        <div class="d-flex justify-space-between align-center mb-4">
          <div>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="showWidgetLibrary = true">
              {{ $t('settings.homepage.addWidget') }}
            </v-btn>
            <v-btn class="ml-2" prepend-icon="mdi-refresh" @click="loadWidgets" :loading="isLoading">
              {{ $t('settings.homepage.refresh') }}
            </v-btn>
          </div>
          <v-chip v-if="hasChanges" color="warning">
            {{ $t('settings.homepage.unsavedChanges') }}
          </v-chip>
        </div>

        <!-- Quick Stats -->
        <v-row class="mb-4">
          <v-col cols="6" md="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4 text-primary">{{ sections.length }}</div>
                <div class="text-caption text-grey">{{ $t('settings.homepage.sections') }}</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" md="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4">{{ widgets.length }}</div>
                <div class="text-caption text-grey">{{ $t('settings.homepage.totalWidgets') }}</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" md="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4 text-success">{{ enabledCount }}</div>
                <div class="text-caption text-grey">{{ $t('settings.homepage.enabled') }}</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" md="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4">{{ menuItems.length }}</div>
                <div class="text-caption text-grey">{{ $t('settings.homepage.menuItems') }}</div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Widgets List with Drag and Drop -->
        <v-card>
          <v-card-title>
            <v-icon class="mr-2">mdi-drag</v-icon>
            {{ $t('settings.homepage.dragToReorder') }}
          </v-card-title>
          <v-divider></v-divider>

          <v-card-text v-if="isLoading" class="text-center py-8">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <div class="mt-2">{{ $t('settings.homepage.loadingWidgets') }}</div>
          </v-card-text>

          <v-card-text v-else-if="widgets.length === 0" class="text-center py-8">
            <v-icon size="64" color="grey">mdi-widgets-outline</v-icon>
            <div class="text-h6 mt-4">{{ $t('settings.homepage.noWidgetsYet') }}</div>
            <div class="text-caption text-grey mb-4">{{ $t('settings.homepage.clickAddWidget') }}</div>
            <v-btn color="primary" @click="showWidgetLibrary = true">{{ $t('settings.homepage.addFirstWidget') }}</v-btn>
          </v-card-text>

          <draggable
            v-else
            v-model="widgets"
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
                  {{ $t('settings.homepage.order') }}: {{ widget.order }} | {{ $t('settings.homepage.anchor') }}: {{ widget.anchor_id || $t('settings.homepage.none') }}
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
                      :title="$t('settings.homepage.editWidget')"
                    ></v-btn>

                    <v-btn
                      icon="mdi-content-copy"
                      size="small"
                      variant="text"
                      @click="duplicateWidget(widget)"
                      :title="$t('settings.homepage.duplicateWidget')"
                    ></v-btn>

                    <v-btn
                      icon="mdi-delete"
                      size="small"
                      variant="text"
                      color="error"
                      @click="confirmDelete(widget)"
                      :title="$t('settings.homepage.deleteWidget')"
                    ></v-btn>
                  </div>
                </template>
              </v-list-item>
            </template>
          </draggable>
        </v-card>
      </v-window-item>

      <!-- Menu Tab -->
      <v-window-item value="menu">
        <menu-builder />
      </v-window-item>
    </v-window>

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

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="500">
      <v-card>
        <v-card-title>{{ $t('settings.homepage.confirmDelete') }}</v-card-title>
        <v-card-text>
          {{ $t('settings.homepage.deleteConfirmMessage', { name: widgetToDelete?.title || widgetToDelete?.type }) }}
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useHomepageStore } from '@/store/homepageStore';
import { getWidgetDefinition } from '@/configs/widgetTypes';
import draggable from 'vuedraggable';
import WidgetEditor from '../homepage/WidgetEditor.vue';
import WidgetLibrary from '../homepage/WidgetLibrary.vue';
import MenuBuilder from '../homepage/MenuBuilder.vue';
import SectionBuilder from '../homepage/SectionBuilder.vue';

const { t } = useI18n();

const homepageStore = useHomepageStore();
const tab = ref('sections'); // Default to sections tab
const isLoading = ref(true);
const hasChanges = ref(false);

const sections = computed(() => homepageStore.orderedSections || []);

const widgets = computed({
  get: () => homepageStore.orderedWidgets,
  set: (value) => {
    // Update local order
    value.forEach((widget, index) => {
      widget.order = index + 1;
    });
    hasChanges.value = true;
  }
});

const menuItems = computed(() => homepageStore.menuItems);
const enabledCount = computed(() => widgets.value.filter(w => w.enabled).length);
const disabledCount = computed(() => widgets.value.filter(w => !w.enabled).length);

// Dialog states
const showEditor = ref(false);
const showWidgetLibrary = ref(false);
const showDeleteDialog = ref(false);
const selectedWidget = ref(null);
const widgetToDelete = ref(null);

// Section widget adding
const widgetSectionContext = ref(null); // { sectionId, column }

// Snackbar
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
    await homepageStore.fetchSections(); // Load sections with widgets
    await homepageStore.fetchWidgets(); // Also load all widgets for legacy tab
    await homepageStore.fetchMenuItems();
    hasChanges.value = false;
  } catch (error) {
    showSnackbar(t('settings.homepage.failedToLoadData'), 'error');
  } finally {
    isLoading.value = false;
  }
}

async function onDragEnd() {
  try {
    const newOrder = widgets.value.map((widget, index) => ({
      id: widget.id,
      order: index + 1
    }));
    await homepageStore.reorderWidgets(newOrder);
    hasChanges.value = false;
    showSnackbar(t('settings.homepage.orderUpdated'), 'success');
  } catch (error) {
    showSnackbar(t('settings.homepage.failedToUpdateOrder'), 'error');
    await loadWidgets(); // Reload to reset order
  }
}

function editWidget(widget) {
  selectedWidget.value = { ...widget };
  showEditor.value = true;
}

async function saveWidget(updatedWidget) {
  try {
    console.log('Saving widget with data:', JSON.stringify(updatedWidget, null, 2));
    await homepageStore.updateWidget(updatedWidget.id, updatedWidget);
    showSnackbar(t('settings.homepage.widgetUpdated'), 'success');
    showEditor.value = false;
  } catch (error) {
    console.error('Failed to save widget:', error);
    showSnackbar(t('settings.homepage.failedToUpdateWidget'), 'error');
  }
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

    // If adding to a section, include section_id and column
    if (widgetSectionContext.value) {
      newWidget.section_id = widgetSectionContext.value.sectionId;
      newWidget.column = widgetSectionContext.value.column;
      newWidget.order = 1; // First in the column
    }

    await homepageStore.createWidget(newWidget);
    showSnackbar(t('settings.homepage.widgetAdded'), 'success');
    showWidgetLibrary.value = false;
    widgetSectionContext.value = null;

    // Refresh sections to show new widget
    if (newWidget.section_id) {
      await homepageStore.fetchSections();
    }
  } catch (error) {
    showSnackbar(t('settings.homepage.failedToAddWidget'), 'error');
  }
}

function handleAddWidgetToSection({ sectionId, column }) {
  widgetSectionContext.value = { sectionId, column };
  showWidgetLibrary.value = true;
}

async function toggleWidget(widget) {
  try {
    await homepageStore.toggleWidget(widget.id);
    showSnackbar(widget.enabled ? t('settings.homepage.widgetEnabled') : t('settings.homepage.widgetDisabled'), 'success');
  } catch (error) {
    showSnackbar(t('settings.homepage.failedToToggleWidget'), 'error');
    widget.enabled = !widget.enabled; // Revert on error
  }
}

async function duplicateWidget(widget) {
  try {
    await homepageStore.duplicateWidget(widget.id);
    showSnackbar(t('settings.homepage.widgetDuplicated'), 'success');
  } catch (error) {
    showSnackbar(t('settings.homepage.failedToDuplicateWidget'), 'error');
  }
}

function confirmDelete(widget) {
  widgetToDelete.value = widget;
  showDeleteDialog.value = true;
}

async function deleteWidget() {
  try {
    await homepageStore.deleteWidget(widgetToDelete.value.id);
    showSnackbar(t('settings.homepage.widgetDeleted'), 'success');
    showDeleteDialog.value = false;
    widgetToDelete.value = null;
  } catch (error) {
    showSnackbar(t('settings.homepage.failedToDeleteWidget'), 'error');
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
