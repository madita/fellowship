<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import DiffView from '@/components/common/DiffView.vue';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * What happened to a ticket, newest first: created by, and every change of
 * status, priority, assignee, type, due date, title, description, visibility
 * and duplicate link, with who did it and when. Description edits open as a diff.
 */
const props = defineProps({
    ticketId: { type: Number, required: true },
    // Changes when the ticket was saved, so the history reloads
    refreshKey: { type: [String, Number], default: null },
});

const emit = defineEmits(['open-comments']);

const { t, locale } = useI18n();
const { getStatusColor, getStatusLabel, getPriorityColor, getPriorityIcon, getPriorityLabel } = useTicketHelpers();

const FIELDS = {
    status: { icon: 'mdi-progress-check', label: 'tickets.fields.status' },
    priority: { icon: 'mdi-flag-outline', label: 'tickets.fields.priority' },
    assigned_to_user_id: { icon: 'mdi-account-arrow-right-outline', label: 'tickets.fields.assignee' },
    ticket_type_id: { icon: 'mdi-shape-outline', label: 'tickets.fields.type' },
    due_date: { icon: 'mdi-calendar-clock-outline', label: 'tickets.fields.dueDate' },
    title: { icon: 'mdi-format-title', label: 'tickets.fields.title' },
    description: { icon: 'mdi-text', label: 'tickets.fields.description' },
    is_public: { icon: 'mdi-eye-outline', label: 'tickets.history.fields.public' },
    duplicate_of_ticket_id: { icon: 'mdi-content-duplicate', label: 'tickets.history.fields.duplicate' },
};

const entries = ref([]);
const loading = ref(false);
const loadFailed = ref(false);
const diff = ref(null);

const load = async () => {
    loading.value = true;
    loadFailed.value = false;
    try {
        const response = await axios.get(`/api/tickets/${props.ticketId}/history`);
        entries.value = response.data.data;
    } catch (err) {
        console.error('Failed to load ticket history:', err);
        loadFailed.value = true;
    } finally {
        loading.value = false;
    }
};

// One dot per entry: the field when one thing changed, otherwise a pencil
const dotOf = (entry) => {
    if (entry.action === 'commented') return { icon: 'mdi-comment-text-outline', color: entry.is_internal ? 'warning' : 'primary' };
    if (entry.action === 'created') return { icon: 'mdi-plus', color: 'primary' };
    if (entry.action === 'deleted') return { icon: 'mdi-delete-outline', color: 'error' };
    if (entry.action === 'restored') return { icon: 'mdi-restore', color: 'success' };
    if (entry.changes.length === 1) {
        const change = entry.changes[0];
        return {
            icon: FIELDS[change.field]?.icon || 'mdi-pencil-outline',
            color: change.field === 'status' ? getStatusColor(change.new) : 'secondary',
        };
    }
    return { icon: 'mdi-pencil-outline', color: 'secondary' };
};

const userName = (entry) => entry.user?.username || t('tickets.history.system');

const actionText = (entry) => t(`tickets.history.actions.${entry.action}`);

// Revisions store dates as 'YYYY-MM-DD HH:MM:SS'
const shortDate = (value) => (value ? new Date(value.replace(' ', 'T')).toLocaleDateString(locale.value) : null);

const plainTitle = (value) => value || '–';

const visibleEntries = computed(() => entries.value.filter(entry => entry.action !== 'updated' || entry.changes.length));

watch(() => [props.ticketId, props.refreshKey], load);
onMounted(load);
</script>

