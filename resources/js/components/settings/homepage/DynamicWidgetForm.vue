<template>
  <div>
    <!-- Render fields based on schema -->
    <div v-for="(field, key) in schema" :key="key" class="mb-4">
      <!-- Text Field -->
      <v-text-field
        v-if="field.type === 'text'"
        v-model="localValue[key]"
        :label="field.label"
        :required="field.required"
        :hint="field.hint"
        persistent-hint
      ></v-text-field>

      <!-- Textarea -->
      <v-textarea
        v-else-if="field.type === 'textarea'"
        v-model="localValue[key]"
        :label="field.label"
        :required="field.required"
        :hint="field.hint"
        :rows="field.rows || 3"
        persistent-hint
        auto-grow
      ></v-textarea>

      <!-- Number -->
      <v-text-field
        v-else-if="field.type === 'number'"
        v-model.number="localValue[key]"
        :label="field.label"
        type="number"
        :min="field.min"
        :max="field.max"
        :required="field.required"
      ></v-text-field>

      <!-- Boolean -->
      <v-switch
        v-else-if="field.type === 'boolean'"
        v-model="localValue[key]"
        :label="field.label"
        color="primary"
        hide-details
      ></v-switch>

      <!-- Icon Picker -->
      <v-text-field
        v-else-if="field.type === 'icon'"
        v-model="localValue[key]"
        :label="field.label"
        hint="Material Design Icon name (e.g., mdi-home)"
        persistent-hint
        :prepend-icon="localValue[key] || 'mdi-checkbox-blank-circle-outline'"
      ></v-text-field>

      <!-- Select -->
      <v-select
        v-else-if="field.type === 'select'"
        v-model="localValue[key]"
        :label="field.label"
        :items="field.options"
        :required="field.required"
      ></v-select>

      <!-- Object (nested fields) -->
      <v-card v-else-if="field.type === 'object'" variant="outlined" class="pa-4">
        <div class="text-subtitle-2 mb-3">{{ field.label }}</div>
        <div v-for="(subField, subKey) in field.fields" :key="subKey" class="mb-3">
          <v-text-field
            v-if="subField.type === 'text'"
            v-model="localValue[key][subKey]"
            :label="subField.label"
            density="comfortable"
          ></v-text-field>
        </div>
      </v-card>

      <!-- Array (list of items) -->
      <v-card v-else-if="field.type === 'array'" variant="outlined" class="pa-4">
        <div class="d-flex justify-space-between align-center mb-3">
          <div class="text-subtitle-2">{{ field.label }}</div>
          <v-btn
            size="small"
            color="primary"
            prepend-icon="mdi-plus"
            @click="addArrayItem(key, field)"
          >
            Add Item
          </v-btn>
        </div>

        <draggable
          v-model="localValue[key]"
          item-key="id"
          handle=".array-drag-handle"
        >
          <template #item="{ element: item, index }">
            <v-card class="mb-3" variant="tonal">
              <v-card-text>
                <div class="d-flex align-center mb-2">
                  <v-icon class="array-drag-handle mr-2" style="cursor: grab;">mdi-drag-vertical</v-icon>
                  <div class="text-caption text-grey">Item {{ index + 1 }}</div>
                  <v-spacer></v-spacer>
                  <v-btn
                    icon="mdi-delete"
                    size="x-small"
                    variant="text"
                    color="error"
                    @click="removeArrayItem(key, index)"
                  ></v-btn>
                </div>

                <!-- Array item fields -->
                <div v-for="(itemField, itemKey) in field.itemSchema" :key="itemKey" class="mb-2">
                  <v-text-field
                    v-if="itemField.type === 'text'"
                    v-model="item[itemKey]"
                    :label="itemField.label"
                    :required="itemField.required"
                    density="comfortable"
                    hide-details
                  ></v-text-field>

                  <v-textarea
                    v-else-if="itemField.type === 'textarea'"
                    v-model="item[itemKey]"
                    :label="itemField.label"
                    :required="itemField.required"
                    density="comfortable"
                    rows="2"
                    hide-details
                  ></v-textarea>

                  <v-text-field
                    v-else-if="itemField.type === 'icon'"
                    v-model="item[itemKey]"
                    :label="itemField.label"
                    density="comfortable"
                    hide-details
                    :prepend-icon="item[itemKey] || 'mdi-checkbox-blank-circle-outline'"
                  ></v-text-field>

                  <v-text-field
                    v-else-if="itemField.type === 'number'"
                    v-model.number="item[itemKey]"
                    :label="itemField.label"
                    type="number"
                    :min="itemField.min"
                    :max="itemField.max"
                    density="comfortable"
                    hide-details
                  ></v-text-field>
                </div>
              </v-card-text>
            </v-card>
          </template>
        </draggable>

        <v-alert v-if="!localValue[key] || localValue[key].length === 0" type="info" variant="tonal" density="compact">
          No items yet. Click "Add Item" to get started.
        </v-alert>
      </v-card>
    </div>

    <v-alert v-if="Object.keys(schema).length === 0" type="info" variant="tonal">
      This widget type has no configurable content fields.
    </v-alert>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { getWidgetDefinition } from '@/configs/widgetTypes';
import draggable from 'vuedraggable';

const props = defineProps({
  modelValue: Object,
  widgetType: String
});

const emit = defineEmits(['update:modelValue']);

const localValue = ref({});
const isUpdatingFromProp = ref(false);
const updateTimeout = ref(null);

const widgetDefinition = computed(() => getWidgetDefinition(props.widgetType));
const schema = computed(() => widgetDefinition.value?.schema || {});

// Initialize local value from props
watch(() => props.modelValue, (newValue) => {
  if (newValue && !isUpdatingFromProp.value) {
    const serialized = JSON.stringify(newValue);
    const currentSerialized = JSON.stringify(localValue.value);

    // Only update if actually different to prevent infinite loop
    if (serialized !== currentSerialized) {
      localValue.value = JSON.parse(serialized);
    }
  }
}, { immediate: true, deep: true });

// Emit changes with debouncing for smooth preview updates
watch(localValue, (newValue) => {
  // Clear existing timeout
  if (updateTimeout.value) {
    clearTimeout(updateTimeout.value);
  }

  // Debounce the update to prevent too many rapid changes
  updateTimeout.value = setTimeout(() => {
    isUpdatingFromProp.value = true;
    emit('update:modelValue', JSON.parse(JSON.stringify(newValue)));

    nextTick(() => {
      isUpdatingFromProp.value = false;
    });
  }, 150); // 150ms debounce
}, { deep: true });

function addArrayItem(key, field) {
  if (!localValue.value[key]) {
    localValue.value[key] = [];
  }

  const newItem = {};
  // Initialize with default values based on schema
  if (field.itemSchema) {
    Object.keys(field.itemSchema).forEach(itemKey => {
      const itemField = field.itemSchema[itemKey];
      if (itemField.type === 'text' || itemField.type === 'textarea' || itemField.type === 'icon') {
        newItem[itemKey] = '';
      } else if (itemField.type === 'number') {
        newItem[itemKey] = itemField.min || 0;
      } else if (itemField.type === 'boolean') {
        newItem[itemKey] = false;
      }
    });
  }

  localValue.value[key].push(newItem);
}

function removeArrayItem(key, index) {
  localValue.value[key].splice(index, 1);
}

// Cleanup timeout on unmount
onBeforeUnmount(() => {
  if (updateTimeout.value) {
    clearTimeout(updateTimeout.value);
  }
});
</script>

<style scoped>
.array-drag-handle:hover {
  cursor: grab;
}

.array-drag-handle:active {
  cursor: grabbing;
}
</style>
