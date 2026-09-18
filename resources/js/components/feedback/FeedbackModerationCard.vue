<template>
    <v-card rounded="lg" elevation="1" border>
        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
            <v-icon icon="mdi-shield-account-outline" size="small" />
            {{ $t('feedback.moderation.title') }}
        </v-card-title>

        <v-card-text>
            <!-- Community numbers, for the ticket admin -->
            <template v-if="showSummary">
                <div class="d-flex align-center ga-4 text-body-2 mb-3">
                    <span class="d-inline-flex align-center ga-1" :title="$t('feedback.votes')">
                        <v-icon size="small" icon="mdi-arrow-up-bold-outline" />
                        <strong>{{ ticket.votes_count }}</strong> {{ $t('feedback.votes') }}
                    </span>
                    <span class="d-inline-flex align-center ga-1" :title="$t('feedback.watchers')">
                        <v-icon size="small" icon="mdi-eye-outline" />
                        <strong>{{ ticket.watchers_count }}</strong> {{ $t('feedback.watchers') }}
                    </span>
                </div>
            </template>

            <v-select
                v-if="showStatus"
                v-model="form.status"
                :items="statusFilterOptions(false)"
                item-title="label"
                item-value="value"
                :label="$t('feedback.fields.status')"
                density="compact"
                class="mb-2"
                hide-details
            />
            <v-autocomplete
                v-model="form.tag_ids"
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
                v-model.number="form.duplicate_of_ticket_id"
                type="number"
                :label="$t('feedback.moderation.duplicateOf')"
                :hint="$t('feedback.moderation.duplicateHint')"
                persistent-hint
                density="compact"
                clearable
                class="mb-2"
            />
            <v-switch
                v-model="form.is_public"
                :label="$t('feedback.moderation.public')"
                color="primary"
                density="compact"
                hide-details
            />
        </v-card-text>

        <v-card-actions class="px-4 pb-4">
            <slot name="actions" />
            <v-spacer />
            <v-btn color="primary" variant="flat" :loading="saving" @click="save">
                {{ $t('feedback.moderation.save') }}
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<script>
import axios from 'axios'
import { useTicketHelpers } from '@/composables/useTicketHelpers.js'

/**
 * Admin moderation of a bug report or feature request: status, tags,
 * duplicate link and visibility. Emits `saved` with the updated ticket
 * (the shape of GET /api/feedback/tickets/{id}).
 */
export default {
    name: 'FeedbackModerationCard',
    props: {
        // A ticket from the feedback API
        ticket: { type: Object, required: true },
        // Off where the page already edits the status (ticket admin)
        showStatus: { type: Boolean, default: true },
        // Votes and watchers, where the page does not show them itself
        showSummary: { type: Boolean, default: false },
    },
    emits: ['saved'],
    setup() {
        const { statusFilterOptions } = useTicketHelpers()
        return { statusFilterOptions }
    },
    data() {
        return {
            tags: [],
            saving: false,
            form: {},
        }
    },
    watch: {
        ticket: {
            immediate: true,
            handler(ticket) {
                this.form = {
                    status: ticket.status,
                    is_public: ticket.is_public,
                    duplicate_of_ticket_id: ticket.duplicate_of?.id ?? null,
                    tag_ids: (ticket.tags || []).map(tag => tag.id),
                }
            },
        },
    },
    mounted() {
        this.loadTags()
    },
    methods: {
        async loadTags() {
            try {
                const { data } = await axios.get('/api/feedback/tags')
                this.tags = data
            } catch (error) {
                console.warn('Failed to load feedback tags:', error)
            }
        },
        async save() {
            if (this.saving) return
            this.saving = true
            try {
                const payload = { ...this.form, duplicate_of_ticket_id: this.form.duplicate_of_ticket_id || null }
                if (!this.showStatus) delete payload.status
                const { data } = await axios.patch(`/api/feedback/tickets/${this.ticket.id}`, payload)
                this.$emit('saved', data)
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
