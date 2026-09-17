<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useDisplay } from 'vuetify';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import TicketForm from '@/components/ticket/TicketForm.vue';
import TicketRelatedContent from '@/components/ticket/TicketRelatedContent.vue';
import TicketActivity from '@/components/ticket/TicketActivity.vue';
import FeedbackModerationCard from '@/components/feedback/FeedbackModerationCard.vue';
import TicketNavList from '@/components/ticket/TicketNavList.vue';
import { rememberedTicketListQuery } from '@/composables/useTicketListQuery.js';
import { useUserStore } from '@/store/userStore.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { useDialog } from '@/composables/useDialog.js';
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * One ticket on its own page (/admin/tickets/:id for the admin,
 * /account/tickets/:id for members): description, related content and
 * activity on the left; properties, feedback moderation and details on
 * the right.
 */
const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const dialog = useDialog();
const { smAndDown } = useDisplay();
const {
    getStatusColor,
    getStatusLabel,
    getPriorityColor,
    getPriorityIcon,
    getPriorityLabel,
    statusFilterOptions,
    priorityFilterOptions,
} = useTicketHelpers();

const ticket = ref(null);
const feedback = ref(null);
const loading = ref(false);
const notFound = ref(false);
const editing = ref(false);
const saving = ref(false);
const deleting = ref(false);
// 'status' | 'priority' | 'assignee' | 'due_date' while that property is saved
const updatingField = ref(null);
const assignableUsers = ref([]);

const user = computed(() => userStore.user || { id: null });
const isAdmin = computed(() => !!user.value?.isAdmin);
const isAdminView = computed(() => route.name === 'admin-ticket');
const listRouteName = computed(() => (isAdminView.value ? 'admin-tickets' : 'my-tickets'));
const canManage = computed(() => isAdmin.value);
const canComment = computed(() => isAdmin.value || ticket.value?.created_by_user_id === user.value.id);

// The list the ticket was opened from, filters included: for "back" and the ticket drawer
const listQuery = rememberedTicketListQuery(listRouteName.value);
const backTo = computed(() => ({ name: listRouteName.value, query: listQuery }));

// Ticket drawer (admin): open by default on wide screens, remembered per browser
const NAV_STORAGE_KEY = 'tickets.navDrawerOpen';
const showNav = computed(() => isAdminView.value);
const navRef = ref(null);
const navOpen = ref((() => {
    if (smAndDown.value) return false;
    try {
        return localStorage.getItem(NAV_STORAGE_KEY) !== 'false';
    } catch {
        return true;
    }
})());

watch(navOpen, (open) => {
    if (smAndDown.value) return;
    try {
        localStorage.setItem(NAV_STORAGE_KEY, String(open));
    } catch {
        // Not remembered, still works
    }
});

const subtitle = computed(() => {
    if (!ticket.value) return '';
    const reporter = ticket.value.creator?.username || ticket.value.creator?.name;
    return [
        `#${ticket.value.id}`,
        reporter ? t('tickets.detail.reportedBy', { name: reporter }) : null,
        formatDateDistanceToNow(ticket.value.created_at),
    ].filter(Boolean).join(' · ');
});

const isFeedback = computed(() => ['bug', 'feature'].includes(ticket.value?.ticket_type?.slug));
const isOverdue = computed(() => ticket.value?.due_date && !['resolved', 'closed'].includes(ticket.value.status)
    && new Date(ticket.value.due_date) < new Date());
const dueDateValue = computed(() => ticket.value?.due_date?.slice(0, 10) || '');
const dueDraft = ref('');
watch(dueDateValue, (value) => { dueDraft.value = value; }, { immediate: true });

const loadTicket = async ({ withFeedback = true } = {}) => {
    loading.value = true;
    notFound.value = false;
    try {
        const response = await axios.get(`/api/tickets/${route.params.id}`);
        ticket.value = response.data;
        if (withFeedback) loadFeedback();
    } catch (err) {
        if ([403, 404].includes(err.response?.status)) {
            ticket.value = null;
            notFound.value = true;
        } else {
            console.error('Failed to load ticket:', err);
            await dialog.requestError(err, t('tickets.messages.loadFailed'));
        }
    } finally {
        loading.value = false;
    }
};

