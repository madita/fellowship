<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import TicketKanban from '@/components/ticket/TicketKanban.vue';
import TicketOverview from '@/components/ticket/TicketOverview.vue';
import TicketForm from '@/components/ticket/TicketForm.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { useUserStore } from '@/store/userStore.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { useDialog } from '@/composables/useDialog.js';
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js';
import {
    TICKET_FILTER_KEYS,
    defaultTicketFilters,
    ticketFiltersFromQuery,
    ticketFilterParams,
    rememberTicketListQuery,
} from '@/composables/useTicketListQuery.js';

/**
 * Tickets for the admin (/admin/tickets: overview, list, kanban) and for
 * members (/account/tickets: their own). A ticket opens on its own page.
 * View, filters and page live in the address, so links and "back" keep them:
 *   /admin/tickets?view=list&status=in_progress&assigned_to=me&priority=urgent
 */
const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const dialog = useDialog();
const {
    getStatusColor,
    getStatusLabel,
    getPriorityColor,
    getPriorityIcon,
    getPriorityLabel,
    statusFilterOptions,
    priorityFilterOptions,
} = useTicketHelpers();

const FILTER_KEYS = TICKET_FILTER_KEYS;
// Set by dashboard links only; shown as removable chips
const LINK_FILTERS = ['mine', 'created_by', 'due'];
const RELATED_KINDS = {
    'App\\Models\\Wiki': { key: 'wiki', icon: 'mdi-book-open-variant' },
    'App\\Models\\Page': { key: 'page', icon: 'mdi-file-document-outline' },
};

const isUserView = computed(() => route.name === 'my-tickets');
const user = computed(() => userStore.user || { id: null });
const isAdmin = computed(() => !!user.value?.isAdmin);
const detailRouteName = computed(() => (isUserView.value ? 'my-ticket' : 'admin-ticket'));
const viewModes = computed(() => (isUserView.value ? ['list', 'kanban'] : ['overview', 'list', 'kanban']));
const defaultView = computed(() => (isUserView.value ? 'list' : 'overview'));

const defaultFilters = defaultTicketFilters;
const filtersFromQuery = () => ticketFiltersFromQuery(route.query);

const viewFromQuery = () => (viewModes.value.includes(route.query.view) ? route.query.view
    : FILTER_KEYS.some(key => route.query[key] !== undefined) ? 'list' : defaultView.value);

const filters = ref(filtersFromQuery());
const viewMode = ref(viewFromQuery());

const tickets = ref([]);
const ticketTypes = ref([]);
const loading = ref(false);
const pagination = ref({ page: Number(route.query.page) || 1, per_page: 20, total: 0, last_page: 1 });

const showComposer = ref(false);
const creating = ref(false);

const pageTitle = computed(() => (isUserView.value ? t('tickets.myTickets') : t('tickets.title')));
const pageSubtitle = computed(() => (isUserView.value ? t('tickets.myTicketsSubtitle') : t('tickets.subtitle')));

const statusItems = computed(() => statusFilterOptions());
const priorityItems = computed(() => priorityFilterOptions());
const typeItems = computed(() => [{ slug: null, name: t('tickets.filters.allTypes') }, ...ticketTypes.value]);
const assigneeItems = computed(() => [
    { value: null, label: t('tickets.filters.allAssignees') },
    { value: 'me', label: t('tickets.filters.assignedToMe') },
    { value: 'unassigned', label: t('tickets.filters.unassigned') },
]);

const linkFilterChips = computed(() => LINK_FILTERS
    .filter(key => filters.value[key])
    .map(key => ({ key, label: t(`tickets.filters.link.${key}_${filters.value[key]}`) })));

const hasActiveFilters = computed(() => {
    const f = filters.value;
    return f.status !== 'open' || FILTER_KEYS.some(key => key !== 'status' && f[key]);
});

const buildQuery = () => {
    const query = {};
    if (viewMode.value !== defaultView.value) query.view = viewMode.value;
    if (viewMode.value === 'list') {
        for (const key of FILTER_KEYS) {
            const value = filters.value[key];
            if (key === 'status') {
                if (value !== 'open') query.status = value ?? '';
            } else if (value) {
                query[key] = value;
            }
        }
        if (pagination.value.page > 1) query.page = String(pagination.value.page);
    }
    return query;
};

