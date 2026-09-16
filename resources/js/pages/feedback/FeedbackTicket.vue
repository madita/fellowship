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
                        <div class="d-flex align-center flex-wrap ga-2 mb-4">
                            <v-chip size="small" variant="tonal" :color="ticket.type?.color">
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
                            prepend-icon="mdi-content-duplicate"
                            class="mb-4"
                        >
                            {{ $t('feedback.duplicateOf') }}
                            <router-link :to="{ name: 'feedback-ticket', params: { id: ticket.duplicate_of.id } }">
                                #{{ ticket.duplicate_of.id }} {{ ticket.duplicate_of.title }}
                            </router-link>
                        </v-alert>

                        <v-card rounded="lg" class="mb-6">
                            <v-card-text class="plain-text text-body-1">{{ ticket.description }}</v-card-text>
                        </v-card>

                        <!-- Comments -->
                        <h2 class="text-h6 mb-3">
                            {{ $t('feedback.comments') }} ({{ ticket.comments.length }})
                        </h2>

                        <v-card rounded="lg">
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
                                        <div class="plain-text text-body-2">{{ comment.comment }}</div>
                                    </v-list-item>
                                </template>
                            </v-list>
                            <empty-state v-else compact icon="mdi-comment-outline" :title="$t('feedback.noComments')" />

                            <template v-if="authStore.isAuthenticated">
                                <v-divider />
                                <v-card-text>
                                    <v-textarea
                                        v-model="newComment"
                                        :label="$t('feedback.addComment')"
                                        rows="3"
                                        auto-grow
                                        hide-details
                                        :disabled="commenting"
                                    />
                                    <div class="d-flex justify-end mt-2">
                                        <v-btn
                                            color="primary"
                                            variant="flat"
                                            prepend-icon="mdi-send"
                                            :loading="commenting"
                                            :disabled="!newComment.trim()"
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
                        <v-card rounded="lg" class="mb-4">
                            <v-card-text>
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
                        <v-card v-if="isAdmin" rounded="lg">
                            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                                <v-icon icon="mdi-shield-account-outline" size="small" />
                                {{ $t('feedback.moderation.title') }}
                            </v-card-title>
                            <v-card-text>
                                <v-select
                                    v-model="moderation.status"
                                    :items="statusItems"
                                    item-title="label"
                                    item-value="value"
                                    :label="$t('feedback.fields.status')"
                                    density="compact"
                                    class="mb-2"
                                    hide-details
                                />
                                <v-autocomplete
                                    v-model="moderation.tag_ids"
                                    :items="tags"
                                    item-title="name"
                                    item-value="id"
                                    :label="$t('feedback.fields.tags')"
                                    density="compact"
                                    multiple
                                    chips
                                    closable-chips
                                    class="mb-2"
                                    hide-details
                                />
                                <v-text-field
                                    v-model.number="moderation.duplicate_of_ticket_id"
                                    type="number"
                                    :label="$t('feedback.moderation.duplicateOf')"
                                    :hint="$t('feedback.moderation.duplicateHint')"
                                    persistent-hint
                                    density="compact"
                                    clearable
                                    class="mb-2"
                                />
                                <v-switch
                                    v-model="moderation.is_public"
                                    :label="$t('feedback.moderation.public')"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                />
                            </v-card-text>
                            <v-card-actions class="px-4 pb-4">
                                <v-btn variant="text" size="small" :to="{ name: 'admin-tickets' }">
                                    {{ $t('feedback.moderation.manage') }}
                                </v-btn>
                                <v-spacer />
                                <v-btn color="primary" variant="flat" :loading="saving" @click="saveModeration">
                                    {{ $t('feedback.moderation.save') }}
                                </v-btn>
                            </v-card-actions>
                        </v-card>
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

export default {
    name: 'FeedbackTicket',
    components: { PageHeader, EmptyState, LoadingState, UserAvatar },
    setup() {
        const authStore = useAuthStore()
        const userStore = useUserStore()
        const { getStatusColor, getStatusLabel, statusFilterOptions } = useTicketHelpers()
        return { authStore, userStore, getStatusColor, getStatusLabel, statusFilterOptions }
    },
    data() {
        return {
            loading: false,
            notFound: false,
            ticket: null,
            tags: [],
            newComment: '',
            commenting: false,
            voting: false,
            watching: false,
            saving: false,
            moderation: {},
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
        statusItems() {
            return this.statusFilterOptions(false)
        },
    },
    watch: {
        // Links between duplicates reuse this page
        '$route.params.id'(id) {
            if (id) this.loadTicket()
        },
        // The user may still be loading when the page mounts
        isAdmin: {
            immediate: true,
            handler(isAdmin) {
                if (isAdmin && !this.tags.length) this.loadTags()
            },
        },
    },
    mounted() {
        this.loadTicket()
    },
    methods: {
        formatDateDistanceToNow,
        async loadTicket() {
            this.loading = true
            this.notFound = false
            try {
                const { data } = await axios.get(`/api/feedback/tickets/${this.$route.params.id}`)
                this.setTicket(data)
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
        async loadTags() {
            try {
                const { data } = await axios.get('/api/feedback/tags')
                this.tags = data
            } catch (error) {
                console.warn('Failed to load feedback tags:', error)
            }
        },
        setTicket(ticket) {
            this.ticket = ticket
            this.moderation = {
                status: ticket.status,
                is_public: ticket.is_public,
                duplicate_of_ticket_id: ticket.duplicate_of?.id ?? null,
                tag_ids: ticket.tags.map(tag => tag.id),
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
            if (this.commenting || !this.newComment.trim()) return
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
        async saveModeration() {
            if (this.saving) return
            this.saving = true
            try {
                const payload = { ...this.moderation, duplicate_of_ticket_id: this.moderation.duplicate_of_ticket_id || null }
                const { data } = await axios.patch(`/api/feedback/tickets/${this.ticket.id}`, payload)
                this.setTicket(data)
                await this.$dialog.success(this.$t('feedback.moderation.saved'))
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.updateFailed'))
            } finally {
                this.saving = false
            }
        },
    },
}
</script>

<style scoped>
.plain-text {
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.official-comment {
    background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
