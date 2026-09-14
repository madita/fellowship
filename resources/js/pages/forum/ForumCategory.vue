<template>
    <div>
        <page-header
            :title="forumStore.currentForum?.name || $t('forum.forums')"
            :subtitle="forumStore.currentForum?.description || ''"
            icon="mdi-forum"
            :back-to="backTo"
        >
            <template #actions>
                <v-btn
                    v-if="canCreateThread"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    @click="createThread"
                >
                    {{ $t('forum.newThread') }}
                </v-btn>
            </template>
            <div
                v-if="forumStore.currentForum?.is_locked || forumStore.currentForum?.is_private"
                class="d-flex align-center flex-wrap ga-2"
            >
                <v-chip
                    v-if="forumStore.currentForum?.is_locked"
                    color="warning"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-lock"
                >
                    {{ $t('forum.locked') }}
                </v-chip>
                <v-chip
                    v-if="forumStore.currentForum?.is_private"
                    color="info"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-lock-outline"
                >
                    {{ $t('forum.private') }}
                </v-chip>
            </div>
        </page-header>

        <v-container fluid>
            <!-- Filter & Sort Controls -->
            <div class="d-flex flex-wrap align-center ga-2 mb-4">
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
                            rounded="lg"
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

            <loading-state v-if="forumStore.loading" :text="$t('forum.loadingThreads')" />

            <!-- Thread List -->
            <div v-else-if="forumStore.threads.length > 0">
                <!-- Pinned Threads -->
                <div v-if="forumStore.pinnedThreads.length" class="mb-4">
                    <v-card
                        v-for="thread in forumStore.pinnedThreads"
                        :key="'pin-' + thread.id"
                        class="thread-card mb-2 pinned-thread"
                        variant="elevated"
                        rounded="lg"
                        @click="goToThread(thread)"
                    >
                        <v-card-text class="py-3">
                            <v-row align="center" no-gutters>
                                <v-col cols="12" md="6">
                                    <div class="d-flex align-center">
                                        <UserAvatar v-if="thread.author || thread.meta?.legacy_author" :user="thread.author" :legacy-name="thread.meta?.legacy_author" />
                                        <div class="ml-3">
                                            <div class="d-flex align-center ga-2 mb-1">
                                                <v-icon size="16" color="primary">mdi-pin</v-icon>
                                                <span class="font-weight-bold">{{ thread.title }}</span>
                                                <v-chip v-if="thread.is_locked" size="x-small" color="warning" variant="tonal" prepend-icon="mdi-lock">
                                                    {{ $t('forum.locked') }}
                                                </v-chip>
                                                <v-chip v-if="isNewThread(thread)" size="x-small" color="success" variant="elevated">
                                                    {{ $t('forum.new') }}
                                                </v-chip>
                                            </div>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ $t('forum.startedBy') }} {{ thread.display_author || thread.author?.username }}
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
                    rounded="lg"
                    @click="goToThread(thread)"
                >
                    <v-card-text class="py-3">
                        <v-row align="center" no-gutters>
                            <v-col cols="12" md="6">
                                <div class="d-flex align-center">
                                    <UserAvatar v-if="thread.author || thread.meta?.legacy_author" :user="thread.author" :legacy-name="thread.meta?.legacy_author" />
                                    <div class="ml-3">
                                        <div class="d-flex align-center ga-2 mb-1">
                                            <span class="font-weight-bold">{{ thread.title }}</span>
                                            <v-chip v-if="thread.is_locked" size="x-small" color="warning" variant="tonal" prepend-icon="mdi-lock">
                                                {{ $t('forum.locked') }}
                                            </v-chip>
                                            <v-chip v-if="isNewThread(thread)" size="x-small" color="success" variant="elevated">
                                                {{ $t('forum.new') }}
                                            </v-chip>
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ $t('forum.startedBy') }} {{ thread.display_author || thread.author?.username }}
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

            <empty-state
                v-else
                icon="mdi-message-text-outline"
                :title="$t('forum.noThreads')"
                :text="$t('forum.noThreadsDescription')"
            >
                <template v-if="canCreateThread" #actions>
                    <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="createThread">
                        {{ $t('forum.newThread') }}
                    </v-btn>
                </template>
            </empty-state>
        </v-container>
    </div>
</template>

<script>
import { useForumStore } from '@/store/forumStore.js'
import { useUserStore } from '@/store/userStore.js'
import { useAuthStore } from '@/store/authStore.js'
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'

export default {
    name: 'ForumCategory',
    components: { UserAvatar, PageHeader, EmptyState, LoadingState },
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
            return this.authStore.isAuthenticated && (this.forumStore.currentForum?.can_create_thread ?? false)
        },
        // Back button target: the parent forum when this is a sub-forum, else the forum index
        backTo() {
            const parent = this.forumStore.currentForum?.parent
            return parent
                ? { name: 'forum-category', params: { slug: parent.slug } }
                : { name: 'forum-index' }
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
            }).catch(error => this.$dialog.requestError(error, this.$t('forum.errorLoading')))
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
        isNewThread(thread) {
            if (!this.userStore.user) return false
            return thread.is_read === false
        },
        formatDateDistance(date) {
            if (!date) return ''
            return formatDateDistanceToNow(date)
        }
    }
}
</script>

<style scoped>
.thread-card {
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
    cursor: pointer;
    transition: all 0.2s ease;
}

.subforum-card:hover {
    background: rgba(var(--v-theme-primary), 0.05);
}
</style>