// Votes, tags and visibility of bug reports and feature requests
const loadFeedback = async () => {
    feedback.value = null;
    if (!isFeedback.value || !canManage.value) return;
    try {
        const response = await axios.get(`/api/feedback/tickets/${ticket.value.id}`);
        feedback.value = response.data;
    } catch (err) {
        console.warn('Failed to load feedback details:', err);
    }
};

const loadAssignableUsers = async () => {
    try {
        const response = await axios.post('/api/users/search', { query: '' });
        const records = Array.isArray(response.data) ? response.data : [];
        // The search leaves out the current user
        assignableUsers.value = [
            ...(user.value?.id ? [{ id: user.value.id, username: user.value.username, avatar: user.value.avatar }] : []),
            ...records.map(u => ({ id: u.id, username: u.username, avatar: u.avatar })),
        ];
    } catch (err) {
        console.error('Failed to load assignable users:', err);
    }
};

// Approvable content follows the status on the server, so re-read the ticket then
const applyUpdate = async (data) => {
    ticket.value = { ...ticket.value, ...data };
    if (ticket.value.is_approvable) await loadTicket({ withFeedback: false });
};

// The native date input only reports complete dates; wait for typing to settle
const saveDueDate = useDebounceFn((value) => {
    if (value === dueDateValue.value) return;
    if (value && !/^(19[7-9]\d|2\d{3})-\d{2}-\d{2}$/.test(value)) return;
    updateProperty('due_date', value || null);
}, 800);

const updateProperty = async (field, value) => {
    if (updatingField.value || !ticket.value) return;
    updatingField.value = field;
    try {
        const response = await axios.patch(`/api/tickets/${ticket.value.id}`, { [field]: value });
        await applyUpdate(response.data);
    } catch (err) {
        console.error(`Failed to update ${field}:`, err);
        await dialog.requestError(err, t('tickets.messages.updateFailed'));
    } finally {
        updatingField.value = null;
    }
};

const updateAssignee = async (userId) => {
    if (updatingField.value || !ticket.value) return;
    updatingField.value = 'assignee';
    try {
        const response = userId
            ? await axios.post(`/api/tickets/${ticket.value.id}/assign`, { user_id: userId })
            : await axios.post(`/api/tickets/${ticket.value.id}/unassign`);
        ticket.value = {
            ...ticket.value,
            status: response.data.status,
            status_label: response.data.status_label,
            assigned_to_user_id: response.data.assigned_to_user_id,
            assignee: response.data.assignee ?? null,
        };
    } catch (err) {
        console.error('Failed to update assignee:', err);
        await dialog.requestError(err, t('tickets.messages.updateFailed'));
    } finally {
        updatingField.value = null;
    }
};

const saveDetails = async (values) => {
    if (saving.value) return;
    saving.value = true;
    try {
        const response = await axios.patch(`/api/tickets/${ticket.value.id}`, values);
        ticket.value = { ...ticket.value, ...response.data };
        editing.value = false;
        loadFeedback();
    } catch (err) {
        console.error('Failed to update ticket:', err);
        await dialog.requestError(err, t('tickets.messages.updateFailed'));
    } finally {
        saving.value = false;
    }
};

const removeTicket = async () => {
    if (deleting.value) return;
    const confirmed = await dialog.confirmDelete(t('tickets.confirm.deleteMessage'), {
        title: t('tickets.confirm.deleteTitle'),
        confirmationText: t('tickets.confirm.deleteConfirm'),
        cancellationText: t('tickets.confirm.deleteCancel'),
    });
    if (!confirmed) return;

    deleting.value = true;
    try {
        await axios.delete(`/api/tickets/${ticket.value.id}`);
        await dialog.success(t('tickets.messages.deleted'));
        router.replace(backTo.value);
    } catch (err) {
        console.error('Failed to delete ticket:', err);
        await dialog.requestError(err, t('tickets.messages.deleteFailed'));
    } finally {
        deleting.value = false;
    }
};

// Marking a duplicate can close the ticket
const onFeedbackSaved = (data) => {
    feedback.value = data;
    loadTicket({ withFeedback: false });
};

const openLegacyUsers = () => {
    const meta = ticket.value?.metadata || {};
    const search = meta.legacy_username || meta.legacy_user_id || meta.legacy_email || '';
    router.push({ path: '/admin/settings/tools/migrations', query: { tab: 'legacyUsers', search } });
};