const sameQuery = (a, b) => JSON.stringify(Object.entries(a).sort()) === JSON.stringify(Object.entries(b).sort());

const listRouteName = route.name;

const syncQuery = () => {
    const query = buildQuery();
    rememberTicketListQuery(listRouteName, query);
    if (!sameQuery(query, route.query)) router.replace({ query });
};

// The address changed from outside (menu link, dashboard link on this page): follow it
watch(() => route.query, (query) => {
    // Opening a ticket changes the route before this page unmounts
    if (route.name !== listRouteName || sameQuery(query, buildQuery())) return;
    rememberTicketListQuery(listRouteName, query);
    filters.value = filtersFromQuery();
    viewMode.value = viewFromQuery();
    pagination.value.page = Number(query.page) || 1;
    if (viewMode.value === 'list') loadTickets();
});

const loadTickets = async () => {
    loading.value = true;
    try {
        const params = { page: pagination.value.page, per_page: pagination.value.per_page, ...ticketFilterParams(filters.value) };
        const response = await axios.get('/api/tickets', { params });
        tickets.value = response.data.data;
        pagination.value = {
            page: response.data.current_page,
            per_page: response.data.per_page,
            total: response.data.total,
            last_page: response.data.last_page,
        };
    } catch (err) {
        console.error('Failed to load tickets:', err);
        await dialog.requestError(err, t('tickets.messages.loadFailed'));
    } finally {
        loading.value = false;
    }
};

const loadTicketTypes = async () => {
    try {
        const response = await axios.get('/api/ticket-types');
        ticketTypes.value = response.data;
    } catch (err) {
        console.error('Failed to load ticket types:', err);
    }
};

const applyFilters = () => {
    pagination.value.page = 1;
    syncQuery();
    loadTickets();
};

const debouncedApplyFilters = useDebounceFn(applyFilters, 300);

const resetFilters = () => {
    filters.value = defaultFilters();
    applyFilters();
};

const removeLinkFilter = (key) => {
    filters.value[key] = null;
    applyFilters();
};

const changePage = (page) => {
    pagination.value.page = page;
    syncQuery();
    loadTickets();
};

const changeView = (mode) => {
    viewMode.value = mode;
    syncQuery();
    if (mode === 'list') loadTickets();
};

// A count on the overview was clicked: the list with exactly that filter
const showFilteredList = (filter) => {
    filters.value = { ...defaultFilters(), status: null, ...filter };
    viewMode.value = 'list';
    applyFilters();
};

const openTicket = (ticket) => {
    router.push({ name: detailRouteName.value, params: { id: ticket.id ?? ticket } });
};

const createTicket = async (values) => {
    if (creating.value) return;
    creating.value = true;
    try {
        const response = await axios.post('/api/tickets', values);
        showComposer.value = false;
        openTicket(response.data);
    } catch (err) {
        console.error('Failed to create ticket:', err);
        await dialog.requestError(err, t('tickets.messages.createFailed'));
    } finally {
        creating.value = false;
    }
};

const isOverdue = (ticket) => ticket.due_date && !['resolved', 'closed'].includes(ticket.status) && new Date(ticket.due_date) < new Date();

onMounted(() => {
    rememberTicketListQuery(listRouteName, route.query);
    if (viewMode.value === 'list') loadTickets();
    loadTicketTypes();
});
</script>

