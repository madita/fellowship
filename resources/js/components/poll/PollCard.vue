<template>
  <v-card class="poll-card mb-4" rounded="lg" variant="outlined">
    <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center ga-2">
      <v-icon color="primary">mdi-poll</v-icon>
      <span class="poll-card-title">{{ current.title }}</span>
      <v-spacer />
      <v-chip v-if="current.is_open" color="success" variant="tonal" size="small">{{ $t('poll.open') }}</v-chip>
      <v-chip v-else variant="tonal" size="small">{{ $t('poll.closed') }}</v-chip>
    </v-card-title>

    <v-card-subtitle v-if="current.description" class="poll-card-description">
      {{ current.description }}
    </v-card-subtitle>

    <v-card-text>
      <!-- Voting UI (open poll, no vote yet or changing the vote) -->
      <div v-if="showVotingUi">
        <v-radio-group
          v-if="current.type === 'single'"
          v-model="selectedOptions"
          :disabled="busy"
          hide-details
        >
          <v-radio
            v-for="option in current.options"
            :key="option.id"
            :label="option.option_text"
            :value="option.id"
          />
        </v-radio-group>

        <div v-else>
          <v-checkbox
            v-for="option in current.options"
            :key="option.id"
            v-model="selectedOptions"
            :label="option.option_text"
            :value="option.id"
            :disabled="busy"
            density="compact"
            hide-details
          />
        </div>

        <div class="d-flex flex-wrap ga-2 mt-3">
          <v-btn
            color="primary"
            variant="flat"
            :disabled="!canVote || busy"
            :loading="voting"
            @click="submitVote"
          >
            {{ $t('poll.submitVote') }}
          </v-btn>
          <v-btn
            v-if="changingVote"
            variant="text"
            :disabled="busy"
            @click="cancelChangeVote"
          >
            {{ $t('common.cancel') }}
          </v-btn>
        </div>
      </div>

      <!-- Results (after voting or when closed) -->
      <template v-else>
        <poll-results
          :poll="current"
          :show-votes="!current.anonymous || !current.is_open"
        />
        <div v-if="current.anonymous && current.is_open" class="text-caption text-medium-emphasis mt-1">
          {{ $t('poll.anonymousNotice') }}
        </div>

        <div v-if="current.is_open && current.has_voted" class="d-flex flex-wrap ga-2 mt-2">
          <v-btn
            variant="text"
            size="small"
            color="primary"
            prepend-icon="mdi-pencil-outline"
            :disabled="busy"
            @click="changeVote"
          >
            {{ $t('poll.changeVote') }}
          </v-btn>
          <v-btn
            variant="text"
            size="small"
            prepend-icon="mdi-undo-variant"
            :disabled="busy"
            :loading="removing"
            @click="removeVote"
          >
            {{ $t('poll.removeVote') }}
          </v-btn>
        </div>
      </template>
    </v-card-text>

    <v-card-actions class="px-4 pb-4 ga-2 flex-wrap">
      <v-chip size="small" variant="tonal" prepend-icon="mdi-account-multiple">
        {{ $t('poll.votesCount', current.total_votes || 0) }}
      </v-chip>
      <v-chip v-if="current.closes_at" size="small" variant="tonal" prepend-icon="mdi-clock-outline">
        {{ formatClosingTime(current.closes_at) }}
      </v-chip>
      <v-chip v-if="current.creator" size="small" variant="text" prepend-icon="mdi-account-outline">
        {{ current.creator.name || current.creator.username }}
      </v-chip>
      <v-spacer />
      <v-btn
        v-if="canEdit"
        icon="mdi-pencil"
        variant="text"
        size="small"
        :aria-label="$t('poll.editPoll')"
        :disabled="busy"
        @click="openEditor"
      />
      <v-btn
        v-if="canDelete"
        icon="mdi-delete"
        variant="text"
        size="small"
        color="error"
        :aria-label="$t('poll.deletePoll')"
        :disabled="busy"
        :loading="deleting"
        @click="deletePoll"
      />
    </v-card-actions>

    <poll-creator
      v-if="canEdit"
      ref="editor"
      hide-activator
      :pollable-type="current.pollable_type"
      :pollable-id="current.pollable_id"
      :existing-poll="current"
      @updated="onUpdated"
    />
  </v-card>
</template>

<script>
import axios from 'axios'
import PollResults from './PollResults.vue'
import PollCreator from './PollCreator.vue'
import { formatDistanceToNow } from 'date-fns'

