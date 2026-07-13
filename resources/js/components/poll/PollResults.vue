<template>
  <div class="poll-results">
    <div
      v-for="result in poll.results"
      :key="result.id"
      class="result-item mb-3"
    >
      <div class="d-flex justify-space-between align-center mb-1">
        <span class="option-text">
          {{ result.option_text }}
          <v-icon
            v-if="isUserVote(result.id)"
            small
            color="primary"
            class="ml-1"
          >
            mdi-check-circle
          </v-icon>
        </span>
        <span class="result-stats">
          <span v-if="showVotes">{{ result.votes }} {{ result.votes === 1 ? 'vote' : 'votes' }}</span>
          <span class="ml-2 font-weight-bold">{{ result.percentage }}%</span>
        </span>
      </div>
      <v-progress-linear
        :value="result.percentage"
        :color="getBarColor(result.percentage)"
        height="8"
        rounded
      ></v-progress-linear>
    </div>

    <div v-if="poll.total_votes === 0" class="text-center text--secondary">
      No votes yet
    </div>
  </div>
</template>

<script>
export default {
  name: 'PollResults',
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
      return 'grey lighten-2'
    }
  }
}
</script>

<style scoped>
.result-item {
  position: relative;
}

.option-text {
  font-size: 14px;
  font-weight: 500;
}

.result-stats {
  font-size: 13px;
  color: #666;
}
</style>
