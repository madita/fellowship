<template>
  <div class="sandbox-editor">
    <!-- Header -->
    <div class="sandbox-header">
      <div class="sandbox-info">
        <v-btn
          v-if="isFullscreen"
          variant="tonal"
          color="primary"
          size="small"
          @click="$emit('toggle-fullscreen')"
        >
          <v-icon start>mdi-fullscreen-exit</v-icon>
          Exit
          <v-tooltip activator="parent" location="bottom">Exit fullscreen (Esc)</v-tooltip>
        </v-btn>
        <v-btn
          v-else
          icon
          variant="text"
          size="small"
          @click="$emit('toggle-fullscreen')"
        >
          <v-icon>mdi-fullscreen</v-icon>
          <v-tooltip activator="parent" location="bottom">Focus mode</v-tooltip>
        </v-btn>

        <v-text-field
          v-if="canEdit && isEditingTitle"
          v-model="editableTitle"
          variant="underlined"
          density="compact"
          hide-details
          autofocus
          class="title-input"
          @blur="saveTitle"
          @keyup.enter="saveTitle"
        />
        <h2 v-else @click="startEditTitle" :class="['sandbox-title', { editable: canEdit }]">
          {{ sandbox?.title || 'Untitled' }}
        </h2>

        <v-chip
          :color="visibilityColor"
          variant="tonal"
          size="small"
          :prepend-icon="visibilityIcon"
        >
          {{ sandbox?.visibility }}
        </v-chip>
      </div>

      <div class="sandbox-actions">
        <!-- Collaborators avatars -->
        <div v-if="activeUsers.length" class="collaborators">
          <div
            v-for="u in activeUsers"
            :key="u.id"
            class="collaborator-wrapper"
          >
            <UserAvatar :user="u" size="32" />
          </div>
        </div>

        <!-- Comments toggle -->
        <v-badge
          :content="threadCount"
          :model-value="threadCount > 0"
          color="primary"
          offset-x="2"
          offset-y="2"
        >
          <v-btn
            icon
            variant="text"
            size="small"
            :color="showComments ? 'primary' : undefined"
            @click="toggleComments"
          >
            <v-icon>mdi-comment-text-multiple-outline</v-icon>
            <v-tooltip activator="parent" location="bottom">Comments</v-tooltip>
          </v-btn>
        </v-badge>

        <!-- Action buttons -->
        <v-btn
          v-if="canEdit"
          variant="tonal"
          size="small"
          :loading="saving"
          @click="saveVersion"
        >
          <v-icon start>mdi-content-save-outline</v-icon>
          Save Version
        </v-btn>

        <v-btn
          icon
          variant="text"
          size="small"
          @click="showVersions = true"
        >
          <v-icon>mdi-history</v-icon>
          <v-tooltip activator="parent" location="bottom">History</v-tooltip>
        </v-btn>

        <v-btn
          v-if="canManage"
          icon
          variant="text"
          size="small"
          @click="showSettings = true"
        >
          <v-icon>mdi-cog-outline</v-icon>
          <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
        </v-btn>

        <v-btn
          v-if="canManage"
          color="primary"
          variant="tonal"
          size="small"
          @click="showCollaborators = true"
        >
          <v-icon start>mdi-share-variant-outline</v-icon>
          Share
        </v-btn>
      </div>
    </div>

    <!-- Connection status -->
    <v-banner
      v-if="!connected"
      color="warning"
      density="compact"
      icon="mdi-loading mdi-spin"
      class="connection-banner"
    >
      Connecting to collaboration server...
    </v-banner>

    <!-- Editor area with comments panel -->
    <div class="editor-area">
      <!-- Editor -->
      <div class="editor-container" :class="{ readonly: !canEdit }">
        <editor-content :editor="editor" class="editor-content" />
      </div>

      <!-- Comments Panel -->
      <SandboxComments
        ref="commentsPanel"
        :visible="showComments"
        :sandbox="sandbox"
        :editor="editor"
        :can-edit="canEdit"
        :current-user-id="currentUser?.user?.id || currentUser?.id"
        @close="showComments = false"
        @thread-created="onThreadCreated"
        @thread-deleted="onThreadDeleted"
      />
    </div>

    <!-- Floating menu for formatting -->
    <bubble-menu
      v-if="editor && canEdit"
      :editor="editor"
      :tippy-options="{ duration: 150, maxWidth: 'none' }"
      class="sandbox-bubble-menu"
    >
      <v-btn-group density="compact" variant="flat" color="grey-darken-4" rounded="lg">
        <v-btn
          size="small"
          :variant="editor.isActive('bold') ? 'elevated' : 'flat'"
          :color="editor.isActive('bold') ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleBold().run()"
        >
          <v-icon size="18">mdi-format-bold</v-icon>
          <v-tooltip activator="parent" location="bottom">Bold</v-tooltip>
        </v-btn>
        <v-btn
          size="small"
          :variant="editor.isActive('italic') ? 'elevated' : 'flat'"
          :color="editor.isActive('italic') ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleItalic().run()"
        >
          <v-icon size="18">mdi-format-italic</v-icon>
          <v-tooltip activator="parent" location="bottom">Italic</v-tooltip>
        </v-btn>
        <v-btn
          size="small"
          :variant="editor.isActive('strike') ? 'elevated' : 'flat'"
          :color="editor.isActive('strike') ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleStrike().run()"
        >
          <v-icon size="18">mdi-format-strikethrough</v-icon>
          <v-tooltip activator="parent" location="bottom">Strikethrough</v-tooltip>
        </v-btn>
        <v-btn
          size="small"
          :variant="editor.isActive('heading', { level: 2 }) ? 'elevated' : 'flat'"
          :color="editor.isActive('heading', { level: 2 }) ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        >
          <v-icon size="18">mdi-format-header-2</v-icon>
          <v-tooltip activator="parent" location="bottom">Heading</v-tooltip>
        </v-btn>
        <v-btn
          size="small"
          :variant="editor.isActive('bulletList') ? 'elevated' : 'flat'"
          :color="editor.isActive('bulletList') ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleBulletList().run()"
        >
          <v-icon size="18">mdi-format-list-bulleted</v-icon>
          <v-tooltip activator="parent" location="bottom">Bullet list</v-tooltip>
        </v-btn>
        <v-btn
          size="small"
          :variant="editor.isActive('orderedList') ? 'elevated' : 'flat'"
          :color="editor.isActive('orderedList') ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleOrderedList().run()"
        >
          <v-icon size="18">mdi-format-list-numbered</v-icon>
          <v-tooltip activator="parent" location="bottom">Numbered list</v-tooltip>
        </v-btn>
        <v-btn
          size="small"
          :variant="editor.isActive('blockquote') ? 'elevated' : 'flat'"
          :color="editor.isActive('blockquote') ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleBlockquote().run()"
        >
          <v-icon size="18">mdi-format-quote-close</v-icon>
          <v-tooltip activator="parent" location="bottom">Quote</v-tooltip>
        </v-btn>
        <v-btn
          size="small"
          :variant="editor.isActive('codeBlock') ? 'elevated' : 'flat'"
          :color="editor.isActive('codeBlock') ? 'white' : 'grey-darken-4'"
          @click="editor.chain().focus().toggleCodeBlock().run()"
        >
          <v-icon size="18">mdi-code-tags</v-icon>
          <v-tooltip activator="parent" location="bottom">Code block</v-tooltip>
        </v-btn>

        <v-divider vertical class="mx-1 my-1" />

        <v-btn
          size="small"
          color="grey-darken-4"
          @click="addComment"
        >
          <v-icon size="18">mdi-comment-plus-outline</v-icon>
          <v-tooltip activator="parent" location="bottom">Add comment</v-tooltip>
        </v-btn>
      </v-btn-group>
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
      :current-content="editor ? editor.getHTML() : ''"
      @close="showVersions = false"
      @restore="onVersionRestore"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Editor, EditorContent, BubbleMenu } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Collaboration from '@tiptap/extension-collaboration'
