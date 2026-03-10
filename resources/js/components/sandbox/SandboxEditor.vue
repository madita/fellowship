<template>
  <div class="sandbox-editor">
    <!-- Header -->
    <div class="sandbox-header">
      <div class="sandbox-info">
        <input
          v-if="canEdit && isEditingTitle"
          v-model="editableTitle"
          @blur="saveTitle"
          @keyup.enter="saveTitle"
          class="title-input"
          autofocus
        />
        <h1 v-else @click="startEditTitle" :class="{ editable: canEdit }">
          {{ sandbox?.title || 'Untitled' }}
        </h1>
        <span class="visibility-badge" :class="sandbox?.visibility">
          {{ sandbox?.visibility }}
        </span>
      </div>

      <div class="sandbox-actions">
        <!-- Collaborators avatars -->
        <div class="collaborators">
          <div
            v-for="u in activeUsers"
            :key="u.id"
            class="collaborator-wrapper"
            :class="{ 'is-typing': u.isTyping }"
          >
            <UserAvatar :user="u" size="36" />
            <span v-if="u.isTyping" class="typing-dot"></span>
          </div>
        </div>

        <!-- Action buttons -->
        <button v-if="canEdit" @click="saveVersion" class="btn btn-secondary" :disabled="saving">
          <i class="fas fa-save"></i>
          Save Version
        </button>
        <button v-if="canManage" @click="showSettings = true" class="btn btn-secondary">
          <i class="fas fa-cog"></i>
        </button>
        <button v-if="canManage" @click="showCollaborators = true" class="btn btn-primary">
          <i class="fas fa-users"></i>
          Share
        </button>
      </div>
    </div>

    <!-- Connection status -->
    <div v-if="!connected" class="connection-status">
      <i class="fas fa-circle-notch fa-spin"></i>
      Connecting...
    </div>

    <!-- Editor -->
    <div class="editor-container" :class="{ readonly: !canEdit }">
      <editor-content :editor="editor" class="editor-content" />
    </div>

    <!-- Floating menu for formatting -->
    <bubble-menu
      v-if="editor && canEdit"
      :editor="editor"
      :tippy-options="{ duration: 100 }"
      class="bubble-menu"
    >
      <button @click="editor.chain().focus().toggleBold().run()" :class="{ active: editor.isActive('bold') }">
        <i class="fas fa-bold"></i>
      </button>
      <button @click="editor.chain().focus().toggleItalic().run()" :class="{ active: editor.isActive('italic') }">
        <i class="fas fa-italic"></i>
      </button>
      <button @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ active: editor.isActive('heading', { level: 2 }) }">
        <i class="fas fa-heading"></i>
      </button>
      <button @click="editor.chain().focus().toggleBulletList().run()" :class="{ active: editor.isActive('bulletList') }">
        <i class="fas fa-list-ul"></i>
      </button>
      <button @click="editor.chain().focus().toggleCodeBlock().run()" :class="{ active: editor.isActive('codeBlock') }">
        <i class="fas fa-code"></i>
      </button>
    </bubble-menu>

    <!-- Settings Modal -->
    <SandboxSettings
      v-if="showSettings"
      :sandbox="sandbox"
      @close="showSettings = false"
      @updated="onSettingsUpdated"
    />

    <!-- Collaborators Modal -->
    <SandboxCollaborators
      v-if="showCollaborators"
      :sandbox="sandbox"
      @close="showCollaborators = false"
      @updated="loadSandbox"
    />

    <!-- Version History Sidebar -->
    <SandboxVersions
      v-if="showVersions"
      :sandbox="sandbox"
      @close="showVersions = false"
      @restore="onVersionRestore"
    />
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Editor, EditorContent, BubbleMenu } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import axios from 'axios'
import SandboxSettings from './SandboxSettings.vue'
import SandboxCollaborators from './SandboxCollaborators.vue'
import SandboxVersions from './SandboxVersions.vue'
import UserAvatar from '../common/UserAvatar.vue'

