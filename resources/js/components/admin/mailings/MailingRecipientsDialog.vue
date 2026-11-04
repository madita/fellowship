<template>
    <v-dialog v-model="open" max-width="760" scrollable>
        <v-card rounded="lg">
            <v-card-title class="d-flex align-center">
                <v-icon class="mr-2" color="primary">mdi-account-multiple-check-outline</v-icon>
                {{ $t('mailings.recipients.title') }}
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" :aria-label="$t('common.close')" @click="open = false" />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <div class="text-body-2 text-medium-emphasis mb-3">{{ mailing?.subject }}</div>

                <!-- Counted off the rows rather than read off the mailing,
                     so a send that is still running says where it is -->
                <div class="d-flex flex-wrap ga-2 mb-4">
                    <v-chip
                        v-for="state in states"
                        :key="state.value"
                        size="small"
                        :color="state.color"
                        :variant="filter === state.value ? 'flat' : 'tonal'"
                        @click="setFilter(state.value)"
                    >
                        <v-icon start size="16">{{ state.icon }}</v-icon>
                        {{ state.label }}: {{ breakdown[state.value] ?? 0 }}
                    </v-chip>
                </div>

                <loading-state v-if="loading && rows.length === 0" />

                <empty-state
                    v-else-if="rows.length === 0"
                    icon="mdi-email-off-outline"
                    :title="$t('mailings.recipients.empty')"
                    compact
                />

                <v-list v-else density="compact" class="pa-0">
                    <v-list-item v-for="row in rows" :key="row.id" class="px-0">
                        <template #prepend>
                            <v-icon :color="colorFor(row.status)" size="18">{{ iconFor(row.status) }}</v-icon>
                        </template>
                        <v-list-item-title class="text-body-2">
                            {{ row.user?.name || row.user?.username || $t('mailings.recipients.goneAccount') }}
                        </v-list-item-title>
                        <v-list-item-subtitle class="text-caption">
                            {{ row.email }}
                            <template v-if="row.reason"> · {{ reasonText(row.reason) }}</template>
                        </v-list-item-subtitle>
                        <template #append>
                            <span class="text-caption text-medium-emphasis">
                                {{ row.sent_at ? formatDateDistanceToNow(row.sent_at) : '' }}
                            </span>
                        </template>
                    </v-list-item>
                </v-list>

                <div v-if="meta.last_page > 1" class="d-flex justify-center mt-4">
                    <v-pagination
                        v-model="page"
                        :length="meta.last_page"
                        :total-visible="5"
                        density="comfortable"
                        @update:model-value="load"
                    />
                </div>
            </v-card-text>

            <v-divider />

            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="open = false">{{ $t('common.close') }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import { useMailingStore } from '@/store/mailingStore.js';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

/**
 * Who got a mailing and who did not — the list an admin opens when
 * somebody says they never received the newsletter.
 *
 * The reason beside a skipped row is the useful part: "opted out" is a
 * complete answer to that question, and it is not one the sender has to
 * guess at.
 */
export default {
    name: 'MailingRecipientsDialog',
    components: { EmptyState, LoadingState },
    props: {
        modelValue: { type: Boolean, default: false },
        mailing: { type: Object, default: null },
    },
    emits: ['update:modelValue'],
    setup() {
        return { store: useMailingStore(), formatDateDistanceToNow };
    },
    data() {
        return {
            rows: [],
            meta: { current_page: 1, last_page: 1, per_page: 50, total: 0 },
            breakdown: {},
            loading: false,
            page: 1,
            filter: null,
        };
    },
    computed: {
        open: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            },
        },
        states() {
            return [
                { value: 'sent', icon: 'mdi-check-circle-outline', color: 'success', label: this.$t('mailings.recipients.sent') },
                { value: 'pending', icon: 'mdi-clock-outline', color: 'info', label: this.$t('mailings.recipients.pending') },
                { value: 'skipped', icon: 'mdi-minus-circle-outline', color: 'grey', label: this.$t('mailings.recipients.skipped') },
                { value: 'failed', icon: 'mdi-alert-circle-outline', color: 'error', label: this.$t('mailings.recipients.failed') },
            ];
        },
    },
    watch: {
        modelValue(opened) {
            if (opened) {
                this.page = 1;
                this.filter = null;
                this.rows = [];
                this.load();
            }
        },
    },
    methods: {
        async load() {
            if (!this.mailing?.id) return;
            this.loading = true;
            try {
                const [list, detail] = await Promise.all([
                    this.store.fetchRecipients(this.mailing.id, {
                        page: this.page,
                        per_page: 50,
                        ...(this.filter ? { status: this.filter } : {}),
                    }),
                    this.store.fetchMailing(this.mailing.id),
                ]);

                this.rows = list.data || [];
                this.meta = {
                    current_page: list.current_page,
                    last_page: list.last_page,
                    per_page: list.per_page,
                    total: list.total,
                };
                this.breakdown = detail.breakdown || {};
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.loading = false;
            }
        },

        setFilter(value) {
            this.filter = this.filter === value ? null : value;
            this.page = 1;
            this.load();
        },

        iconFor(status) {
            return this.states.find((state) => state.value === status)?.icon || 'mdi-email-outline';
        },

        colorFor(status) {
            return this.states.find((state) => state.value === status)?.color || 'grey';
        },

        /**
         * The reasons the code writes are translated; anything else is a
         * message from the mail server, which is shown as it came.
         */
        reasonText(reason) {
            const known = ['opted_out', 'unsubscribed_all', 'cancelled'];

            return known.includes(reason) ? this.$t(`mailings.recipients.reasons.${reason}`) : reason;
        },
    },
};
</script>
