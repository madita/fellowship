<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="700">
    <v-card>
      <v-card-title>
        {{ item?.id ? 'Edit Menu Item' : 'Add Menu Item' }}
      </v-card-title>

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.label"
                label="Label"
                :rules="[rules.required]"
                outlined
                dense
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="form.type"
                :items="types"
                label="Type"
                :rules="[rules.required]"
                outlined
                dense
              />
            </v-col>

            <v-col cols="12" md="8">
              <v-text-field
                v-if="form.type === 'route'"
                v-model="form.route"
                label="Route Path"
                :rules="[rules.required]"
                outlined
                dense
                placeholder="/dashboard"
              />
              <v-text-field
                v-else
                v-model="form.url"
                label="URL"
                :rules="[rules.required]"
                outlined
                dense
                placeholder="https://example.com"
              />
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                v-model="form.icon"
                label="Icon"
                outlined
                dense
                placeholder="mdi-home"
                hint="Material Design Icon"
                persistent-hint
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="form.parent_id"
                :items="parentItems"
                item-title="label"
                item-value="id"
                label="Parent Item"
                outlined
                dense
                clearable
                hint="Leave empty for top-level item"
                persistent-hint
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="form.target"
                :items="targets"
                label="Target"
                outlined
                dense
              />
            </v-col>

            <v-col cols="12">
              <v-divider />
              <p class="text-subtitle-2 mt-3 mb-2">Visibility Settings</p>
            </v-col>

            <v-col cols="12" md="4">
              <v-switch
                v-model="form.auth_required"
                label="Auth Required"
                color="primary"
                hide-details
              />
            </v-col>

            <v-col cols="12" md="4">
              <v-switch
                v-model="form.guest_only"
                label="Guests Only"
                color="primary"
                hide-details
              />
            </v-col>

            <v-col cols="12" md="4">
              <v-switch
                v-model="form.is_active"
                label="Active"
                color="primary"
                hide-details
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.role"
                label="Required Role"
                outlined
                dense
                clearable
                placeholder="admin"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.permission"
                label="Required Permission"
                outlined
                dense
                clearable
                placeholder="manage-users"
              />
            </v-col>

            <v-col cols="12">
              <v-text-field
                v-model.number="form.order"
                label="Order"
                type="number"
                outlined
                dense
                hint="Lower numbers appear first"
                persistent-hint
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn @click="$emit('update:modelValue', false)">
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          :loading="saving"
          :disabled="!valid"
          @click="save"
        >
          Save
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import axios from 'axios';

export default {
  name: 'MenuItemDialog',
  props: {
    modelValue: Boolean,
    item: Object,
    menuId: [Number, String],
    items: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['update:modelValue', 'saved'],
  data() {
    return {
      valid: false,
      saving: false,
      form: {
        label: '',
        type: 'route',
        url: '',
        route: '',
        icon: '',
        parent_id: null,
        target: '_self',
        role: '',
        permission: '',
        auth_required: false,
        guest_only: false,
        order: 0,
        is_active: true,
      },
      types: [
        { title: 'Internal Route', value: 'route' },
        { title: 'Custom Link', value: 'custom' },
        { title: 'External Link', value: 'external' },
        { title: 'Page', value: 'page' },
      ],
      targets: [
        { title: 'Same Window', value: '_self' },
        { title: 'New Window', value: '_blank' },
      ],
      rules: {
        required: (v) => !!v || 'Required',
      },
    };
  },
  computed: {
    parentItems() {
      // Only show root items as possible parents (no nested nesting for simplicity)
      return this.items.filter(item => !item.parent_id && item.id !== this.item?.id);
    },
  },
  watch: {
    item: {
      immediate: true,
      handler(val) {
        if (val?.id) {
          this.form = { ...val };
        } else {
          this.resetForm();
        }
      },
    },
    modelValue(val) {
      if (!val) {
        this.resetForm();
      }
    },
  },
  methods: {
    async save() {
      if (!this.$refs.form.validate()) return;

      this.saving = true;
      try {
        if (this.item?.id) {
          await axios.patch(`/api/admin/menu-items/${this.item.id}`, this.form);
        } else {
          await axios.post(`/api/admin/menus/${this.menuId}/items`, this.form);
        }
        this.$emit('saved');
      } catch (error) {
        console.error('Error saving menu item:', error);
        alert('Failed to save menu item');
      } finally {
        this.saving = false;
      }
    },
    resetForm() {
      this.form = {
        label: '',
        type: 'route',
        url: '',
        route: '',
        icon: '',
        parent_id: null,
        target: '_self',
        role: '',
        permission: '',
        auth_required: false,
        guest_only: false,
        order: this.items.length,
        is_active: true,
      };
      if (this.$refs.form) {
        this.$refs.form.resetValidation();
      }
    },
  },
};
</script>