<template>
    <div class="ticket-history">
        <loading-state v-if="loading && !entries.length" compact />

        <empty-state
            v-else-if="loadFailed"
            compact
            icon="mdi-history"
            :title="t('tickets.history.loadFailed')"
        >
            <template #actions>
                <v-btn variant="tonal" size="small" @click="load">{{ t('tickets.nav.retry') }}</v-btn>
            </template>
        </empty-state>

        <v-timeline
            v-else-if="visibleEntries.length"
            side="end"
            align="start"
            density="compact"
            truncate-line="both"
            class="px-2"
        >
            <v-timeline-item
                v-for="entry in visibleEntries"
                :key="entry.id"
                :dot-color="dotOf(entry).color"
                :icon="dotOf(entry).icon"
                size="small"
                fill-dot
            >
                <div class="d-flex align-center flex-wrap ga-2 text-body-2">
                    <span><strong>{{ userName(entry) }}</strong> {{ actionText(entry) }}</span>
                    <v-chip v-if="entry.is_internal" size="x-small" variant="tonal" color="warning" prepend-icon="mdi-lock-outline">
                        {{ t('tickets.internal') }}
                    </v-chip>
                    <span class="text-caption text-medium-emphasis" :title="formatDate(entry.created_at)">
                        {{ formatDateDistanceToNow(entry.created_at) }}
                    </span>
                </div>

                <!-- A comment shows its first line; the Comments tab has the whole thread -->
                <button
                    v-if="entry.action === 'commented'"
                    type="button"
                    class="comment-excerpt text-body-2 text-medium-emphasis mt-1"
                    :title="t('tickets.history.openComments')"
                    @click="emit('open-comments')"
                >
                    {{ entry.excerpt }}
                </button>

                <ul v-if="entry.changes.length" class="changes text-body-2 mt-1">
                    <li v-for="change in entry.changes" :key="change.field" class="d-flex align-center flex-wrap ga-2">
                        <span class="text-medium-emphasis">{{ t(FIELDS[change.field]?.label || change.field) }}</span>

                        <!-- Status and priority as the chips the rest of the page uses -->
                        <template v-if="change.field === 'status'">
                            <v-chip v-if="change.old" size="x-small" variant="tonal" :color="getStatusColor(change.old)">{{ getStatusLabel(change.old) }}</v-chip>
                            <v-icon size="14" icon="mdi-arrow-right" />
                            <v-chip size="x-small" variant="tonal" :color="getStatusColor(change.new)">{{ getStatusLabel(change.new) }}</v-chip>
                        </template>
                        <template v-else-if="change.field === 'priority'">
                            <v-chip v-if="change.old" size="x-small" variant="tonal" :color="getPriorityColor(change.old)" :prepend-icon="getPriorityIcon(change.old)">
                                {{ getPriorityLabel(change.old) }}
                            </v-chip>
                            <v-icon size="14" icon="mdi-arrow-right" />
                            <v-chip size="x-small" variant="tonal" :color="getPriorityColor(change.new)" :prepend-icon="getPriorityIcon(change.new)">
                                {{ getPriorityLabel(change.new) }}
                            </v-chip>
                        </template>

                        <template v-else-if="change.field === 'assigned_to_user_id'">
                            <span>{{ change.old_display || t('tickets.unassigned') }}</span>
                            <v-icon size="14" icon="mdi-arrow-right" />
                            <strong>{{ change.new_display || t('tickets.unassigned') }}</strong>
                        </template>

                        <template v-else-if="change.field === 'is_public'">
                            <strong>{{ change.new === '1' ? t('tickets.history.public') : t('tickets.history.private') }}</strong>
                        </template>

                        <template v-else-if="change.field === 'due_date'">
                            <span>{{ shortDate(change.old) || t('tickets.history.none') }}</span>
                            <v-icon size="14" icon="mdi-arrow-right" />
                            <strong>{{ shortDate(change.new) || t('tickets.history.none') }}</strong>
                        </template>

                        <template v-else-if="change.field === 'description'">
                            <v-btn
                                variant="text"
                                size="x-small"
                                color="primary"
                                prepend-icon="mdi-file-compare"
                                @click="diff = change"
                            >
                                {{ t('tickets.history.showChanges') }}
                            </v-btn>
                        </template>

                        <template v-else-if="change.field === 'title'">
                            <span class="text-decoration-line-through text-medium-emphasis">{{ plainTitle(change.old) }}</span>
                            <v-icon size="14" icon="mdi-arrow-right" />
                            <strong>{{ plainTitle(change.new) }}</strong>
                        </template>

                        <template v-else>
                            <span>{{ change.old_display || change.old || t('tickets.history.none') }}</span>
                            <v-icon size="14" icon="mdi-arrow-right" />
                            <strong>{{ change.new_display || change.new || t('tickets.history.none') }}</strong>
                        </template>
                    </li>
                </ul>
            </v-timeline-item>
        </v-timeline>

        <empty-state v-else compact icon="mdi-history" :title="t('tickets.history.empty')" />

        <!-- Description changes -->
        <v-dialog :model-value="!!diff" max-width="960" scrollable @update:model-value="diff = null">
            <v-card v-if="diff">
                <v-card-title class="d-flex align-center ga-2 py-3">
                    <v-icon icon="mdi-file-compare" color="primary" />
                    {{ t('tickets.history.descriptionChanges') }}
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" size="small" :aria-label="t('tickets.close')" @click="diff = null" />
                </v-card-title>
                <v-divider />
                <v-card-text class="py-4">
                    <diff-view
                        :old-value="diff.old || ''"
                        :new-value="diff.new || ''"
                        :old-label="t('tickets.history.before')"
                        :new-label="t('tickets.history.after')"
                    />
                </v-card-text>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.comment-excerpt {
    display: block;
    text-align: start;
    cursor: pointer;
    background: none;
    border: 0;
    padding: 0;
}

.comment-excerpt:hover {
    color: rgb(var(--v-theme-primary));
}

.changes {
    list-style: none;
    padding: 0;
    margin: 0;
}

.changes li + li {
    margin-top: 4px;
}
</style>
