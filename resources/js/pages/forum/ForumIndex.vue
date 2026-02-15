<template>
    <div class="forum-container">
        <!-- Header Section -->
        <div class="forum-header">
            <v-container>
                <v-row align="center" class="mb-6">
                    <v-col cols="12">
                        <h1 class="forum-title text-h3 font-weight-bold mb-2">
                            {{ $t('forum.title') }}
                        </h1>
                        <p class="text-subtitle-1 text-medium-emphasis">
                            {{ $t('forum.subtitle') }}
                        </p>
                        <v-text-field
                            v-model="searchQuery"
                            :placeholder="$t('forum.searchPlaceholder')"
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            density="comfortable"
                            hide-details
                            clearable
                            class="mt-4"
                            style="max-width: 500px;"
                            @keydown.enter="goToSearch"
                            @click:clear="searchQuery = ''"
                        />
                    </v-col>
                </v-row>
            </v-container>
        </div>

        <!-- Content Section -->
        <v-container>
            <!-- View Toggle -->
            <div class="d-flex align-center mb-4">
                <v-btn-toggle v-model="viewMode" mandatory density="compact" variant="outlined" color="primary">
                    <v-btn value="categories" prepend-icon="mdi-forum">
                        {{ $t('forum.forums') }}
                    </v-btn>
                    <v-btn value="threads" prepend-icon="mdi-message-text-outline">
                        {{ $t('forum.recentThreadsLabel') }}
                    </v-btn>
                </v-btn-toggle>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="text-center py-12">
                <v-progress-circular
                    size="64"
                    width="4"
                    color="primary"
                    indeterminate
                    class="mb-4"
                />
                <p class="text-body-1 text-medium-emphasis">
                    {{ viewMode === 'categories' ? $t('forum.loadingForums') : $t('forum.loadingThreads') }}
                </p>
            </div>

            <!-- ========== CATEGORIES VIEW ========== -->
            <template v-if="viewMode === 'categories' && !isLoading">
                <div v-if="forumStore.forums.length > 0">
                    <v-card
                        v-for="forum in forumStore.forums"
                        :key="forum.id"
                        class="forum-card mb-4"
                        variant="elevated"
                        @click="goToForum(forum)"
                    >
                        <v-card-text>
                            <v-row align="center">
                                <v-col cols="12" md="6">
                                    <div class="d-flex align-center">
                                        <v-avatar :color="forum.color || 'primary'" size="48" class="mr-4">
                                            <v-icon color="white" size="24">mdi-forum</v-icon>
                                        </v-avatar>
                                        <div>
                                            <div class="d-flex align-center gap-2 mb-1">
                                                <h3 class="text-h6 font-weight-bold">{{ forum.name }}</h3>
                                                <v-chip
                                                    v-if="forum.is_locked"
                                                    size="x-small"
                                                    color="warning"
                                                    prepend-icon="mdi-lock"
                                                >
                                                    {{ $t('forum.locked') }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="forum.is_private"
                                                    size="x-small"
                                                    color="info"
                                                    prepend-icon="mdi-lock-outline"
                                                >
                                                    {{ $t('forum.private') }}
                                                </v-chip>
                                            </div>
                                            <p v-if="forum.description" class="text-body-2 text-medium-emphasis mb-0">
                                                {{ forum.description }}
                                            </p>
                                            <!-- Sub-forums -->
                                            <div v-if="forum.children && forum.children.length" class="mt-2">
                                                <v-chip
                                                    v-for="child in forum.children"
                                                    :key="child.id"
                                                    size="small"
                                                    variant="tonal"
                                                    class="mr-1 mb-1"
                                                    @click.stop="goToForum(child)"
                                                >
                                                    {{ child.name }}
                                                </v-chip>
                                            </div>
                                        </div>
                                    </div>
                                </v-col>

                                <!-- Stats -->
                                <v-col cols="6" md="2" class="text-center">
                                    <div class="text-h6 font-weight-bold">{{ forum.threads_count || 0 }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('forum.threads') }}</div>
                                </v-col>
                                <v-col cols="6" md="2" class="text-center">
                                    <div class="text-h6 font-weight-bold">{{ forum.posts_count || 0 }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('forum.posts') }}</div>
                                </v-col>

                                <!-- Last Post Info -->
                                <v-col cols="12" md="2">
                                    <div v-if="forum.lastPost" class="d-flex align-center">
                                        <UserAvatar v-if="forum.lastPost.author" :user="forum.lastPost.author" />
                                        <div class="ml-2">
                                            <div class="text-caption font-weight-medium">
                                                {{ forum.lastPost.author?.username }}
                                            </div>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ formatDateDistance(forum.last_post_at) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-caption text-medium-emphasis">
                                        {{ $t('forum.noThreads') }}
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                </div>

                <!-- Empty State -->
                <div v-else class="empty-state text-center py-12">
                    <v-icon size="120" color="disabled" class="mb-4">mdi-forum-outline</v-icon>
                    <h3 class="text-h5 font-weight-bold mb-2">{{ $t('forum.noForums') }}</h3>
                    <p class="text-body-1 text-medium-emphasis">{{ $t('forum.noForumsDescription') }}</p>
                </div>
            </template>

            <!-- ========== RECENT THREADS VIEW ========== -->
            <template v-if="viewMode === 'threads' && !isLoading">
                <!-- Filter Chips -->
                <div class="d-flex flex-wrap align-center gap-2 mb-4">
                    <v-chip
                        v-for="f in threadFilters"
                        :key="f.value"
                        :variant="activeThreadFilter === f.value ? 'elevated' : 'outlined'"
                        :color="activeThreadFilter === f.value ? 'primary' : undefined"
                        size="small"
                        @click="setThreadFilter(f.value)"
                    >
                        {{ f.label }}
                    </v-chip>
                </div>

                <div v-if="forumStore.recentThreads.length > 0">
                    <v-card
                        v-for="thread in forumStore.recentThreads"
                        :key="thread.id"
                        class="thread-card mb-2"
                        variant="elevated"
                        @click="goToThread(thread)"
                    >
                        <v-card-text class="py-3">
                            <v-row align="center" no-gutters>
                                <v-col cols="12" md="5">
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
                                                {{ $t('forum.startedBy') }} {{ thread.author?.username }}
                                                &middot; {{ formatDateDistance(thread.created_at) }}
                                            </div>
                                        </div>
                                    </div>
                                </v-col>
                                <!-- Category -->
                                <v-col cols="12" md="2" class="text-center">
                                    <v-chip
                                        size="small"
                                        variant="tonal"
                                        :color="thread.category_color || 'primary'"
                                        @click.stop="goToForum({ slug: thread.category_slug })"
                                    >
                                        {{ thread.category_name }}
                                    </v-chip>
                                </v-col>
                                <!-- Posts count -->
                                <v-col cols="4" md="1" class="text-center">
                                    <div class="text-body-2 font-weight-medium">{{ thread.posts_count || 0 }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('forum.posts') }}</div>
                                </v-col>
                                <!-- Replies count -->
                                <v-col cols="4" md="1" class="text-center">
                                    <div class="text-body-2 font-weight-medium">{{ thread.reply_count || 0 }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('forum.replies') }}</div>
                                </v-col>
                                <!-- Views -->
                                <v-col cols="4" md="1" class="text-center">
                                    <div class="text-body-2 font-weight-medium">{{ thread.view_count || 0 }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('forum.views') }}</div>
                                </v-col>
                                <!-- Last post user -->
                                <v-col cols="12" md="2">
                                    <div v-if="thread.last_post_user" class="d-flex align-center">
                                        <UserAvatar :user="thread.last_post_user" />
                                        <div class="ml-2">
                                            <div class="text-caption font-weight-medium">{{ thread.last_post_user.username }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ formatDateDistance(thread.last_post_at) }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="text-caption text-medium-emphasis">
                                        {{ formatDateDistance(thread.created_at) }}
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>

                    <!-- Pagination -->
                    <div v-if="forumStore.recentThreadsPagination.last_page > 1" class="d-flex justify-center mt-6">
                        <v-pagination
                            v-model="recentThreadsPage"
                            :length="forumStore.recentThreadsPagination.last_page"
                            :total-visible="7"
                            @update:model-value="onRecentThreadsPageChange"
                        />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="empty-state text-center py-12">
                    <v-icon size="120" color="disabled" class="mb-4">mdi-message-text-outline</v-icon>
                    <h3 class="text-h5 font-weight-bold mb-2">{{ $t('forum.noThreads') }}</h3>
                    <p class="text-body-1 text-medium-emphasis">{{ $t('forum.noThreadsDescription') }}</p>
                </div>
            </template>

            <!-- Activity Feed -->
            <div v-if="forumStore.activities.length > 0 && viewMode === 'categories'" class="mt-6">
                <ForumActivityFeed :activities="forumStore.activities" />
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
import { useUserStore } from '@/store/userStore.js'
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import ForumActivityFeed from '@/components/forum/ForumActivityFeed.vue'

