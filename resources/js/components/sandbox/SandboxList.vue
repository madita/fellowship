<template>
  <div class="sandbox-list-page">
    <div class="page-header">
      <div>
        <h1>Sandboxes</h1>
        <p class="subtitle">Collaborate in real-time with others</p>
      </div>
      <button @click="showCreateModal = true" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        New Sandbox
      </button>
    </div>

    <!-- Filters -->
    <div class="filters">
      <button
        v-for="filter in filters"
        :key="filter.value"
        @click="activeFilter = filter.value"
        :class="['filter-btn', { active: activeFilter === filter.value }]"
      >
        {{ filter.label }}
      </button>
    </div>

    <!-- Sandbox grid -->
    <div v-if="loading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      Loading sandboxes...
    </div>

    <div v-else-if="filteredSandboxes.length === 0" class="empty-state">
      <i class="fas fa-file-alt"></i>
      <h3>No sandboxes found</h3>
      <p>Create your first sandbox to start collaborating!</p>
      <button @click="showCreateModal = true" class="btn btn-primary">
        Create Sandbox
      </button>
    </div>

    <div v-else class="sandbox-grid">
      <div
        v-for="sandbox in filteredSandboxes"
        :key="sandbox.id"
        class="sandbox-card"
        @click="openSandbox(sandbox)"
      >
        <div class="card-header">
          <span class="visibility-badge" :class="sandbox.visibility">
            <i :class="getVisibilityIcon(sandbox.visibility)"></i>
          </span>
          <span v-if="sandbox.user_id === currentUserId" class="owner-badge">
            Owner
          </span>
        </div>

        <h3 class="card-title">{{ sandbox.title }}</h3>
        <p class="card-description">{{ sandbox.description || 'No description' }}</p>

        <div class="card-meta">
          <span class="collaborators">
            <i class="fas fa-users"></i>
            {{ sandbox.collaborators_count || 0 }}
          </span>
          <span class="last-edited" v-if="sandbox.last_edited_at">
            {{ formatDate(sandbox.last_edited_at) }}
          </span>
        </div>

        <div class="card-footer">
          <div class="owner-info">
            <span class="avatar">{{ sandbox.owner?.username?.charAt(0).toUpperCase() }}</span>
            <span class="owner-name">{{ sandbox.owner?.username }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="pagination">
      <button
        @click="loadPage(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="page-btn"
      >
        <i class="fas fa-chevron-left"></i>
      </button>
      <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <button
        @click="loadPage(currentPage + 1)"
        :disabled="currentPage >= totalPages"
        class="page-btn"
      >
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Create New Sandbox</h2>
          <button @click="showCreateModal = false" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <form @submit.prevent="createSandbox" class="modal-body">
          <div class="form-group">
            <label for="title">Title *</label>
            <input
              id="title"
              v-model="newSandbox.title"
              type="text"
              class="form-control"
              required
              autofocus
            />
          </div>

          <div class="form-group">
            <label for="description">Description</label>
            <textarea
              id="description"
              v-model="newSandbox.description"
              class="form-control"
              rows="3"
              placeholder="What's this sandbox about?"
            ></textarea>
          </div>

          <div class="form-group">
            <label for="visibility">Visibility</label>
            <select id="visibility" v-model="newSandbox.visibility" class="form-control">
              <option value="private">Private - Only you and collaborators</option>
              <option value="members">Members - All site members can view</option>
              <option value="public">Public - Anyone can view</option>
            </select>
          </div>
        </form>

        <div class="modal-footer">
          <button type="button" @click="showCreateModal = false" class="btn btn-secondary">
            Cancel
          </button>
          <button type="button" @click="createSandbox" class="btn btn-primary" :disabled="creating">
            {{ creating ? 'Creating...' : 'Create Sandbox' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'SandboxList',

  setup() {
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
        showCreateModal.value = false
        router.push(`/sandbox/${response.data.sandbox.slug}`)
      } catch (error) {
        console.error('Failed to create sandbox:', error)
        alert(error.response?.data?.message || 'Failed to create sandbox')
      } finally {
        creating.value = false
      }
    }

    const openSandbox = (sandbox) => {
      router.push(`/sandbox/${sandbox.slug}`)
    }

    const getVisibilityIcon = (visibility) => {
      const icons = {
        private: 'fas fa-lock',
        members: 'fas fa-users',
        public: 'fas fa-globe',
      }
      return icons[visibility] || 'fas fa-file'
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
      filteredSandboxes,
      newSandbox,
      currentUserId,
      loadPage,
      createSandbox,
      openSandbox,
      getVisibilityIcon,
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
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;

  h1 {
    margin: 0 0 0.25rem;
    font-size: 2rem;
  }

  .subtitle {
    margin: 0;
    color: #6b7280;
  }
}

.filters {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.filter-btn {
  padding: 0.5rem 1rem;
  background: #f3f4f6;
  border: none;
  border-radius: 9999px;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    background: #e5e7eb;
  }

  &.active {
    background: #3b82f6;
    color: white;
  }
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #6b7280;

  i {
    font-size: 3rem;
    margin-bottom: 1rem;
  }

  h3 {
    margin: 0 0 0.5rem;
    color: #374151;
  }

  p {
    margin: 0 0 1.5rem;
  }
}

.sandbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.sandbox-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  padding: 1.25rem;
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  }
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.visibility-badge {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;

  &.private { background: #fecaca; color: #dc2626; }
  &.members { background: #fef3c7; color: #d97706; }
  &.public { background: #d1fae5; color: #059669; }
}

.owner-badge {
  font-size: 0.75rem;
  padding: 0.125rem 0.5rem;
  background: #dbeafe;
  color: #2563eb;
  border-radius: 9999px;
}

.card-title {
  margin: 0 0 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
}

.card-description {
  margin: 0 0 1rem;
  font-size: 0.875rem;
  color: #6b7280;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.75rem;
  color: #9ca3af;
  margin-bottom: 1rem;

  i {
    margin-right: 0.25rem;
  }
}

.card-footer {
  padding-top: 0.75rem;
  border-top: 1px solid #f3f4f6;
}

.owner-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;

  .avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
  }

  .owner-name {
    font-size: 0.875rem;
    color: #374151;
  }
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;

  .page-btn {
    padding: 0.5rem 0.75rem;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    cursor: pointer;

    &:hover:not(:disabled) {
      background: #f9fafb;
    }

    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  }

  .page-info {
    font-size: 0.875rem;
    color: #6b7280;
  }
}

// Modal styles
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 0.75rem;
  width: 100%;
  max-width: 480px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;

  h2 {
    margin: 0;
    font-size: 1.25rem;
  }

  .close-btn {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: #6b7280;
  }
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.form-group {
  margin-bottom: 1.25rem;

  label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
  }
}

.form-control {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;

  &:focus {
    outline: none;
    border-color: #3b82f6;
  }
}

.btn {
  padding: 0.625rem 1.25rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &.btn-primary {
    background: #3b82f6;
    color: white;

    &:hover:not(:disabled) {
      background: #2563eb;
    }
  }

  &.btn-secondary {
    background: #e5e7eb;
    color: #374151;

    &:hover:not(:disabled) {
      background: #d1d5db;
    }
  }
}
</style>
