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
            v-for="user in activeUsers"
            :key="user.id"
            class="collaborator-avatar"
            :style="{ backgroundColor: user.color }"
            :title="user.username"
          >
            {{ user.username?.charAt(0).toUpperCase() }}
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
import Collaboration from '@tiptap/extension-collaboration'
import CollaborationCursor from '@tiptap/extension-collaboration-cursor'
import * as Y from 'yjs'
import { WebsocketProvider } from 'y-websocket'
import axios from 'axios'
import SandboxSettings from './SandboxSettings.vue'
import SandboxCollaborators from './SandboxCollaborators.vue'
import SandboxVersions from './SandboxVersions.vue'

// Generate random color for cursor
const getRandomColor = () => {
  const colors = [
    '#958DF1', '#F98181', '#FBBC88', '#FAF594', '#70CFF8',
    '#94FADB', '#B9F18D', '#C3E2C2', '#EAECCC', '#AFC8AD'
  ]
  return colors[Math.floor(Math.random() * colors.length)]
}

export default {
  name: 'SandboxEditor',

  components: {
    EditorContent,
    BubbleMenu,
    SandboxSettings,
    SandboxCollaborators,
    SandboxVersions,
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
    const ydoc = ref(null)
    const provider = ref(null)
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
    const userColor = getRandomColor()
    const currentUser = ref(null)

    // Load sandbox data
    const loadSandbox = async () => {
      try {
        const response = await axios.get(`/api/sandbox/${props.slug}`)
        sandbox.value = response.data.sandbox
        canEdit.value = response.data.canEdit
        canManage.value = response.data.canManage
        
        // Initialize Y.js and editor after loading sandbox
        initializeCollaboration()
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
        // Not logged in
        currentUser.value = null
      }
    }

    // Initialize Y.js collaboration
    const initializeCollaboration = async () => {
      // Create Y.js document
      ydoc.value = new Y.Doc()

      // Load existing state if available
      try {
        const stateResponse = await axios.get(`/api/sandbox/${sandbox.value.id}/state`)
        if (stateResponse.data.state) {
          const state = Uint8Array.from(atob(stateResponse.data.state), c => c.charCodeAt(0))
          Y.applyUpdate(ydoc.value, state)
        }
      } catch (error) {
        console.error('Failed to load state:', error)
      }

      // Connect to WebSocket provider
      // Using y-websocket with a public demo server for now
      // In production, you'd use your own server or Hocuspocus
      const wsUrl = import.meta.env.VITE_COLLAB_WS_URL || 'wss://demos.yjs.dev'
      provider.value = new WebsocketProvider(
        wsUrl,
        `sandbox-${sandbox.value.id}`,
        ydoc.value
      )

      // Track connection status
      provider.value.on('status', ({ status }) => {
        connected.value = status === 'connected'
      })

      // Track awareness (active users)
      const awareness = provider.value.awareness
      
      awareness.setLocalStateField('user', {
        id: currentUser.value?.id || 'guest-' + Math.random().toString(36).substr(2, 9),
        username: currentUser.value?.username || 'Guest',
        color: userColor,
      })

      awareness.on('change', () => {
        const states = Array.from(awareness.getStates().values())
        activeUsers.value = states
          .filter(state => state.user)
          .map(state => state.user)
      })

      // Initialize Tiptap editor with collaboration
      editor.value = new Editor({
        editable: canEdit.value,
        extensions: [
          StarterKit.configure({
            history: false, // Disable default history, Y.js handles this
          }),
          Collaboration.configure({
            document: ydoc.value,
          }),
          CollaborationCursor.configure({
            provider: provider.value,
            user: {
              name: currentUser.value?.username || 'Guest',
              color: userColor,
            },
          }),
        ],
      })

      // Auto-save periodically
      if (canEdit.value) {
        setInterval(() => {
          saveState(false)
        }, 30000) // Every 30 seconds
      }
    }

    // Save Y.js state to server
    const saveState = async (createVersion = false) => {
      if (!canEdit.value || !ydoc.value) return

      saving.value = true
      try {
        const state = Y.encodeStateAsUpdate(ydoc.value)
        const base64State = btoa(String.fromCharCode.apply(null, state))
        
        await axios.post(`/api/sandbox/${sandbox.value.id}/state`, {
          state: base64State,
          createVersion,
        })
      } catch (error) {
        console.error('Failed to save state:', error)
      } finally {
        saving.value = false
      }
    }

    const saveVersion = () => saveState(true)

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
    const onVersionRestore = (state) => {
      if (ydoc.value) {
        const stateArray = Uint8Array.from(atob(state), c => c.charCodeAt(0))
        Y.applyUpdate(ydoc.value, stateArray)
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
      saveState(false)
      
      // Cleanup
      if (editor.value) {
        editor.value.destroy()
      }
      if (provider.value) {
        provider.value.disconnect()
      }
      if (ydoc.value) {
        ydoc.value.destroy()
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
  margin-right: 1rem;

  .collaborator-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    margin-left: -8px;
    border: 2px solid white;
    cursor: default;

    &:first-child {
      margin-left: 0;
    }
  }
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
