<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="900" scrollable>
    <v-card>
      <v-card-title class="d-flex align-center text-h6">
        <v-icon class="mr-2">mdi-widgets</v-icon>
        {{ $t('settings.widgetLibrary.footerWidgetLibrary') }}
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" variant="text" :disabled="adding" @click="close"></v-btn>
      </v-card-title>
      <v-divider></v-divider>

      <v-card-text class="pa-0">
        <!-- Category Filter -->
        <v-tabs v-model="selectedCategory" bg-color="transparent" color="primary">
          <v-tab value="all">{{ $t('settings.widgetLibrary.allWidgets') }}</v-tab>
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
                :disabled="adding"
                @click="selectWidget(widget.type)"
                hover
              >
                <v-card-text class="text-center pa-4">
                  <v-avatar :color="widget.color" size="64" class="mb-3">
                    <v-icon size="32" color="white">{{ widget.icon }}</v-icon>
                  </v-avatar>

                  <div class="text-h6 mb-2">{{ widget.name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ widget.description }}</div>

                  <v-chip
                    :color="widget.color"
                    size="small"
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
                    :loading="adding && addingType === widget.type"
                    :disabled="adding"
                  >
                    {{ $t('settings.widgetLibrary.addWidget') }}
                  </v-btn>
                </v-card-actions>
              </v-card>
            </v-col>
          </v-row>

          <empty-state
            v-if="filteredWidgets.length === 0"
            compact
            icon="mdi-widgets-outline"
            :title="$t('settings.widgetLibrary.noWidgetsInCategory')"
          />
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { getAvailableWidgets, FOOTER_WIDGET_CATEGORIES } from '@/configs/footerWidgetTypes';
import EmptyState from '@/components/common/EmptyState.vue';

const { t } = useI18n();

const props = defineProps({
  modelValue: Boolean,
  // True while the parent is creating the selected widget
  adding: Boolean
});

const emit = defineEmits(['update:modelValue', 'select']);

const selectedCategory = ref('all');
const addingType = ref(null);
const categories = FOOTER_WIDGET_CATEGORIES;

const allWidgets = computed(() => getAvailableWidgets());

const filteredWidgets = computed(() => {
  if (selectedCategory.value === 'all') {
    return allWidgets.value;
  }
  return allWidgets.value.filter(widget => widget.category === selectedCategory.value);
});

function getCategoryLabel(categoryValue) {
  const category = categories.find(c => c.value === categoryValue);
  return category ? category.label : categoryValue;
}

// The parent closes the dialog once the widget has been created, so a
// failed request leaves the library open for another try.
function selectWidget(type) {
  if (props.adding) return;
  addingType.value = type;
  emit('select', type);
}

function close() {
  emit('update:modelValue', false);
}
</script>

<style scoped>
.widget-card {
  cursor: pointer;
  transition: all 0.2s;
}

.widget-card-hover:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.15);
}
</style>
