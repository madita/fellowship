<template>
  <div class="versions-sidebar">
    <div class="sidebar-header">
      <h3>Version History</h3>
      <button @click="$emit('close')" class="close-btn">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="versions-list">
      <div v-if="loading" class="loading">
        <i class="fas fa-spinner fa-spin"></i>
        Loading versions...
      </div>

      <div v-else-if="versions.length === 0" class="empty">
        <i class="fas fa-history"></i>
        <p>No saved versions yet</p>
        <small>Save a version to create a restore point</small>
      </div>

      <div
        v-for="version in versions"
        :key="version.id"
        class="version-item"
      >
        <div class="version-info">
          <span class="version-title">{{ version.title || 'Untitled version' }}</span>
          <span class="version-meta">
            {{ formatDate(version.created_at) }} by {{ version.user?.username }}
          </span>
        </div>
        <button
          @click="restoreVersion(version)"
          class="restore-btn"
          :disabled="restoring === version.id"
        >
          {{ restoring === version.id ? 'Restoring...' : 'Restore' }}
        </button>
      </div>

      <div v-if="hasMore" class="load-more">
        <button @click="loadMore" :disabled="loadingMore">
          {{ loadingMore ? 'Loading...' : 'Load more' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'SandboxVersions',

  props: {
    sandbox: {
      type: Object,
      required: true,
    },
  },

  emits: ['close', 'restore'],

  setup(props, { emit }) {
    const versions = ref([])
    const loading = ref(true)
    const loadingMore = ref(false)
    const restoring = ref(null)
    const hasMore = ref(false)
    const page = ref(1)

    const loadVersions = async () => {
      loading.value = true
      try {
        const response = await axios.get(`/api/sandbox/${props.sandbox.uuid}/versions`, {
          params: { page: page.value }
        })
        versions.value = response.data.data
        hasMore.value = response.data.current_page < response.data.last_page
      } catch (error) {
        console.error('Failed to load versions:', error)
      } finally {
        loading.value = false
      }
    }

    const loadMore = async () => {
      loadingMore.value = true
      page.value++
      try {
        const response = await axios.get(`/api/sandbox/${props.sandbox.uuid}/versions`, {
          params: { page: page.value }
        })
        versions.value = [...versions.value, ...response.data.data]
        hasMore.value = response.data.current_page < response.data.last_page
      } catch (error) {
        console.error('Failed to load more versions:', error)
      } finally {
        loadingMore.value = false
      }
    }

    const restoreVersion = async (version) => {
      if (!confirm('Restore this version? Current changes will be saved first.')) {
        return
      }

      restoring.value = version.id
      try {
        const response = await axios.post(
          `/api/sandbox/${props.sandbox.uuid}/versions/${version.id}/restore`
        )
        emit('restore', response.data.state)
      } catch (error) {
        console.error('Failed to restore version:', error)
        alert('Failed to restore version')
      } finally {
        restoring.value = null
      }
    }

    const formatDate = (dateStr) => {
      const date = new Date(dateStr)
      const now = new Date()
      const diffMs = now - date
      const diffMins = Math.floor(diffMs / 60000)
      const diffHours = Math.floor(diffMs / 3600000)
      const diffDays = Math.floor(diffMs / 86400000)

      if (diffMins < 1) return 'Just now'
      if (diffMins < 60) return `${diffMins} min ago`
      if (diffHours < 24) return `${diffHours} hours ago`
      if (diffDays < 7) return `${diffDays} days ago`

      return date.toLocaleDateString()
    }

    onMounted(() => {
      loadVersions()
    })

    return {
      versions,
      loading,
      loadingMore,
      restoring,
      hasMore,
      loadMore,
      restoreVersion,
      formatDate,
    }
  },
}
</script>

<style lang="scss" scoped>
.versions-sidebar {
  position: fixed;
  top: 0;
  right: 0;
  width: 320px;
  height: 100vh;
  background: white;
  box-shadow: -4px 0 6px -1px rgba(0, 0, 0, 0.1);
  z-index: 100;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;

  h3 {
    margin: 0;
    font-size: 1.125rem;
  }

  .close-btn {
    background: none;
    border: none;
    font-size: 1.125rem;
    cursor: pointer;
    color: #6b7280;

    &:hover {
      color: #374151;
    }
  }
}

.versions-list {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.loading,
.empty {
  text-align: center;
  padding: 2rem;
  color: #6b7280;

  i {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    display: block;
  }

  p {
    margin: 0 0 0.25rem;
  }

  small {
    font-size: 0.75rem;
  }
}

.version-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.875rem;
  border-radius: 0.375rem;
  margin-bottom: 0.5rem;
  border: 1px solid #e5e7eb;

  &:hover {
    background: #f9fafb;
  }
}

.version-info {
  display: flex;
  flex-direction: column;
  overflow: hidden;

  .version-title {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .version-meta {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.125rem;
  }
}

.restore-btn {
  padding: 0.375rem 0.75rem;
  background: #e5e7eb;
  border: none;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;

  &:hover:not(:disabled) {
    background: #d1d5db;
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.load-more {
  text-align: center;
  padding: 0.75rem;

  button {
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    cursor: pointer;

    &:hover:not(:disabled) {
      background: #f9fafb;
    }

    &:disabled {
      opacity: 0.5;
    }
  }
}
</style>
