<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Share Sandbox</h2>
        <button @click="$emit('close')" class="close-btn">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- Share link -->
        <div class="share-link-section">
          <label>Share Link</label>
          <div class="share-link-input">
            <input
              type="text"
              :value="shareLink"
              readonly
              ref="linkInput"
            />
            <button @click="copyLink" class="copy-btn">
              <i :class="copied ? 'fas fa-check' : 'fas fa-copy'"></i>
            </button>
          </div>
          <p class="hint">
            {{ sandbox.visibility === 'public' 
              ? 'Anyone with this link can view' 
              : 'Only collaborators can access' }}
          </p>
        </div>

        <!-- Add collaborator -->
        <div class="add-collaborator">
          <label>Add People</label>
          <div class="search-box">
            <input
              v-model="searchQuery"
              @input="searchUsers"
              type="text"
              placeholder="Search by username..."
              class="form-control"
            />
            <div v-if="searchResults.length > 0" class="search-results">
              <div
                v-for="user in searchResults"
                :key="user.id"
                @click="addCollaborator(user)"
                class="search-result-item"
              >
                <span class="username">{{ user.username }}</span>
                <span class="email">{{ user.email }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Current collaborators -->
        <div class="collaborators-list">
          <label>People with access</label>

          <!-- Owner -->
          <div class="collaborator-item owner">
            <div class="user-info">
              <div class="avatar">{{ sandbox.owner?.username?.charAt(0).toUpperCase() }}</div>
              <div class="details">
                <span class="username">{{ sandbox.owner?.username }}</span>
                <span class="role">Owner</span>
              </div>
            </div>
          </div>

          <!-- Collaborators -->
          <div
            v-for="collab in collaborators"
            :key="collab.id"
            class="collaborator-item"
          >
            <div class="user-info">
              <div class="avatar">{{ collab.username?.charAt(0).toUpperCase() }}</div>
              <div class="details">
                <span class="username">{{ collab.username }}</span>
                <span class="pending" v-if="!collab.pivot?.accepted_at">Pending</span>
              </div>
            </div>
            <div class="actions">
              <select
                v-model="collab.pivot.role"
                @change="updateRole(collab)"
                class="role-select"
              >
                <option value="viewer">Can view</option>
                <option value="editor">Can edit</option>
                <option value="admin">Admin</option>
              </select>
              <button @click="removeCollaborator(collab)" class="remove-btn">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>

          <p v-if="collaborators.length === 0" class="no-collaborators">
            No collaborators yet. Invite people to collaborate!
          </p>
        </div>
      </div>

      <div class="modal-footer">
        <button @click="$emit('close')" class="btn btn-primary">
          Done
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'SandboxCollaborators',

  props: {
    sandbox: {
      type: Object,
      required: true,
    },
  },

  emits: ['close', 'updated'],

  setup(props, { emit }) {
    const searchQuery = ref('')
    const searchResults = ref([])
    const collaborators = ref([])
    const copied = ref(false)
    const linkInput = ref(null)
    let searchTimeout = null

    const shareLink = computed(() => {
      return `${window.location.origin}/sandbox/${props.sandbox.slug}`
    })

    onMounted(() => {
      collaborators.value = props.sandbox.collaborators || []
    })

    const copyLink = async () => {
      try {
        await navigator.clipboard.writeText(shareLink.value)
        copied.value = true
        setTimeout(() => copied.value = false, 2000)
      } catch (error) {
        // Fallback for older browsers
        linkInput.value?.select()
        document.execCommand('copy')
        copied.value = true
        setTimeout(() => copied.value = false, 2000)
      }
    }

    const searchUsers = () => {
      clearTimeout(searchTimeout)
      if (searchQuery.value.length < 2) {
        searchResults.value = []
        return
      }

      searchTimeout = setTimeout(async () => {
        try {
          const response = await axios.post('/api/users/search', {
            query: searchQuery.value,
          })
          // Filter out existing collaborators and owner
          const existingIds = [
            props.sandbox.user_id,
            ...collaborators.value.map(c => c.id)
          ]
          searchResults.value = response.data.filter(
            user => !existingIds.includes(user.id)
          )
        } catch (error) {
          console.error('Search failed:', error)
        }
      }, 300)
    }

    const addCollaborator = async (user) => {
      try {
        const response = await axios.post(
          `/api/sandbox/${props.sandbox.id}/collaborators`,
          { user_id: user.id, role: 'editor' }
        )
        collaborators.value = response.data.collaborators
        searchQuery.value = ''
        searchResults.value = []
        emit('updated')
      } catch (error) {
        console.error('Failed to add collaborator:', error)
        alert(error.response?.data?.error || 'Failed to add collaborator')
      }
    }

    const updateRole = async (collab) => {
      try {
        await axios.post(`/api/sandbox/${props.sandbox.id}/collaborators`, {
          user_id: collab.id,
          role: collab.pivot.role,
        })
      } catch (error) {
        console.error('Failed to update role:', error)
      }
    }

    const removeCollaborator = async (collab) => {
      if (!confirm(`Remove ${collab.username} from this sandbox?`)) return

      try {
        await axios.delete(
          `/api/sandbox/${props.sandbox.id}/collaborators/${collab.id}`
        )
        collaborators.value = collaborators.value.filter(c => c.id !== collab.id)
        emit('updated')
      } catch (error) {
        console.error('Failed to remove collaborator:', error)
      }
    }

    return {
      searchQuery,
      searchResults,
      collaborators,
      copied,
      linkInput,
      shareLink,
      copyLink,
      searchUsers,
      addCollaborator,
      updateRole,
      removeCollaborator,
    }
  },
}
</script>

