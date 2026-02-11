<template>
  <div>
    <!-- Action Buttons -->
    <div class="d-flex justify-space-between align-center mb-4">
      <v-btn color="primary" prepend-icon="mdi-plus" @click="showAddSectionDialog = true">
        {{ $t('settings.homepageBuilder.addSection') }}
      </v-btn>
      <v-btn prepend-icon="mdi-refresh" @click="loadSections" :loading="isLoading">
        {{ $t('settings.homepageBuilder.refresh') }}
      </v-btn>
    </div>

    <!-- Sections List with Drag-and-Drop -->
    <v-card v-if="isLoading" class="pa-8 text-center">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
      <div class="mt-2">{{ $t('settings.homepageBuilder.loadingSections') }}</div>
    </v-card>

    <div v-else-if="sections.length === 0" class="text-center py-8">
      <v-icon size="64" color="grey">mdi-view-grid-outline</v-icon>
      <div class="text-h6 mt-4">{{ $t('settings.homepageBuilder.noSectionsYet') }}</div>
      <div class="text-caption text-grey mb-4">{{ $t('settings.homepageBuilder.createSectionsHint') }}</div>
      <v-btn color="primary" @click="showAddSectionDialog = true">{{ $t('settings.homepageBuilder.addFirstSection') }}</v-btn>
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
                {{ $t('settings.homepageBuilder.layout') }}: {{ getLayoutLabel(section.layout) }} | {{ $t('settings.homepageBuilder.order') }}: {{ section.order }}
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
              :title="$t('settings.homepageBuilder.editSection')"
            ></v-btn>
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="confirmDeleteSection(section)"
              :title="$t('settings.homepageBuilder.deleteSection')"
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
                    {{ $t('settings.homepageBuilder.column') }} {{ colIndex + 1 }}
                    <v-btn
                      size="x-small"
                      variant="text"
                      color="primary"
                      prepend-icon="mdi-plus"
                      @click="addWidgetToColumn(section, colIndex + 1)"
                    >
                      {{ $t('settings.homepageBuilder.addWidget') }}
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
        <v-card-title>{{ editingSection ? $t('settings.homepageBuilder.editSection') : $t('settings.homepageBuilder.addSection') }}</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="sectionFormData.title"
            :label="$t('settings.homepageBuilder.sectionTitle')"
            :hint="$t('settings.homepageBuilder.sectionTitleHint')"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <v-text-field
            v-model="sectionFormData.anchor_id"
            :label="$t('settings.homepageBuilder.anchorId')"
            :hint="$t('settings.homepageBuilder.anchorIdHint')"
            persistent-hint
            prefix="#"
            :rules="[v => !v || /^[a-z0-9-]+$/.test(v) || t('settings.homepageBuilder.anchorIdRule')]"
            class="mb-4"
          ></v-text-field>

          <v-select
            v-model="sectionFormData.layout"
            :label="$t('settings.homepageBuilder.layout')"
            :items="layoutOptions"
            persistent-hint
            :hint="$t('settings.homepageBuilder.layoutHint')"
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

          <v-divider class="my-4"></v-divider>
          <div class="text-subtitle-2 mb-3">{{ $t('settings.homepageBuilder.sectionStyling') }}</div>

          <v-text-field
            v-model="sectionFormData.background"
            :label="$t('settings.homepageBuilder.backgroundClass')"
            :hint="$t('settings.homepageBuilder.backgroundClassHint')"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <v-text-field
            v-model="sectionFormData.backgroundColor"
            :label="$t('settings.homepageBuilder.backgroundColor')"
            :hint="$t('settings.homepageBuilder.backgroundColorHint')"
            persistent-hint
            type="color"
            class="mb-4"
          >
            <template #append-inner>
              <v-btn
                v-if="sectionFormData.backgroundColor"
                icon="mdi-close"
                size="x-small"
                variant="text"
                @click="sectionFormData.backgroundColor = ''"
              ></v-btn>
            </template>
          </v-text-field>

          <v-switch
            v-model="sectionFormData.fullWidth"
            :label="$t('settings.homepageBuilder.fullWidthSection')"
            :hint="$t('settings.homepageBuilder.fullWidthHint')"
            persistent-hint
            color="primary"
            class="mb-2"
          ></v-switch>

          <v-switch
            v-model="sectionFormData.showDecoration"
            :label="$t('settings.homepageBuilder.showSvgDecoration')"
            :hint="$t('settings.homepageBuilder.svgDecorationHint')"
            persistent-hint
            color="primary"
            class="mb-2"
          ></v-switch>

          <template v-if="sectionFormData.showDecoration">
            <v-select
              v-model="sectionFormData.decorationStyle"
              :label="$t('settings.homepageBuilder.decorationStyle')"
              :items="decorationStyles"
              :hint="$t('settings.homepageBuilder.decorationStyleHint')"
              persistent-hint
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
              v-model="sectionFormData.decorationColor"
              :label="$t('settings.homepageBuilder.decorationFillColor')"
              :hint="$t('settings.homepageBuilder.decorationFillColorHint')"
              persistent-hint
              type="color"
              class="mb-4"
            >
              <template #append-inner>
                <v-btn
                  v-if="sectionFormData.decorationColor"
                  icon="mdi-close"
                  size="x-small"
                  variant="text"
                  @click="sectionFormData.decorationColor = '#f2f5f8'"
                ></v-btn>
              </template>
            </v-text-field>

            <v-radio-group
              v-model="sectionFormData.decorationBackgroundType"
              :label="$t('settings.homepageBuilder.backgroundType')"
              :hint="$t('settings.homepageBuilder.backgroundTypeHint')"
              persistent-hint
              inline
              class="mb-2"
            >
              <v-radio :label="$t('settings.homepageBuilder.customColor')" value="custom"></v-radio>
              <v-radio :label="$t('settings.homepageBuilder.themeColor')" value="theme"></v-radio>
              <v-radio :label="$t('settings.homepageBuilder.transparent')" value="transparent"></v-radio>
            </v-radio-group>

            <v-text-field
              v-if="sectionFormData.decorationBackgroundType === 'custom'"
              v-model="sectionFormData.decorationBackgroundColor"
              :label="$t('settings.homepageBuilder.decorationBackgroundColor')"
              :hint="$t('settings.homepageBuilder.decorationBackgroundColorHint')"
              persistent-hint
              type="color"
              class="mb-4"
            >
              <template #append-inner>
                <v-btn
                  v-if="sectionFormData.decorationBackgroundColor"
                  icon="mdi-close"
                  size="x-small"
                  variant="text"
                  @click="sectionFormData.decorationBackgroundColor = ''"
                ></v-btn>
              </template>
            </v-text-field>

            <v-select
              v-if="sectionFormData.decorationBackgroundType === 'theme'"
              v-model="sectionFormData.decorationBackgroundTheme"
              :label="$t('settings.homepageBuilder.themeBackground')"
              :items="themeBackgrounds"
              :hint="$t('settings.homepageBuilder.themeBackgroundHint')"
              persistent-hint
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
          </template>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="cancelSectionEdit">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="primary" @click="saveSection">{{ editingSection ? $t('settings.homepageBuilder.update') : $t('settings.homepageBuilder.add') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Section Confirmation -->
    <v-dialog v-model="showDeleteSectionDialog" max-width="500">
      <v-card>
        <v-card-title>{{ $t('settings.homepageBuilder.confirmDelete') }}</v-card-title>
        <v-card-text>
          {{ $t('settings.homepageBuilder.deleteSectionConfirm', { title: sectionToDelete?.title }) }}
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showDeleteSectionDialog = false">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="error" @click="deleteSection">{{ $t('common.delete') }}</v-btn>
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
import { useI18n } from 'vue-i18n';
import { useHomepageStore } from '@/store/homepageStore';
import { getWidgetDefinition } from '@/configs/widgetTypes';
import draggable from 'vuedraggable';

const { t } = useI18n();
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

const layoutOptions = computed(() => [
  { value: '1-col', title: t('settings.homepageBuilder.layout1Col'), icon: 'mdi-rectangle' },
  { value: '2-col', title: t('settings.homepageBuilder.layout2Col'), icon: 'mdi-view-column' },
  { value: '3-col', title: t('settings.homepageBuilder.layout3Col'), icon: 'mdi-view-grid' },
  { value: '4-col', title: t('settings.homepageBuilder.layout4Col'), icon: 'mdi-view-grid-outline' },
  { value: '2-1-col', title: t('settings.homepageBuilder.layout21Col'), icon: 'mdi-view-split-vertical' },
  { value: '1-2-col', title: t('settings.homepageBuilder.layout12Col'), icon: 'mdi-view-split-vertical' },
]);

const decorationStyles = computed(() => [
  { value: 'wave1', title: t('settings.homepageBuilder.wave1'), icon: 'mdi-wave' },
  { value: 'wave2', title: t('settings.homepageBuilder.wave2'), icon: 'mdi-wave' },
  { value: 'wave3', title: t('settings.homepageBuilder.wave3'), icon: 'mdi-wave' },
  { value: 'curve1', title: t('settings.homepageBuilder.curve1'), icon: 'mdi-chart-line-variant' },
  { value: 'curve2', title: t('settings.homepageBuilder.curve2'), icon: 'mdi-chart-line-variant' },
  { value: 'angle1', title: t('settings.homepageBuilder.angle1'), icon: 'mdi-triangle' },
  { value: 'angle2', title: t('settings.homepageBuilder.angle2'), icon: 'mdi-triangle-outline' },
]);

const themeBackgrounds = computed(() => [
  { value: 'background', title: t('settings.homepageBuilder.bgMain'), icon: 'mdi-palette' },
  { value: 'surface', title: t('settings.homepageBuilder.bgSurface'), icon: 'mdi-palette-outline' },
  { value: 'surface-variant', title: t('settings.homepageBuilder.bgSurfaceVariant'), icon: 'mdi-palette-swatch' },
  { value: 'surface-bright', title: t('settings.homepageBuilder.bgSurfaceBright'), icon: 'mdi-brightness-7' },
  { value: 'surface-dim', title: t('settings.homepageBuilder.bgSurfaceDim'), icon: 'mdi-brightness-5' },
]);

const showAddSectionDialog = ref(false);
const showDeleteSectionDialog = ref(false);
const editingSection = ref(null);
const sectionToDelete = ref(null);

const sectionFormData = ref({
  title: '',
  anchor_id: '',
  layout: '1-col',
  background: '',
  backgroundColor: '',
  fullWidth: false,
  showDecoration: false,
  decorationStyle: 'wave1',
  decorationColor: '#f2f5f8',
  decorationBackgroundType: 'theme',
  decorationBackgroundColor: '',
  decorationBackgroundTheme: 'background'
});

const snackbar = ref(false);
const snackbarMessage = ref('');
const snackbarColor = ref('success');

function getLayoutLabel(layout) {
  return layoutOptions.value.find(opt => opt.value === layout)?.title || layout;
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
    showSnackbar(t('settings.homepageBuilder.failedToLoadSections'), 'error');
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
    showSnackbar(t('settings.homepageBuilder.sectionsReordered'), 'success');
  } catch (error) {
    showSnackbar(t('settings.homepageBuilder.failedToReorderSections'), 'error');
    await loadSections();
  }
}

function editSection(section) {
  editingSection.value = section;
  sectionFormData.value = {
    title: section.title || '',
    anchor_id: section.anchor_id || '',
    layout: section.layout,
    background: section.config?.background || '',
    backgroundColor: section.config?.backgroundColor || '',
    fullWidth: section.config?.fullWidth || false,
    showDecoration: section.config?.showDecoration || false,
    decorationStyle: section.config?.decorationStyle || 'wave1',
    decorationColor: section.config?.decorationColor || '#f2f5f8',
    decorationBackgroundType: section.config?.decorationBackgroundType || 'theme',
    decorationBackgroundColor: section.config?.decorationBackgroundColor || '',
    decorationBackgroundTheme: section.config?.decorationBackgroundTheme || 'background'
  };
  showAddSectionDialog.value = true;
}

async function saveSection() {
  if (!sectionFormData.value.layout) {
    showSnackbar(t('settings.homepageBuilder.pleaseSelectLayout'), 'error');
    return;
  }

  try {
    const sectionData = {
      title: sectionFormData.value.title,
      anchor_id: sectionFormData.value.anchor_id || null,
      layout: sectionFormData.value.layout,
      enabled: true,
      order: editingSection.value ? editingSection.value.order : sections.value.length + 1,
      config: {
        background: sectionFormData.value.background,
        backgroundColor: sectionFormData.value.backgroundColor,
        fullWidth: sectionFormData.value.fullWidth,
        showDecoration: sectionFormData.value.showDecoration,
        decorationStyle: sectionFormData.value.decorationStyle,
        decorationColor: sectionFormData.value.decorationColor,
        decorationBackgroundType: sectionFormData.value.decorationBackgroundType,
        decorationBackgroundColor: sectionFormData.value.decorationBackgroundColor,
        decorationBackgroundTheme: sectionFormData.value.decorationBackgroundTheme
      }
    };

    if (editingSection.value) {
      await homepageStore.updateSection(editingSection.value.id, sectionData);
      showSnackbar(t('settings.homepageBuilder.sectionUpdated'), 'success');
    } else {
      await homepageStore.createSection(sectionData);
      showSnackbar(t('settings.homepageBuilder.sectionAdded'), 'success');
    }

    cancelSectionEdit();
    await loadSections();
  } catch (error) {
    showSnackbar(t('settings.homepageBuilder.failedToSaveSection'), 'error');
  }
}

function cancelSectionEdit() {
  showAddSectionDialog.value = false;
  editingSection.value = null;
  sectionFormData.value = {
    title: '',
    anchor_id: '',
    layout: '1-col',
    background: '',
    backgroundColor: '',
    fullWidth: false,
    showDecoration: false,
    decorationStyle: 'wave1',
    decorationColor: '#f2f5f8',
    decorationBackgroundType: 'theme',
    decorationBackgroundColor: '',
    decorationBackgroundTheme: 'background'
  };
}

async function toggleSection(section) {
  try {
    await homepageStore.toggleSection(section.id);
    showSnackbar(section.enabled ? t('settings.homepageBuilder.sectionEnabled') : t('settings.homepageBuilder.sectionDisabled'), 'success');
  } catch (error) {
    showSnackbar(t('settings.homepageBuilder.failedToToggleSection'), 'error');
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
    showSnackbar(t('settings.homepageBuilder.sectionDeleted'), 'success');
    showDeleteSectionDialog.value = false;
    sectionToDelete.value = null;
    await loadSections();
  } catch (error) {
    showSnackbar(t('settings.homepageBuilder.failedToDeleteSection'), 'error');
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
    showSnackbar(t('settings.homepageBuilder.widgetDeleted'), 'success');
    await loadSections();
  } catch (error) {
    showSnackbar(t('settings.homepageBuilder.failedToDeleteWidget'), 'error');
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

    showSnackbar(t('settings.homepageBuilder.widgetPositionsUpdated'), 'success');
    await loadSections();
  } catch (error) {
    showSnackbar(t('settings.homepageBuilder.failedToUpdateWidgetPositions'), 'error');
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

/* Drop zone placeholder is handled via template */

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
