<template>
  <div>
    <v-alert type="info" variant="tonal" class="mb-4">
      <div class="text-h6 mb-2">{{ $t('settings.footer.title') }}</div>
      <div>{{ $t('settings.footer.description') }}</div>
    </v-alert>

    <!-- Tabs for Sections and Widgets -->
    <v-tabs v-model="tab" bg-color="transparent" color="primary">
      <v-tab value="sections">
        <v-icon class="mr-2">mdi-view-grid-outline</v-icon>
        {{ $t('settings.footer.sectionsGrid') }}
      </v-tab>
      <v-tab value="widgets">
        <v-icon class="mr-2">mdi-widgets</v-icon>
        {{ $t('settings.footer.allWidgets') }}
      </v-tab>
      <v-tab value="custom">
        <v-icon class="mr-2">mdi-code-tags</v-icon>
        {{ $t('settings.footer.customHtml') }}
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
          <v-col cols="6" md="4">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4 text-primary">{{ sections.length }}</div>
                <div class="text-caption text-grey">{{ $t('settings.footer.sections') }}</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" md="4">
            <v-card>
              <v-card-text class="text-center">
                <div class="text-h4">{{ widgets.length }}</div>
                <div class="text-caption text-grey">{{ $t('settings.footer.totalWidgets') }}</div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="6" md="4">
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
                      icon="mdi-content-copy"
                      size="small"
                      variant="text"
                      @click="duplicateWidget(widget)"
                      :title="$t('settings.footer.duplicateWidget')"
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
      </v-window-item>

      <!-- Custom HTML Tab -->
      <v-window-item value="custom">
        <settings-card icon="mdi-code-tags" :title="$t('settings.footer.customFooterHtml')">
          <v-alert type="info" variant="tonal" class="mb-4" density="compact">
            <div class="text-caption">
              {{ $t('settings.footer.customHtmlDescription') }}
            </div>
          </v-alert>

          <v-switch
            v-model="settings.custom_footer_enabled"
            :label="$t('settings.footer.enableCustomFooter')"
            color="primary"
            class="mb-4"
            :hint="$t('settings.footer.enableCustomFooterHint')"
            persistent-hint
          ></v-switch>

          <template v-if="settings.custom_footer_enabled">
            <v-textarea
              v-model="settings.custom_footer_html"
              :label="$t('settings.footer.customFooterHtml')"
              prepend-inner-icon="mdi-code-tags"
              variant="outlined"
              rows="16"
              :error-messages="errors?.custom_footer_html"
              :hint="$t('settings.footer.customHtmlHint')"
              persistent-hint
              :placeholder="$t('settings.footer.customHtmlPlaceholder')"
              class="mb-4"
            ></v-textarea>

            <div class="d-flex gap-2 mb-4">
              <v-btn
                color="primary"
                variant="outlined"
                @click="loadSimpleFooterTemplate"
                prepend-icon="mdi-code-braces"
              >
                {{ $t('settings.footer.loadSimpleTemplate') }}
              </v-btn>
              <v-btn
                color="primary"
                variant="outlined"
                @click="loadComplexFooterTemplate"
                prepend-icon="mdi-code-tags"
              >
                {{ $t('settings.footer.loadComplexTemplate') }}
              </v-btn>
            </div>

            <v-alert type="info" variant="tonal" class="mb-4">
              <div class="text-body-2">
                <strong>{{ $t('settings.footer.availableVariables') }}:</strong>
                <ul class="mt-2">
                  <li><code v-pre>{{appName}}</code> - {{ $t('settings.footer.varAppName') }}</li>
                  <li><code v-pre>{{appCopyright}}</code> - {{ $t('settings.footer.varCopyright') }}</li>
                  <li><code v-pre>{{contactEmail}}</code> - {{ $t('settings.footer.varContactEmail') }}</li>
                  <li><code v-pre>{{contactPhone}}</code> - {{ $t('settings.footer.varContactPhone') }}</li>
                  <li><code v-pre>{{contactAddress}}</code> - {{ $t('settings.footer.varContactAddress') }}</li>
                  <li><code v-pre>{{socialTwitter}}</code> - {{ $t('settings.footer.varTwitter') }}</li>
                  <li><code v-pre>{{socialFacebook}}</code> - {{ $t('settings.footer.varFacebook') }}</li>
                  <li><code v-pre>{{socialInstagram}}</code> - {{ $t('settings.footer.varInstagram') }}</li>
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
              {{ $t('settings.footer.saveSettings') }}
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useFooterStore } from '@/store/footerStore';
import { getWidgetDefinition } from '@/configs/footerWidgetTypes';
import draggable from 'vuedraggable';
import FooterWidgetEditor from '../footer/FooterWidgetEditor.vue';
import FooterWidgetLibrary from '../footer/FooterWidgetLibrary.vue';
import FooterSectionBuilder from '../footer/FooterSectionBuilder.vue';
import SettingsCard from '../SettingsCard.vue';

const { t } = useI18n();

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
    showSnackbar(t('settings.footer.failedToLoadData'), 'error');
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
    showSnackbar(t('settings.footer.orderUpdated'), 'success');
  } catch (error) {
    showSnackbar(t('settings.footer.failedToUpdateOrder'), 'error');
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
    showSnackbar(t('settings.footer.widgetUpdated'), 'success');
    showEditor.value = false;
    // Reload to show updated data
    await loadWidgets();
  } catch (error) {
    console.error('Failed to save widget:', error);
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
    showSnackbar(t('settings.footer.widgetAdded'), 'success');
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
    showSnackbar(t('settings.footer.failedToAddWidget'), 'error');
  }
}

function handleAddWidgetToSection({ sectionId, column }) {
  widgetSectionContext.value = { sectionId, column };
  showWidgetLibrary.value = true;
}

async function toggleWidget(widget) {
  try {
    await footerStore.toggleWidget(widget.id);
    showSnackbar(widget.enabled ? t('settings.footer.widgetEnabled') : t('settings.footer.widgetDisabled'), 'success');
  } catch (error) {
    showSnackbar(t('settings.footer.failedToToggleWidget'), 'error');
    widget.enabled = !widget.enabled; // Revert on error
  }
}

async function duplicateWidget(widget) {
  try {
    const newWidget = {
      ...widget,
      id: undefined,
      title: `${widget.title} (${t('common.copy')})`,
      order: widgets.value.length + 1,
    };
    await footerStore.createWidget(newWidget);
    showSnackbar(t('settings.footer.widgetDuplicated'), 'success');
    await loadWidgets();
  } catch (error) {
    showSnackbar(t('settings.footer.failedToDuplicateWidget'), 'error');
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
