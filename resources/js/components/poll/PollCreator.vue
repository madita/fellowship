<template>
  <v-dialog v-model="dialog" max-width="640" persistent scrollable>
    <template v-if="!hideActivator" v-slot:activator="{ props: activatorProps }">
      <v-btn
        color="primary"
        variant="elevated"
        prepend-icon="mdi-poll"
        v-bind="activatorProps"
      >
        {{ editMode ? $t('poll.editPoll') : $t('poll.createPoll') }}
      </v-btn>
    </template>

    <v-card>
      <v-card-title class="text-h6 d-flex align-center ga-2">
        <v-icon color="primary">mdi-poll</v-icon>
        {{ editMode ? $t('poll.editPoll') : $t('poll.createPoll') }}
      </v-card-title>
      <v-divider />

      <v-card-text>
        <poll-form
          ref="pollForm"
          v-model="poll"
          :disabled="loading"
        />
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer />
        <v-btn
          variant="text"
          :disabled="loading"
          @click="close"
        >
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :loading="loading"
          @click="submit"
        >
          {{ editMode ? $t('poll.updatePoll') : $t('poll.createPoll') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import axios from 'axios'
import PollForm from './PollForm.vue'

/**
 * Dialog that creates a poll on an existing pollable (POST /api/polls)
 * or, when `existingPoll` is given, edits it (PUT /api/polls/{id}).
 * Emits `created` with the saved poll in both cases and `updated` in edit mode.
 */
export default {
  name: 'PollCreator',
  components: { PollForm },
  props: {
    pollableType: {
      type: String,
      required: true
    },
    pollableId: {
      type: Number,
      required: true
    },
    existingPoll: {
      type: Object,
      default: null
    },
    // Render no activator button; open the dialog through `open()` instead
    hideActivator: {
      type: Boolean,
      default: false
    }
  },
  emits: ['created', 'updated'],
  data() {
    return {
      dialog: false,
      loading: false,
      poll: this.pollFromExisting(this.existingPoll)
    }
  },
  computed: {
    editMode() {
      return !!this.existingPoll
    }
  },
  watch: {
    existingPoll(poll) {
      this.poll = this.pollFromExisting(poll)
    },
    dialog(open) {
      if (open) {
        this.poll = this.pollFromExisting(this.existingPoll)
      }
    }
  },
  methods: {
    open() {
      this.dialog = true
    },
    pollFromExisting(poll) {
      if (!poll) return null
      return {
        title: poll.title,
        description: poll.description || '',
        type: poll.type,
        anonymous: !!poll.anonymous,
        closes_at: poll.closes_at || null,
        options: (poll.options || []).map(opt => (typeof opt === 'string' ? opt : opt.option_text))
      }
    },
    async submit() {
      if (this.loading) return
      if (!this.$refs.pollForm?.validate() || !this.poll) return

      this.loading = true
      try {
        // /api/polls expects options as objects, unlike the inline thread/status payload
        const payload = {
          pollable_type: this.pollableType,
          pollable_id: this.pollableId,
          title: this.poll.title,
          description: this.poll.description || null,
          type: this.poll.type,
          anonymous: this.poll.anonymous,
          closes_at: this.poll.closes_at,
          options: this.poll.options.map(text => ({ option_text: text }))
        }

        let response
        if (this.editMode) {
          response = await axios.put(`/api/polls/${this.existingPoll.id}`, payload)
        } else {
          response = await axios.post('/api/polls', payload)
        }

        const saved = response.data.poll
        this.$emit('created', saved)
        if (this.editMode) {
          this.$emit('updated', saved)
        }
        this.dialog = false

        await this.$dialog.success(response.data.message || this.$t('poll.saved'))
      } catch (error) {
        await this.$dialog.requestError(error, this.$t('poll.saveFailed'))
      } finally {
        this.loading = false
      }
    },
    close() {
      if (this.loading) return
      this.dialog = false
    }
  }
}
</script>
