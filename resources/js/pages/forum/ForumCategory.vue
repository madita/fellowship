<template>
    <div class="forum-category-container">
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
                    <template v-if="forumStore.currentForum?.parent">
                        <v-breadcrumbs-divider />
                        <v-breadcrumbs-item
                            :to="{ name: 'forum-category', params: { slug: forumStore.currentForum.parent.slug } }"
                        >
                            {{ forumStore.currentForum.parent.name }}
                        </v-breadcrumbs-item>
                    </template>
                    <v-breadcrumbs-divider />
                    <v-breadcrumbs-item disabled>
                        {{ forumStore.currentForum?.name }}
                    </v-breadcrumbs-item>
                </v-breadcrumbs>

                <v-row align="center">
                    <v-col cols="12" md="8">
                        <h1 class="forum-title text-h3 font-weight-bold mb-2">
                            {{ forumStore.currentForum?.name }}
                        </h1>
                        <p v-if="forumStore.currentForum?.description" class="text-subtitle-1 text-medium-emphasis">
                            {{ forumStore.currentForum.description }}
                        </p>
                        <div class="d-flex align-center gap-2 mt-2">
                            <v-chip
                                v-if="forumStore.currentForum?.is_locked"
                                color="warning"
                                size="small"
                                prepend-icon="mdi-lock"
                            >
                                {{ $t('forum.locked') }}
                            </v-chip>
                            <v-chip
                                v-if="forumStore.currentForum?.is_private"
                                color="info"
                                size="small"
                                prepend-icon="mdi-lock-outline"
                            >
                                {{ $t('forum.private') }}
                            </v-chip>
                        </div>
                    </v-col>
                    <v-col cols="12" md="4" class="text-right">
                        <v-btn
                            v-if="canCreateThread"
                            color="primary"
                            variant="elevated"
                            size="large"
                            prepend-icon="mdi-plus"
                            class="create-btn"
                            @click="createThread"
                        >
                            {{ $t('forum.newThread') }}
                        </v-btn>
                    </v-col>
                </v-row>
            </v-container>
        </div>

        <v-container>
            <!-- Filter & Sort Controls -->
            <div class="d-flex flex-wrap align-center gap-2 mb-4">
                <v-chip
                    v-for="f in filters"
                    :key="f.value"
                    :variant="activeFilter === f.value ? 'elevated' : 'outlined'"
                    :color="activeFilter === f.value ? 'primary' : undefined"
                    size="small"
                    @click="setFilter(f.value)"
                >
                    {{ f.label }}
                </v-chip>
                <v-spacer />
                <v-select
                    v-model="activeSort"
                    :items="sortOptions"
                    item-title="label"
                    item-value="value"
                    :label="$t('forum.sortBy')"
                    density="compact"
                    variant="outlined"
                    hide-details
                    style="max-width: 200px;"
                    @update:model-value="loadForum"
                />
            </div>

            <!-- Sub-forums -->
            <div v-if="forumStore.currentForum?.children?.length" class="mb-6">
                <h3 class="text-h6 font-weight-medium mb-3">{{ $t('forum.subForums') }}</h3>
                <v-row>
                    <v-col
                        v-for="child in forumStore.currentForum.children"
                        :key="child.id"
                        cols="12"
                        sm="6"
                        md="4"
                    >
                        <v-card
                            variant="outlined"
                            class="subforum-card"
                            @click="$router.push({ name: 'forum-category', params: { slug: child.slug } })"
                        >
                            <v-card-text class="d-flex align-center">
                                <v-avatar color="primary" size="36" class="mr-3">
                                    <v-icon color="white" size="18">mdi-forum</v-icon>
                                </v-avatar>
                                <div>
                                    <div class="font-weight-medium">{{ child.name }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ child.threads_count || 0 }} {{ $t('forum.threads') }}
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </div>

            <!-- Loading State -->
            <div v-if="forumStore.loading" class="text-center py-12">
                <v-progress-circular size="64" width="4" color="primary" indeterminate class="mb-4" />
                <p class="text-body-1 text-medium-emphasis">{{ $t('forum.loadingThreads') }}</p>
            </div>

            <!-- Thread List -->
            <div v-else-if="forumStore.threads.length > 0">
                <!-- Pinned Threads -->
                <div v-if="forumStore.pinnedThreads.length" class="mb-4">
                    <v-card
                        v-for="thread in forumStore.pinnedThreads"
                        :key="'pin-' + thread.id"
                        class="thread-card mb-2 pinned-thread"
                        variant="elevated"
                        @click="goToThread(thread)"
                    >
                        <v-card-text class="py-3">
                            <v-row align="center" no-gutters>
                                <v-col cols="12" md="6">
                                    <div class="d-flex align-center">
                                        <UserAvatar v-if="thread.author" :user="thread.author" />
                                        <div class="ml-3">
                                            <div class="d-flex align-center gap-2 mb-1">
                                                <v-icon size="16" color="primary">mdi-pin</v-icon>
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
                                <v-col cols="4" md="2" class="text-center">
                                    <div class="text-body-2 font-weight-medium">{{ thread.reply_count || 0 }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('forum.replies') }}</div>
                                </v-col>
                                <v-col cols="4" md="2" class="text-center">
                                    <div class="text-body-2 font-weight-medium">{{ thread.view_count || 0 }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('forum.views') }}</div>
                                </v-col>
                                <v-col cols="4" md="2">
                                    <div v-if="thread.lastPostUser" class="d-flex align-center">
                                        <UserAvatar :user="thread.lastPostUser" />
                                        <div class="ml-2">
                                            <div class="text-caption font-weight-medium">{{ thread.lastPostUser.username }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ formatDateDistance(thread.last_post_at) }}</div>
                                        </div>
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                </div>

                <!-- Regular Threads -->
                <v-card
                    v-for="thread in forumStore.regularThreads"
                    :key="thread.id"
                    class="thread-card mb-2"
                    variant="elevated"
                    @click="goToThread(thread)"
                >
                    <v-card-text class="py-3">
                        <v-row align="center" no-gutters>
                            <v-col cols="12" md="6">
                                <div class="d-flex align-center">
                                    <UserAvatar v-if="thread.author" :user="thread.author" />
                                    <div class="ml-3">
                                        <div class="d-flex align-center gap-2 mb-1">
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
                            <v-col cols="4" md="2" class="text-center">
                                <div class="text-body-2 font-weight-medium">{{ thread.reply_count || 0 }}</div>
                                <div class="text-caption text-medium-emphasis">{{ $t('forum.replies') }}</div>
                            </v-col>
                            <v-col cols="4" md="2" class="text-center">
                                <div class="text-body-2 font-weight-medium">{{ thread.view_count || 0 }}</div>
                                <div class="text-caption text-medium-emphasis">{{ $t('forum.views') }}</div>
                            </v-col>
                            <v-col cols="4" md="2">
                                <div v-if="thread.lastPostUser" class="d-flex align-center">
                                    <UserAvatar :user="thread.lastPostUser" />
                                    <div class="ml-2">
                                        <div class="text-caption font-weight-medium">{{ thread.lastPostUser.username }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ formatDateDistance(thread.last_post_at) }}</div>
                                    </div>
                                </div>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <!-- Pagination -->
                <div v-if="forumStore.threadsPagination.last_page > 1" class="d-flex justify-center mt-6">
                    <v-pagination
                        v-model="currentPage"
                        :length="forumStore.threadsPagination.last_page"
                        :total-visible="7"
                        @update:model-value="onPageChange"
                    />
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!forumStore.loading" class="empty-state text-center py-12">
                <v-icon size="120" color="disabled" class="mb-4">mdi-message-text-outline</v-icon>
                <h3 class="text-h5 font-weight-bold mb-2">{{ $t('forum.noThreads') }}</h3>
                <p class="text-body-1 text-medium-emphasis mb-6">{{ $t('forum.noThreadsDescription') }}</p>
                <v-btn
                    v-if="canCreateThread"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    size="large"
                    @click="createThread"
                >
                    {{ $t('forum.newThread') }}
                </v-btn>
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
import { useAuthStore } from '@/store/authStore.js'
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'

