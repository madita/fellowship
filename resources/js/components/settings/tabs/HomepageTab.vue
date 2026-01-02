<template>
  <div>
    <v-alert type="info" variant="tonal" class="mb-4">
      <div class="text-h6 mb-2">Homepage Builder</div>
      <div>Drag and drop widgets to reorder them, edit content, and customize your landing page.</div>
    </v-alert>

    <!-- Tabs for Widgets and Menu -->
    <v-tabs v-model="tab" bg-color="transparent" color="primary">
      <v-tab value="widgets">
        <v-icon class="mr-2">mdi-widgets</v-icon>
        Widgets
      </v-tab>
      <v-tab value="menu">
        <v-icon class="mr-2">mdi-menu</v-icon>
        Navigation Menu
      </v-tab>
    </v-tabs>

    <v-divider class="mb-4"></v-divider>

    <v-window v-model="tab">
      <!-- Widgets Tab -->
      <v-window-item value="widgets">
        <!-- Action Buttons -->
        <div class="d-flex justify-space-between align-center mb-4">
          <div>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="showWidgetLibrary = true">
              Add Widget
            </v-btn>
            <v-btn class="ml-2" prepend-icon="mdi-refresh" @click="loadWidgets" :loading="isLoading">
              Refresh
            </v-btn>
          </div>
          <v-chip v-if="hasChanges" color="warning">
            Unsaved Changes
          </v-chip>
        </div>

        <!-- Quick Stats -->
        <v-row class="mb-4">
          <v-col cols="6" sm="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4">{{ widgets.length }}</div>
                <div class="text-caption text-grey">Total Widgets</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" sm="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4 text-success">{{ enabledCount }}</div>
                <div class="text-caption text-grey">Enabled</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" sm="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4 text-grey">{{ disabledCount }}</div>
                <div class="text-caption text-grey">Disabled</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" sm="3">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4">{{ menuItems.length }}</div>
                <div class="text-caption text-grey">Menu Items</div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Widgets List with Drag and Drop -->
        <v-card>
          <v-card-title>
            <v-icon class="mr-2">mdi-drag</v-icon>
            Drag to Reorder Widgets
          </v-card-title>
          <v-divider></v-divider>

          <v-card-text v-if="isLoading" class="text-center py-8">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <div class="mt-2">Loading widgets...</div>
          </v-card-text>

          <v-card-text v-else-if="widgets.length === 0" class="text-center py-8">
            <v-icon size="64" color="grey">mdi-widgets-outline</v-icon>
            <div class="text-h6 mt-4">No widgets yet</div>
            <div class="text-caption text-grey mb-4">Click "Add Widget" to get started</div>
            <v-btn color="primary" @click="showWidgetLibrary = true">Add Your First Widget</v-btn>
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
                  Order: {{ widget.order }} | Anchor: {{ widget.anchor_id || 'None' }}
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
                      title="Edit Widget"
                    ></v-btn>

                    <v-btn
                      icon="mdi-content-copy"
                      size="small"
                      variant="text"
                      @click="duplicateWidget(widget)"
                      title="Duplicate Widget"
                    ></v-btn>

                    <v-btn
                      icon="mdi-delete"
                      size="small"
                      variant="text"
                      color="error"
                      @click="confirmDelete(widget)"
                      title="Delete Widget"
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
        <v-card-title>Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete the widget "{{ widgetToDelete?.title || widgetToDelete?.type }}"?
          This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteWidget">Delete</v-btn>
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
import { useHomepageStore } from '@/store/homepageStore';
import { getWidgetDefinition } from '@/configs/widgetTypes';
import draggable from 'vuedraggable';
import WidgetEditor from '../homepage/WidgetEditor.vue';
import WidgetLibrary from '../homepage/WidgetLibrary.vue';
import MenuBuilder from '../homepage/MenuBuilder.vue';

const homepageStore = useHomepageStore();
const tab = ref('widgets');
const isLoading = ref(true);
const hasChanges = ref(false);

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
    await homepageStore.fetchWidgets();
    await homepageStore.fetchMenuItems();
    hasChanges.value = false;
  } catch (error) {
    showSnackbar('Failed to load widgets', 'error');
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
    showSnackbar('Widget order updated successfully', 'success');
  } catch (error) {
    showSnackbar('Failed to update widget order', 'error');
    await loadWidgets(); // Reload to reset order
  }
}

function editWidget(widget) {
  selectedWidget.value = { ...widget };
  showEditor.value = true;
}

async function saveWidget(updatedWidget) {
  try {
    await homepageStore.updateWidget(updatedWidget.id, updatedWidget);
    showSnackbar('Widget updated successfully', 'success');
    showEditor.value = false;
  } catch (error) {
    showSnackbar('Failed to update widget', 'error');
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

    await homepageStore.createWidget(newWidget);
    showSnackbar('Widget added successfully', 'success');
    showWidgetLibrary.value = false;
  } catch (error) {
    showSnackbar('Failed to add widget', 'error');
  }
}

async function toggleWidget(widget) {
  try {
    await homepageStore.toggleWidget(widget.id);
    showSnackbar(`Widget ${widget.enabled ? 'enabled' : 'disabled'}`, 'success');
  } catch (error) {
    showSnackbar('Failed to toggle widget', 'error');
    widget.enabled = !widget.enabled; // Revert on error
  }
}

async function duplicateWidget(widget) {
  try {
    await homepageStore.duplicateWidget(widget.id);
    showSnackbar('Widget duplicated successfully', 'success');
  } catch (error) {
    showSnackbar('Failed to duplicate widget', 'error');
  }
}

function confirmDelete(widget) {
  widgetToDelete.value = widget;
  showDeleteDialog.value = true;
}

async function deleteWidget() {
  try {
    await homepageStore.deleteWidget(widgetToDelete.value.id);
    showSnackbar('Widget deleted successfully', 'success');
    showDeleteDialog.value = false;
    widgetToDelete.value = null;
  } catch (error) {
    showSnackbar('Failed to delete widget', 'error');
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
