<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="600">
    <v-card>
      <v-card-title>
        {{ menu?.id ? 'Edit Menu' : 'Create Menu' }}
      </v-card-title>

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <v-text-field
            v-model="form.name"
            label="Menu Name"
            :rules="[rules.required]"
            outlined
            dense
          />

          <v-text-field
            v-model="form.slug"
            label="Slug"
            :rules="[rules.required]"
            outlined
            dense
            hint="Unique identifier (e.g., main-nav, footer)"
            persistent-hint
          />

          <v-select
            v-model="form.location"
            :items="locations"
            label="Location"
            outlined
            dense
            clearable
            hint="Where this menu appears"
            persistent-hint
          />

          <v-textarea
            v-model="form.description"
            label="Description"
            outlined
            dense
            rows="2"
          />

          <v-switch
            v-model="form.is_active"
            label="Active"
            color="primary"
          />
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
  name: 'MenuDialog',
  props: {
    modelValue: Boolean,
    menu: Object,
  },
  emits: ['update:modelValue', 'saved'],
  data() {
    return {
      valid: false,
      saving: false,
      form: {
        name: '',
        slug: '',
        location: '',
        description: '',
        is_active: true,
      },
      locations: [
        { title: 'Header', value: 'header' },
        { title: 'Footer', value: 'footer' },
        { title: 'Mobile', value: 'mobile' },
        { title: 'Sidebar', value: 'sidebar' },
      ],
      rules: {
        required: (v) => !!v || 'Required',
      },
    };
  },
  watch: {
    menu: {
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
        if (this.menu?.id) {
          await axios.patch(`/api/admin/menus/${this.menu.id}`, this.form);
        } else {
          await axios.post('/api/admin/menus', this.form);
        }
        this.$emit('saved');
      } catch (error) {
        console.error('Error saving menu:', error);
        alert('Failed to save menu');
      } finally {
        this.saving = false;
      }
    },
    resetForm() {
      this.form = {
        name: '',
        slug: '',
        location: '',
        description: '',
        is_active: true,
      };
      if (this.$refs.form) {
        this.$refs.form.resetValidation();
      }
    },
  },
};
</script>
