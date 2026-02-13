<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 mb-4">Menu Management</h1>
      </v-col>
    </v-row>

    <v-row>
      <!-- Menu List -->
      <v-col cols="12" md="4">
        <v-card>
          <v-card-title class="d-flex justify-space-between align-center">
            Menus
            <v-btn color="primary" size="small" @click="showMenuDialog = true">
              <v-icon left>mdi-plus</v-icon>
              New Menu
            </v-btn>
          </v-card-title>

          <v-divider />

          <v-list>
            <v-list-item
              v-for="menu in menus"
              :key="menu.id"
              :active="selectedMenu && selectedMenu.id === menu.id"
              @click="selectMenu(menu)"
            >
              <v-list-item-title>
                {{ menu.name }}
                <v-chip v-if="menu.location" size="x-small" class="ml-2">
                  {{ menu.location }}
                </v-chip>
              </v-list-item-title>
              <v-list-item-subtitle>
                {{ menu.items_count || 0 }} items
              </v-list-item-subtitle>

              <template #append>
                <v-btn icon size="small" @click.stop="editMenu(menu)">
                  <v-icon>mdi-pencil</v-icon>
                </v-btn>
                <v-btn icon size="small" color="error" @click.stop="deleteMenu(menu)">
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-if="!menus.length" class="text-center text-grey">
            No menus yet. Create one to get started!
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Menu Items -->
      <v-col cols="12" md="8">
        <v-card v-if="selectedMenu">
          <v-card-title class="d-flex justify-space-between align-center">
            {{ selectedMenu.name }} Items
            <v-btn color="primary" size="small" @click="showItemDialog = true">
              <v-icon left>mdi-plus</v-icon>
              Add Item
            </v-btn>
          </v-card-title>

          <v-divider />

          <v-card-text>
            <!-- Draggable Menu Items -->
            <draggable
              v-model="menuItems"
              :options="{ animation: 200, handle: '.drag-handle' }"
              @end="reorderItems"
            >
              <template #item="{ element: item }">
                <v-card class="mb-2" elevation="1">
                  <v-card-text class="d-flex align-center">
                    <v-icon class="drag-handle mr-3" style="cursor: move;">
                      mdi-drag-vertical
                    </v-icon>

                    <v-icon v-if="item.icon" class="mr-2">{{ item.icon }}</v-icon>

                    <div class="flex-grow-1">
                      <div class="font-weight-medium">{{ item.label }}</div>
                      <div class="text-caption text-grey">
                        {{ item.type }}: {{ item.href || item.route || item.url }}
                      </div>
                      <div v-if="item.auth_required || item.role || item.permission" class="text-caption">
                        <v-chip v-if="item.auth_required" size="x-small" class="mr-1">
                          Auth Required
                        </v-chip>
                        <v-chip v-if="item.role" size="x-small" class="mr-1">
                          Role: {{ item.role }}
                        </v-chip>
                        <v-chip v-if="item.permission" size="x-small">
                          Permission: {{ item.permission }}
                        </v-chip>
                      </div>
                    </div>

                    <v-chip v-if="!item.is_active" color="grey" size="small" class="mr-2">
                      Inactive
                    </v-chip>

                    <v-btn icon size="small" @click="editItem(item)">
                      <v-icon>mdi-pencil</v-icon>
                    </v-btn>
                    <v-btn icon size="small" color="error" @click="deleteItem(item)">
                      <v-icon>mdi-delete</v-icon>
                    </v-btn>
                  </v-card-text>

                  <!-- Child Items -->
                  <v-card-text v-if="item.children && item.children.length" class="pl-12">
                    <v-card
                      v-for="child in item.children"
                      :key="child.id"
                      class="mb-1"
                      elevation="0"
                      outlined
                    >
                      <v-card-text class="d-flex align-center py-2">
                        <v-icon v-if="child.icon" class="mr-2" size="small">{{ child.icon }}</v-icon>
                        <div class="flex-grow-1 text-caption">
                          {{ child.label }}
                        </div>
                        <v-btn icon size="x-small" @click="editItem(child)">
                          <v-icon size="small">mdi-pencil</v-icon>
                        </v-btn>
                        <v-btn icon size="x-small" color="error" @click="deleteItem(child)">
                          <v-icon size="small">mdi-delete</v-icon>
                        </v-btn>
                      </v-card-text>
                    </v-card>
                  </v-card-text>
                </v-card>
              </template>
            </draggable>

            <div v-if="!menuItems.length" class="text-center text-grey py-8">
              No menu items yet. Add some to build your menu!
            </div>
          </v-card-text>
        </v-card>

        <v-card v-else class="text-center py-12">
          <v-icon size="64" color="grey-lighten-1">mdi-menu</v-icon>
          <p class="text-h6 mt-4">Select a menu to manage its items</p>
        </v-card>
      </v-col>
    </v-row>

    <!-- Menu Dialog -->
    <menu-dialog
      v-model="showMenuDialog"
      :menu="editingMenu"
      @saved="onMenuSaved"
    />

    <!-- Menu Item Dialog -->
    <menu-item-dialog
      v-model="showItemDialog"
      :item="editingItem"
      :menu-id="selectedMenu?.id"
      :items="menuItems"
      @saved="onItemSaved"
    />
  </v-container>
