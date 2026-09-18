<template>
    <div>
        <page-header
            :title="$t('feedback.title')"
            :subtitle="$t('feedback.subtitle')"
            icon="mdi-message-alert-outline"
        >
            <template #actions>
                <v-btn
                    v-if="authStore.isAuthenticated"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    :to="{ name: 'feedback-new', query: type ? { type } : {} }"
                >
                    {{ type ? $t(`feedback.${type}.create`) : $t('feedback.create') }}
                </v-btn>
            </template>
        </page-header>

        <v-container>
            <v-alert
                v-if="!authStore.isAuthenticated"
                type="info"
                variant="tonal"
                prepend-icon="mdi-login"
                class="mb-4"
            >
                {{ $t('feedback.loginHint') }}
            </v-alert>

            <v-tabs :model-value="tab" color="primary" class="mb-4" @update:model-value="selectTab">
                <v-tab v-for="item in tabs" :key="item.value" :value="item.value" :prepend-icon="item.icon">
                    {{ $t(`feedback.tabs.${item.value}`) }}
                </v-tab>
            </v-tabs>

            <!-- Filters -->
            <v-row dense class="mb-2">
                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="filters.search"
                        :label="$t('feedback.filters.search')"
                        prepend-inner-icon="mdi-magnify"
                        density="compact"
                        hide-details
                        clearable
                        @update:model-value="debouncedApplyFilters"
                    />
                </v-col>
                <v-col cols="12" sm="4" md="3">
                    <v-select
                        v-model="filters.status"
                        :items="statusItems"
                        item-title="label"
                        item-value="value"
                        :label="$t('feedback.fields.status')"
                        density="compact"
                        hide-details
                        @update:model-value="applyFilters"
                    />
                </v-col>
                <v-col cols="12" sm="4" md="2">
                    <v-select
                        v-model="filters.tag"
                        :items="tagItems"
                        item-title="name"
                        item-value="slug"
                        :label="$t('feedback.fields.tag')"
                        density="compact"
                        hide-details
                        @update:model-value="applyFilters"
                    />
                </v-col>
                <v-col cols="12" sm="4" md="3">
                    <v-select
                        v-model="filters.sort"
                        :items="sortItems"
                        :label="$t('feedback.fields.sort')"
                        density="compact"
                        hide-details
                        @update:model-value="applyFilters"
                    />
                </v-col>
            </v-row>

            <loading-state v-if="loading && !tickets.length" />

            <template v-else-if="tickets.length">
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />

                <v-card
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    class="mb-3"
                    rounded="lg"
                    :to="{ name: 'feedback-ticket', params: { id: ticket.id } }"
                >
                    <div class="d-flex align-center pa-4 ga-4">
                        <!-- Vote -->
                        <div class="vote-box text-center flex-shrink-0">
                            <v-btn
                                icon="mdi-arrow-up-bold"
                                size="small"
                                :variant="ticket.user_has_voted ? 'flat' : 'tonal'"
                                :color="ticket.user_has_voted ? 'primary' : undefined"
                                :disabled="!authStore.isAuthenticated"
                                :loading="votingId === ticket.id"
                                :title="ticket.user_has_voted ? $t('feedback.voted') : $t('feedback.vote')"
                                :aria-label="ticket.user_has_voted ? $t('feedback.voted') : $t('feedback.vote')"
                                @click.prevent.stop="toggleVote(ticket)"
                            />
                            <div class="text-subtitle-2 font-weight-bold mt-1">{{ ticket.votes_count }}</div>
                        </div>

                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex align-center flex-wrap ga-2 mb-1">
                                <v-icon
                                    :icon="typeIcon(ticket.type?.slug)"
                                    :color="ticket.type?.color"
                                    size="small"
                                    :title="ticket.type?.name"
                                    :aria-label="ticket.type?.name"
                                />
                                <span class="text-subtitle-1 font-weight-medium">{{ ticket.title }}</span>
                                <v-chip size="x-small" variant="tonal" :color="getStatusColor(ticket.status)">
                                    {{ getStatusLabel(ticket.status) }}
                                </v-chip>
                                <v-chip v-if="!ticket.is_public" size="x-small" variant="tonal" prepend-icon="mdi-eye-off-outline">
                                    {{ $t('feedback.hidden') }}
                                </v-chip>
                                <v-chip
                                    v-for="tag in ticket.tags"
                                    :key="tag.id"
                                    size="x-small"
                                    variant="tonal"
                                    :color="tag.color"
                                >
                                    {{ tag.name }}
                                </v-chip>
                            </div>
                            <div class="d-flex align-center flex-wrap ga-3 text-caption text-medium-emphasis">
                                <span>#{{ ticket.id }}</span>
                                <span v-if="ticket.author">{{ $t('feedback.postedBy', { name: ticket.author.username }) }}</span>
                                <span>{{ formatDateDistanceToNow(ticket.created_at) }}</span>
                                <span class="d-inline-flex align-center ga-1" :title="$t('feedback.comments')">
                                    <v-icon size="small" icon="mdi-comment-outline" />{{ ticket.comments_count }}
                                </span>
                                <span class="d-inline-flex align-center ga-1" :title="$t('feedback.watchers')">
                                    <v-icon size="small" icon="mdi-eye-outline" />{{ ticket.watchers_count }}
                                </span>
                            </div>
                        </div>
                    </div>
                </v-card>

                <div v-if="pagination.last_page > 1" class="d-flex justify-center mt-4">
                    <v-pagination
                        v-model="pagination.page"
                        :length="pagination.last_page"
                        :total-visible="7"
                        rounded="circle"
                        @update:model-value="loadTickets"
                    />
                </div>
            </template>

            <empty-state
                v-else
                :icon="typeIcon(type)"
                :title="type ? $t(`feedback.${type}.empty`) : $t('feedback.empty')"
                :text="$t('feedback.filters.emptyHint')"
            />
        </v-container>
    </div>
