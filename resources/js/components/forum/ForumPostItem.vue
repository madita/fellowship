<template>
    <div class="forum-post-item" :class="{ 'is-solution': post.is_solution }">
        <v-card variant="outlined" rounded="lg" class="mb-3" :color="post.is_solution ? 'success' : undefined">
            <!-- Post Header -->
            <v-card-text class="pb-2">
                <div class="d-flex align-center mb-3">
                    <UserAvatar v-if="post.author || post.meta?.legacy_author" :user="post.author" :legacy-name="post.meta?.legacy_author" />
                    <div class="ml-3">
                        <span class="font-weight-medium">{{ post.display_author || post.author?.username }}</span>
                        <div class="text-caption text-medium-emphasis">
                            {{ formatDateDistance(post.created_at) }}
                        </div>
                    </div>
                    <v-spacer />
                    <v-chip
                        v-if="post.is_solution"
                        color="success"
                        variant="tonal"
                        size="small"
                        prepend-icon="mdi-check-circle"
                    >
                        {{ $t('forum.solution') }}
                    </v-chip>
                </div>

                <!-- Post Body (view mode) -->
                <div v-if="!editing" class="post-body" v-html="post.body"></div>

                <!-- Post Body (edit mode) -->
                <div v-else>
                    <Tiptap v-model="editBody" type="simple" />
                    <div class="d-flex ga-2 mt-2">
                        <v-btn
                            color="primary"
                            variant="flat"
                            size="small"
                            :loading="busy"
                            @click="saveEdit"
                        >
                            {{ $t('forum.save') }}
                        </v-btn>
                        <v-btn
                            variant="text"
                            size="small"
                            :disabled="busy"
                            @click="cancelEdit"
                        >
                            {{ $t('forum.cancel') }}
                        </v-btn>
                    </div>
                </div>
            </v-card-text>

            <!-- Action Bar -->
            <v-card-actions v-if="!editing" class="pt-0">
                <v-btn
                    size="small"
                    variant="text"
                    :color="post.is_liked ? 'error' : undefined"
                    :prepend-icon="post.is_liked ? 'mdi-heart' : 'mdi-heart-outline'"
                    :loading="busy && busyAction === 'like'"
                    :disabled="busy && busyAction !== 'like'"
                    @click="emitAction('like', 'toggle-like')"
                >
                    {{ post.like_count || 0 }}
                </v-btn>
                <v-btn
                    v-if="canReply && !threadLocked"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-reply"
                    :disabled="busy"
                    @click="onReplyClick"
                >
                    {{ $t('forum.reply') }}
                </v-btn>
                <v-btn
                    v-if="canEdit"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-pencil"
                    :disabled="busy"
                    @click="startEdit"
                >
                    {{ $t('forum.edit') }}
                </v-btn>
                <v-btn
                    v-if="canMarkSolution"
                    size="small"
                    variant="text"
                    :prepend-icon="post.is_solution ? 'mdi-close-circle' : 'mdi-check-circle'"
                    color="success"
                    :loading="busy && busyAction === 'solution'"
                    :disabled="busy && busyAction !== 'solution'"
                    @click="emitAction('solution', 'mark-solution')"
                >
                    {{ post.is_solution ? $t('forum.unmarkSolution') : $t('forum.markAsSolution') }}
                </v-btn>
                <v-spacer />
                <v-btn
                    v-if="canDelete"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-delete"
                    color="error"
                    :loading="busy && busyAction === 'delete'"
                    :disabled="busy && busyAction !== 'delete'"
                    @click="emitAction('delete', 'delete-post')"
                >
                    {{ $t('forum.delete') }}
                </v-btn>
            </v-card-actions>
        </v-card>

        <!-- Nested Replies (threaded mode only) -->
        <div v-if="post.children && post.children.length" class="nested-replies" :style="{ marginLeft: depth < 4 ? '24px' : '0' }">
            <ForumPostItem
                v-for="child in post.children"
                :key="child.id"
                :post="child"
                :thread="thread"
                :current-user="currentUser"
                :can-reply="canReply"
                :thread-locked="threadLocked"
                :can-moderate="canModerate"
                :can-delete-others="canDeleteOthers"
                :busy-post-id="busyPostId"
                :depth="depth + 1"
                @mark-solution="$emit('mark-solution', $event)"
                @delete-post="$emit('delete-post', $event)"
                @quote-reply="$emit('quote-reply', $event)"
                @update-post="$emit('update-post', $event)"
                @toggle-like="$emit('toggle-like', $event)"
            />
        </div>
    </div>
