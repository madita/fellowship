<template>
  <v-dialog :model-value="modelValue" max-width="480" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="text-h6">
        {{ $t('irc.joinDialog.title') }}
      </v-card-title>
      <v-divider />

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <p class="text-body-2 text-medium-emphasis mb-4">
            {{ $t('irc.joinDialog.server', { name: connection?.server?.name }) }}
          </p>

          <v-text-field
            v-model="channelName"
            :label="$t('irc.joinDialog.channel')"
            :rules="[rules.required]"
            placeholder="#channelname"
            :hint="$t('irc.joinDialog.channelHint')"
            persistent-hint
            autofocus
          />
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="joining" @click="$emit('update:modelValue', false)">
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :loading="joining"
          :disabled="!valid"
          @click="join"
        >
          {{ $t('irc.joinDialog.join') }}
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
        required: (v) => !!v || this.$t('irc.joinDialog.required'),
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
      if (this.joining) return;
      if (!this.$refs.form.validate()) return;

      this.joining = true;
      try {
        await axios.post(`/api/irc/connections/${this.connection.id}/join`, {
          channel: this.channelName,
        });
        this.$emit('joined');
      } catch (error) {
        console.error('Error joining channel:', error);
        await this.$dialog.requestError(error, this.$t('irc.joinDialog.joinFailed'));
      } finally {
        this.joining = false;
      }
    },
  },
};
</script>
