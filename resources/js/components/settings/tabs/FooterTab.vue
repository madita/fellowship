<template>
  <div>
    <v-alert type="info" variant="tonal" class="mb-4">
      <div class="text-h6 mb-2">Footer Builder</div>
      <div>Create sections with column layouts, drag widgets into columns, and customize your footer.</div>
    </v-alert>

    <!-- Tabs for Sections and Widgets -->
    <v-tabs v-model="tab" bg-color="transparent" color="primary">
      <v-tab value="sections">
        <v-icon class="mr-2">mdi-view-grid-outline</v-icon>
        Sections & Grid
      </v-tab>
      <v-tab value="widgets">
        <v-icon class="mr-2">mdi-widgets</v-icon>
        All Widgets
      </v-tab>
      <v-tab value="custom">
        <v-icon class="mr-2">mdi-code-tags</v-icon>
        Custom HTML
      </v-tab>
    </v-tabs>

    <v-divider class="mb-4"></v-divider>

    <v-window v-model="tab">
      <!-- Sections Tab -->
      <v-window-item value="sections">
        <footer-section-builder
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
          <v-col cols="6" md="4">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4 text-primary">{{ sections.length }}</div>
                <div class="text-caption text-grey">Sections</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" md="4">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4">{{ widgets.length }}</div>
                <div class="text-caption text-grey">Total Widgets</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" md="4">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4 text-success">{{ enabledCount }}</div>
                <div class="text-caption text-grey">Enabled</div>
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
                  Order: {{ widget.order }}
                  <span v-if="widget.section_id"> | Section: {{ widget.section_id }} | Column: {{ widget.column }}</span>
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

      <!-- Custom HTML Tab -->
      <v-window-item value="custom">
        <settings-card icon="mdi-code-tags" title="Custom Footer HTML">
          <v-alert type="info" variant="tonal" class="mb-4" density="compact">
            <div class="text-caption">
              Use custom HTML instead of the widget-based footer. The custom footer will replace all widget sections.
            </div>
          </v-alert>

          <v-switch
            v-model="settings.custom_footer_enabled"
            label="Enable Custom Footer HTML"
            color="primary"
            class="mb-4"
            hint="Replace the widget-based footer with custom HTML"
            persistent-hint
          ></v-switch>

          <template v-if="settings.custom_footer_enabled">
            <v-textarea
              v-model="settings.custom_footer_html"
              label="Custom Footer HTML"
              prepend-inner-icon="mdi-code-tags"
              variant="outlined"
              rows="16"
              :error-messages="errors?.custom_footer_html"
              hint="Custom HTML for the footer section"
              persistent-hint
              placeholder="Enter your custom HTML here..."
              class="mb-4"
            ></v-textarea>

            <div class="d-flex gap-2 mb-4">
              <v-btn
                color="primary"
                variant="outlined"
                @click="loadSimpleFooterTemplate"
                prepend-icon="mdi-code-braces"
              >
                Load Simple Template
              </v-btn>
              <v-btn
                color="primary"
                variant="outlined"
                @click="loadComplexFooterTemplate"
                prepend-icon="mdi-code-tags"
              >
                Load Complex Template
              </v-btn>
            </div>

            <v-alert type="info" variant="tonal" class="mb-4">
              <div class="text-body-2">
                <strong>Available Variables:</strong>
                <ul class="mt-2">
                  <li><code v-pre>{{appName}}</code> - Application name</li>
                  <li><code v-pre>{{appCopyright}}</code> - Copyright text</li>
                  <li><code v-pre>{{contactEmail}}</code> - Contact email</li>
                  <li><code v-pre>{{contactPhone}}</code> - Contact phone</li>
                  <li><code v-pre>{{contactAddress}}</code> - Contact address</li>
                  <li><code v-pre>{{socialTwitter}}</code> - Twitter URL</li>
                  <li><code v-pre>{{socialFacebook}}</code> - Facebook URL</li>
                  <li><code v-pre>{{socialInstagram}}</code> - Instagram URL</li>
                </ul>
              </div>
            </v-alert>

            <v-btn
              :loading="isSaving"
              block
              size="large"
              color="primary"
              @click="handleSave"
              prepend-icon="mdi-content-save"
            >
              Save Settings
            </v-btn>
          </template>
        </settings-card>
      </v-window-item>
    </v-window>

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
import { useFooterStore } from '@/store/footerStore';
import { getWidgetDefinition } from '@/configs/footerWidgetTypes';
import draggable from 'vuedraggable';
import FooterWidgetEditor from '../footer/FooterWidgetEditor.vue';
import FooterWidgetLibrary from '../footer/FooterWidgetLibrary.vue';
import FooterSectionBuilder from '../footer/FooterSectionBuilder.vue';
import SettingsCard from '../SettingsCard.vue';

const props = defineProps({
  settings: Object,
  errors: Object,
  isSaving: Boolean,
});

const emit = defineEmits(['save', 'message']);

const footerStore = useFooterStore();
const tab = ref('sections'); // Default to sections tab
const isLoading = ref(true);
const hasChanges = ref(false);

const sections = computed(() => footerStore.orderedSections || []);

const widgets = computed({
  get: () => footerStore.orderedWidgets || [],
  set: (value) => {
    // Update local order
    value.forEach((widget, index) => {
      widget.order = index + 1;
    });
    hasChanges.value = true;
  }
});

