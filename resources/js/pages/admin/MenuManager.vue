<template>
    <div class="flex-grow-1">
    <page-header
        :title="t('menuAdmin.title')"
        :subtitle="t('menuAdmin.subtitle')"
        icon="mdi-menu"
        fluid
    />

    <v-container fluid>
        <v-row>
            <!-- Menu list -->
            <v-col cols="12" md="4">
                <v-card flat border>
                    <v-card-title class="d-flex align-center pa-3">
                        <span class="text-subtitle-1 font-weight-medium">
                            {{ t('menuAdmin.menus') }}
                        </span>
                        <v-spacer />
                        <v-btn
                            color="primary"
                            variant="tonal"
                            size="small"
                            prepend-icon="mdi-plus"
                            @click="openMenuDialog()"
                        >
                            {{ t('menuAdmin.newMenu') }}
                        </v-btn>
                    </v-card-title>

                    <v-divider />

                    <v-list v-if="menus.length" density="compact" nav>
                        <v-list-item
                            v-for="menu in menus"
                            :key="menu.id"
                            :active="selectedMenu?.id === menu.id"
                            :disabled="deletingMenuId === menu.id"
                            @click="selectMenu(menu)"
                        >
                            <template v-slot:prepend>
                                <v-progress-circular v-if="loadingItems && selectedMenu?.id === menu.id" size="20" width="2" indeterminate />
                                <v-icon v-else-if="selectedMenu?.id === menu.id">mdi-check</v-icon>
                                <div v-else style="width: 24px;"></div>
                            </template>

                            <v-list-item-title>
                                {{ menu.name }}
                                <v-chip
                                    v-if="menu.location"
                                    size="x-small"
                                    variant="tonal"
                                    class="ml-2"
                                >
                                    {{ menu.location }}
                                </v-chip>
                            </v-list-item-title>
                            <v-list-item-subtitle>
                                {{ t('menuAdmin.itemsCount', { count: menu.items?.length ?? 0 }, menu.items?.length ?? 0) }}
                            </v-list-item-subtitle>

                            <template v-slot:append>
                                <v-btn
                                    icon="mdi-pencil"
                                    size="x-small"
                                    variant="text"
                                    :disabled="deletingMenuId !== null"
                                    @click.stop="openMenuDialog(menu)"
                                />
                                <v-btn
                                    icon="mdi-delete"
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    :loading="deletingMenuId === menu.id"
                                    :disabled="deletingMenuId !== null"
                                    @click.stop="confirmDeleteMenu(menu)"
                                />
                            </template>
                        </v-list-item>
                    </v-list>

                    <empty-state
                        v-else
                        compact
                        icon="mdi-menu"
                        :title="t('menuAdmin.emptyMenus')"
                    />
                </v-card>
            </v-col>

            <!-- Menu items -->
            <v-col cols="12" md="8">
                <v-card v-if="selectedMenu" flat border>
                    <v-card-title class="d-flex align-center pa-3">
                        <span class="text-subtitle-1 font-weight-medium">
                            {{ t('menuAdmin.itemsHeader', { name: selectedMenu.name }) }}
                        </span>
                        <v-spacer />
                        <v-btn
                            color="primary"
                            variant="tonal"
                            size="small"
                            prepend-icon="mdi-plus"
                            @click="openItemDialog()"
                        >
                            {{ t('menuAdmin.addItem') }}
                        </v-btn>
                    </v-card-title>

                    <v-divider />

                    <v-progress-linear v-if="loadingItems || reordering" indeterminate color="primary" />

                    <v-card-text>
                        <draggable
                            v-if="menuItems.length"
                            v-model="menuItems"
                            :animation="200"
                            handle=".drag-handle"
                            item-key="id"
                            :disabled="reordering || deletingItemId !== null"
                            @end="reorderItems"
                        >
                            <template #item="{ element: item }">
                                <v-sheet
                                    border
                                    rounded
                                    class="mb-2 pa-2"
                                >
                                    <div class="d-flex align-center">
                                        <v-icon
                                            class="drag-handle me-2"
                                            style="cursor: move;"
                                        >
                                            mdi-drag-vertical
                                        </v-icon>

                                        <v-icon v-if="item.icon" class="me-2">
                                            {{ item.icon }}
                                        </v-icon>

                                        <div class="flex-grow-1 min-width-0">
                                            <div class="font-weight-medium text-truncate">
                                                {{ item.label }}
                                            </div>
                                            <div class="text-caption text-medium-emphasis text-truncate">
                                                {{ item.type }} · {{ item.href || item.route || item.url }}
                                            </div>
                                            <div
                                                v-if="item.auth_required || item.guest_only || item.role || item.permission"
                                                class="mt-1"
                                            >
                                                <v-chip
                                                    v-if="item.auth_required"
                                                    size="x-small"
                                                    variant="tonal"
                                                    class="me-1"
                                                >
                                                    {{ t('menuAdmin.authRequired') }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="item.guest_only"
                                                    size="x-small"
                                                    variant="tonal"
                                                    class="me-1"
                                                >
                                                    {{ t('menuAdmin.guestOnly') }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="item.role"
                                                    size="x-small"
                                                    variant="tonal"
                                                    class="me-1"
                                                >
                                                    {{ item.role }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="item.permission"
                                                    size="x-small"
                                                    variant="tonal"
                                                >
                                                    {{ item.permission }}
                                                </v-chip>
                                            </div>
                                        </div>

                                        <v-chip
                                            v-if="!item.is_active"
                                            size="x-small"
                                            variant="tonal"
                                            class="me-2"
                                        >
                                            {{ t('menuAdmin.active') }}: —
                                        </v-chip>

                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            variant="text"
                                            :disabled="deletingItemId !== null"
                                            @click="openItemDialog(item)"
                                        />
                                        <v-btn
                                            icon="mdi-delete"
                                            size="x-small"
                                            variant="text"
                                            color="error"
                                            :loading="deletingItemId === item.id"
                                            :disabled="deletingItemId !== null"
                                            @click="confirmDeleteItem(item)"
                                        />
                                    </div>

                                    <!-- Children (single nested level shown inline) -->
                                    <div
                                        v-if="item.children?.length"
                                        class="mt-2 ps-8"
                                    >
                                        <v-sheet
                                            v-for="child in item.children"
                                            :key="child.id"
                                            border
                                            rounded
                                            class="mb-1 pa-2 d-flex align-center"
                                        >
                                            <v-icon v-if="child.icon" size="small" class="me-2">
                                                {{ child.icon }}
                                            </v-icon>
                                            <div class="flex-grow-1 text-caption text-truncate">
                                                {{ child.label }}
                                            </div>
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="x-small"
                                                variant="text"
                                                :disabled="deletingItemId !== null"
                                                @click="openItemDialog(child)"
                                            />
                                            <v-btn
                                                icon="mdi-delete"
                                                size="x-small"
                                                variant="text"
                                                color="error"
                                                :loading="deletingItemId === child.id"
                                                :disabled="deletingItemId !== null"
                                                @click="confirmDeleteItem(child)"
                                            />
                                        </v-sheet>
                                    </div>
                                </v-sheet>
                            </template>
                        </draggable>

                        <empty-state
                            v-else
                            compact
                            icon="mdi-format-list-bulleted"
                            :title="t('menuAdmin.emptyItems')"
                        />
                    </v-card-text>
                </v-card>

                <v-card v-else flat border>
                    <empty-state
                        icon="mdi-menu"
                        :title="t('menuAdmin.selectPrompt')"
                    />
                </v-card>
            </v-col>
        </v-row>

        <!-- Dialogs -->
        <menu-dialog
            v-model="showMenuDialog"
            :menu="editingMenu"
            @saved="onMenuSaved"
            @error="onError"
        />

        <menu-item-dialog
            v-model="showItemDialog"
            :item="editingItem"
            :menu-id="selectedMenu?.id"
            :items="menuItems"
            @saved="onItemSaved"
            @error="onError"
        />

        <v-snackbar
            v-model="snack.open"
            :color="snack.color"
            location="bottom right"
            timeout="3000"
        >
            {{ snack.text }}
        </v-snackbar>
    </v-container>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import draggable from 'vuedraggable';
import MenuDialog from '@/components/admin/MenuDialog.vue';
import MenuItemDialog from '@/components/admin/MenuItemDialog.vue';
import PageHeader from '../../components/common/PageHeader.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import { useDialog } from '@/composables/useDialog.js';

const { t } = useI18n();
const dialog = useDialog();

const menus = ref([]);
const selectedMenu = ref(null);
const menuItems = ref([]);
const loadingItems = ref(false);
const reordering = ref(false);
const deletingMenuId = ref(null);
const deletingItemId = ref(null);

const showMenuDialog = ref(false);
const showItemDialog = ref(false);
const editingMenu = ref(null);
const editingItem = ref(null);

const snack = reactive({
    open: false,
    color: 'success',
    text: '',
});

function notify(text, color = 'success') {
    snack.text = text;
    snack.color = color;
    snack.open = true;
}

function onError({ error }) {
    notify(error?.response?.data?.message || t('menuAdmin.saveError'), 'error');
}

async function fetchMenus() {
    try {
        const { data } = await axios.get('/api/admin/menus');
        menus.value = data;
    } catch (error) {
        console.error('Error fetching menus:', error);
        notify(error?.response?.data?.message || t('menuAdmin.saveError'), 'error');
    }
}

async function selectMenu(menu) {
    selectedMenu.value = menu;
    loadingItems.value = true;
    try {
        const { data } = await axios.get(`/api/admin/menus/${menu.id}/items`);
        menuItems.value = data;
    } catch (error) {
        console.error('Error fetching menu items:', error);
        menuItems.value = [];
        notify(error?.response?.data?.message || t('menuAdmin.saveError'), 'error');
    } finally {
        loadingItems.value = false;
    }
}

function openMenuDialog(menu = null) {
    editingMenu.value = menu ? { ...menu } : null;
    showMenuDialog.value = true;
}

function openItemDialog(item = null) {
    editingItem.value = item ? { ...item } : null;
    showItemDialog.value = true;
}

async function confirmDeleteMenu(menu) {
    if (deletingMenuId.value !== null) return;
    const ok = await dialog.confirmDelete(t('menuAdmin.confirmDeleteMenu', { name: menu.name }), {
        title: t('menuAdmin.editMenu'),
    });
    if (!ok) return;

    deletingMenuId.value = menu.id;
    try {
        await axios.delete(`/api/admin/menus/${menu.id}`);
        if (selectedMenu.value?.id === menu.id) {
            selectedMenu.value = null;
            menuItems.value = [];
        }
        await fetchMenus();
        notify(t('menuAdmin.deletedMenu'));
    } catch (error) {
        console.error('Error deleting menu:', error);
        notify(error?.response?.data?.message || t('menuAdmin.deleteError'), 'error');
    } finally {
        deletingMenuId.value = null;
    }
}

async function confirmDeleteItem(item) {
    if (deletingItemId.value !== null) return;
    const ok = await dialog.confirmDelete(t('menuAdmin.confirmDeleteItem', { label: item.label }), {
        title: t('menuAdmin.editItem'),
    });
    if (!ok) return;

    deletingItemId.value = item.id;
    try {
        await axios.delete(`/api/admin/menu-items/${item.id}`);
        await selectMenu(selectedMenu.value);
        notify(t('menuAdmin.deletedItem'));
    } catch (error) {
        console.error('Error deleting item:', error);
        notify(error?.response?.data?.message || t('menuAdmin.deleteError'), 'error');
    } finally {
        deletingItemId.value = null;
    }
}

async function onMenuSaved() {
    showMenuDialog.value = false;
    editingMenu.value = null;
    await fetchMenus();
    notify(t('menuAdmin.savedMenu'));
}

async function onItemSaved() {
    showItemDialog.value = false;
    editingItem.value = null;
    if (selectedMenu.value) await selectMenu(selectedMenu.value);
    notify(t('menuAdmin.savedItem'));
}

async function reorderItems() {
    if (!selectedMenu.value || reordering.value) return;
    const items = menuItems.value.map((item, index) => ({
        id: item.id,
        order: index,
        parent_id: item.parent_id,
    }));
    reordering.value = true;
    try {
        await axios.post(`/api/admin/menus/${selectedMenu.value.id}/reorder`, { items });
        notify(t('menuAdmin.reordered'));
    } catch (error) {
        console.error('Error reordering items:', error);
        notify(error?.response?.data?.message || t('menuAdmin.saveError'), 'error');
        await selectMenu(selectedMenu.value); // revert on error
    } finally {
        reordering.value = false;
    }
}

onMounted(fetchMenus);
</script>