export default {
    name: 'ForumIndex',
    components: { UserAvatar, ForumActivityFeed },
    setup() {
        const forumStore = useForumStore()
        const userStore = useUserStore()
        return { forumStore, userStore }
    },
    data() {
        return {
            searchQuery: '',
            viewMode: 'categories',
            recentThreadsPage: 1,
            activeThreadFilter: null
        }
    },
    computed: {
        isLoading() {
            return this.viewMode === 'categories' ? this.forumStore.loading : this.forumStore.recentThreadsLoading
        },
        threadFilters() {
            return [
                { value: null, label: this.$t('forum.allThreads') },
                { value: 'popular', label: this.$t('forum.popular') },
                { value: 'unanswered', label: this.$t('forum.unanswered') },
                { value: 'mine', label: this.$t('forum.myThreads') }
            ]
        }
    },
    watch: {
        viewMode(newVal) {
            if (newVal === 'threads' && this.forumStore.recentThreads.length === 0) {
                this.loadRecentThreads()
            }
        }
    },
    mounted() {
        this.forumStore.fetchForums()
        this.forumStore.fetchActivities()
    },
    methods: {
        goToForum(forum) {
            this.$router.push({ name: 'forum-category', params: { slug: forum.slug } })
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
        goToSearch() {
            if (this.searchQuery && this.searchQuery.trim().length >= 2) {
                this.$router.push({ name: 'forum-search', query: { q: this.searchQuery.trim() } })
            }
        },
        loadRecentThreads() {
            this.forumStore.fetchRecentThreads(this.recentThreadsPage, { filter: this.activeThreadFilter })
        },
        setThreadFilter(value) {
            this.activeThreadFilter = value
            this.recentThreadsPage = 1
            this.loadRecentThreads()
        },
        onRecentThreadsPageChange(page) {
            this.recentThreadsPage = page
            this.loadRecentThreads()
            window.scrollTo({ top: 0, behavior: 'smooth' })
        },
        formatDateDistance(date) {
            if (!date) return ''
            return formatDateDistanceToNow(date)
        }
    }
}
</script>

<style scoped>
.forum-container {
    min-height: 100vh;
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
}

.forum-header {
    background: rgba(var(--v-theme-primary), 0.1);
    padding: 32px 0;
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

.forum-card {
    border-radius: 16px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.forum-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(var(--v-theme-on-surface), 0.12) !important;
}

.thread-card {
    border-radius: 12px !important;
    transition: all 0.2s ease;
    cursor: pointer;
}

.thread-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.1) !important;
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
        padding: 24px 0;
    }
}
</style>
