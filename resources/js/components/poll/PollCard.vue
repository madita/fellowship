<template>
  <v-card class="poll-card mb-4">
    <v-card-title class="d-flex align-center">
      <v-icon left>mdi-poll</v-icon>
      {{ poll.title }}
      <v-spacer></v-spacer>
      <v-chip v-if="poll.is_open" color="success" small>Open</v-chip>
      <v-chip v-else color="grey" small>Closed</v-chip>
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
          ></v-radio>
        </v-radio-group>

        <div v-else>
          <v-checkbox
            v-for="option in poll.options"
            :key="option.id"
            v-model="selectedOptions"
            :label="option.option_text"
            :value="option.id"
            :disabled="loading"
          ></v-checkbox>
        </div>

        <v-btn
          color="primary"
          :disabled="!canVote"
          :loading="loading"
          @click="submitVote"
        >
          Submit Vote
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
        text
        small
        color="primary"
        class="mt-2"
        @click="changeVote"
      >
        Change Vote
      </v-btn>
    </v-card-text>

    <v-card-actions class="px-4 pb-4">
      <v-chip small>
        <v-icon left small>mdi-account-multiple</v-icon>
        {{ poll.total_votes }} {{ poll.total_votes === 1 ? 'vote' : 'votes' }}
      </v-chip>
      <v-chip v-if="poll.closes_at" small class="ml-2">
        <v-icon left small>mdi-clock-outline</v-icon>
        {{ formatClosingTime(poll.closes_at) }}
      </v-chip>
      <v-spacer></v-spacer>
      <v-btn
        v-if="canEdit"
        icon
        small
        @click="$emit('edit', poll)"
      >
        <v-icon>mdi-pencil</v-icon>
      </v-btn>
      <v-btn
        v-if="canDelete"
        icon
        small
        @click="$emit('delete', poll)"
      >
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </v-card-actions>
  </v-card>
</template>

<script>
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
      this.loading = true
      try {
        const optionIds = this.poll.type === 'single'
          ? [this.selectedOptions]
          : this.selectedOptions

        const response = await this.$axios.post(`/polls/${this.poll.id}/vote`, {
          option_ids: optionIds
        })

        // Update poll data
        Object.assign(this.poll, response.data.poll)
        this.$emit('voted', this.poll)

        this.$notify({
          type: 'success',
          title: 'Success',
          text: response.data.message
        })
      } catch (error) {
        this.$notify({
          type: 'error',
          title: 'Error',
          text: error.response?.data?.message || 'Failed to submit vote'
        })
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
      
      if (date < now) {
        return 'Closed ' + formatDistanceToNow(date, { addSuffix: true })
      }
      return 'Closes ' + formatDistanceToNow(date, { addSuffix: true })
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
  border-left: 4px solid #1976d2;
}
</style>
