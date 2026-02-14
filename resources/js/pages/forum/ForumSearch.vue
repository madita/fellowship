<template>
    <div class="forum-search-container">
        <!-- Header Section -->
        <div class="forum-header">
            <v-container>
                <!-- Breadcrumbs -->
                <v-breadcrumbs class="px-0 mb-4">
                    <v-breadcrumbs-item :to="{ name: 'forum-index' }">
                        {{ $t('forum.home') }}
                    </v-breadcrumbs-item>
                    <v-breadcrumbs-divider />
                    <v-breadcrumbs-item :to="{ name: 'forum-index' }">
                        {{ $t('forum.forums') }}
                    </v-breadcrumbs-item>
                    <v-breadcrumbs-divider />
                    <v-breadcrumbs-item disabled>
                        {{ $t('forum.searchResults') }}
                    </v-breadcrumbs-item>
                </v-breadcrumbs>

                <h1 class="forum-title text-h3 font-weight-bold mb-2">
                    {{ $t('forum.searchResults') }}
                </h1>
                <p v-if="forumStore.searchQuery" class="text-subtitle-1 text-medium-emphasis mb-4">
                    {{ $t('forum.searchResultsFor', { query: forumStore.searchQuery }) }}
                </p>

                <!-- Search Input -->
                <v-text-field
                    v-model="searchInput"
                    :placeholder="$t('forum.searchPlaceholder')"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    clearable
                    style="max-width: 500px;"
                    @keydown.enter="doSearch"
                    @click:clear="searchInput = ''"
                />
            </v-container>
        </div>

        <v-container>
            <!-- Loading State -->
            <div v-if="forumStore.searchLoading" class="text-center py-12">
                <v-progress-circular size="64" width="4" color="primary" indeterminate class="mb-4" />
                <p class="text-body-1 text-medium-emphasis">{{ $t('forum.searching') }}</p>
            </div>

            <!-- Results -->
            <div v-else-if="forumStore.hasSearchResults">
                <p class="text-body-2 text-medium-emphasis mb-4">
                    {{ $t('forum.searchResultsCount', { count: forumStore.totalSearchResults }) }}
                </p>

                <!-- Tabs -->
                <v-tabs v-model="activeTab" color="primary" class="mb-4">
                    <v-tab value="threads">
                        {{ $t('forum.threads') }}
                        <v-chip size="x-small" class="ml-2" color="primary" variant="tonal">
                            {{ forumStore.searchResults.threads?.total || 0 }}
                        </v-chip>
                    </v-tab>
                    <v-tab value="posts">
                        {{ $t('forum.posts') }}
                        <v-chip size="x-small" class="ml-2" color="primary" variant="tonal">
                            {{ forumStore.searchResults.posts?.total || 0 }}
                        </v-chip>
                    </v-tab>
                </v-tabs>

                <v-window v-model="activeTab">
                    <!-- Threads Tab -->
                    <v-window-item value="threads">
                        <div v-if="forumStore.searchResults.threads?.data?.length > 0">
                            <v-card
                                v-for="thread in forumStore.searchResults.threads.data"
                                :key="'t-' + thread.id"
                                class="result-card mb-3"
                                variant="elevated"
                                @click="goToThread(thread)"
                            >
                                <v-card-text>
                                    <v-row align="center" no-gutters>
                                        <v-col cols="12" md="7">
                                            <div class="d-flex align-center">
                                                <UserAvatar v-if="thread.author" :user="thread.author" />
                                                <div class="ml-3">
                                                    <div class="d-flex align-center gap-2 mb-1">
                                                        <v-icon v-if="thread.is_pinned" size="16" color="primary">mdi-pin</v-icon>
                                                        <span class="font-weight-bold">{{ thread.title }}</span>
                                                        <v-chip v-if="thread.is_locked" size="x-small" color="warning" prepend-icon="mdi-lock">
                                                            {{ $t('forum.locked') }}
                                                        </v-chip>
                                                    </div>
                                                    <div class="text-caption text-medium-emphasis">
                                                        {{ thread.author?.username }}
                                                        &middot; {{ formatDateDistance(thread.created_at) }}
                                                    </div>
                                                    <div class="text-body-2 text-medium-emphasis mt-1 body-snippet">
                                                        {{ snippet(thread.body) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </v-col>
                                        <v-col cols="4" md="1" class="text-center">
                                            <div class="text-body-2 font-weight-medium">{{ thread.reply_count || 0 }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ $t('forum.replies') }}</div>
                                        </v-col>
                                        <v-col cols="4" md="1" class="text-center">
                                            <div class="text-body-2 font-weight-medium">{{ thread.view_count || 0 }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ $t('forum.views') }}</div>
                                        </v-col>
                                        <v-col cols="4" md="3">
                                            <v-chip size="small" variant="tonal" color="primary">
                                                {{ thread.category_name }}
                                            </v-chip>
                                        </v-col>
                                    </v-row>
                                </v-card-text>
                            </v-card>

                            <!-- Thread Pagination -->
                            <div v-if="forumStore.searchResults.threads.last_page > 1" class="d-flex justify-center mt-6">
                                <v-pagination
                                    v-model="threadPage"
                                    :length="forumStore.searchResults.threads.last_page"
                                    :total-visible="7"
                                    @update:model-value="onThreadPageChange"
                                />
                            </div>
                        </div>
                        <div v-else class="text-center py-8">
                            <v-icon size="64" color="disabled" class="mb-3">mdi-message-text-outline</v-icon>
                            <p class="text-body-1 text-medium-emphasis">{{ $t('forum.noThreadResults') }}</p>
                        </div>
                    </v-window-item>

                    <!-- Posts Tab -->
                    <v-window-item value="posts">
                        <div v-if="forumStore.searchResults.posts?.data?.length > 0">
                            <v-card
                                v-for="post in forumStore.searchResults.posts.data"
                                :key="'p-' + post.id"
                                class="result-card mb-3"
                                variant="elevated"
                                @click="goToPost(post)"
                            >
                                <v-card-text>
                                    <div class="d-flex align-center">
                                        <UserAvatar v-if="post.author" :user="post.author" />
                                        <div class="ml-3 flex-grow-1">
                                            <div class="d-flex align-center gap-2 mb-1">
                                                <span class="font-weight-medium">{{ post.author?.username }}</span>
                                                <span class="text-caption text-medium-emphasis">
                                                    {{ $t('forum.replyIn') }}
                                                </span>
                                                <span class="font-weight-bold">{{ post.thread_title }}</span>
                                                <v-chip v-if="post.is_solution" size="x-small" color="success" prepend-icon="mdi-check-circle">
                                                    {{ $t('forum.solution') }}
                                                </v-chip>
                                            </div>
                                            <div class="text-body-2 text-medium-emphasis body-snippet">
                                                {{ snippet(post.body) }}
                                            </div>
                                            <div class="d-flex align-center gap-2 mt-2">
                                                <v-chip size="x-small" variant="tonal" color="primary">
                                                    {{ post.category_name }}
                                                </v-chip>
                                                <span class="text-caption text-medium-emphasis">
                                                    {{ formatDateDistance(post.created_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>

                            <!-- Post Pagination -->
                            <div v-if="forumStore.searchResults.posts.last_page > 1" class="d-flex justify-center mt-6">
                                <v-pagination
                                    v-model="postPage"
                                    :length="forumStore.searchResults.posts.last_page"
                                    :total-visible="7"
                                    @update:model-value="onPostPageChange"
                                />
                            </div>
                        </div>
                        <div v-else class="text-center py-8">
                            <v-icon size="64" color="disabled" class="mb-3">mdi-comment-text-outline</v-icon>
                            <p class="text-body-1 text-medium-emphasis">{{ $t('forum.noPostResults') }}</p>
                        </div>
                    </v-window-item>
                </v-window>
            </div>

            <!-- No Results -->
            <div v-else-if="!forumStore.searchLoading && forumStore.searchQuery" class="empty-state text-center py-12">
                <v-icon size="120" color="disabled" class="mb-4">mdi-magnify-close</v-icon>
                <h3 class="text-h5 font-weight-bold mb-2">{{ $t('forum.noSearchResults') }}</h3>
                <p class="text-body-1 text-medium-emphasis">
                    {{ $t('forum.noResults') }}
                </p>
            </div>

            <!-- Error State -->
            <v-alert
                v-if="forumStore.error"
                type="error"
                variant="tonal"
                class="mt-4"
                closable
                @click:close="forumStore.error = null"
            >
                {{ forumStore.error }}
            </v-alert>
        </v-container>
    </div>
</template>

<script>
import { useForumStore } from '@/store/forumStore.js'
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'

export default {
    name: 'ForumSearch',
    components: { UserAvatar },
    setup() {
        const forumStore = useForumStore()
        return { forumStore }
    },
    data() {
        return {
            searchInput: '',
            activeTab: 'threads',
            threadPage: 1,
            postPage: 1
        }
    },
    watch: {
        '$route.query.q'(newQuery) {
            if (newQuery) {
                this.searchInput = newQuery
                this.threadPage = 1
                this.postPage = 1
                this.performSearch()
            }
        }
    },
    mounted() {
        const q = this.$route.query.q
        if (q) {
            this.searchInput = q
            this.performSearch()
        }
    },
    beforeUnmount() {
        this.forumStore.clearSearch()
    },
    methods: {
        performSearch() {
            if (this.searchInput && this.searchInput.trim().length >= 2) {
                this.forumStore.searchForum(this.searchInput.trim())
            }
        },
        doSearch() {
            if (this.searchInput && this.searchInput.trim().length >= 2) {
                this.$router.replace({ name: 'forum-search', query: { q: this.searchInput.trim() } })
                this.threadPage = 1
                this.postPage = 1
                this.performSearch()
            }
        },
        onThreadPageChange(page) {
            this.threadPage = page
            this.forumStore.searchForum(this.forumStore.searchQuery, { type: 'threads', page })
            window.scrollTo({ top: 0, behavior: 'smooth' })
        },
        onPostPageChange(page) {
            this.postPage = page
            this.forumStore.searchForum(this.forumStore.searchQuery, { type: 'posts', page })
            window.scrollTo({ top: 0, behavior: 'smooth' })
        },
        goToThread(thread) {
            this.$router.push({
                name: 'forum-thread',
                params: {
                    forumSlug: thread.category_slug,
                    threadSlug: thread.slug
                }
            })
        },
        goToPost(post) {
            this.$router.push({
                name: 'forum-thread',
                params: {
                    forumSlug: post.category_slug,
                    threadSlug: post.thread_slug
                }
            })
        },
        snippet(html, maxLength = 150) {
            if (!html) return ''
            const text = html.replace(/<[^>]*>/g, '')
            return text.length > maxLength ? text.substring(0, maxLength) + '...' : text
        },
        formatDateDistance(date) {
            if (!date) return ''
            return formatDateDistanceToNow(date)
        }
    }
}
</script>

<style scoped>
.forum-search-container {
    min-height: 100vh;
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
}

.forum-header {
    background: rgba(var(--v-theme-primary), 0.1);
    padding: 24px 0;
    backdrop-filter: blur(10px);
}

.v-theme--light .forum-header {
    background: linear-gradient(135deg, rgba(25, 118, 210, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
}

.v-theme--dark .forum-header {
    background: linear-gradient(135deg, rgba(77, 166, 199, 0.15) 0%, rgba(124, 169, 186, 0.15) 100%);
}

.forum-title {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.result-card {
    border-radius: 12px !important;
    transition: all 0.2s ease;
    cursor: pointer;
}

.result-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.1) !important;
}

.body-snippet {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.gap-2 {
    gap: 8px;
}

.empty-state {
    max-width: 400px;
    margin: 0 auto;
}

@media (max-width: 960px) {
    .forum-header {
        padding: 16px 0;
    }
}
</style>
