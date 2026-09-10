<template>
    <div>
        <page-header
            :title="$t('forum.createThread')"
            :subtitle="forumStore.currentForum?.name || ''"
            icon="mdi-message-plus-outline"
            :back-to="{ name: 'forum-category', params: { slug: $route.params.slug } }"
        />

        <v-container>
            <v-card class="new-thread-card" variant="elevated" rounded="lg">
                <v-card-text>
                    <!-- Title -->
                    <v-text-field
                        v-model="title"
                        :label="$t('forum.threadTitle')"
                        :placeholder="$t('forum.threadTitlePlaceholder')"
                        density="comfortable"
                        :error-messages="titleErrors"
                        :disabled="forumStore.submitting"
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

                    <!-- Poll -->
                    <div class="mb-2">
                        <v-btn
                            :variant="pollEnabled ? 'flat' : 'tonal'"
                            color="primary"
                            size="small"
                            :prepend-icon="pollEnabled ? 'mdi-close' : 'mdi-poll'"
                            :disabled="forumStore.submitting"
                            @click="togglePoll"
                        >
                            {{ pollEnabled ? $t('poll.removePoll') : $t('poll.addPoll') }}
                        </v-btn>
                    </div>

                    <v-expand-transition>
                        <v-card v-if="pollEnabled" variant="tonal" rounded="lg" class="mb-2">
                            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                                <v-icon color="primary">mdi-poll</v-icon>
                                {{ $t('poll.attachPoll') }}
                            </v-card-title>
                            <v-card-text>
                                <poll-form
                                    ref="pollForm"
                                    v-model="poll"
                                    :disabled="forumStore.submitting"
                                />
                            </v-card-text>
                        </v-card>
                    </v-expand-transition>
                </v-card-text>

                <v-card-actions class="px-4 pb-4">
                    <v-btn
                        variant="text"
                        :disabled="forumStore.submitting"
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
import PageHeader from '@/components/common/PageHeader.vue'
import PollForm from '@/components/poll/PollForm.vue'

export default {
    name: 'ForumNewThread',
    components: { Tiptap, PageHeader, PollForm },
    setup() {
        const forumStore = useForumStore()
        return { forumStore }
    },
    data() {
        return {
            title: '',
            body: '',
            titleErrors: [],
            bodyErrors: [],
            pollEnabled: false,
            poll: null
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
                .catch(error => this.$dialog.requestError(error, this.$t('forum.errorLoading')))
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
            const pollValid = !this.pollEnabled || (this.$refs.pollForm?.validate() ?? false)
            return this.titleErrors.length === 0 && this.bodyErrors.length === 0 && pollValid
        },
        togglePoll() {
            this.pollEnabled = !this.pollEnabled
            if (!this.pollEnabled) {
                this.poll = null
            }
        },
        async submitThread() {
            if (this.forumStore.submitting || !this.validate()) return

            const forumId = this.forumStore.currentForum?.id
            if (!forumId) {
                this.$dialog.error(this.$t('forum.forumNotFound'))
                return
            }

            try {
                const thread = await this.forumStore.createThread(forumId, {
                    title: this.title,
                    body: this.body,
                    poll: this.pollEnabled ? this.poll : null
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
                this.$dialog.requestError(error, this.$t('forum.errorCreating'))
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
.new-thread-card {
    max-width: 900px;
    margin: 0 auto;
}
</style>
