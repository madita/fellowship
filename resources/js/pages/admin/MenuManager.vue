<template>
    <v-container fluid>
        <h1 class="text-h4 mb-4">{{ t('menuAdmin.title') }}</h1>

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
                            @click="selectMenu(menu)"
                        >
                            <template v-slot:prepend>
                                <v-icon v-if="selectedMenu?.id === menu.id">mdi-check</v-icon>
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
                                    @click.stop="openMenuDialog(menu)"
                                />
                                <v-btn
                                    icon="mdi-delete"
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    @click.stop="confirmDeleteMenu(menu)"
                                />
                            </template>
                        </v-list-item>
                    </v-list>

                    <v-card-text v-else class="text-center text-medium-emphasis">
                        {{ t('menuAdmin.emptyMenus') }}
                    </v-card-text>
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

                    <v-card-text>
                        <draggable
                            v-if="menuItems.length"
                            v-model="menuItems"
                            :animation="200"
                            handle=".drag-handle"
                            item-key="id"
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
                                            color="grey"
                                            variant="tonal"
                                            class="me-2"
                                        >
                                            {{ t('menuAdmin.active') }}: —
                                        </v-chip>

                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            variant="text"
                                            @click="openItemDialog(item)"
                                        />
                                        <v-btn
                                            icon="mdi-delete"
                                            size="x-small"
                                            variant="text"
                                            color="error"
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
                                                @click="openItemDialog(child)"
                                            />
                                            <v-btn
                                                icon="mdi-delete"
                                                size="x-small"
                                                variant="text"
                                                color="error"
                                                @click="confirmDeleteItem(child)"
                                            />
                                        </v-sheet>
                                    </div>
                                </v-sheet>
                            </template>
                        </draggable>

                        <div
                            v-else
                            class="text-center text-medium-emphasis py-8"
                        >
                            {{ t('menuAdmin.emptyItems') }}
                        </div>
                    </v-card-text>
                </v-card>

                <v-card
                    v-else
                    flat
                    border
                    class="text-center pa-12"
                >
                    <v-icon size="64" color="grey-lighten-1">mdi-menu</v-icon>
                    <p class="text-body-1 mt-3 text-medium-emphasis">
                        {{ t('menuAdmin.selectPrompt') }}
                    </p>
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

        <confirm-dialog
            v-model="confirm.open"
            :title="confirm.title"
            :content="confirm.content"
            :confirmation-text="t('common.delete')"
            :cancellation-text="t('common.cancel')"
            :resolve="confirm.resolve"
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
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import draggable from 'vuedraggable';
import MenuDialog from '@/components/admin/MenuDialog.vue';
import MenuItemDialog from '@/components/admin/MenuItemDialog.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';

const { t } = useI18n();

const menus = ref([]);
const selectedMenu = ref(null);
const menuItems = ref([]);

const showMenuDialog = ref(false);
const showItemDialog = ref(false);
const editingMenu = ref(null);
const editingItem = ref(null);

const confirm = reactive({
    open: false,
    title: '',
    content: '',
    resolve: () => {},
});

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
    try {
        const { data } = await axios.get(`/api/admin/menus/${menu.id}/items`);
        menuItems.value = data;
    } catch (error) {
        console.error('Error fetching menu items:', error);
        menuItems.value = [];
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

function confirmDeleteMenu(menu) {
    confirm.title = t('menuAdmin.editMenu');
    confirm.content = t('menuAdmin.confirmDeleteMenu', { name: menu.name });
    confirm.resolve = async (ok) => {
        confirm.open = false;
        if (!ok) return;
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
        }
    };
    confirm.open = true;
}

function confirmDeleteItem(item) {
    confirm.title = t('menuAdmin.editItem');
    confirm.content = t('menuAdmin.confirmDeleteItem', { label: item.label });
    confirm.resolve = async (ok) => {
        confirm.open = false;
        if (!ok) return;
        try {
            await axios.delete(`/api/admin/menu-items/${item.id}`);
            await selectMenu(selectedMenu.value);
            notify(t('menuAdmin.deletedItem'));
        } catch (error) {
            console.error('Error deleting item:', error);
            notify(error?.response?.data?.message || t('menuAdmin.deleteError'), 'error');
        }
    };
    confirm.open = true;
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
    if (!selectedMenu.value) return;
    const items = menuItems.value.map((item, index) => ({
        id: item.id,
        order: index,
        parent_id: item.parent_id,
    }));
    try {
        await axios.post(`/api/admin/menus/${selectedMenu.value.id}/reorder`, { items });
        notify(t('menuAdmin.reordered'));
    } catch (error) {
        console.error('Error reordering items:', error);
        notify(error?.response?.data?.message || t('menuAdmin.saveError'), 'error');
        await selectMenu(selectedMenu.value); // revert on error
    }
}

onMounted(fetchMenus);
</script>
