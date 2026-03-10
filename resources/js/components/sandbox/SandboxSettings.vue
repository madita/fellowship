<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Sandbox Settings</h2>
        <button @click="$emit('close')" class="close-btn">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <form @submit.prevent="saveSettings" class="modal-body">
        <div class="form-group">
          <label for="title">Title</label>
          <input
            id="title"
            v-model="form.title"
            type="text"
            class="form-control"
            required
          />
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea
            id="description"
            v-model="form.description"
            class="form-control"
            rows="3"
          ></textarea>
        </div>

        <div class="form-group">
          <label for="visibility">Visibility</label>
          <select id="visibility" v-model="form.visibility" class="form-control">
            <option value="private">Private - Only you and collaborators</option>
            <option value="members">Members - All site members can view</option>
            <option value="public">Public - Anyone can view</option>
          </select>
        </div>

        <div class="form-group">
          <label>Editor Settings</label>
          <div class="checkbox-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.settings.showCursors" />
              Show collaborator cursors
            </label>
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.settings.autoSave" />
              Auto-save changes
            </label>
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.settings.allowComments" />
              Allow comments
            </label>
          </div>
        </div>

        <div class="danger-zone">
          <h3>Danger Zone</h3>
          <button type="button" @click="confirmDelete" class="btn btn-danger">
            <i class="fas fa-trash"></i>
            Delete Sandbox
          </button>
        </div>
      </form>

      <div class="modal-footer">
        <button type="button" @click="$emit('close')" class="btn btn-secondary">
          Cancel
        </button>
        <button type="button" @click="saveSettings" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'SandboxSettings',

  props: {
    sandbox: {
      type: Object,
      required: true,
    },
  },

  emits: ['close', 'updated'],

  setup(props, { emit }) {
    const saving = ref(false)
    const form = reactive({
      title: '',
      description: '',
      visibility: 'private',
      settings: {
        showCursors: true,
        autoSave: true,
        allowComments: true,
      },
    })

    onMounted(() => {
      form.title = props.sandbox.title
      form.description = props.sandbox.description || ''
      form.visibility = props.sandbox.visibility
      form.settings = { ...form.settings, ...(props.sandbox.settings || {}) }
    })

    const saveSettings = async () => {
      saving.value = true
      try {
        const response = await axios.put(`/api/sandbox/${props.sandbox.uuid}`, form)
        emit('updated', response.data.sandbox)
      } catch (error) {
        console.error('Failed to save settings:', error)
        alert('Failed to save settings')
      } finally {
        saving.value = false
      }
    }

    const confirmDelete = async () => {
      if (confirm('Are you sure you want to delete this sandbox? This action cannot be undone.')) {
        try {
          await axios.delete(`/api/sandbox/${props.sandbox.uuid}`)
          window.location.href = '/sandbox'
        } catch (error) {
          console.error('Failed to delete:', error)
          alert('Failed to delete sandbox')
        }
      }
    }

    return {
      form,
      saving,
      saveSettings,
      confirmDelete,
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
  max-width: 500px;
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

    &:hover {
      color: #374151;
    }
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
    color: #374151;
  }
}

.form-control {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;

  &:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
}

.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;

  input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
  }
}

.danger-zone {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid #fecaca;

  h3 {
    color: #dc2626;
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
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
  }

  &.btn-secondary {
    background: #e5e7eb;
    color: #374151;
  }

  &.btn-danger {
    background: #dc2626;
    color: white;
  }
}
</style>