export default {
    name: 'ForumCategory',
    components: { UserAvatar },
    setup() {
        const forumStore = useForumStore()
        const userStore = useUserStore()
        const authStore = useAuthStore()
        return { forumStore, userStore, authStore }
    },
    data() {
        return {
            currentPage: 1,
            activeFilter: null,
            activeSort: 'latest'
        }
    },
    computed: {
        canCreateThread() {
            return this.authStore.isAuthenticated && !this.forumStore.isForumLocked
        },
        filters() {
            return [
                { value: null, label: this.$t('forum.allThreads') },
                { value: 'popular', label: this.$t('forum.popular') },
                { value: 'unanswered', label: this.$t('forum.unanswered') },
                { value: 'mine', label: this.$t('forum.myThreads') },
                { value: 'solved', label: this.$t('forum.solved') }
            ]
        },
        sortOptions() {
            return [
                { value: 'latest', label: this.$t('forum.sortLatest') },
                { value: 'oldest', label: this.$t('forum.sortOldest') },
                { value: 'most_views', label: this.$t('forum.sortMostViews') }
            ]
        }
    },
    watch: {
        '$route.params.slug'(newSlug) {
            if (newSlug) {
                this.currentPage = 1
                this.loadForum()
            }
        }
    },
    mounted() {
        this.loadForum()
    },
    methods: {
        loadForum() {
            const slug = this.$route.params.slug
            this.forumStore.fetchForum(slug, this.currentPage, {
                filter: this.activeFilter,
                sort: this.activeSort
            })
        },
        setFilter(value) {
            this.activeFilter = value
            this.currentPage = 1
            this.loadForum()
        },
        goToThread(thread) {
            this.$router.push({
                name: 'forum-thread',
                params: {
                    forumSlug: this.$route.params.slug,
                    threadSlug: thread.slug
                }
            })
        },
        createThread() {
            this.$router.push({
                name: 'forum-new-thread',
                params: { slug: this.$route.params.slug }
            })
        },
        onPageChange(page) {
            this.currentPage = page
            this.loadForum()
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
.forum-category-container {
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

.thread-card {
    border-radius: 12px !important;
    transition: all 0.2s ease;
    cursor: pointer;
}

.thread-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.1) !important;
}

.pinned-thread {
    border-left: 3px solid rgb(var(--v-theme-primary));
}

.subforum-card {
    border-radius: 12px !important;
    cursor: pointer;
    transition: all 0.2s ease;
}

.subforum-card:hover {
    background: rgba(var(--v-theme-primary), 0.05);
}

.create-btn {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
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

    .text-right {
        text-align: center !important;
    }

    .create-btn {
        width: 100%;
        margin-top: 16px;
    }
}
</style>
