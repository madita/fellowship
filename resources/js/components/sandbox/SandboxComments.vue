<template>
  <div class="comments-panel" :class="{ open: visible }">
    <!-- Header -->
    <div class="comments-panel-header">
      <h3 class="text-subtitle-1 font-weight-bold">Comments</h3>
      <div class="d-flex ga-1">
        <v-btn
          icon
          variant="text"
          size="x-small"
          :color="showResolved ? 'primary' : undefined"
          @click="showResolved = !showResolved"
        >
          <v-icon>mdi-check-circle-outline</v-icon>
          <v-tooltip activator="parent" location="bottom">
            {{ showResolved ? 'Hide resolved' : 'Show resolved' }}
          </v-tooltip>
        </v-btn>
        <v-btn
          icon
          variant="text"
          size="x-small"
          @click="$emit('close')"
        >
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>
    </div>

    <!-- Body -->
    <div class="comments-panel-body">
      <!-- New Comment Form -->
      <v-card v-if="pendingThread" variant="outlined" color="primary" class="mb-3">
        <v-card-text class="pa-3">
          <div class="thread-quote mb-2">
            <v-icon size="12" class="mr-1" color="primary">mdi-format-quote-open</v-icon>
            {{ pendingThread.quote }}
          </div>
          <v-textarea
            ref="newCommentInput"
            v-model="pendingThread.content"
            placeholder="Add a comment..."
            variant="outlined"
            density="compact"
            rows="3"
            hide-details
            auto-grow
            autofocus
            @keydown.ctrl.enter="submitNewThread"
            @keydown.meta.enter="submitNewThread"
          />
          <div class="d-flex justify-end ga-2 mt-2">
            <v-btn variant="text" size="small" @click="cancelNewThread">
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              size="small"
              :disabled="!pendingThread.content.trim()"
              :loading="submitting"
              @click="submitNewThread"
            >
              Comment
            </v-btn>
          </div>
        </v-card-text>
      </v-card>

      <!-- Loading -->
      <div v-if="loading" class="d-flex justify-center pa-6">
        <v-progress-circular indeterminate color="primary" size="32" />
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredThreads.length === 0 && !pendingThread" class="empty-comments">
        <v-icon size="48" color="medium-emphasis" class="mb-3">mdi-comment-text-outline</v-icon>
        <p class="text-body-2 text-medium-emphasis mb-1">No comments yet</p>
        <p class="text-caption text-disabled">Select text and click the comment button to start a discussion</p>
      </div>

      <!-- Thread List -->
      <v-card
        v-for="thread in filteredThreads"
        :key="thread.id"
        :variant="selectedThreadId === thread.id ? 'tonal' : 'outlined'"
        :color="selectedThreadId === thread.id ? 'primary' : undefined"
        :class="['thread-card', { 'thread-resolved': thread.resolved_at }]"
        @click="selectThread(thread)"
        @mouseenter="hoverThread(thread)"
        @mouseleave="unhoverThread(thread)"
      >
        <v-card-text class="pa-3">
          <!-- Quote -->
          <div v-if="thread.quote" class="thread-quote mb-2">
            <v-icon size="12" class="mr-1" color="primary">mdi-format-quote-open</v-icon>
            {{ thread.quote }}
          </div>

          <!-- Resolved badge -->
          <v-chip
            v-if="thread.resolved_at"
            color="success"
            variant="tonal"
            size="x-small"
            prepend-icon="mdi-check-circle"
            class="mb-2"
          >
            Resolved
          </v-chip>

          <!-- Comments -->
          <div
            v-for="comment in thread.comments"
            :key="comment.id"
            class="comment-item"
          >
            <div class="comment-header">
              <UserAvatar v-if="comment.user" :user="comment.user" class="comment-avatar" />
              <span class="comment-author text-body-2 font-weight-medium">
                {{ comment.user?.username || 'Unknown' }}
              </span>
              <span class="comment-time text-caption text-disabled">
                {{ formatDate(comment.created_at) }}
              </span>
              <v-btn
                v-if="comment.user_id === currentUserId"
                icon
                variant="text"
                size="x-small"
                color="error"
                class="delete-btn"
                @click.stop="deleteComment(thread, comment)"
              >
                <v-icon size="14">mdi-delete-outline</v-icon>
                <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
              </v-btn>
            </div>
            <p class="comment-content text-body-2 mb-0">{{ comment.content }}</p>
          </div>

          <!-- Reply input -->
          <div v-if="!thread.resolved_at" class="reply-box mt-2 pt-2">
            <v-text-field
              v-model="replyTexts[thread.id]"
              :placeholder="thread.comments.length ? 'Reply...' : 'Add a comment...'"
              variant="outlined"
              density="compact"
              hide-details
              @keyup.enter="submitReply(thread)"
              @click.stop
            >
              <template #append-inner>
                <v-btn
                  v-if="replyTexts[thread.id]?.trim()"
                  icon
                  variant="text"
                  size="x-small"
                  color="primary"
                  @click.stop="submitReply(thread)"
                >
                  <v-icon size="18">mdi-send</v-icon>
                </v-btn>
              </template>
            </v-text-field>
          </div>

          <!-- Thread actions -->
          <div class="thread-actions mt-2 pt-2 d-flex ga-2">
            <v-btn
              v-if="!thread.resolved_at"
              variant="text"
              size="x-small"
              color="success"
              prepend-icon="mdi-check"
              @click.stop="resolveThread(thread)"
            >
              Resolve
            </v-btn>
            <v-btn
              v-else
              variant="text"
              size="x-small"
              prepend-icon="mdi-refresh"
              @click.stop="unresolveThread(thread)"
            >
              Reopen
            </v-btn>
            <v-spacer />
            <v-btn
              v-if="thread.user_id === currentUserId || canEdit"
              variant="text"
              size="x-small"
              color="error"
              prepend-icon="mdi-delete-outline"
              @click.stop="deleteThread(thread)"
            >
              Delete
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import axios from 'axios'
import UserAvatar from '../common/UserAvatar.vue'

