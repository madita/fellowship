<template>
  <v-dialog :model-value="modelValue" max-width="600" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="text-h6">
        {{ connection?.id ? $t('irc.connectionDialog.editTitle') : $t('irc.connectionDialog.newTitle') }}
      </v-card-title>
      <v-divider />

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <v-select
            v-model="form.irc_server_id"
            :items="servers"
            item-title="name"
            item-value="id"
            :label="$t('irc.connectionDialog.server')"
            :rules="[rules.required]"
            class="mb-2"
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
            :label="$t('irc.connectionDialog.nickname')"
            :rules="[rules.required]"
            :hint="$t('irc.connectionDialog.nicknameHint')"
            persistent-hint
            class="mb-2"
          />

          <v-text-field
            v-model="form.username"
            :label="$t('irc.connectionDialog.username')"
            :hint="$t('irc.connectionDialog.usernameHint')"
            class="mb-2"
          />

          <v-text-field
            v-model="form.realname"
            :label="$t('irc.connectionDialog.realname')"
            :hint="$t('irc.connectionDialog.realnameHint')"
            class="mb-2"
          />

          <v-textarea
            v-model="channelsText"
            :label="$t('irc.connectionDialog.autoJoin')"
            rows="3"
            :hint="$t('irc.connectionDialog.autoJoinHint')"
            persistent-hint
            class="mb-2"
          />

          <v-switch
            v-model="form.auto_connect"
            :label="$t('irc.connectionDialog.autoConnect')"
            color="primary"
            hide-details
          />
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="saving" @click="$emit('update:modelValue', false)">
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :loading="saving"
          :disabled="!valid"
          @click="save"
        >
          {{ $t('common.save') }}
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
        required: (v) => !!v || this.$t('irc.connectionDialog.required'),
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
      if (this.saving) return;
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
        await this.$dialog.requestError(error, this.$t('irc.connectionDialog.saveFailed'));
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
