<template>
  <v-card class="poll-card mb-4" rounded="lg">
    <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center ga-2">
      <v-icon color="primary">mdi-poll</v-icon>
      {{ poll.title }}
      <v-spacer />
      <v-chip v-if="poll.is_open" color="success" variant="tonal" size="small">{{ $t('poll.open') }}</v-chip>
      <v-chip v-else variant="tonal" size="small">{{ $t('poll.closed') }}</v-chip>
    </v-card-title>

    <v-card-subtitle v-if="poll.description">
      {{ poll.description }}
    </v-card-subtitle>

    <v-card-text>
      <!-- Voting UI (if poll is open and user hasn't voted) -->
      <div v-if="poll.is_open && !poll.has_voted">
        <v-radio-group
          v-if="poll.type === 'single'"
          v-model="selectedOptions"
          :disabled="loading"
        >
          <v-radio
            v-for="option in poll.options"
            :key="option.id"
            :label="option.option_text"
            :value="option.id"
          />
        </v-radio-group>

        <div v-else>
          <v-checkbox
            v-for="option in poll.options"
            :key="option.id"
            v-model="selectedOptions"
            :label="option.option_text"
            :value="option.id"
            :disabled="loading"
          />
        </div>

        <v-btn
          color="primary"
          variant="flat"
          :disabled="!canVote"
          :loading="loading"
          @click="submitVote"
        >
          {{ $t('poll.submitVote') }}
        </v-btn>
      </div>

      <!-- Results (if user has voted or poll is closed) -->
      <poll-results
        v-else
        :poll="poll"
        :show-votes="!poll.anonymous || !poll.is_open"
      />

      <!-- Change vote button -->
      <v-btn
        v-if="poll.is_open && poll.has_voted"
        variant="text"
        size="small"
        color="primary"
        class="mt-2"
        @click="changeVote"
      >
        {{ $t('poll.changeVote') }}
      </v-btn>
    </v-card-text>

    <v-card-actions class="px-4 pb-4 ga-2">
      <v-chip size="small" variant="tonal" prepend-icon="mdi-account-multiple">
        {{ $t('poll.votesCount', poll.total_votes) }}
      </v-chip>
      <v-chip v-if="poll.closes_at" size="small" variant="tonal" prepend-icon="mdi-clock-outline">
        {{ formatClosingTime(poll.closes_at) }}
      </v-chip>
      <v-spacer />
      <v-btn
        v-if="canEdit"
        icon="mdi-pencil"
        variant="text"
        size="small"
        @click="$emit('edit', poll)"
      />
      <v-btn
        v-if="canDelete"
        icon="mdi-delete"
        variant="text"
        size="small"
        color="error"
        @click="$emit('delete', poll)"
      />
    </v-card-actions>
  </v-card>
</template>

<script>
import axios from 'axios'
import PollResults from './PollResults.vue'
import { format, formatDistanceToNow } from 'date-fns'

export default {
  name: 'PollCard',
  components: {
    PollResults
  },
  props: {
    poll: {
      type: Object,
      required: true
    },
    currentUser: {
      type: Object,
      default: null
    }
  },
  data() {
    return {
      selectedOptions: this.poll.type === 'single' ? null : [],
      loading: false
    }
  },
  computed: {
    canVote() {
      if (this.poll.type === 'single') {
        return this.selectedOptions !== null
      }
      return this.selectedOptions.length > 0
    },
    canEdit() {
      return this.currentUser && this.currentUser.id === this.poll.creator.id && !this.poll.has_voted
    },
    canDelete() {
      return this.currentUser && this.currentUser.id === this.poll.creator.id
    }
  },
  methods: {
    async submitVote() {
      if (this.loading) return

      this.loading = true
      try {
        const optionIds = this.poll.type === 'single'
          ? [this.selectedOptions]
          : this.selectedOptions

        const response = await axios.post(`/api/polls/${this.poll.id}/vote`, {
          option_ids: optionIds
        })

        // Update poll data
        Object.assign(this.poll, response.data.poll)
        this.$emit('voted', this.poll)

        await this.$dialog.success(response.data.message || this.$t('poll.voteSubmitted'))
      } catch (error) {
        await this.$dialog.requestError(error, this.$t('poll.voteFailed'))
      } finally {
        this.loading = false
      }
    },
    changeVote() {
      this.selectedOptions = this.poll.type === 'single'
        ? (this.poll.user_votes[0] || null)
        : [...this.poll.user_votes]
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
  },
  watch: {
    'poll.user_votes': {
      handler(newVotes) {
        if (this.poll.type === 'single') {
          this.selectedOptions = newVotes[0] || null
        } else {
          this.selectedOptions = [...newVotes]
        }
      },
      immediate: true
    }
  }
}
</script>

<style scoped>
.poll-card {
  border-left: 4px solid rgb(var(--v-theme-primary));
}
</style>
