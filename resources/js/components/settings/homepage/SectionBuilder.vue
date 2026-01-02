<template>
  <div>
    <!-- Action Buttons -->
    <div class="d-flex justify-space-between align-center mb-4">
      <v-btn color="primary" prepend-icon="mdi-plus" @click="showAddSectionDialog = true">
        Add Section
      </v-btn>
      <v-btn prepend-icon="mdi-refresh" @click="loadSections" :loading="isLoading">
        Refresh
      </v-btn>
    </div>

    <!-- Sections List with Drag-and-Drop -->
    <v-card v-if="isLoading" class="pa-8 text-center">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
      <div class="mt-2">Loading sections...</div>
    </v-card>

    <div v-else-if="sections.length === 0" class="text-center py-8">
      <v-icon size="64" color="grey">mdi-view-grid-outline</v-icon>
      <div class="text-h6 mt-4">No sections yet</div>
      <div class="text-caption text-grey mb-4">Create sections to organize your homepage widgets</div>
      <v-btn color="primary" @click="showAddSectionDialog = true">Add Your First Section</v-btn>
    </div>

    <draggable
      v-else
      v-model="sections"
      item-key="id"
      handle=".section-drag-handle"
      @end="onSectionDragEnd"
    >
      <template #item="{ element: section }">
        <v-card class="mb-4" :class="{ 'section-disabled': !section.enabled }">
          <!-- Section Header -->
          <v-card-title class="d-flex align-center bg-grey-lighten-4">
            <v-icon class="section-drag-handle mr-2" style="cursor: grab;">mdi-drag-vertical</v-icon>
            <div class="flex-grow-1">
              <div class="text-h6">{{ section.title || `Section ${section.order}` }}</div>
              <div class="text-caption text-grey">
                Layout: {{ getLayoutLabel(section.layout) }} | Order: {{ section.order }}
              </div>
            </div>
            <v-switch
              v-model="section.enabled"
              hide-details
              density="compact"
              color="success"
              class="mr-2"
              @change="toggleSection(section)"
            ></v-switch>
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              @click="editSection(section)"
              title="Edit Section"
            ></v-btn>
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="confirmDeleteSection(section)"
              title="Delete Section"
            ></v-btn>
          </v-card-title>

          <!-- Section Grid with Columns -->
          <v-card-text class="pa-4">
            <v-row>
              <v-col
                v-for="(colWidth, colIndex) in getColumnWidths(section.layout)"
                :key="colIndex"
                :cols="12"
                :md="colWidth"
              >
                <div class="column-container pa-3">
                  <div class="text-caption text-grey mb-2">
                    Column {{ colIndex + 1 }}
                    <v-btn
                      size="x-small"
                      variant="text"
                      color="primary"
                      prepend-icon="mdi-plus"
                      @click="addWidgetToColumn(section, colIndex + 1)"
                    >
                      Add Widget
                    </v-btn>
                  </div>

                  <!-- Widgets in this column -->
                  <draggable
                    :model-value="getWidgetsForColumn(section, colIndex + 1)"
                    @update:model-value="updateColumnWidgets(section, colIndex + 1, $event)"
                    item-key="id"
                    group="widgets"
                    handle=".widget-drag-handle"
                    @end="onWidgetDragEnd"
                    class="widget-drop-zone"
                    :class="{ 'widget-drop-zone-empty': getWidgetsForColumn(section, colIndex + 1).length === 0 }"
                  >
                    <template #item="{ element: widget }">
                      <v-card
                        class="widget-card mb-2"
                        variant="outlined"
                        :class="{ 'widget-disabled': !widget.enabled }"
                      >
                        <v-card-text class="pa-2 d-flex align-center">
                          <v-icon class="widget-drag-handle mr-2" size="small" style="cursor: grab;">mdi-drag</v-icon>
                          <v-icon :color="getWidgetColor(widget.type)" class="mr-2">{{ getWidgetIcon(widget.type) }}</v-icon>
                          <div class="flex-grow-1">
                            <div class="text-body-2">{{ widget.title || widget.type }}</div>
                          </div>
                          <v-btn
                            icon="mdi-pencil"
                            size="x-small"
                            variant="text"
                            @click="editWidget(widget)"
                          ></v-btn>
                          <v-btn
                            icon="mdi-delete"
                            size="x-small"
                            variant="text"
                            color="error"
                            @click="deleteWidget(widget)"
                          ></v-btn>
                        </v-card-text>
                      </v-card>
                    </template>
                  </draggable>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </template>
    </draggable>

    <!-- Add/Edit Section Dialog -->
    <v-dialog v-model="showAddSectionDialog" max-width="600px">
      <v-card>
        <v-card-title>{{ editingSection ? 'Edit' : 'Add' }} Section</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="sectionFormData.title"
            label="Section Title"
            hint="Optional: Name for this section"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <v-select
            v-model="sectionFormData.layout"
            label="Layout"
            :items="layoutOptions"
            persistent-hint
            hint="Choose how many columns this section should have"
            class="mb-4"
          >
            <template #item="{ props, item }">
              <v-list-item v-bind="props">
                <template #prepend>
                  <v-icon>{{ item.raw.icon }}</v-icon>
                </template>
              </v-list-item>
            </template>
          </v-select>

          <v-text-field
            v-model="sectionFormData.background"
            label="Background Class"
            hint="Vuetify class (e.g., 'bg-grey-lighten-4', 'bg-primary')"
            persistent-hint
            class="mb-4"
          ></v-text-field>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="cancelSectionEdit">Cancel</v-btn>
          <v-btn color="primary" @click="saveSection">{{ editingSection ? 'Update' : 'Add' }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Section Confirmation -->
    <v-dialog v-model="showDeleteSectionDialog" max-width="500">
      <v-card>
        <v-card-title>Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete the section "{{ sectionToDelete?.title }}"?
          All widgets in this section will also be deleted.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showDeleteSectionDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteSection">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
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

const emit = defineEmits(['edit-widget', 'add-widget']);

const homepageStore = useHomepageStore();
const isLoading = ref(true);

const sections = computed({
  get: () => homepageStore.orderedSections,
  set: (value) => {
    value.forEach((section, index) => {
      section.order = index + 1;
    });
  }
});

const layoutOptions = [
  { value: '1-col', title: '1 Column (Full Width)', icon: 'mdi-rectangle' },
  { value: '2-col', title: '2 Columns (Equal)', icon: 'mdi-view-column' },
  { value: '3-col', title: '3 Columns (Equal)', icon: 'mdi-view-grid' },
  { value: '4-col', title: '4 Columns (Equal)', icon: 'mdi-view-grid-outline' },
  { value: '2-1-col', title: '2 Columns (66% / 33%)', icon: 'mdi-view-split-vertical' },
  { value: '1-2-col', title: '2 Columns (33% / 66%)', icon: 'mdi-view-split-vertical' },
];

const showAddSectionDialog = ref(false);
const showDeleteSectionDialog = ref(false);
const editingSection = ref(null);
const sectionToDelete = ref(null);

const sectionFormData = ref({
  title: '',
  layout: '1-col',
  background: ''
});

const snackbar = ref(false);
const snackbarMessage = ref('');
const snackbarColor = ref('success');

function getLayoutLabel(layout) {
  return layoutOptions.find(opt => opt.value === layout)?.title || layout;
}

function getColumnWidths(layout) {
  const layoutMap = {
    '1-col': [12],
    '2-col': [6, 6],
    '3-col': [4, 4, 4],
    '4-col': [3, 3, 3, 3],
    '2-1-col': [8, 4],
    '1-2-col': [4, 8],
  };
  return layoutMap[layout] || [12];
}

function getWidgetsForColumn(section, columnNumber) {
  if (!section.widgets) return [];
  return section.widgets
    .filter(w => w.column === columnNumber)
    .sort((a, b) => a.order - b.order);
}

function getWidgetIcon(type) {
  const definition = getWidgetDefinition(type);
  return definition?.icon || 'mdi-widgets';
}

function getWidgetColor(type) {
  const definition = getWidgetDefinition(type);
  return definition?.color || 'grey';
}

async function loadSections() {
  isLoading.value = true;
  try {
    await homepageStore.fetchSections();
  } catch (error) {
    showSnackbar('Failed to load sections', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function onSectionDragEnd() {
  try {
    const newOrder = sections.value.map((section, index) => ({
      id: section.id,
      order: index + 1
    }));
    await homepageStore.reorderSections(newOrder);
    showSnackbar('Sections reordered successfully', 'success');
  } catch (error) {
    showSnackbar('Failed to reorder sections', 'error');
    await loadSections();
  }
}

function editSection(section) {
  editingSection.value = section;
  sectionFormData.value = {
    title: section.title || '',
    layout: section.layout,
    background: section.config?.background || ''
  };
  showAddSectionDialog.value = true;
}

async function saveSection() {
  if (!sectionFormData.value.layout) {
    showSnackbar('Please select a layout', 'error');
    return;
  }

  try {
    const sectionData = {
      title: sectionFormData.value.title,
      layout: sectionFormData.value.layout,
      enabled: true,
      order: editingSection.value ? editingSection.value.order : sections.value.length + 1,
      config: {
        background: sectionFormData.value.background
      }
    };

    if (editingSection.value) {
      await homepageStore.updateSection(editingSection.value.id, sectionData);
      showSnackbar('Section updated successfully', 'success');
    } else {
      await homepageStore.createSection(sectionData);
      showSnackbar('Section added successfully', 'success');
    }

    cancelSectionEdit();
    await loadSections();
  } catch (error) {
    showSnackbar('Failed to save section', 'error');
  }
}

function cancelSectionEdit() {
  showAddSectionDialog.value = false;
  editingSection.value = null;
  sectionFormData.value = { title: '', layout: '1-col', background: '' };
}

async function toggleSection(section) {
  try {
    await homepageStore.toggleSection(section.id);
    showSnackbar(`Section ${section.enabled ? 'enabled' : 'disabled'}`, 'success');
  } catch (error) {
    showSnackbar('Failed to toggle section', 'error');
    section.enabled = !section.enabled;
  }
}

function confirmDeleteSection(section) {
  sectionToDelete.value = section;
  showDeleteSectionDialog.value = true;
}

async function deleteSection() {
  try {
    await homepageStore.deleteSection(sectionToDelete.value.id);
    showSnackbar('Section deleted successfully', 'success');
    showDeleteSectionDialog.value = false;
    sectionToDelete.value = null;
    await loadSections();
  } catch (error) {
    showSnackbar('Failed to delete section', 'error');
  }
}

function addWidgetToColumn(section, column) {
  emit('add-widget', { sectionId: section.id, column });
}

function editWidget(widget) {
  emit('edit-widget', widget);
}

async function deleteWidget(widget) {
  try {
    await homepageStore.deleteWidget(widget.id);
    showSnackbar('Widget deleted successfully', 'success');
    await loadSections();
  } catch (error) {
    showSnackbar('Failed to delete widget', 'error');
  }
}

function updateColumnWidgets(section, columnNumber, newWidgets) {
  // This is called when widgets are dragged between columns
  // We need to update all affected widgets
  newWidgets.forEach((widget, index) => {
    widget.column = columnNumber;
    widget.order = index + 1;
    widget.section_id = section.id;
  });
}

async function onWidgetDragEnd() {
  // After drag-drop, save all widget positions
  try {
    // Collect all widgets from all sections/columns with their new positions
    const updates = [];
    sections.value.forEach(section => {
      const layoutWidths = getColumnWidths(section.layout);
      layoutWidths.forEach((_, colIndex) => {
        const columnWidgets = getWidgetsForColumn(section, colIndex + 1);
        columnWidgets.forEach((widget, widgetIndex) => {
          homepageStore.updateWidget(widget.id, {
            section_id: section.id,
            column: colIndex + 1,
            order: widgetIndex + 1
          });
        });
      });
    });

    showSnackbar('Widget positions updated', 'success');
    await loadSections();
  } catch (error) {
    showSnackbar('Failed to update widget positions', 'error');
    await loadSections();
  }
}

function showSnackbar(message, color = 'success') {
  snackbarMessage.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
}

onMounted(async () => {
  await loadSections();
});
</script>

<style scoped>
.section-disabled {
  opacity: 0.6;
}

.section-drag-handle:hover {
  cursor: grab;
}

.section-drag-handle:active {
  cursor: grabbing;
}

.column-container {
  border: 2px dashed rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  min-height: 100px;
  background: rgba(var(--v-theme-surface-variant), 0.3);
}

.widget-drop-zone {
  min-height: 60px;
}

.widget-drop-zone-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(var(--v-theme-on-surface), 0.38);
}

.widget-drop-zone-empty::before {
  content: 'Drop widgets here';
}

.widget-card {
  transition: all 0.2s;
}

.widget-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.widget-disabled {
  opacity: 0.5;
}

.widget-drag-handle:hover {
  cursor: grab;
}

.widget-drag-handle:active {
  cursor: grabbing;
}
</style>
