<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="700">
    <v-card>
      <v-card-title class="text-h5">
        {{ type === 'bug' ? 'Report a Bug' : 'Request a Feature' }}
      </v-card-title>

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <v-text-field
            v-model="form.title"
            label="Title"
            :rules="[rules.required]"
            outlined
            dense
            class="mb-3"
          />

          <v-textarea
            v-model="form.description"
            label="Description"
            :rules="[rules.required]"
            outlined
            rows="8"
            :hint="hint"
            persistent-hint
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
          :loading="submitting"
          :disabled="!valid"
          @click="submit"
        >
          Submit
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import axios from 'axios';

export default {
  name: 'FeedbackComposer',
  props: {
    modelValue: {
      type: Boolean,
      default: false,
    },
    type: {
      type: String,
      required: true,
      validator: (value) => ['bug', 'feature'].includes(value),
    },
  },
  emits: ['update:modelValue', 'submitted'],
  data() {
    return {
      valid: false,
      submitting: false,
      form: {
        title: '',
        description: '',
      },
      rules: {
        required: (v) => !!v || 'This field is required',
      },
    };
  },
  computed: {
    hint() {
      return this.type === 'bug'
        ? 'Please describe the bug, steps to reproduce, and expected vs actual behavior.'
        : 'Please describe the feature you\'d like to see and how it would benefit users.';
    },
  },
  watch: {
    modelValue(val) {
      if (!val) {
        this.reset();
      }
    },
  },
  methods: {
    async submit() {
      if (!this.$refs.form.validate()) return;

      this.submitting = true;
      try {
        const endpoint = this.type === 'bug' ? 'bugs' : 'features';
        const { data } = await axios.post(`/api/feedback/${endpoint}`, this.form);

        this.$emit('submitted', data.ticket);
        this.$emit('update:modelValue', false);
      } catch (error) {
        console.error('Error submitting:', error);
        alert('Failed to submit. Please try again.');
      } finally {
        this.submitting = false;
      }
    },
    reset() {
      this.form.title = '';
      this.form.description = '';
      if (this.$refs.form) {
        this.$refs.form.resetValidation();
      }
    },
  },
};
</script>
