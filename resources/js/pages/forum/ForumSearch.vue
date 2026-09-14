<template>
    <div>
        <page-header
            :title="$t('forum.searchResults')"
            :subtitle="forumStore.searchQuery ? $t('forum.searchResultsFor', { query: forumStore.searchQuery }) : ''"
            icon="mdi-magnify"
            :back-to="{ name: 'forum-index' }"
        >
            <v-text-field
                v-model="searchInput"
                :placeholder="$t('forum.searchPlaceholder')"
                prepend-inner-icon="mdi-magnify"
                density="comfortable"
                hide-details
                clearable
                style="max-width: 500px;"
                @keydown.enter="doSearch"
                @click:clear="searchInput = ''"
            />
        </page-header>

        <v-container fluid>
            <!-- Search Degraded Warning -->
            <v-alert
                v-if="forumStore.searchDegraded"
                type="warning"
                variant="tonal"
                class="mb-4"
                closable
            >
                {{ $t('forum.searchDegradedWarning') }}
            </v-alert>

            <loading-state v-if="forumStore.searchLoading" :text="$t('forum.searching')" />

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
                                rounded="lg"
                                @click="goToThread(thread)"
                            >
                                <v-card-text>
                                    <v-row align="center" no-gutters>
                                        <v-col cols="12" md="7">
                                            <div class="d-flex align-center">
                                                <UserAvatar v-if="thread.author || thread.meta?.legacy_author" :user="thread.author" :legacy-name="thread.meta?.legacy_author" />
                                                <div class="ml-3">
                                                    <div class="d-flex align-center ga-2 mb-1">
                                                        <v-icon v-if="thread.is_pinned" size="16" color="primary">mdi-pin</v-icon>
                                                        <span class="font-weight-bold">{{ thread.title }}</span>
                                                        <v-chip v-if="thread.is_locked" size="x-small" color="warning" variant="tonal" prepend-icon="mdi-lock">
                                                            {{ $t('forum.locked') }}
                                                        </v-chip>
                                                    </div>
                                                    <div class="text-caption text-medium-emphasis">
                                                        {{ thread.display_author || thread.author?.username }}
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
                        <empty-state v-else compact icon="mdi-message-text-outline" :title="$t('forum.noThreadResults')" />
                    </v-window-item>

                    <!-- Posts Tab -->
                    <v-window-item value="posts">
                        <div v-if="forumStore.searchResults.posts?.data?.length > 0">
                            <v-card
                                v-for="post in forumStore.searchResults.posts.data"
                                :key="'p-' + post.id"
                                class="result-card mb-3"
                                variant="elevated"
                                rounded="lg"
                                @click="goToPost(post)"
                            >
                                <v-card-text>
                                    <div class="d-flex align-center">
                                        <UserAvatar v-if="post.author || post.meta?.legacy_author" :user="post.author" :legacy-name="post.meta?.legacy_author" />
                                        <div class="ml-3 flex-grow-1">
                                            <div class="d-flex align-center ga-2 mb-1">
                                                <span class="font-weight-medium">{{ post.display_author || post.author?.username }}</span>
                                                <span class="text-caption text-medium-emphasis">
                                                    {{ $t('forum.replyIn') }}
                                                </span>
                                                <span class="font-weight-bold">{{ post.thread_title }}</span>
                                                <v-chip v-if="post.is_solution" size="x-small" color="success" variant="tonal" prepend-icon="mdi-check-circle">
                                                    {{ $t('forum.solution') }}
                                                </v-chip>
                                            </div>
                                            <div class="text-body-2 text-medium-emphasis body-snippet">
                                                {{ snippet(post.body) }}
                                            </div>
                                            <div class="d-flex align-center ga-2 mt-2">
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
                        <empty-state v-else compact icon="mdi-comment-text-outline" :title="$t('forum.noPostResults')" />
                    </v-window-item>
                </v-window>
            </div>

            <empty-state
                v-else-if="forumStore.searchQuery"
                icon="mdi-magnify-close"
                :title="$t('forum.noSearchResults')"
                :text="$t('forum.noSearchResultsHint')"
            />
        </v-container>
    </div>
</template>

<script>
import { useForumStore } from '@/store/forumStore.js'
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'

export default {
    name: 'ForumSearch',
    components: { UserAvatar, PageHeader, EmptyState, LoadingState },
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
                this.runSearch(this.searchInput.trim())
            }
        },
        // Every search goes through here so a failed request is reported once.
        runSearch(query, options) {
            return this.forumStore.searchForum(query, options)
                .catch(error => this.$dialog.requestError(error, this.$t('forum.errorLoading')))
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
            this.runSearch(this.forumStore.searchQuery, { type: 'threads', page })
            window.scrollTo({ top: 0, behavior: 'smooth' })
        },
        onPostPageChange(page) {
            this.postPage = page
            this.runSearch(this.forumStore.searchQuery, { type: 'posts', page })
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
.result-card {
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
</style>