export default {
  name: 'SandboxComments',

  components: {
    UserAvatar,
  },

  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    sandbox: {
      type: Object,
      required: true,
    },
    editor: {
      type: Object,
      default: null,
    },
    canEdit: {
      type: Boolean,
      default: false,
    },
    currentUserId: {
      type: Number,
      default: null,
    },
  },

  emits: ['close', 'thread-created', 'thread-deleted'],

  setup(props, { emit }) {
    const threads = ref([])
    const loading = ref(true)
    const submitting = ref(false)
    const showResolved = ref(false)
    const selectedThreadId = ref(null)
    const pendingThread = ref(null)
    const replyTexts = ref({})
    const newCommentInput = ref(null)

    const filteredThreads = computed(() => {
      if (showResolved.value) return threads.value
      return threads.value.filter((t) => !t.resolved_at)
    })

    const loadThreads = async () => {
      if (!props.sandbox?.uuid) return
      loading.value = true
      try {
        const response = await axios.get(`/api/sandbox/${props.sandbox.uuid}/threads`)
        threads.value = response.data.threads
      } catch (error) {
        console.error('Failed to load threads:', error)
      } finally {
        loading.value = false
      }
    }

    const startNewThread = (quote) => {
      pendingThread.value = {
        quote: quote || '',
        content: '',
      }
      nextTick(() => {
        newCommentInput.value?.focus()
      })
    }

    const cancelNewThread = () => {
      if (pendingThread.value && props.editor) {
        props.editor.commands.unsetComment('pending')
      }
      pendingThread.value = null
    }

    const submitNewThread = async () => {
      if (!pendingThread.value?.content.trim() || submitting.value) return

      submitting.value = true
      try {
        const response = await axios.post(`/api/sandbox/${props.sandbox.uuid}/threads`, {
          quote: pendingThread.value.quote,
          content: pendingThread.value.content,
        })

        const thread = response.data.thread

        if (props.editor) {
          props.editor.commands.unsetComment('pending')

          const { from, to } = props.editor.state.selection
          if (from !== to) {
            props.editor.commands.setComment(thread.uuid)
          }
        }

        threads.value.unshift(thread)
        pendingThread.value = null

        emit('thread-created', thread)
      } catch (error) {
        console.error('Failed to create thread:', error)
      } finally {
        submitting.value = false
      }
    }

    const submitReply = async (thread) => {
      const content = replyTexts.value[thread.id]
      if (!content?.trim()) return

      try {
        const response = await axios.post(
          `/api/sandbox/${props.sandbox.uuid}/threads/${thread.id}/comments`,
          { content: content.trim() }
        )

        thread.comments.push(response.data.comment)
        replyTexts.value[thread.id] = ''
      } catch (error) {
        console.error('Failed to add reply:', error)
      }
    }

    const resolveThread = async (thread) => {
      try {
        await axios.put(`/api/sandbox/${props.sandbox.uuid}/threads/${thread.id}`, {
          resolved: true,
        })
        thread.resolved_at = new Date().toISOString()
      } catch (error) {
        console.error('Failed to resolve thread:', error)
      }
    }

    const unresolveThread = async (thread) => {
      try {
        await axios.put(`/api/sandbox/${props.sandbox.uuid}/threads/${thread.id}`, {
          resolved: false,
        })
        thread.resolved_at = null
      } catch (error) {
        console.error('Failed to unresolve thread:', error)
      }
    }

    const deleteThread = async (thread) => {
      if (!confirm('Delete this comment thread?')) return

      try {
        await axios.delete(`/api/sandbox/${props.sandbox.uuid}/threads/${thread.id}`)

        if (props.editor) {
          props.editor.commands.unsetComment(thread.uuid)
        }

        threads.value = threads.value.filter((t) => t.id !== thread.id)
        emit('thread-deleted', thread)
      } catch (error) {
        console.error('Failed to delete thread:', error)
      }
    }

    const deleteComment = async (thread, comment) => {
      const isLastComment = thread.comments.length === 1
      if (isLastComment && !confirm('Deleting the last comment will also delete this thread. Continue?')) {
        return
      }

      try {
        await axios.delete(
          `/api/sandbox/${props.sandbox.uuid}/threads/${thread.id}/comments/${comment.id}`
        )
        thread.comments = thread.comments.filter((c) => c.id !== comment.id)

        if (thread.comments.length === 0) {
          await axios.delete(`/api/sandbox/${props.sandbox.uuid}/threads/${thread.id}`)
          threads.value = threads.value.filter((t) => t.id !== thread.id)
          if (props.editor) {
            props.editor.commands.unsetComment(thread.uuid)
          }
          emit('thread-deleted', thread)
        }
      } catch (error) {
        console.error('Failed to delete comment:', error)
      }
    }

    const selectThread = (thread) => {
      selectedThreadId.value = thread.id

      if (props.editor) {
        const { doc } = props.editor.state
        let targetPos = null

        doc.descendants((node, pos) => {
          if (targetPos !== null) return false
          if (!node.isText) return
          const mark = node.marks.find(
            (m) => m.type.name === 'comment' && m.attrs.threadId === thread.uuid
          )
          if (mark) {
            targetPos = pos
            return false
          }
        })

        if (targetPos !== null) {
          props.editor.commands.setTextSelection(targetPos)
          props.editor.commands.scrollIntoView()
        }
      }
    }

    const hoverThread = (thread) => {
      if (!props.editor) return
      const { tr } = props.editor.state
      tr.setMeta('commentHover', thread.uuid)
      props.editor.view.dispatch(tr)
    }

    const unhoverThread = (thread) => {
      if (!props.editor) return
      const { tr } = props.editor.state
      tr.setMeta('commentUnhover', thread.uuid)
      props.editor.view.dispatch(tr)
    }

    const formatDate = (dateStr) => {
      if (!dateStr) return ''
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

    watch(
      () => props.visible,
      (visible) => {
        if (visible) loadThreads()
      }
    )

    onMounted(() => {
      if (props.visible) loadThreads()
    })

    return {
      threads,
      loading,
      submitting,
      showResolved,
      selectedThreadId,
      pendingThread,
      replyTexts,
      newCommentInput,
      filteredThreads,
      startNewThread,
      cancelNewThread,
      submitNewThread,
      submitReply,
      resolveThread,
      unresolveThread,
      deleteThread,
      deleteComment,
      selectThread,
      hoverThread,
      unhoverThread,
      formatDate,
      loadThreads,
    }
  },
}
</script>

