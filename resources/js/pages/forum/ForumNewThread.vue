<template>
    <div class="forum-new-thread-container">
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
                    <v-breadcrumbs-item
                        :to="{ name: 'forum-category', params: { slug: $route.params.slug } }"
                    >
                        {{ forumStore.currentForum?.name || $route.params.slug }}
                    </v-breadcrumbs-item>
                    <v-breadcrumbs-divider />
                    <v-breadcrumbs-item disabled>
                        {{ $t('forum.newThread') }}
                    </v-breadcrumbs-item>
                </v-breadcrumbs>

                <h1 class="forum-title text-h4 font-weight-bold">
                    {{ $t('forum.createThread') }}
                </h1>
            </v-container>
        </div>

        <v-container>
            <v-card class="new-thread-card" variant="elevated">
                <v-card-text>
                    <!-- Error Alert -->
                    <v-alert
                        v-if="forumStore.error"
                        type="error"
                        variant="tonal"
                        class="mb-4"
                        closable
                        @click:close="forumStore.error = null"
                    >
                        {{ forumStore.error }}
                    </v-alert>

                    <!-- Title -->
                    <v-text-field
                        v-model="title"
                        :label="$t('forum.threadTitle')"
                        :placeholder="$t('forum.threadTitlePlaceholder')"
                        variant="outlined"
                        density="comfortable"
                        :error-messages="titleErrors"
                        class="mb-4"
                        @input="titleErrors = []"
                    />

                    <!-- Body -->
                    <div class="mb-4">
                        <label class="text-body-2 font-weight-medium mb-2 d-block">{{ $t('forum.threadBody') }}</label>
                        <Tiptap v-model="body" type="full" />
                        <div v-if="bodyErrors.length" class="text-error text-caption mt-1">
                            {{ bodyErrors[0] }}
                        </div>
                    </div>
                </v-card-text>

                <v-card-actions class="px-4 pb-4">
                    <v-btn
                        variant="text"
                        @click="cancel"
                    >
                        {{ $t('forum.cancel') }}
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="elevated"
                        :loading="forumStore.submitting"
                        :disabled="!isValid"
                        @click="submitThread"
                    >
                        {{ $t('forum.createThread') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-container>
    </div>
</template>

<script>
import { useForumStore } from '@/store/forumStore.js'
import Tiptap from '@/components/common/tiptap/Tiptap.vue'

export default {
    name: 'ForumNewThread',
    components: { Tiptap },
    setup() {
        const forumStore = useForumStore()
        return { forumStore }
    },
    data() {
        return {
            title: '',
            body: '',
            titleErrors: [],
            bodyErrors: []
        }
    },
    computed: {
        isValid() {
            return this.title.trim().length > 0 && this.body.trim().length > 0
        }
    },
    mounted() {
        // Ensure the forum data is loaded for breadcrumbs
        if (!this.forumStore.currentForum || this.forumStore.currentForum.slug !== this.$route.params.slug) {
            this.forumStore.fetchForum(this.$route.params.slug)
        }
    },
    methods: {
        validate() {
            this.titleErrors = []
            this.bodyErrors = []
            if (!this.title.trim()) {
                this.titleErrors.push(this.$t('forum.titleRequired'))
            }
            if (!this.body.trim()) {
                this.bodyErrors.push(this.$t('forum.bodyRequired'))
            }
            return this.titleErrors.length === 0 && this.bodyErrors.length === 0
        },
        async submitThread() {
            if (!this.validate()) return

            try {
                const forumId = this.forumStore.currentForum?.id
                if (!forumId) {
                    this.forumStore.error = 'Forum not found'
                    return
                }

                const thread = await this.forumStore.createThread(forumId, {
                    title: this.title,
                    body: this.body
                })

                // Redirect to new thread
                this.$router.push({
                    name: 'forum-thread',
                    params: {
                        forumSlug: this.$route.params.slug,
                        threadSlug: thread.slug
                    }
                })
            } catch (error) {
                // Error is handled by the store
                console.error('Failed to create thread:', error)
            }
        },
        cancel() {
            this.$router.push({
                name: 'forum-category',
                params: { slug: this.$route.params.slug }
            })
        }
    }
}
</script>

<style scoped>
.forum-new-thread-container {
    min-height: 100vh;
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
}

.forum-header {
    background: rgba(var(--v-theme-primary), 0.05);
    padding: 16px 0;
}

.forum-title {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.new-thread-card {
    border-radius: 16px !important;
    max-width: 900px;
    margin: 24px auto;
}

@media (max-width: 960px) {
    .forum-header {
        padding: 12px 0;
    }
}
</style>
