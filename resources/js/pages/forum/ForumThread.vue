<template>
    <div class="forum-thread-container">
        <!-- Header Section -->
        <div class="forum-header">
            <v-container>
                <!-- Breadcrumbs -->
                <v-breadcrumbs class="px-0 mb-4">
                    <v-breadcrumbs-item :to="{ name: 'forum-index' }">
                        {{ $t('forum.forums') }}
                    </v-breadcrumbs-item>
                    <template v-if="forumStore.currentThread?.forum">
                        <v-breadcrumbs-divider/>
                        <v-breadcrumbs-item
                            :to="{ name: 'forum-category', params: { slug: forumStore.currentThread.forum.slug } }"
                        >
                            {{ forumStore.currentThread.forum.name }}
                        </v-breadcrumbs-item>
                    </template>
                    <v-breadcrumbs-divider/>
                    <v-breadcrumbs-item disabled>
                        {{ forumStore.currentThread?.title }}
                    </v-breadcrumbs-item>
                </v-breadcrumbs>
            </v-container>
        </div>

        <v-container>
            <!-- Loading State -->
            <div v-if="forumStore.loading" class="text-center py-12">
                <v-progress-circular size="64" width="4" color="primary" indeterminate class="mb-4"/>
                <p class="text-body-1 text-medium-emphasis">{{ $t('forum.loadingThread') }}</p>
            </div>

            <template v-else-if="forumStore.currentThread">
                <!-- Thread Header Card -->
                <v-card class="thread-header-card mb-6" variant="elevated">
                    <v-card-text>
                        <div class="d-flex align-center justify-space-between mb-4">
                            <div class="d-flex align-center gap-2">
                                <h1 class="text-h4 font-weight-bold">{{ forumStore.currentThread.title }}</h1>
                                <v-chip v-if="forumStore.currentThread.is_pinned" size="small" color="primary"
                                        prepend-icon="mdi-pin">
                                    {{ $t('forum.pinned') }}
                                </v-chip>
                                <v-chip v-if="forumStore.currentThread.is_locked" size="small" color="warning"
                                        prepend-icon="mdi-lock">
                                    {{ $t('forum.locked') }}
                                </v-chip>
                            </div>
                            <div
                                v-if="forumStore.threadPermissions.can_edit || forumStore.threadPermissions.can_delete">
                                <v-menu>
                                    <template v-slot:activator="{ props }">
                                        <v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" :loading="deletingThread"/>
                                    </template>
                                    <v-list density="compact">
                                        <v-list-item
                                            v-if="forumStore.threadPermissions.can_edit"
                                            prepend-icon="mdi-pencil"
                                            :title="$t('forum.edit')"
                                            :disabled="deletingThread"
                                            @click="startEditThread"
                                        />
                                        <v-list-item
                                            v-if="forumStore.threadPermissions.can_delete"
                                            prepend-icon="mdi-delete"
                                            :title="$t('forum.delete')"
                                            class="text-error"
                                            :disabled="deletingThread"
                                            @click="deleteThread"
                                        />
                                    </v-list>
                                </v-menu>
                            </div>
                        </div>

                        <!-- Thread Author + Stats -->
                        <div class="d-flex align-center mb-4">
                            <UserAvatar v-if="forumStore.currentThread.author || forumStore.currentThread.meta?.legacy_author" :user="forumStore.currentThread.author" :legacy-name="forumStore.currentThread.meta?.legacy_author"/>
                            <div class="ml-3">
                                <span class="font-weight-medium">{{ forumStore.currentThread.display_author || forumStore.currentThread.author?.username }}</span>
                                <div class="text-caption text-medium-emphasis">
                                    {{ formatDateDistance(forumStore.currentThread.created_at) }}
                                </div>
                            </div>
                            <v-spacer/>
                            <div class="d-flex align-center gap-4">
                                <v-btn
                                    v-if="authStore.isAuthenticated"
                                    :variant="forumStore.isSubscribed ? 'elevated' : 'outlined'"
                                    :color="forumStore.isSubscribed ? 'primary' : undefined"
                                    size="small"
                                    :prepend-icon="forumStore.isSubscribed ? 'mdi-bell-ring' : 'mdi-bell-outline'"
                                    :loading="forumStore.submitting"
                                    @click="onToggleSubscription"
                                >
                                    {{ forumStore.isSubscribed ? $t('forum.subscribed') : $t('forum.subscribe') }}
                                </v-btn>
                                <div class="text-center">
                                    <v-icon size="16" class="mr-1">mdi-message-reply-text</v-icon>
                                    <span class="text-body-2">{{ forumStore.currentThread.reply_count || 0 }}</span>
                                </div>
                                <div class="text-center">
                                    <v-icon size="16" class="mr-1">mdi-eye</v-icon>
                                    <span class="text-body-2">{{ forumStore.currentThread.view_count || 0 }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Thread Body (view mode) -->
                        <div v-if="!editingThread" class="thread-body" v-html="forumStore.currentThread.body"></div>

                        <!-- Thread Body (edit mode) -->
                        <div v-else>
                            <v-text-field
                                v-model="editThreadTitle"
                                :label="$t('forum.threadTitle')"
                                variant="outlined"
                                density="comfortable"
                                class="mb-3"
                            />
                            <Tiptap v-model="editThreadBody" type="full"/>
                            <div class="d-flex gap-2 mt-3">
                                <v-btn color="primary" :loading="forumStore.submitting" @click="saveEditThread">
                                    {{ $t('forum.save') }}
                                </v-btn>
                                <v-btn variant="text" :disabled="forumStore.submitting" @click="cancelEditThread">{{ $t('forum.cancel') }}</v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Post View Toggle -->
                <div class="d-flex align-center mb-4">
                    <v-btn-toggle v-model="postViewMode" mandatory density="compact" variant="outlined" color="primary">
                        <v-btn value="chronological" prepend-icon="mdi-sort-clock-ascending-outline" size="small">
                            {{ $t('forum.viewChronological') }}
                        </v-btn>
                        <v-btn value="threaded" prepend-icon="mdi-file-tree-outline" size="small">
                            {{ $t('forum.viewThreaded') }}
                        </v-btn>
                    </v-btn-toggle>
                </div>

                <!-- Posts -->
                <div v-if="displayPosts.length > 0" class="mb-6">
                    <ForumPostItem
                        v-for="post in displayPosts"
                        :key="post.id"
                        :post="post"
                        :thread="forumStore.currentThread"
                        :current-user="currentUser"
                        :can-reply="forumStore.threadPermissions.can_reply"
                        :thread-locked="forumStore.currentThread.is_locked"
                        :can-moderate="forumStore.threadPermissions.can_moderate"
                        :can-delete-others="forumStore.threadPermissions.can_delete_others"
                        :busy-post-id="busyPostId"
                        @mark-solution="onMarkSolution"
                        @delete-post="onDeletePost"
                        @quote-reply="onQuoteReply"
                        @update-post="onUpdatePost"
                        @toggle-like="onToggleLike"
                    />
                </div>

                <!-- No Posts -->
                <div v-else class="text-center py-8 text-medium-emphasis">
                    <v-icon size="48" class="mb-2">mdi-message-text-outline</v-icon>
                    <p>{{ $t('forum.noPosts') }}</p>
                </div>

                <!-- Pagination -->
                <div v-if="forumStore.postsPagination.last_page > 1" class="d-flex justify-center mb-6">
                    <v-pagination
                        v-model="currentPage"
                        :length="forumStore.postsPagination.last_page"
                        :total-visible="7"
                        @update:model-value="onPageChange"
                    />
                </div>

                <!-- Reply Form -->
                <v-card
                    v-if="forumStore.threadPermissions.can_reply && !forumStore.currentThread.is_locked"
                    ref="replyCard"
                    class="reply-card"
                    variant="elevated"
                >
                    <v-card-title class="text-h6">{{ $t('forum.reply') }}</v-card-title>
                    <v-card-text>
                        <Tiptap v-model="replyBody" type="full"/>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-spacer/>
                        <v-btn
                            color="primary"
                            variant="elevated"
                            :loading="forumStore.submitting"
                            :disabled="isReplyBodyEmpty"
                            @click="submitReply"
                        >
                            {{ $t('forum.submit') }}
                        </v-btn>
                    </v-card-actions>
                </v-card>

                <!-- Locked / Login notice -->
                <v-alert
                    v-else-if="forumStore.currentThread.is_locked"
                    type="info"
                    variant="tonal"
                    prepend-icon="mdi-lock"
                    class="mt-4"
                >
                    {{ $t('forum.threadLocked') }}
                </v-alert>
                <v-alert
                    v-else-if="!authStore.isAuthenticated"
                    type="info"
                    variant="tonal"
                    prepend-icon="mdi-login"
                    class="mt-4"
                >
                    {{ $t('forum.loginToReply') }}
                </v-alert>
            </template>
        </v-container>
    </div>
</template>

<script>
import {useForumStore} from '@/store/forumStore.js'
import {useUserStore} from '@/store/userStore.js'
import {useAuthStore} from '@/store/authStore.js'
import {formatDateDistanceToNow} from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import Tiptap from '@/components/common/tiptap/Tiptap.vue'
import ForumPostItem from '@/components/forum/ForumPostItem.vue'

export default {
    name: 'ForumThread',
    components: {UserAvatar, Tiptap, ForumPostItem},
    setup() {
        const forumStore = useForumStore()
        const userStore = useUserStore()
        const authStore = useAuthStore()
        return {forumStore, userStore, authStore}
    },
    data() {
        return {
            currentPage: 1,
            replyBody: '',
            replyParentId: null,
            postViewMode: 'chronological',
            editingThread: false,
            editThreadTitle: '',
            editThreadBody: '',
            deletingThread: false,
            busyPostId: null
        }
    },
    computed: {
        currentUser() {
            return this.userStore.user
        },
        isReplyBodyEmpty() {
            if (!this.replyBody) return true
            const tmp = document.createElement('div')
            tmp.innerHTML = this.replyBody
            return !tmp.textContent.trim() && !tmp.querySelector('img')
        },
        displayPosts() {
            if (this.postViewMode === 'threaded') {
                return this.threadedPosts
            }
            return this.forumStore.posts
        },
        threadedPosts() {
            const posts = this.forumStore.posts
            if (!posts || !posts.length) return []

            const map = {}
            const roots = []

            // Index all posts by id
            for (const post of posts) {
                map[post.id] = {...post, children: []}
            }

            // Build the tree
            for (const post of posts) {
                if (post.parent_id && map[post.parent_id]) {
                    map[post.parent_id].children.push(map[post.id])
                } else {
                    roots.push(map[post.id])
                }
            }

            return roots
        }
    },
    watch: {
        '$route.params.threadSlug'(newSlug) {
            if (newSlug) {
                this.currentPage = 1
                this.loadThread()
            }
        }
    },
    mounted() {
        this.loadThread()
    },
    methods: {
        loadThread() {
            const {forumSlug, threadSlug} = this.$route.params
            this.forumStore.fetchThread(forumSlug, threadSlug, this.currentPage)
                .catch(error => this.$dialog.requestError(error, this.$t('forum.errorLoading')))
        },
        onPageChange(page) {
            this.currentPage = page
            const {forumSlug, threadSlug} = this.$route.params
            this.forumStore.fetchThread(forumSlug, threadSlug, page)
                .catch(error => this.$dialog.requestError(error, this.$t('forum.errorLoading')))
            window.scrollTo({top: 0, behavior: 'smooth'})
        },
        async submitReply() {
            if (this.isReplyBodyEmpty || this.forumStore.submitting) return
            try {
                await this.forumStore.createPost(this.forumStore.currentThread.id, {
                    body: this.replyBody,
                    parent_id: this.replyParentId
                })
                this.replyBody = ''
                this.replyParentId = null
            } catch (error) {
                this.$dialog.requestError(error, this.$t('forum.errorSubmitting'))
            }
        },
        escapeHtml(str) {
            const div = document.createElement('div')
            div.appendChild(document.createTextNode(str))
            return div.innerHTML
        },
        onQuoteReply({postId, username, body}) {
            this.replyParentId = postId

            // Strip HTML to get plain text for the quote, then truncate
            const tmp = document.createElement('div')
            tmp.innerHTML = body
            const plainText = tmp.textContent || tmp.innerText || ''
            const snippet = plainText.length > 200 ? plainText.substring(0, 200) + '...' : plainText

            const quote = `<blockquote><div class="quote-author">@${this.escapeHtml(username)}</div><p>${this.escapeHtml(snippet)}</p></blockquote><p></p>`
            this.replyBody = quote

            this.$nextTick(() => {
                if (this.$refs.replyCard?.$el) {
                    this.$refs.replyCard.$el.scrollIntoView({behavior: 'smooth', block: 'center'})
                }
            })
        },
        // Runs one store action for a single post; the post's own buttons
        // show a loader via busyPostId and stay blocked until it settles.
        async runPostAction(postId, action, fallbackKey) {
            if (this.busyPostId) return false
            this.busyPostId = postId
            try {
                await action()
                return true
            } catch (error) {
                this.$dialog.requestError(error, this.$t(fallbackKey))
                return false
            } finally {
                this.busyPostId = null
            }
        },
        async onUpdatePost({postId, body, done}) {
            const ok = await this.runPostAction(postId, () => this.forumStore.updatePost(postId, {body}), 'forum.errorSubmitting')
            if (typeof done === 'function') done(ok)
        },
        async onDeletePost(postId) {
            if (this.busyPostId) return
            const ok = await this.$dialog.confirmDelete(this.$t('forum.confirmDeletePost'))
            if (!ok) return
            await this.runPostAction(postId, () => this.forumStore.deletePost(postId), 'forum.errorSubmitting')
        },
        async onToggleSubscription() {
            if (this.forumStore.submitting) return
            try {
                await this.forumStore.toggleSubscription(this.forumStore.currentThread.id)
            } catch (error) {
                this.$dialog.requestError(error, this.$t('forum.errorSubmitting'))
            }
        },
        onToggleLike(postId) {
            return this.runPostAction(postId, () => this.forumStore.toggleLike(postId), 'forum.errorSubmitting')
        },
        onMarkSolution(postId) {
            return this.runPostAction(postId, () => this.forumStore.markAsSolution(postId), 'forum.errorSubmitting')
        },
        startEditThread() {
            this.editingThread = true
            this.editThreadTitle = this.forumStore.currentThread.title
            this.editThreadBody = this.forumStore.currentThread.body
        },
        cancelEditThread() {
            this.editingThread = false
            this.editThreadTitle = ''
            this.editThreadBody = ''
        },
        async saveEditThread() {
            if (this.forumStore.submitting) return
            try {
                await this.forumStore.updateThread(this.forumStore.currentThread.id, {
                    title: this.editThreadTitle,
                    body: this.editThreadBody
                })
                this.editingThread = false
            } catch (error) {
                this.$dialog.requestError(error, this.$t('forum.errorSubmitting'))
            }
        },
        async deleteThread() {
            if (this.deletingThread) return
            const ok = await this.$dialog.confirmDelete(this.$t('forum.confirmDeleteThread'))
            if (!ok) return

            this.deletingThread = true
            try {
                const forumSlug = this.forumStore.currentThread.forum?.slug || this.$route.params.forumSlug
                await this.forumStore.deleteThread(this.forumStore.currentThread.id)
                this.$router.push({name: 'forum-category', params: {slug: forumSlug}})
            } catch (error) {
                this.$dialog.requestError(error, this.$t('forum.errorSubmitting'))
            } finally {
                this.deletingThread = false
            }
        },
        formatDateDistance(date) {
            if (!date) return ''
            return formatDateDistanceToNow(date)
        }
    }
}
</script>

<style scoped>
.forum-thread-container {
    min-height: 100vh;
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
}

.forum-header {
    background: rgba(var(--v-theme-primary), 0.05);
    padding: 16px 0;
}

.thread-header-card {
    border-radius: 16px !important;
}

.thread-body {
    line-height: 1.7;
}

.thread-body :deep(img) {
    max-width: 100%;
    height: auto;
}

.reply-card {
    border-radius: 16px !important;
}

.gap-2 {
    gap: 8px;
}

.gap-4 {
    gap: 16px;
}

@media (max-width: 960px) {
    .forum-header {
        padding: 12px 0;
    }
}
</style>