<style lang="scss" scoped>
.comments-panel {
  position: absolute;
  top: 0;
  right: 0;
  width: 360px;
  height: 100%;
  background: rgb(var(--v-theme-surface));
  border-left: 1px solid rgb(var(--v-border-color));
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 0.25s ease;
  z-index: 20;

  &.open {
    transform: translateX(0);
  }
}

.comments-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid rgb(var(--v-border-color));

  h3 {
    margin: 0;
  }
}

.comments-panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 0.75rem;
}

.empty-comments {
  text-align: center;
  padding: 2rem 1rem;
}

.thread-quote {
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  padding: 0.4rem 0.6rem;
  background: rgba(var(--v-theme-primary), 0.06);
  border-left: 3px solid rgb(var(--v-theme-primary));
  border-radius: 0 4px 4px 0;
  word-break: break-word;
  line-height: 1.4;
}

.thread-card {
  margin-bottom: 0.5rem;
  cursor: pointer;
  transition: all 0.15s;

  &.thread-resolved {
    opacity: 0.6;
  }

  .delete-btn {
    opacity: 0;
    transition: opacity 0.15s;
  }

  &:hover .delete-btn {
    opacity: 1;
  }
}

.comment-item {
  margin-bottom: 0.5rem;

  &:last-child {
    margin-bottom: 0;
  }
}

.comment-header {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 2px;

  .comment-avatar {
    flex-shrink: 0;

    :deep(.v-avatar) {
      width: 22px !important;
      height: 22px !important;
      font-size: 0.55rem;

      .text-h4, .text-h5 {
        font-size: 0.55rem !important;
      }
    }
  }

  .comment-time {
    flex: 1;
    text-align: right;
  }
}

.comment-content {
  padding-left: 30px;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), 0.85);
}

.reply-box {
  border-top: 1px solid rgb(var(--v-border-color));
}

.thread-actions {
  border-top: 1px solid rgb(var(--v-border-color));
}

@media (max-width: 768px) {
  .comments-panel {
    width: 100%;
  }
}
</style>