/**
 * Shows a poll, lets the user vote / change / remove the vote and, for the
 * creator or an admin, edit (while no votes exist) or delete it.
 * Keeps its own copy of the poll and emits `voted`, `updated` (both with the
 * fresh poll payload) and `deleted` (with the poll id) so the owner can sync.
 */
export default {
  name: 'PollCard',
  components: {
    PollResults,
    PollCreator
  },
  props: {
    poll: {
      type: Object,
      required: true
    },
    // Kept for callers that still pass it; permissions now come from the payload
    currentUser: {
      type: Object,
      default: null
    }
  },
  emits: ['voted', 'updated', 'deleted', 'edit', 'delete'],
  data() {
    return {
      current: { ...this.poll },
      selectedOptions: this.poll.type === 'single' ? null : [],
      changingVote: false,
      voting: false,
      removing: false,
      deleting: false
    }
  },
  computed: {
    busy() {
      return this.voting || this.removing || this.deleting
    },
    showVotingUi() {
      return this.current.is_open && (!this.current.has_voted || this.changingVote)
    },
    canVote() {
      if (this.current.type === 'single') {
        return this.selectedOptions !== null && this.selectedOptions !== undefined
      }
      return Array.isArray(this.selectedOptions) && this.selectedOptions.length > 0
    },
    // The API refuses updates once a vote exists, so only offer editing before that
    canEdit() {
      return !!this.current.can_edit && (this.current.total_votes || 0) === 0
    },
    canDelete() {
      return !!this.current.can_delete
    }
  },
  watch: {
    poll: {
      handler(poll) {
        this.current = { ...poll }
        this.syncSelection()
      },
      deep: true
    },
    'current.user_votes': {
      handler() {
        this.syncSelection()
      },
      immediate: true
    }
  },
  methods: {
    syncSelection() {
      const votes = this.current.user_votes || []
      if (this.current.type === 'single') {
        this.selectedOptions = votes[0] ?? null
      } else {
        this.selectedOptions = [...votes]
      }
    },
    applyPoll(poll) {
      if (!poll) return
      this.current = { ...this.current, ...poll }
      this.changingVote = false
    },
    async submitVote() {
      if (this.busy || !this.canVote) return

      this.voting = true
      try {
        const optionIds = this.current.type === 'single'
          ? [this.selectedOptions]
          : this.selectedOptions

        const response = await axios.post(`/api/polls/${this.current.id}/vote`, {
          option_ids: optionIds
        })

        this.applyPoll(response.data.poll)
        this.$emit('voted', this.current)
      } catch (error) {
        await this.$dialog.requestError(error, this.$t('poll.voteFailed'))
      } finally {
        this.voting = false
      }
    },
    async removeVote() {
      if (this.busy) return

      this.removing = true
      try {
        const response = await axios.delete(`/api/polls/${this.current.id}/vote`)
        this.applyPoll(response.data.poll)
        this.$emit('voted', this.current)
      } catch (error) {
        await this.$dialog.requestError(error, this.$t('poll.removeVoteFailed'))
      } finally {
        this.removing = false
      }
    },
    changeVote() {
      this.syncSelection()
      this.changingVote = true
    },
    cancelChangeVote() {
      this.syncSelection()
      this.changingVote = false
    },
    openEditor() {
      if (this.busy) return
      this.$emit('edit', this.current)
      this.$refs.editor?.open()
    },
    onUpdated(poll) {
      this.applyPoll(poll)
      this.$emit('updated', this.current)
    },
    async deletePoll() {
      if (this.busy) return

      const ok = await this.$dialog.confirmDelete(this.$t('poll.confirmDelete', { title: this.current.title }), {
        title: this.$t('poll.deletePoll')
      })
      if (!ok) return

      this.deleting = true
      try {
        const response = await axios.delete(`/api/polls/${this.current.id}`)
        this.$emit('delete', this.current)
        this.$emit('deleted', this.current.id)
        await this.$dialog.success(response.data?.message || this.$t('poll.deleted'))
      } catch (error) {
        await this.$dialog.requestError(error, this.$t('poll.deleteFailed'))
      } finally {
        this.deleting = false
      }
    },
    formatClosingTime(closesAt) {
      const date = new Date(closesAt)
      const now = new Date()
      const time = formatDistanceToNow(date, { addSuffix: true })

      if (date < now) {
        return this.$t('poll.closedAt', { time })
      }
      return this.$t('poll.closesAt', { time })
    }
  }
}
</script>

<style scoped>
.poll-card {
  border-left: 4px solid rgb(var(--v-theme-primary));
}

.poll-card-title {
  white-space: normal;
  word-break: break-word;
}

.poll-card-description {
  white-space: pre-line;
}
</style>