</template>

<script>
import axios from 'axios';
import draggable from 'vuedraggable';
import MenuDialog from '@/components/admin/MenuDialog.vue';
import MenuItemDialog from '@/components/admin/MenuItemDialog.vue';

export default {
  name: 'MenuManager',
  components: {
    draggable,
    MenuDialog,
    MenuItemDialog,
  },
  data() {
    return {
      menus: [],
      selectedMenu: null,
      menuItems: [],
      showMenuDialog: false,
      showItemDialog: false,
      editingMenu: null,
      editingItem: null,
    };
  },
  mounted() {
    this.fetchMenus();
  },
  methods: {
    async fetchMenus() {
      try {
        const { data } = await axios.get('/api/admin/menus');
        this.menus = data.map(menu => ({
          ...menu,
          items_count: menu.items?.length || 0,
        }));
      } catch (error) {
        console.error('Error fetching menus:', error);
      }
    },
    async selectMenu(menu) {
      this.selectedMenu = menu;
      try {
        const { data } = await axios.get(`/api/admin/menus/${menu.id}/items`);
        this.menuItems = data;
      } catch (error) {
        console.error('Error fetching menu items:', error);
      }
    },
    editMenu(menu) {
      this.editingMenu = { ...menu };
      this.showMenuDialog = true;
    },
    async deleteMenu(menu) {
      if (!confirm(`Delete menu "${menu.name}"? This will also delete all its items.`)) {
        return;
      }

      try {
        await axios.delete(`/api/admin/menus/${menu.id}`);
        this.fetchMenus();
        if (this.selectedMenu?.id === menu.id) {
          this.selectedMenu = null;
          this.menuItems = [];
        }
      } catch (error) {
        console.error('Error deleting menu:', error);
        alert('Failed to delete menu');
      }
    },
    onMenuSaved() {
      this.showMenuDialog = false;
      this.editingMenu = null;
      this.fetchMenus();
    },
    editItem(item) {
      this.editingItem = { ...item };
      this.showItemDialog = true;
    },
    async deleteItem(item) {
      if (!confirm(`Delete menu item "${item.label}"?`)) {
        return;
      }

      try {
        await axios.delete(`/api/admin/menu-items/${item.id}`);
        this.selectMenu(this.selectedMenu); // Refresh items
      } catch (error) {
        console.error('Error deleting item:', error);
        alert('Failed to delete item');
      }
    },
    onItemSaved() {
      this.showItemDialog = false;
      this.editingItem = null;
      this.selectMenu(this.selectedMenu); // Refresh items
    },
    async reorderItems() {
      const items = this.menuItems.map((item, index) => ({
        id: item.id,
        order: index,
        parent_id: item.parent_id,
      }));

      try {
        await axios.post(`/api/admin/menus/${this.selectedMenu.id}/reorder`, { items });
      } catch (error) {
        console.error('Error reordering items:', error);
        this.selectMenu(this.selectedMenu); // Revert on error
      }
    },
  },
};
</script>

<style scoped>
.flex-grow-1 {
  flex-grow: 1;
}
</style>
