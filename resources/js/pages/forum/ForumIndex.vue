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
            <!-- Loading State -->
            <div v-if="forumStore.loading" class="text-center py-12">
                <v-progress-circular
                    size="64"
                    width="4"
                    color="primary"
                    indeterminate
                    class="mb-4"
                />
                <p class="text-body-1 text-medium-emphasis">
                    {{ $t('forum.loadingForums') }}
                </p>
            </div>

            <!-- Forum List -->
            <div v-else-if="forumStore.forums.length > 0">
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
                                    <v-avatar color="primary" size="48" class="mr-4">
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

            <!-- Activity Feed -->
            <div v-if="forumStore.activities.length > 0" class="mt-6">
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
            searchQuery: ''
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
        goToSearch() {
            if (this.searchQuery && this.searchQuery.trim().length >= 2) {
                this.$router.push({ name: 'forum-search', query: { q: this.searchQuery.trim() } })
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