const enabledCount = computed(() => widgets.value.filter(w => w.enabled).length);

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
    await footerStore.fetchSections(); // Load sections with widgets
    await footerStore.fetchWidgets(); // Also load all widgets for legacy tab
    hasChanges.value = false;
  } catch (error) {
    showSnackbar('Failed to load data', 'error');
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
    await footerStore.reorderWidgets(newOrder);
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
    console.log('Saving widget with data:', JSON.stringify(updatedWidget, null, 2));
    await footerStore.updateWidget(updatedWidget.id, updatedWidget);
    showSnackbar('Widget updated successfully', 'success');
    showEditor.value = false;
    // Reload to show updated data
    await loadWidgets();
  } catch (error) {
    console.error('Failed to save widget:', error);
    showSnackbar('Failed to update widget', 'error');
  }
}

async function addWidget(widgetType) {
  try {
    const definition = getWidgetDefinition(widgetType);
    if (!definition) {
      showSnackbar(`Widget type "${widgetType}" not found`, 'error');
      return;
    }

    const newWidget = {
      type: widgetType,
      enabled: true,
      config: definition.defaultConfig || {},
    };

    // If adding to a section, include section_id and column
    if (widgetSectionContext.value) {
      newWidget.section_id = parseInt(widgetSectionContext.value.sectionId);
      newWidget.column = parseInt(widgetSectionContext.value.column);

      // Calculate order based on existing widgets in the same column
      const section = sections.value.find(s => s.id === widgetSectionContext.value.sectionId);
      if (section && section.widgets) {
        const columnWidgets = section.widgets.filter(w => Number(w.column) === newWidget.column);
        newWidget.order = columnWidgets.length + 1;
      } else {
        newWidget.order = 1;
      }
    } else {
      // Adding as standalone widget (not in section)
      newWidget.order = widgets.value.length + 1;
    }

    console.log('Creating widget with data:', JSON.stringify(newWidget, null, 2));
    await footerStore.createWidget(newWidget);
    showSnackbar('Widget added successfully', 'success');
    showWidgetLibrary.value = false;
    widgetSectionContext.value = null;

    // Refresh sections to show new widget
    if (newWidget.section_id) {
      await footerStore.fetchSections();
    } else {
      await loadWidgets();
    }
  } catch (error) {
    console.error('Failed to add widget:', error);
    showSnackbar('Failed to add widget', 'error');
  }
}

function handleAddWidgetToSection({ sectionId, column }) {
  widgetSectionContext.value = { sectionId, column };
  showWidgetLibrary.value = true;
}

async function toggleWidget(widget) {
  try {
    await footerStore.toggleWidget(widget.id);
    showSnackbar(`Widget ${widget.enabled ? 'enabled' : 'disabled'}`, 'success');
  } catch (error) {
    showSnackbar('Failed to toggle widget', 'error');
    widget.enabled = !widget.enabled; // Revert on error
  }
}

async function duplicateWidget(widget) {
  try {
    const newWidget = {
      ...widget,
      id: undefined,
      title: `${widget.title} (Copy)`,
      order: widgets.value.length + 1,
    };
    await footerStore.createWidget(newWidget);
    showSnackbar('Widget duplicated successfully', 'success');
    await loadWidgets();
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
    await footerStore.deleteWidget(widgetToDelete.value.id);
    showSnackbar('Widget deleted successfully', 'success');
    showDeleteDialog.value = false;
    widgetToDelete.value = null;
    await loadWidgets();
  } catch (error) {
    showSnackbar('Failed to delete widget', 'error');
  }
}

function showSnackbar(message, color = 'success') {
  snackbarMessage.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
}

function handleSave() {
  emit('save');
}

// Template loading functions
function loadSimpleFooterTemplate() {
  props.settings.custom_footer_html = `<div class="v-footer v-theme--light bg-transparent" style="padding: 16px;">
  <div class="v-container">
    <div class="text-center">
      <div class="mb-2">
        <strong>{{appName}}</strong>
      </div>
      <div class="text-caption mb-2">
        {{appCopyright}}
      </div>
      <div class="text-caption mb-2" data-if="contactEmail">
        Contact: <a href="mailto:{{contactEmail}}" style="text-decoration: none; color: rgb(var(--v-theme-primary));">{{contactEmail}}</a>
      </div>
    </div>
  </div>
</div>`;
}

function loadComplexFooterTemplate() {
  props.settings.custom_footer_html = `<div class="v-footer v-theme--light bg-transparent" style="padding: 40px 0;">
  <div class="v-container">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 32px; margin-bottom: 24px;">
      <div>
        <div style="font-size: 1.25rem; font-weight: bold; margin-bottom: 8px;">Navigation</div>
        <div style="width: 80px; height: 2px; background: rgb(var(--v-theme-primary)); margin-bottom: 20px;"></div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <a href="/" style="text-decoration: none; color: rgb(var(--v-theme-primary));">Home</a>
          <a href="/about" style="text-decoration: none; color: rgb(var(--v-theme-primary));">About</a>
        </div>
      </div>
    </div>
  </div>
</div>`;
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

.gap-2 {
  gap: 8px;
}
</style>