import CollaborationCursor from '@tiptap/extension-collaboration-cursor'
import * as Y from 'yjs'
import { WebsocketProvider } from 'y-websocket'
import axios from 'axios'
import CommentMark from './extensions/CommentMark.js'
import SandboxSettings from './SandboxSettings.vue'
import SandboxCollaborators from './SandboxCollaborators.vue'
import SandboxVersions from './SandboxVersions.vue'
import SandboxComments from './SandboxComments.vue'
import UserAvatar from '../common/UserAvatar.vue'
import colourHelper from '@/helpers/colour.js'

function getUserColor(user) {
  if (user?.colour) return user.colour
  const seed = user?.username?.length || user?.id || 0
  return colourHelper.randomBackgroundColor(seed, null)
}

export default {
  name: 'SandboxEditor',

  components: {
    EditorContent,
    BubbleMenu,
    SandboxSettings,
    SandboxCollaborators,
    SandboxVersions,
    SandboxComments,
    UserAvatar,
  },

  props: {
    uuid: {
      type: String,
      required: true,
    },
    isFullscreen: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['toggle-fullscreen'],

  setup(props) {
    const sandbox = ref(null)
    const editor = ref(null)
    const connected = ref(false)
    const saving = ref(false)
    const canEdit = ref(false)
    const canManage = ref(false)
    const activeUsers = ref([])
    const showSettings = ref(false)
    const showCollaborators = ref(false)
    const showVersions = ref(false)
    const showComments = ref(false)
    const isEditingTitle = ref(false)
    const editableTitle = ref('')
    const currentUser = ref(null)
    const commentsPanel = ref(null)
    const threadCount = ref(0)

    let ydoc = null
    let provider = null
    let echoChannel = null
    let autoSaveInterval = null
    let pendingRestoreContent = null

    const visibilityColor = computed(() => {
      const map = { private: 'error', members: 'warning', public: 'success' }
      return map[sandbox.value?.visibility] || 'default'
    })

    const visibilityIcon = computed(() => {
      const map = { private: 'mdi-lock', members: 'mdi-account-group', public: 'mdi-earth' }
      return map[sandbox.value?.visibility] || 'mdi-file'
    })

    const loadCurrentUser = async () => {
      try {
        const response = await axios.get('/api/user')
        currentUser.value = response.data
      } catch (error) {
        currentUser.value = null
      }
    }

    const loadSandbox = async () => {
      try {
        const response = await axios.get(`/api/sandbox/${props.uuid}`)
        sandbox.value = response.data.sandbox
        canEdit.value = response.data.canEdit
        canManage.value = response.data.canManage

        initializeEditor()
        initializeEcho()
        loadThreadCount()
      } catch (error) {
        console.error('Failed to load sandbox:', error)
      }
    }

    const loadThreadCount = async () => {
      try {
        const response = await axios.get(`/api/sandbox/${sandbox.value.uuid}/threads`)
        threadCount.value = response.data.threads.filter(t => !t.resolved_at).length
      } catch (error) {
        // Ignore
      }
    }

    const initializeEditor = () => {
      ydoc = new Y.Doc()

      const wsUrl = import.meta.env.VITE_YJS_WS_URL || 'ws://localhost:1234'
      const roomName = `sandbox-${sandbox.value.id}`

      provider = new WebsocketProvider(wsUrl, roomName, ydoc)

      const userObj = currentUser.value?.user || currentUser.value || {}
      const userName = userObj.name || userObj.username || 'Anonymous'
      const cursorColor = getUserColor(userObj)

      editor.value = new Editor({
        editable: canEdit.value,
        extensions: [
          StarterKit.configure({
            history: false,
          }),
          Collaboration.configure({
            document: ydoc,
          }),
          CollaborationCursor.configure({
            provider,
            user: {
              name: userName,
              color: cursorColor,
            },
          }),
          CommentMark,
        ],
      })

      provider.on('synced', ({ synced }) => {
        if (synced) {
          connected.value = true

          if (pendingRestoreContent) {
            // Force-set restored content, clearing whatever the Yjs server had cached
            editor.value.commands.setContent(pendingRestoreContent)
            pendingRestoreContent = null
            // Persist immediately so autosave doesn't overwrite with stale data
            saveContent(false)
          } else {
            const yXmlFragment = ydoc.getXmlFragment('default')
            if (yXmlFragment.length === 0 && sandbox.value.content) {
              editor.value.commands.setContent(sandbox.value.content)
            }
          }
        }
      })

      provider.on('status', ({ status }) => {
        connected.value = status === 'connected'
      })

      if (canEdit.value) {
        autoSaveInterval = setInterval(() => {
          saveContent(false)
        }, 30000)
      }
    }

    const initializeEcho = () => {
      if (!window.Echo) return

      const channelName = `sandbox.${sandbox.value.id}`

      echoChannel = window.Echo.join(channelName)
        .here((users) => {
          const seen = new Set()
          activeUsers.value = users
            .filter(u => {
              if (u.id === currentUser.value?.user?.id) return false
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
            }))
        })
        .joining((user) => {
          if (user.id === currentUser.value?.user?.id) return
          if (activeUsers.value.some(u => u.id === user.id)) return
          activeUsers.value.push({
            id: user.id,
            username: user.username || user.name,
            name: user.name,
            avatar: user.avatar,
            initials: user.initials,
          })
        })
        .leaving((user) => {
          activeUsers.value = activeUsers.value.filter(u => u.id !== user.id)
        })
    }

    const saveContent = async (createVersion = false) => {
      if (!canEdit.value || !editor.value) return

      saving.value = true
      try {
        await axios.post(`/api/sandbox/${sandbox.value.uuid}/state`, {
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

    const startEditTitle = () => {
      if (!canEdit.value) return
      isEditingTitle.value = true
      editableTitle.value = sandbox.value.title
    }

    const saveTitle = async () => {
      if (editableTitle.value !== sandbox.value.title) {
        try {
          await axios.put(`/api/sandbox/${sandbox.value.uuid}`, {
            title: editableTitle.value,
          })
          sandbox.value.title = editableTitle.value
        } catch (error) {
          console.error('Failed to save title:', error)
        }
      }
      isEditingTitle.value = false
    }

    const toggleComments = () => {
      showComments.value = !showComments.value
    }

    const addComment = () => {
      if (!editor.value) return
      const { from, to } = editor.value.state.selection
      if (from === to) return

      const quote = editor.value.state.doc.textBetween(from, to, ' ')
      editor.value.commands.setComment('pending')

      showComments.value = true
      setTimeout(() => {
        commentsPanel.value?.startNewThread(quote)
      }, 100)
    }

    const onThreadCreated = (thread) => {
      threadCount.value++
    }

    const onThreadDeleted = (thread) => {
      if (!thread.resolved_at) {
        threadCount.value = Math.max(0, threadCount.value - 1)
      }
    }

    const onSettingsUpdated = (updatedSandbox) => {
      sandbox.value = { ...sandbox.value, ...updatedSandbox }
      showSettings.value = false
    }

    const onVersionRestore = async (content) => {
      // Stop autosave before tearing down
      if (autoSaveInterval) { clearInterval(autoSaveInterval); autoSaveInterval = null }
      if (provider) { provider.destroy(); provider = null }
      if (ydoc) { ydoc.destroy(); ydoc = null }
      if (editor.value) { editor.value.destroy(); editor.value = null }

      sandbox.value.content = content
      pendingRestoreContent = content
      initializeEditor()
      showVersions.value = false
    }

    onMounted(async () => {
      await loadCurrentUser()
      await loadSandbox()
    })

    onUnmounted(() => {
      saveContent(false)
      if (autoSaveInterval) clearInterval(autoSaveInterval)
      if (provider) provider.destroy()
      if (ydoc) ydoc.destroy()
      if (editor.value) editor.value.destroy()
      if (echoChannel && sandbox.value) window.Echo.leave(`sandbox.${sandbox.value.id}`)
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
      showComments,
      isEditingTitle,
      editableTitle,
      currentUser,
      commentsPanel,
      threadCount,
      visibilityColor,
      visibilityIcon,
      loadSandbox,
      saveVersion,
      startEditTitle,
      saveTitle,
      toggleComments,
      addComment,
      onThreadCreated,
      onThreadDeleted,
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
  background: rgb(var(--v-theme-background));
}

.sandbox-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 1rem;
  border-bottom: 1px solid rgb(var(--v-border-color));
  background: rgb(var(--v-theme-surface));
  gap: 1rem;
  flex-wrap: wrap;
}

.sandbox-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
  flex: 1;
}

.sandbox-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;

  &.editable {
    cursor: pointer;
    &:hover {
      color: rgb(var(--v-theme-primary));
    }
  }
}

.title-input {
  max-width: 300px;
}

.sandbox-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.collaborators {
  display: flex;
  align-items: center;
  margin-right: 0.25rem;

  .collaborator-wrapper {
    position: relative;
    margin-left: -8px;

    &:first-child {
      margin-left: 0;
    }
  }
}

.connection-banner {
  flex-shrink: 0;
}

.editor-area {
  flex: 1;
  position: relative;
  overflow: hidden;
  display: flex;
}

.editor-container {
  flex: 1;
  padding: 2rem;
  max-width: 900px;
  margin: 0 auto;
  width: 100%;
  overflow-y: auto;

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
      background-color: rgba(var(--v-theme-on-surface), 0.08);
      color: rgb(var(--v-theme-on-surface));
      padding: 0.2em 0.4em;
      border-radius: 4px;
      font-size: 0.9em;
    }

    pre {
      background: #1e1e1e;
      color: #d4d4d4;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      overflow-x: auto;

      code {
        color: inherit;
        padding: 0;
        background: none;
        font-size: inherit;
      }
    }

    blockquote {
      border-left: 3px solid rgb(var(--v-theme-primary));
      padding-left: 1rem;
      color: rgba(var(--v-theme-on-surface), 0.7);
    }

    // Comment highlight mark
    .comment-highlight {
      background-color: rgba(255, 212, 0, 0.2);
      border-bottom: 2px solid rgba(255, 212, 0, 0.5);
      cursor: pointer;
      transition: background-color 0.15s;
      padding: 1px 0;

      &:hover {
        background-color: rgba(255, 212, 0, 0.4);
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
</style>

<!-- Non-scoped styles for the bubble menu (teleported to document.body by Tippy.js) -->
<style lang="scss">
.sandbox-bubble-menu {
  .v-btn-group {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.24);
  }
}
</style>