export default {
  name: 'SandboxEditor',

  components: {
    EditorContent,
    BubbleMenu,
    SandboxSettings,
    SandboxCollaborators,
    SandboxVersions,
    UserAvatar,
  },

  props: {
    slug: {
      type: String,
      required: true,
    },
  },

  setup(props) {
    const route = useRoute()
    const sandbox = ref(null)
    const editor = ref(null)
    const echoChannel = ref(null)
    const connected = ref(false)
    const saving = ref(false)
    const canEdit = ref(false)
    const canManage = ref(false)
    const activeUsers = ref([])
    const showSettings = ref(false)
    const showCollaborators = ref(false)
    const showVersions = ref(false)
    const isEditingTitle = ref(false)
    const editableTitle = ref('')
    const currentUser = ref(null)
    let autoSaveInterval = null
    let typingTimeout = null
    let isRemoteUpdate = false
    let broadcastDebounce = null
    let saveDebounce = null

    // Load sandbox data
    const loadSandbox = async () => {
      try {
        const response = await axios.get(`/api/sandbox/${props.slug}`)
        sandbox.value = response.data.sandbox
        canEdit.value = response.data.canEdit
        canManage.value = response.data.canManage

        initializeEditor()
        initializeEcho()
      } catch (error) {
        console.error('Failed to load sandbox:', error)
      }
    }

    // Get current user
    const loadCurrentUser = async () => {
      try {
        const response = await axios.get('/api/user')
        currentUser.value = response.data
      } catch (error) {
        currentUser.value = null
      }
    }

    // Initialize Tiptap editor
    const initializeEditor = () => {
      editor.value = new Editor({
        editable: canEdit.value,
        content: sandbox.value.content || '',
        extensions: [
          StarterKit,
        ],
        onUpdate: ({ editor: ed }) => {
          if (isRemoteUpdate) return

          const html = ed.getHTML()

          // Broadcast content to other collaborators via server (debounced 300ms to batch keystrokes)
          if (sandbox.value && canEdit.value) {
            clearTimeout(broadcastDebounce)
            broadcastDebounce = setTimeout(() => {
              axios.post(`/api/sandbox/${sandbox.value.id}/broadcast`, {
                content: html,
              }).catch(err => console.error('Broadcast failed:', err))
            }, 300)
          }

          // Save to server (debounced 2s to avoid flooding DB)
          if (sandbox.value && canEdit.value) {
            clearTimeout(saveDebounce)
            saveDebounce = setTimeout(() => {
              axios.post(`/api/sandbox/${sandbox.value.id}/state`, {
                content: html,
              }).catch(err => console.error('Save failed:', err))
            }, 2000)
          }
        },
      })

      // Auto-save periodically
      if (canEdit.value) {
        autoSaveInterval = setInterval(() => {
          saveContent(false)
        }, 30000)
      }
    }

    // Initialize Laravel Echo presence channel
    const initializeEcho = () => {
      if (!window.Echo) return

      const channelName = `sandbox.${sandbox.value.id}`

      echoChannel.value = window.Echo.join(channelName)
        .here((users) => {
          // Deduplicate by user id, exclude self
          const seen = new Set()
          activeUsers.value = users
            .filter(u => {
              if (u.id === currentUser.value?.id) return false
              if (seen.has(u.id)) return false
              seen.add(u.id)
              return true
            })
            .map(u => ({
              id: u.id,
              username: u.username || u.name,
              name: u.name,
              avatar: u.avatar,
              initials: u.initials,
              isTyping: false,
            }))
          connected.value = true
        })
        .joining((user) => {
          if (user.id === currentUser.value?.id) return
          if (activeUsers.value.some(u => u.id === user.id)) return
          activeUsers.value.push({
            id: user.id,
            username: user.username || user.name,
            name: user.name,
            avatar: user.avatar,
            initials: user.initials,
            isTyping: false,
          })
        })
        .leaving((user) => {
          activeUsers.value = activeUsers.value.filter(u => u.id !== user.id)
        })
        .listen('.content-updated', (e) => {
          if (e.userId === currentUser.value?.id) return
          if (!editor.value) return

          // Show typing indicator on user avatar
          const typingUser = activeUsers.value.find(u => u.id === e.userId)
          if (typingUser) {
            typingUser.isTyping = true
            clearTimeout(typingTimeout)
            typingTimeout = setTimeout(() => {
              typingUser.isTyping = false
            }, 2000)
          }

          // Apply remote content
          isRemoteUpdate = true
          const { from, to } = editor.value.state.selection
          editor.value.commands.setContent(e.content, false)
          try {
            editor.value.commands.setTextSelection({ from, to })
          } catch {
            // Selection may be out of range after remote update
          }
          isRemoteUpdate = false
        })
    }

    // Save content to server
    const saveContent = async (createVersion = false) => {
      if (!canEdit.value || !editor.value) return

      saving.value = true
      try {
        await axios.post(`/api/sandbox/${sandbox.value.id}/state`, {
          content: editor.value.getHTML(),
          createVersion,
        })
      } catch (error) {
        console.error('Failed to save content:', error)
      } finally {
        saving.value = false
      }
    }

    const saveVersion = () => saveContent(true)

    // Title editing
    const startEditTitle = () => {
      if (!canEdit.value) return
      isEditingTitle.value = true
      editableTitle.value = sandbox.value.title
    }

    const saveTitle = async () => {
      if (editableTitle.value !== sandbox.value.title) {
        try {
          await axios.put(`/api/sandbox/${sandbox.value.id}`, {
            title: editableTitle.value,
          })
          sandbox.value.title = editableTitle.value
        } catch (error) {
          console.error('Failed to save title:', error)
        }
      }
      isEditingTitle.value = false
    }

    // Settings updated
    const onSettingsUpdated = (updatedSandbox) => {
      sandbox.value = { ...sandbox.value, ...updatedSandbox }
      showSettings.value = false
    }

    // Version restore
    const onVersionRestore = (content) => {
      if (editor.value) {
        editor.value.commands.setContent(content, false)
      }
      showVersions.value = false
    }

    // Lifecycle
    onMounted(async () => {
      await loadCurrentUser()
      await loadSandbox()
    })

    onUnmounted(() => {
      // Save before leaving
      saveContent(false)

      // Cleanup
      if (autoSaveInterval) {
        clearInterval(autoSaveInterval)
      }
      if (editor.value) {
        editor.value.destroy()
      }
      if (echoChannel.value && sandbox.value) {
        window.Echo.leave(`sandbox.${sandbox.value.id}`)
      }
    })

    return {
      sandbox,
      editor,
      connected,
      saving,
      canEdit,
      canManage,
      activeUsers,
      showSettings,
      showCollaborators,
      showVersions,
      isEditingTitle,
      editableTitle,
      loadSandbox,
      saveVersion,
      startEditTitle,
      saveTitle,
      onSettingsUpdated,
      onVersionRestore,
    }
  },
}
</script>

