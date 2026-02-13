<template>
    <div class="forum-post-item" :class="{ 'is-solution': post.is_solution }">
        <v-card variant="outlined" class="mb-3" :color="post.is_solution ? 'success' : undefined">
            <!-- Post Header -->
            <v-card-text class="pb-2">
                <div class="d-flex align-center mb-3">
                    <UserAvatar v-if="post.author" :user="post.author" />
                    <div class="ml-3">
                        <span class="font-weight-medium">{{ post.author?.username }}</span>
                        <div class="text-caption text-medium-emphasis">
                            {{ formatDateDistance(post.created_at) }}
                        </div>
                    </div>
                    <v-spacer />
                    <v-chip
                        v-if="post.is_solution"
                        color="success"
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
                    <div class="d-flex gap-2 mt-2">
                        <v-btn
                            color="primary"
                            size="small"
                            :loading="submitting"
                            @click="saveEdit"
                        >
                            {{ $t('forum.save') }}
                        </v-btn>
                        <v-btn
                            variant="text"
                            size="small"
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
                    :color="post.is_liked ? 'red' : undefined"
                    :prepend-icon="post.is_liked ? 'mdi-heart' : 'mdi-heart-outline'"
                    @click="$emit('toggle-like', post.id)"
                >
                    {{ post.like_count || 0 }}
                </v-btn>
                <v-btn
                    v-if="canReply && !threadLocked"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-reply"
                    @click="toggleReplyForm"
                >
                    {{ $t('forum.reply') }}
                </v-btn>
                <v-btn
                    v-if="canEdit"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-pencil"
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
                    @click="$emit('mark-solution', post.id)"
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
                    @click="confirmDelete"
                >
                    {{ $t('forum.delete') }}
                </v-btn>
            </v-card-actions>
        </v-card>

        <!-- Inline Reply Form -->
        <div v-if="showReplyForm" class="reply-form ml-6 mb-3">
            <Tiptap v-model="replyBody" type="simple" />
            <div class="d-flex gap-2 mt-2">
                <v-btn
                    color="primary"
                    size="small"
                    :loading="submitting"
                    :disabled="!replyBody?.trim()"
                    @click="submitReply"
                >
                    {{ $t('forum.reply') }}
                </v-btn>
                <v-btn
                    variant="text"
                    size="small"
                    @click="showReplyForm = false; replyBody = ''"
                >
                    {{ $t('forum.cancel') }}
                </v-btn>
            </div>
        </div>

        <!-- Nested Replies -->
        <div v-if="post.replies && post.replies.length && depth < 3" class="nested-replies" style="margin-left: 24px;">
            <ForumPostItem
                v-for="reply in post.replies"
                :key="reply.id"
                :post="reply"
                :thread="thread"
                :current-user="currentUser"
                :can-reply="canReply"
                :thread-locked="threadLocked"
                :depth="depth + 1"
                @mark-solution="$emit('mark-solution', $event)"
                @delete-post="$emit('delete-post', $event)"
                @create-reply="$emit('create-reply', $event)"
                @update-post="$emit('update-post', $event)"
                @toggle-like="$emit('toggle-like', $event)"
            />
        </div>

        <!-- Flat nested replies at max depth -->
        <div v-else-if="post.replies && post.replies.length && depth >= 3" class="nested-replies" style="margin-left: 24px;">
            <ForumPostItem
                v-for="reply in post.replies"
                :key="reply.id"
                :post="reply"
                :thread="thread"
                :current-user="currentUser"
                :can-reply="canReply"
                :thread-locked="threadLocked"
                :depth="depth"
                @mark-solution="$emit('mark-solution', $event)"
                @delete-post="$emit('delete-post', $event)"
                @create-reply="$emit('create-reply', $event)"
                @update-post="$emit('update-post', $event)"
                @toggle-like="$emit('toggle-like', $event)"
            />
        </div>

        <!-- Confirm Delete Dialog -->
        <ConfirmDialog
            v-model="showDeleteDialog"
            :content="$t('forum.confirmDeletePost')"
            :resolve="onDeleteConfirm"
        />
    </div>
</template>

<script>
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import Tiptap from '@/components/common/tiptap/Tiptap.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'

export default {
    name: 'ForumPostItem',
    components: { UserAvatar, Tiptap, ConfirmDialog },
    props: {
        post: { type: Object, required: true },
        thread: { type: Object, required: true },
        currentUser: { type: Object, default: null },
        canReply: { type: Boolean, default: false },
        threadLocked: { type: Boolean, default: false },
        depth: { type: Number, default: 0 }
    },
    emits: ['mark-solution', 'delete-post', 'create-reply', 'update-post', 'toggle-like'],
    data() {
        return {
            showReplyForm: false,
            replyBody: '',
            editing: false,
            editBody: '',
            showDeleteDialog: false,
            submitting: false
        }
    },
    computed: {
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
            if (this.isAdmin) return true
            if (!this.isAuthor) return false
            // Authors can edit within 1 hour
            const created = new Date(this.post.created_at)
            const hourAgo = new Date(Date.now() - 60 * 60 * 1000)
            return created > hourAgo
        },
        canDelete() {
            return this.isAuthor || this.isAdmin
        },
        canMarkSolution() {
            return (this.isThreadAuthor || this.isAdmin) && !this.threadLocked
        }
    },
    methods: {
        formatDateDistance(date) {
            return formatDateDistanceToNow(date)
        },
        toggleReplyForm() {
            this.showReplyForm = !this.showReplyForm
            if (this.showReplyForm) {
                this.replyBody = ''
            }
        },
        async submitReply() {
            if (!this.replyBody?.trim()) return
            this.submitting = true
            try {
                this.$emit('create-reply', {
                    body: this.replyBody,
                    parent_id: this.post.id
                })
                this.showReplyForm = false
                this.replyBody = ''
            } finally {
                this.submitting = false
            }
        },
        startEdit() {
            this.editing = true
            this.editBody = this.post.body
        },
        cancelEdit() {
            this.editing = false
            this.editBody = ''
        },
        async saveEdit() {
            if (!this.editBody?.trim()) return
            this.submitting = true
            try {
                this.$emit('update-post', {
                    postId: this.post.id,
                    body: this.editBody
                })
                this.editing = false
            } finally {
                this.submitting = false
            }
        },
        confirmDelete() {
            this.showDeleteDialog = true
        },
        onDeleteConfirm(confirmed) {
            if (confirmed) {
                this.$emit('delete-post', this.post.id)
            }
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

.reply-form {
    padding: 8px 0;
}

.gap-2 {
    gap: 8px;
}
</style>
