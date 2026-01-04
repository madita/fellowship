<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="1200px" scrollable persistent>
    <v-card v-if="widget">
      <v-card-title class="d-flex align-center bg-primary text-white">
        <v-icon class="mr-2">{{ widgetIcon }}</v-icon>
        Edit {{ widgetDefinition?.name || widget.type }}
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" variant="text" @click="cancel"></v-btn>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="pa-0" style="height: 600px;">
        <v-row no-gutters style="height: 100%;">
          <!-- Left: Form -->
          <v-col cols="12" md="7" class="pa-4" style="overflow-y: auto; height: 100%;">
            <v-text-field
              v-model="editedWidget.title"
              label="Widget Title"
              hint="Display name for this widget in admin"
              persistent-hint
              class="mb-4"
            ></v-text-field>

            <v-divider class="my-4"></v-divider>

            <div class="text-h6 mb-4">Widget Content</div>

            <!-- Dynamic form based on widget type -->
            <dynamic-widget-form
              v-model="editedWidget.content"
              :widget-type="widget.type"
            />
          </v-col>

          <!-- Right: Preview -->
          <v-col cols="12" md="5" class="bg-grey-lighten-4 pa-4" style="overflow-y: auto; height: 100%;">
            <div class="sticky-top">
              <div class="text-subtitle-1 font-weight-bold mb-2">
                <v-icon class="mr-1">mdi-eye</v-icon>
                Preview
              </div>
              <v-divider class="mb-4"></v-divider>

              <v-sheet class="preview-container" elevation="2">
                <div v-if="previewError" class="pa-8 text-center">
                  <v-icon size="48" color="error">mdi-alert-circle</v-icon>
                  <p class="text-subtitle-1 mt-2 text-error">Preview Error</p>
                  <p class="text-caption">{{ previewError }}</p>
                  <v-btn size="small" class="mt-2" @click="previewError = null">Retry</v-btn>
                </div>
                <div v-else-if="!widgetComponent" class="pa-8 text-center text-grey">
                  <v-icon size="48" color="grey">mdi-alert-circle</v-icon>
                  <p class="text-subtitle-1 mt-2">Preview not available</p>
                  <p class="text-caption">Widget type: {{ widget?.type }}</p>
                </div>
                <div v-else-if="!editedWidget || !editedWidget.content" class="pa-8 text-center text-grey">
                  <v-progress-circular indeterminate></v-progress-circular>
                  <p class="text-caption mt-2">Loading preview...</p>
                </div>
                <component
                  v-else
                  :is="widgetComponent"
                  :content="editedWidget.content"
                  :config="editedWidget.config || {}"
                />
              </v-sheet>

              <v-alert type="info" variant="tonal" class="mt-4" density="compact">
                <div class="text-caption">
                  Changes are previewed in real-time. Click "Save" to apply them to your homepage.
                </div>
              </v-alert>
            </div>
          </v-col>
        </v-row>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4">
        <v-btn @click="cancel">Cancel</v-btn>
        <v-spacer></v-spacer>
        <v-btn color="primary" @click="save">
          <v-icon class="mr-1">mdi-content-save</v-icon>
          Save Changes
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch, onErrorCaptured } from 'vue';
import { getWidgetDefinition } from '@/configs/widgetTypes';
import { getWidgetComponent } from '@/components/landing/widgets';
import DynamicWidgetForm from './DynamicWidgetForm.vue';

const props = defineProps({
  modelValue: Boolean,
  widget: Object
});

const emit = defineEmits(['update:modelValue', 'save']);

const editedWidget = ref(null);
const previewError = ref(null);

// Capture errors in preview component
onErrorCaptured((err) => {
  previewError.value = err.message;
  console.error('Widget preview error:', err);
  return false;
});

// Watch for content changes to clear preview errors
watch(() => editedWidget.value?.content, () => {
  // Clear error when content changes (user is fixing it)
  if (previewError.value) {
    previewError.value = null;
  }
}, { deep: true });

const widgetDefinition = computed(() => {
  return props.widget ? getWidgetDefinition(props.widget.type) : null;
});

const widgetIcon = computed(() => widgetDefinition.value?.icon || 'mdi-widgets');

const widgetComponent = computed(() => {
  if (!props.widget) return null;
  const component = getWidgetComponent(props.widget.type);
  if (!component) {
    console.warn(`No component found for widget type: ${props.widget.type}`);
  }
  return component;
});

// Debug preview content updates
const previewContent = computed(() => {
  if (!editedWidget.value?.content) return {};
  return editedWidget.value.content;
});

watch(previewContent, (newContent) => {
  console.log('Preview content updated:', props.widget?.type, newContent);
}, { deep: true });

watch(() => props.widget, (newWidget) => {
  if (newWidget) {
    previewError.value = null; // Clear any previous errors

    const cloned = JSON.parse(JSON.stringify(newWidget));

    // Merge with default content to ensure all fields exist
    const definition = getWidgetDefinition(newWidget.type);
    if (definition && definition.defaultContent) {
      // Use default content as base, then override with actual content
      // This ensures missing fields are filled in, but existing data is preserved
      cloned.content = mergeContent(definition.defaultContent, cloned.content || {});
    }

    // Ensure config exists
    if (!cloned.config) {
      cloned.config = {};
    }

    editedWidget.value = cloned;
  }
}, { immediate: true });

// Helper function to merge content with defaults
function mergeContent(defaults, actual) {
  const merged = { ...defaults };

  for (const key in actual) {
    if (actual[key] !== null && actual[key] !== undefined) {
      // If both are objects (but not arrays), merge recursively
      if (
        typeof actual[key] === 'object' &&
        typeof defaults[key] === 'object' &&
        !Array.isArray(actual[key]) &&
        !Array.isArray(defaults[key]) &&
        actual[key] !== null &&
        defaults[key] !== null
      ) {
        merged[key] = mergeContent(defaults[key], actual[key]);
      } else {
        // For arrays and primitives, use actual value
        merged[key] = actual[key];
      }
    }
  }

  return merged;
}

function save() {
  emit('save', editedWidget.value);
}

function cancel() {
  emit('update:modelValue', false);
}
</script>

<style scoped>
.preview-container {
  min-height: 200px;
  max-height: 500px;
  background: white;
  border-radius: 4px;
  overflow-y: auto;
}

.preview-container :deep(*) {
  /* Scale down content for preview */
  font-size: 0.85em;
}

.sticky-top {
  position: sticky;
  top: 0;
}
</style>