<style lang="scss" scoped>
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
  border-radius: 0.5rem;
  width: 100%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
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
  padding: 1rem 1.5rem;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
}

.share-link-section {
  margin-bottom: 1.5rem;

  label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
  }

  .hint {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
  }
}

.share-link-input {
  display: flex;
  gap: 0.5rem;

  input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: #f9fafb;
  }

  .copy-btn {
    padding: 0.5rem 0.75rem;
    background: #e5e7eb;
    border: none;
    border-radius: 0.375rem;
    cursor: pointer;

    &:hover {
      background: #d1d5db;
    }
  }
}

.add-collaborator {
  margin-bottom: 1.5rem;

  label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
  }
}

.search-box {
  position: relative;
}

.form-control {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
}

.search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  margin-top: 0.25rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  z-index: 10;
}

.search-result-item {
  padding: 0.625rem 0.875rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;

  &:hover {
    background: #f3f4f6;
  }

  .username {
    font-weight: 500;
  }

  .email {
    color: #6b7280;
    font-size: 0.875rem;
  }
}

.collaborators-list {
  label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.75rem;
  }
}

.collaborator-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  border-radius: 0.375rem;

  &:hover {
    background: #f9fafb;
  }

  &.owner {
    background: #f0fdf4;
  }
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
}

.details {
  display: flex;
  flex-direction: column;

  .username {
    font-weight: 500;
  }

  .role {
    font-size: 0.75rem;
    color: #059669;
  }

  .pending {
    font-size: 0.75rem;
    color: #d97706;
  }
}

.actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.role-select {
  padding: 0.25rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  background: white;
}

.remove-btn {
  padding: 0.25rem 0.5rem;
  background: none;
  border: none;
  color: #dc2626;
  cursor: pointer;
  border-radius: 0.25rem;

  &:hover {
    background: #fef2f2;
  }
}

.no-collaborators {
  text-align: center;
  color: #6b7280;
  padding: 1rem;
}

.btn {
  padding: 0.625rem 1.25rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  border: none;

  &.btn-primary {
    background: #3b82f6;
    color: white;
  }
}
</style>
