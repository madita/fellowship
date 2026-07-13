<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="400">
    <v-card>
      <v-card-title>
        Join IRC Channel
      </v-card-title>

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <p class="text-subtitle-2 mb-2">
            Server: {{ connection?.server?.name }}
          </p>

          <v-text-field
            v-model="channelName"
            label="Channel Name"
            :rules="[rules.required]"
            outlined
            dense
            placeholder="#channelname"
            hint="# prefix is optional"
            persistent-hint
            autofocus
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
          :loading="joining"
          :disabled="!valid"
          @click="join"
        >
          Join
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import axios from 'axios';

export default {
  name: 'IrcJoinDialog',
  props: {
    modelValue: Boolean,
    connection: Object,
  },
  emits: ['update:modelValue', 'joined'],
  data() {
    return {
      valid: false,
      joining: false,
      channelName: '',
      rules: {
        required: (v) => !!v || 'Required',
      },
    };
  },
  watch: {
    modelValue(val) {
      if (!val) {
        this.channelName = '';
        if (this.$refs.form) {
          this.$refs.form.resetValidation();
        }
      }
    },
  },
  methods: {
    async join() {
      if (!this.$refs.form.validate()) return;

      this.joining = true;
      try {
        await axios.post(`/api/irc/connections/${this.connection.id}/join`, {
          channel: this.channelName,
        });
        this.$emit('joined');
      } catch (error) {
        console.error('Error joining channel:', error);
        alert('Failed to join channel');
      } finally {
        this.joining = false;
      }
    },
  },
};
</script>
