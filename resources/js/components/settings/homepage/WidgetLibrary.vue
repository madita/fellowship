<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="900px" scrollable>
    <v-card>
      <v-card-title class="bg-primary text-white">
        <v-icon class="mr-2">mdi-widgets</v-icon>
        Widget Library
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" variant="text" @click="close"></v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <!-- Category Filter -->
        <v-tabs v-model="selectedCategory" bg-color="grey-lighten-4" color="primary">
          <v-tab value="all">All Widgets</v-tab>
          <v-tab v-for="category in categories" :key="category.value" :value="category.value">
            {{ category.label }}
          </v-tab>
        </v-tabs>

        <v-divider></v-divider>

        <div class="pa-4" style="min-height: 400px;">
          <!-- Widget Grid -->
          <v-row>
            <v-col
              v-for="widget in filteredWidgets"
              :key="widget.type"
              cols="12"
              sm="6"
              md="4"
            >
              <v-card
                class="widget-card"
                :class="{ 'widget-card-hover': true }"
                variant="outlined"
                @click="selectWidget(widget.type)"
                hover
              >
                <v-card-text class="text-center pa-4">
                  <v-avatar :color="widget.color" size="64" class="mb-3">
                    <v-icon size="32" color="white">{{ widget.icon }}</v-icon>
                  </v-avatar>

                  <div class="text-h6 mb-2">{{ widget.name }}</div>
                  <div class="text-caption text-grey">{{ widget.description }}</div>

                  <v-chip
                    :color="widget.color"
                    size="x-small"
                    class="mt-3"
                    variant="tonal"
                  >
                    {{ getCategoryLabel(widget.category) }}
                  </v-chip>
                </v-card-text>

                <v-divider></v-divider>

                <v-card-actions class="justify-center">
                  <v-btn
                    color="primary"
                    variant="text"
                    size="small"
                    prepend-icon="mdi-plus"
                  >
                    Add Widget
                  </v-btn>
                </v-card-actions>
              </v-card>
            </v-col>
          </v-row>

          <v-alert v-if="filteredWidgets.length === 0" type="info" variant="tonal" class="mt-4">
            No widgets found in this category.
          </v-alert>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { getAvailableWidgets, WIDGET_CATEGORIES } from '@/configs/widgetTypes';

const props = defineProps({
  modelValue: Boolean
});

const emit = defineEmits(['update:modelValue', 'select']);

const selectedCategory = ref('all');
const categories = WIDGET_CATEGORIES;

const allWidgets = computed(() => getAvailableWidgets());

const filteredWidgets = computed(() => {
  if (selectedCategory.value === 'all') {
    return allWidgets.value;
  }
  return allWidgets.value.filter(w => w.category === selectedCategory.value);
});

function getCategoryLabel(categoryValue) {
  const category = categories.find(c => c.value === categoryValue);
  return category?.label || categoryValue;
}

function selectWidget(widgetType) {
  emit('select', widgetType);
}

function close() {
  emit('update:modelValue', false);
}
</script>

<style scoped>
.widget-card {
  transition: all 0.3s;
  cursor: pointer;
}

.widget-card-hover:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>