<template>
    <div>
        <page-header :title="pageTitle" :subtitle="pageSubtitle" icon="mdi-ticket-outline">
            <template #actions>
                <v-btn-toggle
                    :model-value="viewMode"
                    mandatory
                    density="compact"
                    variant="outlined"
                    divided
                    @update:model-value="changeView"
                >
                    <v-btn v-if="!isUserView" value="overview" size="small" icon="mdi-view-dashboard-outline" :title="t('tickets.overviewView')" :aria-label="t('tickets.overviewView')" />
                    <v-btn value="list" size="small" icon="mdi-view-list" :title="t('tickets.listView')" :aria-label="t('tickets.listView')" />
                    <v-btn value="kanban" size="small" icon="mdi-view-column" :title="t('tickets.kanbanView')" :aria-label="t('tickets.kanbanView')" />
                </v-btn-toggle>
                <v-btn v-if="isAdmin" color="primary" variant="elevated" prepend-icon="mdi-plus" @click="showComposer = true">
                    {{ t('tickets.createTicket') }}
                </v-btn>
            </template>
        </page-header>

        <v-container v-if="viewMode === 'overview'" class="pt-0">
            <ticket-overview @filter="showFilteredList" @open-ticket="openTicket" />
        </v-container>

        <v-container v-else-if="viewMode === 'kanban'" fluid class="kanban-container pt-0">
            <ticket-kanban class="fill-height" @open-ticket="openTicket" />
        </v-container>

        <v-container v-else>
            <!-- Filters -->
            <v-row dense class="mb-2">
                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="filters.search"
                        :label="t('tickets.filters.searchPlaceholder')"
                        prepend-inner-icon="mdi-magnify"
                        density="compact"
                        hide-details
                        clearable
                        @update:model-value="debouncedApplyFilters"
                    />
                </v-col>
                <v-col cols="6" sm="3" md="2">
                    <v-select
                        v-model="filters.status"
                        :items="statusItems"
                        item-title="label"
                        item-value="value"
                        :label="t('tickets.fields.status')"
                        density="compact"
                        hide-details
                        @update:model-value="applyFilters"
                    />
                </v-col>
                <v-col cols="6" sm="3" md="2">
                    <v-select
                        v-model="filters.type"
                        :items="typeItems"
                        item-title="name"
                        item-value="slug"
                        :label="t('tickets.fields.type')"
                        density="compact"
                        hide-details
                        @update:model-value="applyFilters"
                    />
                </v-col>
                <v-col cols="6" sm="3" md="2">
                    <v-select
                        v-model="filters.priority"
                        :items="priorityItems"
                        item-title="label"
                        item-value="value"
                        :label="t('tickets.fields.priority')"
                        density="compact"
                        hide-details
                        @update:model-value="applyFilters"
                    />
                </v-col>
                <v-col v-if="isAdmin && !isUserView" cols="6" sm="3" md="2">
                    <v-select
                        v-model="filters.assigned_to"
                        :items="assigneeItems"
                        item-title="label"
                        item-value="value"
                        :label="t('tickets.fields.assignee')"
                        density="compact"
                        hide-details
                        @update:model-value="applyFilters"
                    />
                </v-col>
            </v-row>

            <div v-if="linkFilterChips.length || hasActiveFilters" class="d-flex align-center flex-wrap ga-2 mb-3">
                <v-chip
                    v-for="chip in linkFilterChips"
                    :key="chip.key"
                    size="small"
                    closable
                    @click:close="removeLinkFilter(chip.key)"
                >
                    {{ chip.label }}
                </v-chip>
                <v-spacer />
                <v-btn variant="text" size="small" prepend-icon="mdi-filter-off-outline" @click="resetFilters">
                    {{ t('tickets.reset') }}
                </v-btn>
            </div>

            <loading-state v-if="loading && !tickets.length" />

            <template v-else-if="tickets.length">
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />

                <v-card
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    class="mb-2"
                    rounded="lg"
                    :to="{ name: detailRouteName, params: { id: ticket.id } }"
                >
                    <div class="d-flex align-center pa-4 ga-4">
                        <v-avatar size="40" variant="tonal" :color="ticket.ticket_type?.color" class="flex-shrink-0 d-none d-sm-flex">
                            <v-icon :icon="ticket.ticket_type?.icon || 'mdi-ticket-outline'" :title="ticket.ticket_type?.name" />
                        </v-avatar>

                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex align-center flex-wrap ga-2 mb-1">
                                <span class="text-subtitle-1 font-weight-medium">{{ ticket.title }}</span>
                                <v-chip size="x-small" variant="tonal" :color="getStatusColor(ticket.status)">
                                    {{ getStatusLabel(ticket.status) }}
                                </v-chip>
                                <v-chip
                                    v-if="ticket.priority !== 'normal'"
                                    size="x-small"
                                    variant="tonal"
                                    :color="getPriorityColor(ticket.priority)"
                                    :prepend-icon="getPriorityIcon(ticket.priority)"
                                >
                                    {{ getPriorityLabel(ticket.priority) }}
                                </v-chip>
                                <v-chip v-if="isOverdue(ticket)" size="x-small" variant="tonal" color="error" prepend-icon="mdi-clock-alert-outline">
                                    {{ t('tickets.detail.overdue') }}
                                </v-chip>
                                <v-chip
                                    v-if="RELATED_KINDS[ticket.ticketable_type]"
                                    size="x-small"
                                    variant="outlined"
                                    :prepend-icon="RELATED_KINDS[ticket.ticketable_type].icon"
                                >
                                    {{ t(`tickets.related.kinds.${RELATED_KINDS[ticket.ticketable_type].key}`) }}
                                </v-chip>
                            </div>
                            <div class="d-flex align-center flex-wrap ga-3 text-caption text-medium-emphasis">
                                <span>#{{ ticket.id }}</span>
                                <span v-if="ticket.ticket_type">{{ ticket.ticket_type.name }}</span>
                                <span v-if="ticket.creator">{{ t('tickets.detail.reportedBy', { name: ticket.creator.username || ticket.creator.name }) }}</span>
                                <span :title="formatDate(ticket.created_at)">{{ formatDateDistanceToNow(ticket.created_at) }}</span>
                                <span v-if="ticket.due_date" class="d-inline-flex align-center ga-1" :title="t('tickets.fields.dueDate')">
                                    <v-icon size="small" icon="mdi-calendar-clock-outline" />{{ formatDate(ticket.due_date) }}
                                </span>
                                <span class="d-inline-flex align-center ga-1" :title="t('tickets.comment')">
                                    <v-icon size="small" icon="mdi-comment-outline" />{{ ticket.comments_count ?? 0 }}
                                </span>
                            </div>
                        </div>

                        <div class="assignee text-caption text-medium-emphasis text-end flex-shrink-0 d-none d-md-block">
                            <template v-if="ticket.assignee">
                                <v-icon size="small" icon="mdi-account-check-outline" class="mr-1" />{{ ticket.assignee.username }}
                            </template>
                            <template v-else>{{ t('tickets.unassigned') }}</template>
                        </div>
                    </div>
                </v-card>

                <div v-if="pagination.last_page > 1" class="d-flex justify-center mt-4">
                    <v-pagination
                        :model-value="pagination.page"
                        :length="pagination.last_page"
                        :total-visible="7"
                        rounded="circle"
                        @update:model-value="changePage"
                    />
                </div>
            </template>

            <empty-state
                v-else
                icon="mdi-ticket-outline"
                :title="t('tickets.noTickets')"
                :text="hasActiveFilters ? t('tickets.noTicketsFilterHint') : ''"
            >
                <template v-if="hasActiveFilters" #actions>
                    <v-btn variant="tonal" size="small" @click="resetFilters">{{ t('tickets.reset') }}</v-btn>
                </template>
            </empty-state>
        </v-container>

        <!-- Create -->
        <v-dialog v-model="showComposer" max-width="700" :persistent="creating">
            <v-card>
                <v-card-title class="d-flex align-center ga-2 pt-4 px-6">
                    <v-icon icon="mdi-ticket-outline" color="primary" />
                    {{ t('tickets.createTicket') }}
                </v-card-title>
                <v-card-text class="px-6 pb-6">
                    <ticket-form
                        v-if="showComposer"
                        :saving="creating"
                        :submit-label="t('tickets.create')"
                        @submit="createTicket"
                        @cancel="showComposer = false"
                    />
                </v-card-text>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.kanban-container {
    height: calc(100vh - 220px);
    min-height: 480px;
}

.min-width-0 {
    min-width: 0;
}

.assignee {
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