</template>

<script>
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'
import { useAuthStore } from '@/store/authStore.js'
import { useTicketHelpers } from '@/composables/useTicketHelpers.js'
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'

const TYPES = ['bug', 'feature']

const defaultFilters = () => ({ search: '', status: 'open', tag: null, sort: 'popular' })

export default {
    name: 'FeedbackList',
    components: { PageHeader, EmptyState, LoadingState },
    setup() {
        const authStore = useAuthStore()
        const { getStatusColor, getStatusLabel, statusFilterOptions } = useTicketHelpers()
        return { authStore, getStatusColor, getStatusLabel, statusFilterOptions }
    },
    data() {
        return {
            loading: false,
            tickets: [],
            tags: [],
            filters: defaultFilters(),
            pagination: { page: 1, last_page: 1 },
            votingId: null,
            tabs: [
                { value: 'all', icon: 'mdi-view-list-outline' },
                { value: 'bug', icon: 'mdi-bug-outline' },
                { value: 'feature', icon: 'mdi-lightbulb-on-outline' },
            ],
        }
    },
    computed: {
        // The tab lives in the address (?type=bug|feature) so links and "back" keep it
        type() {
            return TYPES.includes(this.$route.query.type) ? this.$route.query.type : null
        },
        tab() {
            return this.type || 'all'
        },
        statusItems() {
            return this.statusFilterOptions().map(option => option.value === null
                ? { ...option, label: this.$t('feedback.filters.allStatuses') }
                : option)
        },
        tagItems() {
            return [{ name: this.$t('feedback.filters.allTags'), slug: null }, ...this.tags]
        },
        sortItems() {
            return ['popular', 'newest', 'oldest'].map(value => ({ value, title: this.$t(`feedback.sort.${value}`) }))
        },
    },
    watch: {
        type() {
            this.tickets = []
            this.applyFilters()
        },
    },
    created() {
        this.debouncedApplyFilters = useDebounceFn(this.applyFilters, 300)
    },
    mounted() {
        this.loadTickets()
        this.loadTags()
    },
    methods: {
        formatDateDistanceToNow,
        typeIcon(type) {
            return { bug: 'mdi-bug-outline', feature: 'mdi-lightbulb-on-outline' }[type] || 'mdi-message-alert-outline'
        },
        selectTab(tab) {
            const query = { ...this.$route.query }
            if (tab === 'all') {
                delete query.type
            } else {
                query.type = tab
            }
            this.$router.replace({ query })
        },
        applyFilters() {
            this.pagination.page = 1
            this.loadTickets()
        },
        async loadTickets() {
            this.loading = true
            const type = this.type
            try {
                const params = { page: this.pagination.page, sort: this.filters.sort }
                if (type) params.type = type
                if (this.filters.search) params.search = this.filters.search
                if (this.filters.status) params.status = this.filters.status
                if (this.filters.tag) params.tag = this.filters.tag

                const { data } = await axios.get('/api/feedback/tickets', { params })
                // The tab may have changed while this request was running
                if (type !== this.type) return
                this.tickets = data.data
                this.pagination = { page: data.current_page, last_page: data.last_page }
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.loadFailed'))
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
        async toggleVote(ticket) {
            if (!this.authStore.isAuthenticated || this.votingId) return
            this.votingId = ticket.id
            try {
                const { data } = await axios.post(`/api/feedback/tickets/${ticket.id}/vote`)
                ticket.user_has_voted = data.voted
                ticket.votes_count = data.votes_count
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.voteFailed'))
            } finally {
                this.votingId = null
            }
        },
    },
}
</script>

<style scoped>
.vote-box {
    min-width: 48px;
}

.min-width-0 {
    min-width: 0;
}
</style>
