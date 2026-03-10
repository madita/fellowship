<template>
  <div :class="['sandbox-list-page', { 'compact-mode': compact }]">
    <div class="page-header">
      <div v-if="!compact">
        <h1 class="text-h4 font-weight-bold">Sandboxes</h1>
        <p class="text-body-2 text-medium-emphasis">Collaborate in real-time with others</p>
      </div>
      <h3 v-else class="text-subtitle-1 font-weight-bold">Sandboxes</h3>
      <v-btn color="primary" :size="compact ? 'small' : 'default'" @click="showCreateModal = true">
        <v-icon start>mdi-plus</v-icon>
        <span v-if="!compact">New Sandbox</span>
      </v-btn>
    </div>

    <!-- Filters -->
    <div class="filters">
      <v-chip
        v-for="filter in filters"
        :key="filter.value"
        :color="activeFilter === filter.value ? 'primary' : undefined"
        :variant="activeFilter === filter.value ? 'elevated' : 'tonal'"
        :size="compact ? 'x-small' : 'small'"
        @click="activeFilter = filter.value"
      >
        {{ filter.label }}
      </v-chip>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <v-progress-circular indeterminate color="primary" :size="compact ? 24 : 40" />
      <span v-if="!compact" class="text-body-2 text-medium-emphasis ml-3">Loading sandboxes...</span>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredSandboxes.length === 0" class="empty-state">
      <v-icon :size="compact ? 40 : 64" color="medium-emphasis" class="mb-3">mdi-file-document-outline</v-icon>
      <h3 class="text-subtitle-1 font-weight-medium mb-1">No sandboxes found</h3>
      <p v-if="!compact" class="text-body-2 text-medium-emphasis mb-3">Create your first sandbox to start collaborating!</p>
      <v-btn color="primary" size="small" @click="showCreateModal = true">
        Create Sandbox
      </v-btn>
    </div>

    <!-- Compact: Vertical list -->
    <div v-else-if="compact" class="sandbox-compact-list">
      <div
        v-for="sandbox in filteredSandboxes"
        :key="sandbox.id"
        :class="['sandbox-item', { selected: selectedUuid === sandbox.uuid }]"
        @click="selectSandbox(sandbox)"
      >
        <div class="item-top">
          <span class="item-title text-body-2 font-weight-medium">{{ sandbox.title }}</span>
          <v-icon :color="getVisibilityColor(sandbox.visibility)" size="14">
            {{ getVisibilityIcon(sandbox.visibility) }}
          </v-icon>
        </div>
        <div class="item-meta">
          <span class="text-caption text-disabled">{{ sandbox.owner?.username }}</span>
          <span v-if="sandbox.last_edited_at" class="text-caption text-disabled">{{ formatDate(sandbox.last_edited_at) }}</span>
        </div>
      </div>
    </div>

    <!-- Full: Grid layout -->
    <div v-else class="sandbox-grid">
      <v-card
        v-for="sandbox in filteredSandboxes"
        :key="sandbox.id"
        variant="outlined"
        class="sandbox-card"
        @click="openSandbox(sandbox)"
      >
        <v-card-text>
          <div class="card-header mb-3">
            <v-chip
              :color="getVisibilityColor(sandbox.visibility)"
              variant="tonal"
              size="x-small"
              :prepend-icon="getVisibilityIcon(sandbox.visibility)"
            >
              {{ sandbox.visibility }}
            </v-chip>
            <v-chip
              v-if="sandbox.user_id === currentUserId"
              color="primary"
              variant="tonal"
              size="x-small"
            >
              Owner
            </v-chip>
          </div>

          <h3 class="text-subtitle-1 font-weight-bold mb-1">{{ sandbox.title }}</h3>
          <p class="text-body-2 text-medium-emphasis card-description mb-3">{{ sandbox.description || 'No description' }}</p>

          <div class="card-meta mb-3">
            <span class="text-caption text-disabled d-flex align-center ga-1">
              <v-icon size="14">mdi-account-group-outline</v-icon>
              {{ sandbox.collaborators_count || 0 }}
            </span>
            <span v-if="sandbox.last_edited_at" class="text-caption text-disabled">
              {{ formatDate(sandbox.last_edited_at) }}
            </span>
          </div>

          <v-divider class="mb-3" />

          <div class="d-flex align-center ga-2">
            <UserAvatar v-if="sandbox.owner" :user="sandbox.owner" size="24" />
            <span class="text-body-2">{{ sandbox.owner?.username }}</span>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Pagination (full mode only) -->
    <div v-if="!compact && totalPages > 1" class="pagination">
      <v-btn
        icon
        variant="outlined"
        size="small"
        :disabled="currentPage <= 1"
        @click="loadPage(currentPage - 1)"
      >
        <v-icon>mdi-chevron-left</v-icon>
      </v-btn>
      <span class="text-body-2 text-medium-emphasis">Page {{ currentPage }} of {{ totalPages }}</span>
      <v-btn
        icon
        variant="outlined"
        size="small"
        :disabled="currentPage >= totalPages"
        @click="loadPage(currentPage + 1)"
      >
        <v-icon>mdi-chevron-right</v-icon>
      </v-btn>
    </div>

    <!-- Create Modal -->
    <v-dialog v-model="showCreateModal" max-width="480" persistent>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
          <span>Create New Sandbox</span>
          <v-btn icon variant="text" size="small" @click="showCreateModal = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>

        <v-card-text>
          <v-text-field
            v-model="newSandbox.title"
            label="Title *"
            variant="outlined"
            density="compact"
            autofocus
            class="mb-3"
          />

          <v-textarea
            v-model="newSandbox.description"
            label="Description"
            variant="outlined"
            density="compact"
            rows="3"
            placeholder="What's this sandbox about?"
            class="mb-3"
          />

          <v-select
            v-model="newSandbox.visibility"
            label="Visibility"
            variant="outlined"
            density="compact"
            :items="visibilityOptions"
            item-title="text"
            item-value="value"
          />
        </v-card-text>

        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="showCreateModal = false">Cancel</v-btn>
          <v-btn color="primary" :loading="creating" :disabled="!newSandbox.title.trim()" @click="createSandbox">
            Create Sandbox
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import UserAvatar from '../common/UserAvatar.vue'