watch(() => route.params.id, (id) => {
    if (id) {
        editing.value = false;
        loadTicket();
    }
});

// Keep the drawer entry in step with changes made on the page
watch(() => ticket.value && [ticket.value.title, ticket.value.status, ticket.value.priority], () => {
    if (ticket.value) navRef.value?.updateItem(ticket.value);
});

watch(canManage, (manage) => {
    if (manage && !assignableUsers.value.length) loadAssignableUsers();
}, { immediate: true });

onMounted(loadTicket);
</script>

<template>
    <v-layout class="ticket-workspace" :class="{ 'ticket-workspace--nav': showNav }">
        <!-- Ticket drawer: jump between the tickets of the list -->
        <v-navigation-drawer
            v-if="showNav"
            v-model="navOpen"
            absolute
            touchless
            :temporary="smAndDown"
            width="300"
            class="ticket-nav-drawer"
        >
            <ticket-nav-list
                ref="navRef"
                :list-query="listQuery"
                :detail-route-name="route.name"
                :current-id="Number(route.params.id)"
                @close="navOpen = false"
            />
        </v-navigation-drawer>

        <v-main :scrollable="showNav">
            <!-- Switching tickets from the drawer keeps the current one visible until the next is loaded -->
            <v-progress-linear :active="loading && !!ticket" indeterminate color="primary" height="2" class="ticket-switching" />
            <loading-state v-if="loading && !ticket" />

            <v-container v-else-if="notFound">
                <empty-state icon="mdi-ticket-outline" :title="t('tickets.detail.notFound')">
                    <template #actions>
                        <v-btn color="primary" variant="flat" :to="{ name: listRouteName }">
                            {{ isAdminView ? t('tickets.title') : t('tickets.myTickets') }}
                        </v-btn>
                    </template>
                </empty-state>
            </v-container>

            <template v-else-if="ticket">
                <page-header
                    :title="ticket.title"
                    :subtitle="subtitle"
                    :icon="ticket.ticket_type?.icon || 'mdi-ticket-outline'"
                    :back-to="backTo"
                >
                    <template v-if="canManage" #actions>
                        <v-btn
                            v-if="showNav && !navOpen"
                            variant="text"
                            prepend-icon="mdi-format-list-bulleted"
                            :title="t('tickets.nav.show')"
                            @click="navOpen = true"
                        >
                            {{ t('tickets.nav.title') }}
                        </v-btn>
                        <v-btn
                            v-if="!editing"
                            variant="tonal"
                            prepend-icon="mdi-pencil-outline"
                            @click="editing = true"
                        >
                            {{ t('tickets.edit') }}
                        </v-btn>
                        <v-btn
                            variant="text"
                            color="error"
                            prepend-icon="mdi-delete-outline"
                            :loading="deleting"
                            @click="removeTicket"
                        >
                            {{ t('tickets.delete') }}
                        </v-btn>
                    </template>
                </page-header>

                <v-container>
                    <v-row>
                        <!-- Main column -->
                        <v-col cols="12" md="8">
                            <div class="d-flex align-center flex-wrap ga-2 mb-4">
                                <v-chip v-if="ticket.ticket_type" size="small" variant="tonal" :color="ticket.ticket_type.color" :prepend-icon="ticket.ticket_type.icon">
                                    {{ ticket.ticket_type.name }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" :color="getStatusColor(ticket.status)">
                                    {{ getStatusLabel(ticket.status) }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" :color="getPriorityColor(ticket.priority)" :prepend-icon="getPriorityIcon(ticket.priority)">
                                    {{ getPriorityLabel(ticket.priority) }}
                                </v-chip>
                                <v-chip v-if="isOverdue" size="small" variant="tonal" color="error" prepend-icon="mdi-clock-alert-outline">
                                    {{ t('tickets.detail.overdue') }}
                                </v-chip>
                                <v-chip v-if="feedback && !feedback.is_public" size="small" variant="tonal" prepend-icon="mdi-eye-off-outline">
                                    {{ t('feedback.hidden') }}
                                </v-chip>
                            </div>

                            <!-- Legacy account claim -->
                            <v-card v-if="canManage && ticket.metadata?.legacy_username" rounded="lg" variant="tonal" color="info" class="mb-4">
                                <v-card-text>
                                    <div class="text-subtitle-1 font-weight-medium mb-2">{{ t('tickets.legacyClaim.title') }}</div>
                                    <div class="d-flex flex-wrap align-center ga-2 mb-3">
                                        <v-chip size="small" prepend-icon="mdi-account-clock">{{ ticket.metadata.legacy_username }}</v-chip>
                                        <v-chip v-if="ticket.metadata.legacy_email" size="small" prepend-icon="mdi-email-outline">{{ ticket.metadata.legacy_email }}</v-chip>
                                        <v-chip v-if="ticket.metadata.legacy_user_id" size="small" prepend-icon="mdi-identifier">{{ ticket.metadata.legacy_user_id }}</v-chip>
                                    </div>
                                    <v-btn variant="flat" size="small" prepend-icon="mdi-account-convert" @click="openLegacyUsers">
                                        {{ t('tickets.legacyClaim.open') }}
                                    </v-btn>
                                </v-card-text>
                            </v-card>

                            <!-- Description / edit -->
                            <v-card rounded="lg" variant="outlined" class="mb-4">
                                <v-card-text v-if="editing">
                                    <ticket-form
                                        :ticket="ticket"
                                        :saving="saving"
                                        :submit-label="t('tickets.update')"
                                        @submit="saveDetails"
                                        @cancel="editing = false"
                                    />
                                </v-card-text>
                                <v-card-text v-else-if="ticket.description" class="plain-text text-body-1">{{ ticket.description }}</v-card-text>
                                <v-card-text v-else class="text-medium-emphasis">{{ t('tickets.detail.noDescription') }}</v-card-text>
                            </v-card>

                            <!-- What the ticket is about -->
                            <ticket-related-content
                                v-if="ticket.ticketable_type && ticket.ticketable_id"
                                :ticket="ticket"
                                :is-admin="canManage"
                                class="mb-6"
                                @updated="ticket = { ...ticket, ...$event }"
                            />

                            <ticket-activity
                                :ticket-id="ticket.id"
                                :comments="ticket.comments || []"
                                :can-comment="canComment"
                                :is-admin="canManage"
                                :current-user-id="user.id"
                            />
                        </v-col>

                        <!-- Side column -->
                        <v-col cols="12" md="4">
                            <v-card rounded="lg" variant="outlined" class="mb-4">
                                <v-card-title class="text-subtitle-1">{{ t('tickets.detail.properties') }}</v-card-title>
                                <v-card-text>
                                    <template v-if="canManage">
                                        <v-select
                                            :model-value="ticket.status"
                                            :items="statusFilterOptions(false)"
                                            item-title="label"
                                            item-value="value"
                                            :label="t('tickets.fields.status')"
                                            density="compact"
                                            class="mb-3"
                                            hide-details
                                            :loading="updatingField === 'status'"
                                            :disabled="!!updatingField"
                                            @update:model-value="updateProperty('status', $event)"
                                        >
                                            <template #selection="{ item }">
                                                <v-icon size="10" :color="item.raw.color" icon="mdi-circle" class="mr-2" />
                                                {{ item.raw.label }}
                                            </template>
                                        </v-select>
                                        <v-select
                                            :model-value="ticket.priority"
                                            :items="priorityFilterOptions(false)"
                                            item-title="label"
                                            item-value="value"
                                            :label="t('tickets.fields.priority')"
                                            density="compact"
                                            class="mb-3"
                                            hide-details
                                            :loading="updatingField === 'priority'"
                                            :disabled="!!updatingField"
                                            @update:model-value="updateProperty('priority', $event)"
                                        >
                                            <template #selection="{ item }">
                                                <v-icon size="small" :color="item.raw.color" :icon="item.raw.icon" class="mr-2" />
                                                {{ item.raw.label }}
                                            </template>
                                        </v-select>
                                        <v-autocomplete
                                            :model-value="ticket.assigned_to_user_id"
                                            :items="assignableUsers"
                                            item-title="username"
                                            item-value="id"
                                            :label="t('tickets.fields.assignee')"
                                            :placeholder="t('tickets.unassigned')"
                                            persistent-placeholder
                                            density="compact"
                                            clearable
                                            hide-details
                                            :loading="updatingField === 'assignee'"
                                            :disabled="!!updatingField"
                                            @update:model-value="updateAssignee"
                                        />
                                        <div class="d-flex justify-end mb-3">
                                            <v-btn
                                                v-if="ticket.assigned_to_user_id !== user.id"
                                                variant="text"
                                                size="small"
                                                color="primary"
                                                :disabled="!!updatingField"
                                                @click="updateAssignee(user.id)"
                                            >
                                                {{ t('tickets.assignToMe') }}
                                            </v-btn>
                                        </div>
                                        <v-text-field
                                            v-model="dueDraft"
                                            type="date"
                                            :label="t('tickets.fields.dueDate')"
                                            density="compact"
                                            hide-details
                                            clearable
                                            :loading="updatingField === 'due_date'"
                                            :disabled="!!updatingField && updatingField !== 'due_date'"
                                            @update:model-value="saveDueDate($event || '')"
                                        />
                                    </template>

                                    <!-- Members see the properties, admins handle them -->
                                    <dl v-else class="property-list text-body-2">
                                        <dt>{{ t('tickets.fields.status') }}</dt>
                                        <dd>
                                            <v-chip size="small" variant="tonal" :color="getStatusColor(ticket.status)">{{ getStatusLabel(ticket.status) }}</v-chip>
                                        </dd>
                                        <dt>{{ t('tickets.fields.priority') }}</dt>
                                        <dd>
                                            <v-chip size="small" variant="tonal" :color="getPriorityColor(ticket.priority)" :prepend-icon="getPriorityIcon(ticket.priority)">
                                                {{ getPriorityLabel(ticket.priority) }}
                                            </v-chip>
                                        </dd>
                                        <dt>{{ t('tickets.fields.assignee') }}</dt>
                                        <dd>{{ ticket.assignee?.username || t('tickets.unassigned') }}</dd>
                                        <template v-if="ticket.due_date">
                                            <dt>{{ t('tickets.fields.dueDate') }}</dt>
                                            <dd>{{ formatDate(ticket.due_date) }}</dd>
                                        </template>
                                    </dl>
                                </v-card-text>
                            </v-card>

                            <feedback-moderation-card
                                v-if="feedback"
                                :ticket="feedback"
                                :show-status="false"
                                show-summary
                                class="mb-4"
                                @saved="onFeedbackSaved"
                            >
                                <template #actions>
                                    <v-btn variant="text" size="small" prepend-icon="mdi-open-in-new" :to="`/feedback/${ticket.id}`">
                                        {{ t('tickets.detail.publicPage') }}
                                    </v-btn>
                                </template>
                            </feedback-moderation-card>

                            <v-card rounded="lg" variant="outlined">
                                <v-card-title class="text-subtitle-1">{{ t('tickets.details') }}</v-card-title>
                                <v-card-text>
                                    <dl class="property-list text-body-2">
                                        <dt>{{ t('tickets.fields.reporter') }}</dt>
                                        <dd>{{ ticket.creator?.username || ticket.creator?.name || t('tickets.unknown') }}</dd>
                                        <dt>{{ t('tickets.fields.created') }}</dt>
                                        <dd>{{ formatDate(ticket.created_at) }}</dd>
                                        <dt>{{ t('tickets.fields.updated') }}</dt>
                                        <dd>{{ formatDate(ticket.updated_at) }}</dd>
                                        <template v-if="ticket.resolved_at">
                                            <dt>{{ t('tickets.fields.resolvedAt') }}</dt>
                                            <dd>{{ formatDate(ticket.resolved_at) }}</dd>
                                        </template>
                                        <template v-if="ticket.closed_at">
                                            <dt>{{ t('tickets.fields.closedAt') }}</dt>
                                            <dd>{{ formatDate(ticket.closed_at) }}</dd>
                                        </template>
                                    </dl>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </template>
        </v-main>
    </v-layout>
</template>

<style scoped>
/* With the drawer, drawer and ticket scroll separately within the window height */
.ticket-workspace--nav {
    height: calc(100dvh - var(--v-layout-top, 64px) - var(--v-layout-bottom, 0px));
    min-height: 480px;
}

.ticket-switching {
    position: sticky;
    top: 0;
    z-index: 2;
    margin-bottom: -2px;
}
.plain-text {
    white-space: pre-line;
    overflow-wrap: anywhere;
    line-height: 1.6;
}

.property-list {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 10px 16px;
    align-items: center;
    margin: 0;
}

.property-list dt {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.property-list dd {
    margin: 0;
    min-width: 0;
}
</style>
