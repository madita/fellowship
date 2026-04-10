<template>
  <div>
    <v-alert type="info" variant="tonal" class="mb-4">
      <div class="text-subtitle-1 font-weight-bold">{{ $t('settings.menuBuilder.title') }}</div>
      <div>{{ $t('settings.menuBuilder.description') }}</div>
    </v-alert>

    <!-- Action Buttons -->
    <div class="d-flex justify-space-between align-center mb-4">
      <v-btn color="primary" prepend-icon="mdi-plus" @click="showAddDialog = true">
        {{ $t('settings.menuBuilder.addMenuItem') }}
      </v-btn>
      <v-btn prepend-icon="mdi-refresh" @click="loadMenuItems" :loading="isLoading">
        {{ $t('settings.menuBuilder.refresh') }}
      </v-btn>
    </div>

    <!-- Menu Items List -->
    <v-card>
      <v-card-title>
        <v-icon class="mr-2">mdi-drag</v-icon>
        {{ $t('settings.menuBuilder.dragToReorder') }}
      </v-card-title>
      <v-divider></v-divider>

      <v-card-text v-if="isLoading" class="text-center py-8">
        <v-progress-circular indeterminate color="primary"></v-progress-circular>
        <div class="mt-2">{{ $t('settings.menuBuilder.loadingMenuItems') }}</div>
      </v-card-text>

      <v-card-text v-else-if="menuItems.length === 0" class="text-center py-8">
        <v-icon size="64" color="grey">mdi-menu</v-icon>
        <div class="text-h6 mt-4">{{ $t('settings.menuBuilder.noMenuItemsYet') }}</div>
        <div class="text-caption text-grey mb-4">{{ $t('settings.menuBuilder.addMenuItemsHint') }}</div>
        <v-btn color="primary" @click="showAddDialog = true">{{ $t('settings.menuBuilder.addFirstMenuItem') }}</v-btn>
      </v-card-text>

      <draggable
        v-else
        v-model="menuItems"
        item-key="id"
        handle=".menu-drag-handle"
        @end="onDragEnd"
        class="menu-list pa-4"
      >
        <template #item="{ element: item }">
          <v-list-item
            :key="item.id"
            class="menu-item mb-2"
            :class="{ 'menu-disabled': !item.enabled }"
          >
            <template #prepend>
              <v-icon class="menu-drag-handle mr-3" style="cursor: grab;">mdi-drag-vertical</v-icon>
              <v-avatar :color="item.enabled ? 'primary' : 'grey'" size="32" class="mr-3">
                <v-icon size="18" color="white">mdi-menu</v-icon>
              </v-avatar>
            </template>

            <v-list-item-title>
              {{ item.label }}
            </v-list-item-title>

            <v-list-item-subtitle>
              {{ $t('settings.menuBuilder.target') }}: {{ item.anchor_target }} | {{ $t('settings.menuBuilder.order') }}: {{ item.order }}
            </v-list-item-subtitle>

            <template #append>
              <div class="d-flex align-center">
                <v-switch
                  v-model="item.enabled"
                  hide-details
                  density="compact"
                  color="success"
                  class="mr-2"
                  @change="toggleMenuItem(item)"
                ></v-switch>

                <v-btn
                  icon="mdi-pencil"
                  size="small"
                  variant="text"
                  @click="editMenuItem(item)"
                  :title="$t('settings.menuBuilder.editMenuItem')"
                ></v-btn>

                <v-btn
                  icon="mdi-delete"
                  size="small"
                  variant="text"
                  color="error"
                  @click="confirmDelete(item)"
                  :title="$t('settings.menuBuilder.deleteMenuItem')"
                ></v-btn>
              </div>
            </template>
          </v-list-item>
        </template>
      </draggable>
    </v-card>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="showAddDialog" max-width="600px">
      <v-card>
        <v-card-title>{{ editingItem ? $t('settings.menuBuilder.editMenuItem') : $t('settings.menuBuilder.addMenuItem') }}</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="formData.label"
            :label="$t('settings.menuBuilder.menuLabel')"
            :hint="$t('settings.menuBuilder.menuLabelHint')"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <v-select
            v-model="formData.anchor_target"
            :label="$t('settings.menuBuilder.linkTarget')"
            :items="anchorOptions"
            :hint="$t('settings.menuBuilder.linkTargetHint')"
            persistent-hint
            class="mb-4"
          >
            <template #prepend-inner>
              <span class="text-grey">#</span>
            </template>
          </v-select>

          <v-text-field
            v-if="formData.anchor_target === 'custom'"
            v-model="customAnchor"
            :label="$t('settings.menuBuilder.customAnchorId')"
            :hint="$t('settings.menuBuilder.customAnchorIdHint')"
            persistent-hint
            prefix="#"
          ></v-text-field>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="cancelEdit">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="primary" @click="saveMenuItem">{{ editingItem ? $t('settings.menuBuilder.update') : $t('settings.menuBuilder.add') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="500">
      <v-card>
        <v-card-title>{{ $t('settings.menuBuilder.confirmDelete') }}</v-card-title>
        <v-card-text>
          {{ $t('settings.menuBuilder.deleteConfirmMessage', { label: itemToDelete?.label }) }}
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showDeleteDialog = false">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="error" @click="deleteMenuItem">{{ $t('common.delete') }}</v-btn>
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
import draggable from 'vuedraggable';

const { t } = useI18n();
const homepageStore = useHomepageStore();
const isLoading = ref(true);

const menuItems = computed({
  get: () => homepageStore.orderedMenuItems,
  set: (value) => {
    value.forEach((item, index) => {
      item.order = index + 1;
    });
  }
});

// Get available anchors from sections
const anchorOptions = computed(() => {
  const sections = homepageStore.sections.filter(s => s.anchor_id);
  const options = sections.map(s => ({
    value: s.anchor_id,
    title: `${s.title || t('settings.menuBuilder.section')} (#${s.anchor_id})`
  }));
  options.push({ value: 'custom', title: t('settings.menuBuilder.customAnchor') });
  return options;
});

// Dialog states
const showAddDialog = ref(false);
const showDeleteDialog = ref(false);
const editingItem = ref(null);
const itemToDelete = ref(null);
const customAnchor = ref('');

const formData = ref({
  label: '',
  anchor_target: '',
  enabled: true
});

// Snackbar
const snackbar = ref(false);
const snackbarMessage = ref('');
const snackbarColor = ref('success');

async function loadMenuItems() {
  isLoading.value = true;
  try {
    await homepageStore.fetchSections();
    await homepageStore.fetchMenuItems();
  } catch (error) {
    showSnackbar(t('settings.menuBuilder.failedToLoadMenuItems'), 'error');
  } finally {
    isLoading.value = false;
  }
}

async function onDragEnd() {
  try {
    const newOrder = menuItems.value.map((item, index) => ({
      id: item.id,
      order: index + 1
    }));
    await homepageStore.reorderMenu(newOrder);
    showSnackbar(t('settings.menuBuilder.menuOrderUpdated'), 'success');
  } catch (error) {
    showSnackbar(t('settings.menuBuilder.failedToUpdateMenuOrder'), 'error');
    await loadMenuItems();
  }
}

function editMenuItem(item) {
  editingItem.value = item;
  formData.value = {
    label: item.label,
    anchor_target: item.anchor_target,
    enabled: item.enabled
  };
  showAddDialog.value = true;
}

async function saveMenuItem() {
  const finalAnchor = formData.value.anchor_target === 'custom' ? customAnchor.value : formData.value.anchor_target;

  if (!formData.value.label || !finalAnchor) {
    showSnackbar(t('settings.menuBuilder.fillRequiredFields'), 'error');
    return;
  }

  try {
    const menuData = {
      label: formData.value.label,
      anchor_target: finalAnchor.startsWith('#') ? finalAnchor.substring(1) : finalAnchor,
      enabled: formData.value.enabled || true,
      order: editingItem.value ? editingItem.value.order : menuItems.value.length + 1
    };

    if (editingItem.value) {
      await homepageStore.updateMenuItem(editingItem.value.id, menuData);
      showSnackbar(t('settings.menuBuilder.menuItemUpdated'), 'success');
    } else {
      await homepageStore.createMenuItem(menuData);
      showSnackbar(t('settings.menuBuilder.menuItemAdded'), 'success');
    }

    cancelEdit();
  } catch (error) {
    showSnackbar(t('settings.menuBuilder.failedToSaveMenuItem'), 'error');
  }
}

function cancelEdit() {
  showAddDialog.value = false;
  editingItem.value = null;
  formData.value = { label: '', anchor_target: '', enabled: true };
  customAnchor.value = '';
}

async function toggleMenuItem(item) {
  // The toggle happens in the UI, but we need to save it
  try {
    await homepageStore.updateMenuItem(item.id, item);
    showSnackbar(item.enabled ? t('settings.menuBuilder.menuItemEnabled') : t('settings.menuBuilder.menuItemDisabled'), 'success');
  } catch (error) {
    showSnackbar(t('settings.menuBuilder.failedToToggleMenuItem'), 'error');
    item.enabled = !item.enabled;
  }
}

function confirmDelete(item) {
  itemToDelete.value = item;
  showDeleteDialog.value = true;
}

async function deleteMenuItem() {
  try {
    await homepageStore.deleteMenuItem(itemToDelete.value.id);
    showSnackbar(t('settings.menuBuilder.menuItemDeleted'), 'success');
    showDeleteDialog.value = false;
    itemToDelete.value = null;
  } catch (error) {
    showSnackbar(t('settings.menuBuilder.failedToDeleteMenuItem'), 'error');
  }
}

function showSnackbar(message, color = 'success') {
  snackbarMessage.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
}

onMounted(async () => {
  await loadMenuItems();
});
</script>

<style scoped>
.menu-list {
  min-height: 200px;
}

.menu-item {
  background: rgba(var(--v-theme-surface-variant), 0.4);
  border-radius: 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  transition: all 0.2s;
}

.menu-item:hover {
  background: rgba(var(--v-theme-surface-variant), 0.6);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.menu-disabled {
  opacity: 0.6;
}

.menu-drag-handle:hover {
  cursor: grab;
}

.menu-drag-handle:active {
  cursor: grabbing;
}
</style>
