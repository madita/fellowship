<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="600">
    <v-card>
      <v-card-title>
        {{ connection?.id ? 'Edit Connection' : 'New IRC Connection' }}
      </v-card-title>

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <v-select
            v-model="form.irc_server_id"
            :items="servers"
            item-title="name"
            item-value="id"
            label="IRC Server"
            :rules="[rules.required]"
            outlined
            dense
          >
            <template #item="{ props, item }">
              <v-list-item v-bind="props">
                <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                <v-list-item-subtitle>{{ item.raw.host }}:{{ item.raw.port }}</v-list-item-subtitle>
              </v-list-item>
            </template>
          </v-select>

          <v-text-field
            v-model="form.nickname"
            label="Nickname"
            :rules="[rules.required]"
            outlined
            dense
            hint="Your IRC nickname"
            persistent-hint
          />

          <v-text-field
            v-model="form.username"
            label="Username (optional)"
            outlined
            dense
            hint="Defaults to nickname"
          />

          <v-text-field
            v-model="form.realname"
            label="Real Name (optional)"
            outlined
            dense
            hint="Defaults to nickname"
          />

          <v-textarea
            v-model="channelsText"
            label="Auto-join Channels"
            outlined
            rows="3"
            hint="One per line, e.g., #channel"
            persistent-hint
          />

          <v-switch
            v-model="form.auto_connect"
            label="Auto-connect on login"
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
  name: 'IrcConnectionDialog',
  props: {
    modelValue: Boolean,
    connection: Object,
    servers: Array,
  },
  emits: ['update:modelValue', 'saved'],
  data() {
    return {
      valid: false,
      saving: false,
      form: {
        irc_server_id: null,
        nickname: '',
        username: '',
        realname: '',
        auto_connect: false,
        auto_join_channels: [],
      },
      channelsText: '',
      rules: {
        required: (v) => !!v || 'Required',
      },
    };
  },
  watch: {
    connection: {
      immediate: true,
      handler(val) {
        if (val?.id) {
          this.form = { ...val };
          this.channelsText = (val.auto_join_channels || []).join('\n');
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

      // Parse channels
      this.form.auto_join_channels = this.channelsText
        .split('\n')
        .map((c) => c.trim())
        .filter((c) => c.length > 0);

      this.saving = true;
      try {
        if (this.connection?.id) {
          await axios.patch(`/api/irc/connections/${this.connection.id}`, this.form);
        } else {
          await axios.post('/api/irc/connections', this.form);
        }
        this.$emit('saved');
      } catch (error) {
        console.error('Error saving connection:', error);
        alert('Failed to save connection');
      } finally {
        this.saving = false;
      }
    },
    resetForm() {
      this.form = {
        irc_server_id: null,
        nickname: '',
        username: '',
        realname: '',
        auto_connect: false,
        auto_join_channels: [],
      };
      this.channelsText = '';
      if (this.$refs.form) {
        this.$refs.form.resetValidation();
      }
    },
  },
};
</script>
