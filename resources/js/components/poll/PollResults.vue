<template>
  <div class="poll-results">
    <div
      v-for="result in poll.results"
      :key="result.id"
      class="mb-3"
    >
      <div class="d-flex justify-space-between align-center mb-1">
        <span class="text-body-2 font-weight-medium">
          {{ result.option_text }}
          <v-icon
            v-if="isUserVote(result.id)"
            size="small"
            color="primary"
            class="ml-1"
          >
            mdi-check-circle
          </v-icon>
        </span>
        <span class="text-caption text-medium-emphasis">
          <span v-if="showVotes">{{ $t('poll.votesCount', result.votes) }}</span>
          <span class="ml-2 font-weight-bold">{{ result.percentage }}%</span>
        </span>
      </div>
      <v-progress-linear
        :model-value="result.percentage"
        :color="getBarColor(result.percentage)"
        height="8"
        rounded
      />
    </div>

    <empty-state
      v-if="poll.total_votes === 0"
      compact
      icon="mdi-poll"
      :title="$t('poll.noVotes')"
    />
  </div>
</template>

<script>
import EmptyState from '../common/EmptyState.vue'

export default {
  name: 'PollResults',
  components: {
    EmptyState
  },
  props: {
    poll: {
      type: Object,
      required: true
    },
    showVotes: {
      type: Boolean,
      default: true
    }
  },
  methods: {
    isUserVote(optionId) {
      return this.poll.user_votes.includes(optionId)
    },
    getBarColor(percentage) {
      if (percentage >= 50) return 'success'
      if (percentage >= 25) return 'primary'
      if (percentage > 0) return 'info'
      return 'surface-variant'
    }
  }
}
</script>