export default {
  name: 'SandboxList',

  components: {
    UserAvatar,
  },

  props: {
    compact: {
      type: Boolean,
      default: false,
    },
    selectedUuid: {
      type: String,
      default: null,
    },
  },

  emits: ['select', 'created'],

  setup(props, { emit }) {
    const router = useRouter()
    const sandboxes = ref([])
    const loading = ref(true)
    const creating = ref(false)
    const showCreateModal = ref(false)
    const currentPage = ref(1)
    const totalPages = ref(1)
    const activeFilter = ref('all')
    const currentUserId = ref(null)

    const filters = [
      { value: 'all', label: 'All' },
      { value: 'owned', label: 'My Sandboxes' },
      { value: 'shared', label: 'Shared with me' },
    ]

    const visibilityOptions = [
      { value: 'private', text: 'Private - Only you and collaborators' },
      { value: 'members', text: 'Members - All site members can view' },
      { value: 'public', text: 'Public - Anyone can view' },
    ]

    const newSandbox = ref({
      title: '',
      description: '',
      visibility: 'private',
    })

    const filteredSandboxes = computed(() => {
      if (activeFilter.value === 'all') return sandboxes.value
      if (activeFilter.value === 'owned') {
        return sandboxes.value.filter(s => s.user_id === currentUserId.value)
      }
      if (activeFilter.value === 'shared') {
        return sandboxes.value.filter(s => s.user_id !== currentUserId.value)
      }
      return sandboxes.value
    })

    const loadSandboxes = async (page = 1) => {
      loading.value = true
      try {
        const response = await axios.get('/api/sandbox', { params: { page } })
        sandboxes.value = response.data.data
        currentPage.value = response.data.current_page
        totalPages.value = response.data.last_page
      } catch (error) {
        console.error('Failed to load sandboxes:', error)
      } finally {
        loading.value = false
      }
    }

    const loadPage = (page) => {
      if (page >= 1 && page <= totalPages.value) {
        loadSandboxes(page)
      }
    }

    const loadCurrentUser = async () => {
      try {
        const response = await axios.get('/api/user')
        currentUserId.value = response.data.id
      } catch (error) {
        // Not logged in
      }
    }

    const createSandbox = async () => {
      if (!newSandbox.value.title.trim()) return

      creating.value = true
      try {
        const response = await axios.post('/api/sandbox', newSandbox.value)
        const sandbox = response.data.sandbox
        showCreateModal.value = false
        newSandbox.value = { title: '', description: '', visibility: 'private' }

        // Re-fetch list so it shows the new sandbox
        await loadSandboxes(1)

        if (props.compact) {
          emit('created', sandbox)
        } else {
          router.push(`/sandbox/${sandbox.uuid}`)
        }
      } catch (error) {
        console.error('Failed to create sandbox:', error)
        alert(error.response?.data?.message || 'Failed to create sandbox')
      } finally {
        creating.value = false
      }
    }

    const selectSandbox = (sandbox) => {
      emit('select', sandbox.uuid)
    }

    const openSandbox = (sandbox) => {
      if (props.compact) {
        emit('select', sandbox.uuid)
      } else {
        router.push(`/sandbox/${sandbox.uuid}`)
      }
    }

    const getVisibilityIcon = (visibility) => {
      const icons = {
        private: 'mdi-lock',
        members: 'mdi-account-group',
        public: 'mdi-earth',
      }
      return icons[visibility] || 'mdi-file'
    }

    const getVisibilityColor = (visibility) => {
      const colors = {
        private: 'error',
        members: 'warning',
        public: 'success',
      }
      return colors[visibility] || 'default'
    }

    const formatDate = (dateStr) => {
      const date = new Date(dateStr)
      const now = new Date()
      const diffMs = now - date
      const diffMins = Math.floor(diffMs / 60000)
      const diffHours = Math.floor(diffMs / 3600000)
      const diffDays = Math.floor(diffMs / 86400000)

      if (diffMins < 1) return 'Just now'
      if (diffMins < 60) return `${diffMins}m ago`
      if (diffHours < 24) return `${diffHours}h ago`
      if (diffDays < 7) return `${diffDays}d ago`

      return date.toLocaleDateString()
    }

    onMounted(async () => {
      await loadCurrentUser()
      await loadSandboxes()
    })

    return {
      sandboxes,
      loading,
      creating,
      showCreateModal,
      currentPage,
      totalPages,
      activeFilter,
      filters,
      visibilityOptions,
      filteredSandboxes,
      newSandbox,
      currentUserId,
      loadPage,
      createSandbox,
      selectSandbox,
      openSandbox,
      getVisibilityIcon,
      getVisibilityColor,
      formatDate,
    }
  },
}
</script>

