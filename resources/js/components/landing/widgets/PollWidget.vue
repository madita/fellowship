<template>
  <v-container :class="containerClass">
    <div v-if="content.title" class="text-h5 font-weight-bold mb-2">
      {{ content.title }}
    </div>
    <div v-if="content.subtitle" class="text-subtitle-2 text-medium-emphasis mb-4">
      {{ content.subtitle }}
    </div>

    <loading-state v-if="loading" compact :text="t('widgets.landing.loadingPolls')" />

    <v-alert v-else-if="error" type="error" class="my-4">
      {{ error }}
    </v-alert>

    <empty-state
      v-else-if="polls.length === 0"
      compact
      icon="mdi-poll-box-outline"
      :title="content.emptyText || t('widgets.landing.noPolls')"
    />

    <div v-else>
      <v-row v-if="config.style === 'grid'">
        <v-col
          v-for="poll in displayedPolls"
          :key="poll.id"
          :cols="config.cols || 12"
          :sm="config.sm || 12"
          :md="config.md || 6"
          :lg="config.lg || 4"
        >
          <poll-card
            :poll="poll"
            :current-user="currentUser"
            @voted="onVoted"
            @edit="$emit('edit', poll)"
            @delete="$emit('delete', poll)"
          />
        </v-col>
      </v-row>

      <div v-else>
        <poll-card
          v-for="poll in displayedPolls"
          :key="poll.id"
          :poll="poll"
          :current-user="currentUser"
          class="mb-4"
          @voted="onVoted"
          @edit="$emit('edit', poll)"
          @delete="$emit('delete', poll)"
        />
      </div>

      <div v-if="content.viewAllUrl" class="text-center mt-4">
        <v-btn
          :href="content.viewAllUrl"
          :to="content.viewAllInternal ? content.viewAllUrl : undefined"
          :color="config.viewAllColor || 'primary'"
          :variant="config.viewAllText ? 'text' : (config.viewAllOutlined ? 'outlined' : 'tonal')"
          append-icon="mdi-arrow-right"
        >
          {{ content.viewAllLabel || 'View All Polls' }}
        </v-btn>
      </div>
    </div>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PollCard from '@/components/poll/PollCard.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import axios from 'axios'

const { t } = useI18n()

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Community Polls',
      subtitle: 'Have your say',
      emptyText: 'No polls available',
      viewAllUrl: '/polls',
      viewAllLabel: 'View All Polls',
      viewAllInternal: true,
      pollableType: null,      // Filter by type (e.g., 'App\\Models\\Forum')
      pollableId: null,         // Filter by ID
      specificPollIds: [],      // Show specific poll IDs only
      showClosed: false         // Include closed polls
    })
  },
  config: {
    type: Object,
    default: () => ({
      style: 'list',            // 'list' or 'grid'
      cols: 12,
      sm: 12,
      md: 6,
      lg: 4,
      maxPolls: 3,              // Maximum number of polls to show
      autoRefresh: false,       // Auto-refresh results
      refreshInterval: 30000,   // Refresh interval in ms (30s default)
      viewAllColor: 'primary',
      viewAllOutlined: false,
      viewAllText: false,
      containerClass: 'py-4'
    })
  },
  currentUser: {
    type: Object,
    default: null
  }
})

const polls = ref([])
const loading = ref(true)
const error = ref(null)
let refreshTimer = null

const containerClass = computed(() => {
  return props.config.containerClass || 'py-4'
})

const displayedPolls = computed(() => {
  let filtered = [...polls.value]
  
  // Filter by open/closed status
  if (!props.content.showClosed) {
    filtered = filtered.filter(p => p.is_open)
  }
  
  // Limit number of polls
  const max = props.config.maxPolls || 3
  return filtered.slice(0, max)
})

const fetchPolls = async () => {
  try {
    loading.value = true
    error.value = null
    
    // Build query parameters
    const params = {}
    
    if (props.content.pollableType && props.content.pollableId) {
      params.pollable_type = props.content.pollableType
      params.pollable_id = props.content.pollableId
    }
    
    // Fetch polls
    const response = await axios.get('/api/polls', { params })
    
    let pollsData = response.data.polls || []
    
    // Filter by specific poll IDs if provided
    if (props.content.specificPollIds && props.content.specificPollIds.length > 0) {
      pollsData = pollsData.filter(p => props.content.specificPollIds.includes(p.id))
    }
    
    polls.value = pollsData
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load polls'
    console.error('Failed to fetch polls:', err)
  } finally {
    loading.value = false
  }
}

const onVoted = (updatedPoll) => {
  // Update the poll in the list
  const index = polls.value.findIndex(p => p.id === updatedPoll.id)
  if (index !== -1) {
    polls.value[index] = updatedPoll
  }
}

const startAutoRefresh = () => {
  if (props.config.autoRefresh && props.config.refreshInterval) {
    refreshTimer = setInterval(fetchPolls, props.config.refreshInterval)
  }
}

const stopAutoRefresh = () => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
  }
}

onMounted(() => {
  fetchPolls()
  startAutoRefresh()
})

// Cleanup on unmount
import { onUnmounted } from 'vue'
onUnmounted(() => {
  stopAutoRefresh()
})

// Expose refresh method for parent components
defineExpose({
  refresh: fetchPolls
})
</script>

<style scoped>
/* Styles inherited from PollCard component */
</style>