</template>

<script>
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import Tiptap from '@/components/common/tiptap/Tiptap.vue'

export default {
    name: 'ForumPostItem',
    components: { UserAvatar, Tiptap },
    props: {
        post: { type: Object, required: true },
        thread: { type: Object, required: true },
        currentUser: { type: Object, default: null },
        canReply: { type: Boolean, default: false },
        threadLocked: { type: Boolean, default: false },
        canModerate: { type: Boolean, default: false },
        canDeleteOthers: { type: Boolean, default: false },
        // Id of the post the parent is currently running a request for;
        // the matching item shows a loader and blocks further clicks.
        busyPostId: { type: [Number, String], default: null },
        depth: { type: Number, default: 0 }
    },
    emits: ['mark-solution', 'delete-post', 'quote-reply', 'update-post', 'toggle-like'],
    data() {
        return {
            editing: false,
            editBody: '',
            busyAction: null
        }
    },
    computed: {
        busy() {
            return this.busyPostId !== null && String(this.busyPostId) === String(this.post.id)
        },
        isAuthor() {
            return this.currentUser && this.post.author && this.currentUser.id === this.post.author.id
        },
        isThreadAuthor() {
            return this.currentUser && this.thread.author && this.currentUser.id === this.thread.author.id
        },
        isAdmin() {
            return this.currentUser?.isAdmin || false
        },
        canEdit() {
            if (this.isAdmin || this.canModerate) return true
            if (!this.isAuthor) return false
            const created = new Date(this.post.created_at)
            const hourAgo = new Date(Date.now() - 60 * 60 * 1000)
            return created > hourAgo
        },
        canDelete() {
            return this.isAuthor || this.isAdmin || this.canDeleteOthers
        },
        canMarkSolution() {
            return (this.isThreadAuthor || this.isAdmin || this.canModerate) && !this.threadLocked
        }
    },
    methods: {
        formatDateDistance(date) {
            return formatDateDistanceToNow(date)
        },
        onReplyClick() {
            this.$emit('quote-reply', {
                postId: this.post.id,
                username: this.post.display_author || this.post.author?.username || '',
                body: this.post.body
            })
        },
        startEdit() {
            this.editing = true
            this.editBody = this.post.body
        },
        cancelEdit() {
            this.editing = false
            this.editBody = ''
        },
        // Hand an action to the parent (which runs the store request and
        // reports back via busyPostId). Ignored while any post is busy.
        emitAction(action, event) {
            if (this.busyPostId !== null) return
            this.busyAction = action
            this.$emit(event, this.post.id)
        },
        saveEdit() {
            if (!this.editBody?.trim() || this.busyPostId !== null) return
            this.busyAction = 'save'
            this.$emit('update-post', {
                postId: this.post.id,
                body: this.editBody,
                // Called by the parent once the request settled; the edit
                // stays open on failure so nothing typed is lost.
                done: (ok) => {
                    if (ok) this.cancelEdit()
                }
            })
        }
    }
}
</script>

<style scoped>
.forum-post-item.is-solution > .v-card {
    border-color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.05);
}

.post-body {
    line-height: 1.6;
}

.post-body :deep(img) {
    max-width: 100%;
    height: auto;
}

.post-body :deep(blockquote) {
    border-left: 3px solid rgba(var(--v-theme-primary), 0.5);
    padding: 8px 16px;
    margin: 8px 0;
    background: rgba(var(--v-theme-primary), 0.05);
    border-radius: 0 8px 8px 0;
}

.post-body :deep(blockquote .quote-author) {
    font-weight: 600;
    font-size: 0.85em;
    margin-bottom: 4px;
    color: rgb(var(--v-theme-primary));
}
</style>
