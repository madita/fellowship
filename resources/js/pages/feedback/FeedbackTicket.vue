<template>
    <div>
        <loading-state v-if="loading && !ticket" />

        <v-container v-else-if="notFound">
            <empty-state icon="mdi-ticket-outline" :title="$t('feedback.messages.notFound')">
                <template #actions>
                    <v-btn color="primary" variant="flat" :to="{ name: 'feedback' }">
                        {{ $t('feedback.title') }}
                    </v-btn>
                </template>
            </empty-state>
        </v-container>

        <template v-else-if="ticket">
            <page-header
                :title="ticket.title"
                :subtitle="subtitle"
                :icon="ticket.type?.icon || 'mdi-ticket-outline'"
                :back-to="listRoute"
            />

            <v-container>
                <v-row>
                    <v-col cols="12" md="8">
                        <v-card class="content-card mb-6" elevation="2" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-text</v-icon>
                                {{ $t('feedback.fields.description') }}
                            </v-card-title>
                            <!-- Sanitized by renderRichText -->
                            <v-card-text class="rich-content rich-text text-body-1 pa-6 pt-2" v-html="renderRichText(ticket.description)" />
                        </v-card>

                        <!-- Comments -->
                        <h2 class="text-h6 mb-3">
                            {{ $t('feedback.comments') }} ({{ ticket.comments.length }})
                        </h2>

                        <v-card class="content-card" elevation="2" rounded="lg">
                            <v-list v-if="ticket.comments.length" lines="three" class="py-0">
                                <template v-for="(comment, index) in ticket.comments" :key="comment.id">
                                    <v-divider v-if="index > 0" />
                                    <v-list-item class="py-3" :class="{ 'official-comment': comment.is_official }">
                                        <template #prepend>
                                            <user-avatar :user="comment.author" class="mr-3" />
                                        </template>
                                        <div class="d-flex align-center flex-wrap ga-2 mb-1">
                                            <strong>{{ comment.author?.username }}</strong>
                                            <v-chip
                                                v-if="comment.is_official"
                                                size="x-small"
                                                color="primary"
                                                prepend-icon="mdi-shield-check"
                                            >
                                                {{ $t('feedback.official') }}
                                            </v-chip>
                                            <span class="text-caption text-medium-emphasis">
                                                {{ formatDateDistanceToNow(comment.created_at) }}
                                            </span>
                                        </div>
                                        <!-- Sanitized by renderRichText -->
                                        <div class="rich-content rich-text text-body-2" v-html="renderRichText(comment.comment)" />
                                    </v-list-item>
                                </template>
                            </v-list>
                            <empty-state v-else compact icon="mdi-comment-outline" :title="$t('feedback.noComments')" />

                            <template v-if="authStore.isAuthenticated">
                                <v-divider />
                                <v-card-text>
                                    <simple-editor
                                        v-model="newComment"
                                        :placeholder="$t('feedback.addComment')"
                                        :limit="5000"
                                        :disabled="commenting"
                                        min-height="88px"
                                        @submit="submitComment"
                                    />
                                    <div class="d-flex justify-end mt-2">
                                        <v-btn
                                            color="primary"
                                            variant="flat"
                                            prepend-icon="mdi-send"
                                            :loading="commenting"
                                            :disabled="!hasRichText(newComment)"
                                            @click="submitComment"
                                        >
                                            {{ $t('feedback.postComment') }}
                                        </v-btn>
                                    </div>
                                </v-card-text>
                            </template>
                        </v-card>

                        <v-alert
                            v-if="!authStore.isAuthenticated"
                            type="info"
                            variant="tonal"
                            prepend-icon="mdi-login"
                            class="mt-4"
                        >
                            {{ $t('feedback.loginHint') }}
                        </v-alert>
                    </v-col>

                    <!-- Sidebar -->
                    <v-col cols="12" md="4">
                        <v-card class="content-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-tune-variant</v-icon>
                                {{ $t('feedback.details') }}
                            </v-card-title>
                            <v-card-text>
                                <div class="d-flex align-center flex-wrap ga-2 mb-4">
                                    <v-chip size="small" variant="tonal" :color="ticket.type?.color" :prepend-icon="ticket.type?.icon">
                                        {{ ticket.type?.name }}
                                    </v-chip>
                                    <v-chip size="small" variant="tonal" :color="getStatusColor(ticket.status)">
                                        {{ getStatusLabel(ticket.status) }}
                                    </v-chip>
                                    <v-chip v-if="!ticket.is_public" size="small" variant="tonal" prepend-icon="mdi-eye-off-outline">
                                        {{ $t('feedback.hidden') }}
                                    </v-chip>
                                    <v-chip
                                        v-for="tag in ticket.tags"
                                        :key="tag.id"
                                        size="small"
                                        variant="tonal"
                                        :color="tag.color"
                                    >
                                        {{ tag.name }}
                                    </v-chip>
                                </div>

                                <v-alert
                                    v-if="ticket.duplicate_of"
                                    type="warning"
                                    variant="tonal"
                                    density="compact"
                                    prepend-icon="mdi-content-duplicate"
                                    class="mb-4 text-body-2"
                                >
                                    {{ $t('feedback.duplicateOf') }}
                                    <router-link :to="{ name: 'feedback-ticket', params: { id: ticket.duplicate_of.id } }">
                                        #{{ ticket.duplicate_of.id }} {{ ticket.duplicate_of.title }}
                                    </router-link>
                                </v-alert>

                                <v-btn
                                    block
                                    class="mb-2"
                                    prepend-icon="mdi-arrow-up-bold"
                                    :color="ticket.user_has_voted ? 'primary' : undefined"
                                    :variant="ticket.user_has_voted ? 'flat' : 'tonal'"
                                    :disabled="!authStore.isAuthenticated"
                                    :loading="voting"
                                    @click="toggleVote"
                                >
                                    {{ ticket.user_has_voted ? $t('feedback.voted') : $t('feedback.vote') }}
                                    ({{ ticket.votes_count }})
                                </v-btn>
                                <v-btn
                                    block
                                    :prepend-icon="ticket.user_is_watching ? 'mdi-eye-check' : 'mdi-eye-outline'"
                                    :color="ticket.user_is_watching ? 'secondary' : undefined"
                                    :variant="ticket.user_is_watching ? 'flat' : 'tonal'"
                                    :disabled="!authStore.isAuthenticated"
                                    :loading="watching"
                                    @click="toggleWatch"
                                >
                                    {{ ticket.user_is_watching ? $t('feedback.watching') : $t('feedback.watch') }}
                                    ({{ ticket.watchers_count }})
                                </v-btn>
                                <div class="text-caption text-medium-emphasis mt-2">{{ $t('feedback.watchHint') }}</div>

                                <template v-if="ticket.duplicates.length">
                                    <v-divider class="my-4" />
                                    <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">
                                        {{ $t('feedback.duplicates') }}
                                    </div>
                                    <div v-for="duplicate in ticket.duplicates" :key="duplicate.id" class="text-body-2">
                                        <router-link :to="{ name: 'feedback-ticket', params: { id: duplicate.id } }">
                                            #{{ duplicate.id }} {{ duplicate.title }}
                                        </router-link>
                                    </div>
                                </template>
                            </v-card-text>
                        </v-card>

                        <!-- Moderation (admins) -->
                        <feedback-moderation-card v-if="isAdmin" :ticket="ticket" @saved="ticket = $event">
                            <template #actions>
                                <v-btn variant="text" size="small" :to="{ name: 'admin-ticket', params: { id: ticket.id } }">
                                    {{ $t('feedback.moderation.manage') }}
                                </v-btn>
                            </template>
                        </feedback-moderation-card>
                    </v-col>
                </v-row>
            </v-container>
        </template>
    </div>