<style lang="scss" scoped>
.sandbox-list-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;

  &.compact-mode {
    max-width: none;
    padding: 0;
  }
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;

  .compact-mode & {
    align-items: center;
    padding: 0.75rem 1rem;
    margin-bottom: 0;
    border-bottom: 1px solid rgb(var(--v-border-color));
  }
}

.filters {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;

  .compact-mode & {
    padding: 0.5rem 1rem;
    margin-bottom: 0;
    border-bottom: 1px solid rgb(var(--v-border-color));
  }
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;

  .compact-mode & {
    padding: 2rem 1rem;
  }
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;

  .compact-mode & {
    padding: 2rem 1rem;
  }
}

// Compact list styles
.sandbox-compact-list {
  overflow-y: auto;
}

.sandbox-item {
  padding: 0.75rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid rgb(var(--v-border-color));
  transition: background 0.15s;

  &:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
  }

  &.selected {
    background: rgba(var(--v-theme-primary), 0.08);
    border-left: 3px solid rgb(var(--v-theme-primary));
    padding-left: calc(1rem - 3px);
  }
}

.item-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.item-title {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-meta {
  display: flex;
  justify-content: space-between;
  margin-top: 0.25rem;
}

// Grid styles (full mode)
.sandbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.sandbox-card {
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
  }
}

.card-header {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.card-description {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-meta {
  display: flex;
  gap: 1rem;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
}
</style>