<style lang="scss" scoped>
.sandbox-editor {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 100vh;
  background: var(--bg-primary, #fff);
}

.sandbox-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  background: var(--bg-secondary, #f9fafb);

  .sandbox-info {
    display: flex;
    align-items: center;
    gap: 1rem;

    h1 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;

      &.editable {
        cursor: pointer;
        &:hover {
          color: var(--primary-color, #3b82f6);
        }
      }
    }

    .title-input {
      font-size: 1.5rem;
      font-weight: 600;
      border: none;
      border-bottom: 2px solid var(--primary-color, #3b82f6);
      background: transparent;
      outline: none;
      padding: 0;
    }

    .visibility-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;

      &.private { background: #fecaca; color: #dc2626; }
      &.members { background: #fef3c7; color: #d97706; }
      &.public { background: #d1fae5; color: #059669; }
    }
  }

  .sandbox-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
  }
}

.collaborators {
  display: flex;
  align-items: center;
  margin-right: 1rem;

  .collaborator-wrapper {
    position: relative;
    margin-left: -8px;

    &:first-child {
      margin-left: 0;
    }

    .typing-dot {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #22c55e;
      border: 2px solid white;
      animation: pulse-typing 1s infinite;
    }

    &.is-typing {
      outline: 2px solid #22c55e;
      outline-offset: 1px;
      border-radius: 50%;
    }
  }
}

@keyframes pulse-typing {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.6; transform: scale(0.8); }
}

.connection-status {
  padding: 0.5rem 1rem;
  background: #fef3c7;
  color: #d97706;
  text-align: center;
  font-size: 0.875rem;

  i {
    margin-right: 0.5rem;
  }
}

.editor-container {
  flex: 1;
  padding: 2rem;
  max-width: 900px;
  margin: 0 auto;
  width: 100%;

  &.readonly {
    .editor-content {
      cursor: default;
    }
  }
}

.editor-content {
  min-height: 500px;

  :deep(.ProseMirror) {
    outline: none;
    min-height: 500px;

    > * + * {
      margin-top: 0.75em;
    }

    h1, h2, h3 {
      line-height: 1.1;
    }

    code {
      background-color: rgba(97, 97, 97, 0.1);
      color: #616161;
      padding: 0.2em 0.4em;
      border-radius: 3px;
    }

    pre {
      background: #0d0d0d;
      color: #fff;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;

      code {
        color: inherit;
        padding: 0;
        background: none;
      }
    }

    // Collaboration cursors
    .collaboration-cursor__caret {
      position: relative;
      margin-left: -1px;
      margin-right: -1px;
      border-left: 1px solid;
      border-right: 1px solid;
      word-break: normal;
      pointer-events: none;
    }

    .collaboration-cursor__label {
      position: absolute;
      top: -1.4em;
      left: -1px;
      font-size: 12px;
      font-style: normal;
      font-weight: 600;
      line-height: normal;
      user-select: none;
      color: white;
      padding: 0.1rem 0.3rem;
      border-radius: 3px 3px 3px 0;
      white-space: nowrap;
    }
  }
}

.bubble-menu {
  display: flex;
  background-color: #0d0d0d;
  padding: 0.2rem;
  border-radius: 0.5rem;

  button {
    border: none;
    background: none;
    color: #fff;
    font-size: 0.85rem;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    cursor: pointer;

    &:hover {
      background-color: #303030;
    }

    &.active {
      background-color: #fff;
      color: #0d0d0d;
    }
  }
}

.btn {
  padding: 0.5rem 1rem;
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
    background: var(--primary-color, #3b82f6);
    color: white;

    &:hover:not(:disabled) {
      background: var(--primary-hover, #2563eb);
    }
  }

  &.btn-secondary {
    background: var(--bg-tertiary, #e5e7eb);
    color: var(--text-primary, #374151);

    &:hover:not(:disabled) {
      background: var(--bg-quaternary, #d1d5db);
    }
  }
}


</style>