</template>

<script>
import axios from 'axios'
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import { useTicketHelpers } from '@/composables/useTicketHelpers.js'
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import UserAvatar from '@/components/common/UserAvatar.vue'
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue'
import { renderRichText, hasRichText } from '@/utils/richText.js'
import FeedbackModerationCard from '@/components/feedback/FeedbackModerationCard.vue'

export default {
    name: 'FeedbackTicket',
    components: { PageHeader, EmptyState, LoadingState, UserAvatar, SimpleEditor, FeedbackModerationCard },
    setup() {
        const authStore = useAuthStore()
        const userStore = useUserStore()
        const { getStatusColor, getStatusLabel } = useTicketHelpers()
        return { authStore, userStore, getStatusColor, getStatusLabel }
    },
    data() {
        return {
            loading: false,
            notFound: false,
            ticket: null,
            newComment: '',
            commenting: false,
            voting: false,
            watching: false,
        }
    },
    computed: {
        isAdmin() {
            return !!this.userStore.user?.isAdmin
        },
        listRoute() {
            return { name: 'feedback', query: { type: this.ticket?.type?.slug } }
        },
        subtitle() {
            const author = this.ticket.author ? this.$t('feedback.postedBy', { name: this.ticket.author.username }) : ''
            return [`#${this.ticket.id}`, author, formatDateDistanceToNow(this.ticket.created_at)].filter(Boolean).join(' · ')
        },
    },
    watch: {
        // Links between duplicates reuse this page
        '$route.params.id'(id) {
            if (id) this.loadTicket()
        },
    },
    mounted() {
        this.loadTicket()
    },
    methods: {
        formatDateDistanceToNow,
        renderRichText,
        hasRichText,
        async loadTicket() {
            this.loading = true
            this.notFound = false
            try {
                const { data } = await axios.get(`/api/feedback/tickets/${this.$route.params.id}`)
                this.ticket = data
            } catch (error) {
                if (error.response?.status === 404) {
                    this.ticket = null
                    this.notFound = true
                } else {
                    await this.$dialog.requestError(error, this.$t('feedback.messages.loadFailed'))
                }
            } finally {
                this.loading = false
            }
        },
        async toggleVote() {
            if (this.voting) return
            this.voting = true
            try {
                const { data } = await axios.post(`/api/feedback/tickets/${this.ticket.id}/vote`)
                this.ticket.user_has_voted = data.voted
                this.ticket.votes_count = data.votes_count
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.voteFailed'))
            } finally {
                this.voting = false
            }
        },
        async toggleWatch() {
            if (this.watching) return
            this.watching = true
            try {
                const { data } = await axios.post(`/api/feedback/tickets/${this.ticket.id}/watch`)
                this.ticket.user_is_watching = data.watching
                this.ticket.watchers_count = data.watchers_count
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.watchFailed'))
            } finally {
                this.watching = false
            }
        },
        async submitComment() {
            if (this.commenting || !hasRichText(this.newComment)) return
            this.commenting = true
            try {
                const { data } = await axios.post(`/api/feedback/tickets/${this.ticket.id}/comments`, {
                    comment: this.newComment,
                })
                this.ticket.comments.push(data)
                this.newComment = ''
                // Commenting watches the ticket
                if (!this.ticket.user_is_watching) {
                    this.ticket.user_is_watching = true
                    this.ticket.watchers_count++
                }
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.commentFailed'))
            } finally {
                this.commenting = false
            }
        },
    },
}
</script>

<style scoped>
.content-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.rich-text {
    overflow-wrap: anywhere;
    line-height: 1.6;
}

.official-comment {
    background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
