<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import TicketSidebar from './TicketSidebar.vue';
import TicketKanban from './TicketKanban.vue';
import { useUserStore } from '@/store/userStore.js';
import { useDateFormat } from '@/plugins/formatDate.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';

const { t } = useI18n();
const route = useRoute();
const userStore = useUserStore();
const { formatDate: formatDateUtil } = useDateFormat();
const {
    getStatusColor,
    getPriorityColor,
    getPriorityIcon,
    statusFilterOptions,
    priorityFilterOptions,
} = useTicketHelpers();

const tickets = ref([]);
const ticketTypes = ref([]);
const loading = ref(false);
const showFilters = ref(false);
const selectedTicket = ref(null);
const isDrawerOpen = ref(false);
const editMode = ref(false);
const viewMode = ref('list'); // 'list' | 'kanban'
const kanbanRef = ref(null);

// Related content
const relatedContent = ref(null);
const relatedContentLoading = ref(false);
const relatedContentType = ref(null); // 'wiki' | 'page' | null

// Filters
const filters = ref({
    status: 'open',
    type: null,
    assigned_to: null,
    priority: null,
    search: '',
});

const pagination = ref({
    page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
});

const user = computed(() => userStore.user || { id: null });
const isAdmin = computed(() => user.value?.isAdmin || false);
const isUserView = computed(() => route.path === '/account/tickets');

const pageTitle = computed(() => isUserView.value ? t('tickets.myTickets') : t('tickets.title'));
const pageSubtitle = computed(() => isUserView.value ? t('tickets.myTicketsSubtitle') : t('tickets.subtitle'));

const assigneeFilterOptions = computed(() => [
    { value: null, label: t('tickets.filters.allAssignees') },
    { value: 'me', label: t('tickets.filters.assignedToMe') },
    { value: 'unassigned', label: t('tickets.filters.unassigned') },
]);

const activeFilterCount = computed(() => {
    let count = 0;
    if (filters.value.priority) count++;
    if (filters.value.type) count++;
    if (filters.value.assigned_to) count++;
    return count;
});

const loadTickets = async () => {
    loading.value = true;
    try {
        const params = {
            page: pagination.value.page,
            per_page: pagination.value.per_page,
            ...filters.value,
        };

        // Remove null/empty filters
        Object.keys(params).forEach(key => {
            if (params[key] === null || params[key] === '') {
                delete params[key];
            }
        });

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
    } finally {
        loading.value = false;
    }
};

const loadRelatedContent = async (ticket) => {
    relatedContent.value = null;
    relatedContentType.value = null;

    if (!ticket?.ticketable || !ticket?.ticketable_type) return;

    const ticketable = ticket.ticketable;
    const type = ticket.ticketable_type;

    relatedContentLoading.value = true;
    try {
        if (type === 'App\\Models\\Wiki') {
            const response = await axios.get(`/api/wiki/${ticketable.slug}`);
            relatedContent.value = response.data.page || response.data;
            relatedContentType.value = 'wiki';
        } else if (type === 'App\\Models\\Page') {
            const response = await axios.get(`/api/pages/${ticketable.slug}`);
            relatedContent.value = response.data.page || response.data;
            relatedContentType.value = 'page';
        }
    } catch (err) {
        console.error('Failed to load related content:', err);
    } finally {
        relatedContentLoading.value = false;
    }
};

const relatedContentHtml = computed(() => {
    if (!relatedContent.value) return null;
    if (relatedContentType.value === 'wiki') {
        return relatedContent.value.content || null;
    }
    if (relatedContentType.value === 'page') {
        return relatedContent.value.body || relatedContent.value.content || null;
    }
    return null;
});

const relatedContentTitle = computed(() => {
    if (!relatedContent.value) return null;
    return relatedContent.value.title || null;
});

const selectTicket = (ticket) => {
    selectedTicket.value = ticket;
    editMode.value = false;
    loadRelatedContent(ticket);
};

const openDrawer = () => {
    isDrawerOpen.value = true;
    editMode.value = false;
};

const createNewTicket = () => {
    selectedTicket.value = {
        title: '',
        description: '',
        priority: 'normal',
        status: 'open',
    };
    editMode.value = true;
    isDrawerOpen.value = true;
};

const handleAddTicket = async (ticketData) => {
    try {
        const response = await axios.post('/api/tickets', ticketData);
        tickets.value.unshift(response.data);
        pagination.value.total++;
        selectedTicket.value = response.data;
        loadRelatedContent(response.data);
    } catch (err) {
        console.error('Failed to create ticket:', err);
    }
};

const handleUpdateTicket = async (ticketData) => {
    try {
        const response = await axios.patch(`/api/tickets/${ticketData.id}`, ticketData);
        const index = tickets.value.findIndex(t => t.id === ticketData.id);
        if (index !== -1) {
            tickets.value[index] = response.data;
        }
        selectedTicket.value = response.data;
    } catch (err) {
        console.error('Failed to update ticket:', err);
    }
};

const handleRemoveTicket = async (ticketId) => {
    try {
        await axios.delete(`/api/tickets/${ticketId}`);
        tickets.value = tickets.value.filter(t => t.id !== parseInt(ticketId));
        pagination.value.total--;
        selectedTicket.value = null;
        relatedContent.value = null;
        relatedContentType.value = null;
    } catch (err) {
        console.error('Failed to delete ticket:', err);
    }
};

const handleTicketUpdated = () => {
    loadTickets();
    if (kanbanRef.value) {
        kanbanRef.value.loadTickets();
    }
};

const handleKanbanOpenTicket = (ticket) => {
    selectedTicket.value = ticket;
    editMode.value = false;
    isDrawerOpen.value = true;
};

const handleKanbanTicketUpdated = () => {
    loadTickets();
};

const loadTicketTypes = async () => {
    try {
        const response = await axios.get('/api/ticket-types');
        ticketTypes.value = [
            { id: null, name: t('tickets.filters.allTypes'), slug: null },
            ...response.data
        ];
    } catch (err) {
        console.error('Failed to load ticket types:', err);
    }
};

const applyFilters = () => {
    if (pagination.value.page === 1) {
        loadTickets();
    } else {
        pagination.value.page = 1;
    }
};

const debouncedApplyFilters = useDebounceFn(applyFilters, 300);

const resetFilters = () => {
    filters.value = {
        status: 'open',
        type: null,
        assigned_to: null,
        priority: null,
        search: '',
    };
    applyFilters();
};

watch(() => pagination.value.page, () => {
    loadTickets();
});

onMounted(() => {
    loadTickets();
    loadTicketTypes();
});
</script>

<template>
    <v-container fluid class="ticket-layout pa-0">
        <!-- Kanban View -->
        <template v-if="viewMode === 'kanban'">
            <div class="kanban-wrapper d-flex flex-column fill-height">
                <!-- Kanban Header -->
                <div class="kanban-header pa-4 d-flex align-center">
                    <div class="flex-grow-1">
                        <h3 class="text-h6 font-weight-bold">{{ pageTitle }}</h3>
                        <p class="text-caption text-medium-emphasis mb-0">{{ pageSubtitle }}</p>
                    </div>
                    <v-btn-toggle v-model="viewMode" mandatory density="compact" class="mr-2">
                        <v-btn value="list" size="small" :title="t('tickets.listView')">
                            <v-icon>mdi-view-list</v-icon>
                        </v-btn>
                        <v-btn value="kanban" size="small" :title="t('tickets.kanbanView')">
                            <v-icon>mdi-view-column</v-icon>
                        </v-btn>
                    </v-btn-toggle>
                    <v-btn
                        v-if="isAdmin"
                        icon="mdi-plus"
                        variant="tonal"
                        color="primary"
                        size="small"
                        @click="createNewTicket"
                        :title="t('tickets.createTicket')"
                    />
                </div>

                <v-divider />

                <!-- Kanban Board -->
                <TicketKanban
                    ref="kanbanRef"
                    class="flex-grow-1"
                    @open-ticket="handleKanbanOpenTicket"
                    @ticket-updated="handleKanbanTicketUpdated"
                />
            </div>
        </template>

        <!-- List View -->
        <v-row v-else no-gutters class="fill-height">
            <!-- Left Panel - Ticket List -->
            <v-col cols="12" md="4" lg="3" class="left-panel d-flex flex-column">
                <!-- Header -->
                <div class="left-panel-header pa-4">
                    <div class="d-flex align-center justify-space-between mb-2">
                        <div>
                            <h3 class="text-h6 font-weight-bold">{{ pageTitle }}</h3>
                            <p class="text-caption text-medium-emphasis mb-0">{{ pageSubtitle }}</p>
                        </div>
                        <div class="d-flex align-center ga-1">
                            <v-btn-toggle v-model="viewMode" mandatory density="compact">
                                <v-btn value="list" size="small" :title="t('tickets.listView')">
                                    <v-icon>mdi-view-list</v-icon>
                                </v-btn>
                                <v-btn value="kanban" size="small" :title="t('tickets.kanbanView')">
                                    <v-icon>mdi-view-column</v-icon>
                                </v-btn>
                            </v-btn-toggle>
                            <v-btn
                                v-if="isAdmin"
                                icon="mdi-plus"
                                variant="tonal"
                                color="primary"
                                size="small"
                                @click="createNewTicket"
                                :title="t('tickets.createTicket')"
                            />
                        </div>
                    </div>

                    <!-- Search -->
                    <v-text-field
                        v-model="filters.search"
                        :placeholder="t('tickets.filters.searchPlaceholder')"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                        class="mb-2"
                        @update:model-value="debouncedApplyFilters"
                    />

                    <!-- Status + Filter toggle row -->
                    <div class="d-flex align-center ga-2">
                        <v-select
                            v-model="filters.status"
                            :items="statusFilterOptions()"
                            item-title="label"
                            item-value="value"
                            variant="outlined"
                            density="compact"
                            hide-details
                            class="flex-grow-1"
                            @update:model-value="applyFilters"
                        />
                        <v-btn
                            :icon="showFilters ? 'mdi-filter-off' : 'mdi-filter-variant'"
                            variant="text"
                            density="compact"
                            size="small"
                            @click="showFilters = !showFilters"
                        >
                            <v-icon>{{ showFilters ? 'mdi-filter-off' : 'mdi-filter-variant' }}</v-icon>
                            <v-badge
                                v-if="activeFilterCount > 0 && !showFilters"
                                :content="activeFilterCount"
                                color="primary"
                                floating
                            />
                        </v-btn>
                    </div>

                    <!-- Expandable Filters -->
                    <v-expand-transition>
                        <div v-if="showFilters" class="mt-2">
                            <v-select
                                v-model="filters.priority"
                                :items="priorityFilterOptions()"
                                item-title="label"
                                item-value="value"
                                :label="t('tickets.fields.priority')"
                                variant="outlined"
                                density="compact"
                                hide-details
                                class="mb-2"
                                @update:model-value="applyFilters"
                            />
                            <v-select
                                v-model="filters.type"
                                :items="ticketTypes"
                                item-title="name"
                                item-value="slug"
                                :label="t('tickets.fields.type')"
                                variant="outlined"
                                density="compact"
                                hide-details
                                class="mb-2"
                                @update:model-value="applyFilters"
                            />
                            <v-select
                                v-if="isAdmin"
                                v-model="filters.assigned_to"
                                :items="assigneeFilterOptions"
                                item-title="label"
                                item-value="value"
                                :label="t('tickets.fields.assignee')"
                                variant="outlined"
                                density="compact"
                                hide-details
                                class="mb-2"
                                @update:model-value="applyFilters"
                            />
                            <v-btn
                                variant="text"
                                density="compact"
                                size="small"
                                color="secondary"
                                block
                                @click="resetFilters"
                            >
                                {{ t('tickets.reset') }}
                            </v-btn>
                        </div>
                    </v-expand-transition>
                </div>

                <v-divider />

                <!-- Ticket List -->
                <div class="ticket-list-items flex-grow-1 overflow-y-auto">
                    <v-progress-linear v-if="loading" indeterminate color="primary" />

                    <v-list v-if="tickets.length > 0" class="pa-0" density="compact">
                        <v-list-item
                            v-for="ticket in tickets"
                            :key="ticket.id"
                            :active="selectedTicket?.id === ticket.id"
                            class="ticket-list-item"
                            @click="selectTicket(ticket)"
                        >
                            <template #prepend>
                                <v-icon
                                    :color="getPriorityColor(ticket.priority)"
                                    size="small"
                                    class="mr-1"
                                >
                                    {{ getPriorityIcon(ticket.priority) }}
                                </v-icon>
                            </template>

                            <v-list-item-title class="text-body-2 font-weight-medium">
                                {{ ticket.title }}
                            </v-list-item-title>

                            <v-list-item-subtitle class="d-flex align-center ga-2 mt-1">
                                <v-chip
                                    size="x-small"
                                    :color="getStatusColor(ticket.status)"
                                >
                                    {{ ticket.status_label }}
                                </v-chip>
                                <v-chip
                                    v-if="ticket.ticket_type"
                                    size="x-small"
                                    :color="ticket.ticket_type.color"
                                    variant="outlined"
                                >
                                    {{ ticket.ticket_type.name }}
                                </v-chip>
                                <span class="text-caption text-medium-emphasis">#{{ ticket.id }}</span>
                            </v-list-item-subtitle>
                        </v-list-item>
                    </v-list>

                    <!-- Empty State -->
                    <div v-else-if="!loading" class="text-center pa-8">
                        <v-icon size="48" color="medium-emphasis" class="mb-3">
                            mdi-ticket-outline
                        </v-icon>
                        <h4 class="text-subtitle-1 mb-1">{{ t('tickets.noTickets') }}</h4>
                        <p class="text-caption text-medium-emphasis mb-3">
                            {{ filters.status ? t('tickets.noTicketsFilterHint') : t('tickets.noTicketsCreateHint') }}
                        </p>
                        <v-btn
                            v-if="isAdmin"
                            color="primary"
                            size="small"
                            prepend-icon="mdi-plus"
                            @click="createNewTicket"
                        >
                            {{ t('tickets.createTicket') }}
                        </v-btn>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.total > pagination.per_page" class="pa-2 border-t">
                    <v-pagination
                        v-model="pagination.page"
                        :length="pagination.last_page"
                        :total-visible="5"
                        density="compact"
                        rounded="circle"
                    />
                </div>
            </v-col>

            <!-- Right Panel - Related Content -->
            <v-col cols="12" md="8" lg="9" class="right-panel d-flex flex-column">
                <template v-if="selectedTicket?.id">
                    <!-- Content Header Bar -->
                    <div class="content-header pa-4 d-flex align-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-center ga-2 mb-1">
                                <v-chip
                                    v-if="selectedTicket.ticket_type"
                                    size="small"
                                    :color="selectedTicket.ticket_type.color"
                                >
                                    <v-icon start size="small">{{ selectedTicket.ticket_type.icon }}</v-icon>
                                    {{ selectedTicket.ticket_type.name }}
                                </v-chip>
                                <v-chip size="small" :color="getStatusColor(selectedTicket.status)">
                                    {{ selectedTicket.status_label }}
                                </v-chip>
                                <v-chip size="small" :color="getPriorityColor(selectedTicket.priority)">
                                    <v-icon start size="x-small">{{ getPriorityIcon(selectedTicket.priority) }}</v-icon>
                                    {{ selectedTicket.priority_label }}
                                </v-chip>
                                <span class="text-caption text-medium-emphasis">#{{ selectedTicket.id }}</span>
                            </div>
                            <h2 class="text-h6 font-weight-medium">{{ selectedTicket.title }}</h2>
                        </div>
                        <v-btn
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-information-outline"
                            @click="openDrawer"
                        >
                            {{ t('tickets.details') }}
                        </v-btn>
                    </div>

                    <v-divider />

                    <!-- Related Content Area -->
                    <div class="content-area flex-grow-1 overflow-y-auto pa-6">
                        <!-- Ticket Description -->
                        <div v-if="selectedTicket.description" class="mb-6">
                            <h3 class="text-subtitle-1 font-weight-medium mb-2">{{ t('tickets.fields.description') }}</h3>
                            <div class="description-content">{{ selectedTicket.description }}</div>
                        </div>

                        <!-- Related Model Content -->
                        <template v-if="selectedTicket.ticketable">
                            <v-divider v-if="selectedTicket.description" class="mb-6" />

                            <div class="d-flex align-center mb-4">
                                <v-icon class="mr-2" color="primary">
                                    {{ selectedTicket.ticketable_type === 'App\\Models\\Wiki' ? 'mdi-book-open-variant' : 'mdi-file-document-outline' }}
                                </v-icon>
                                <h3 class="text-subtitle-1 font-weight-medium">
                                    {{ t('tickets.sidebar.relatedTo') }}: {{ relatedContentTitle || selectedTicket.ticketable.title || selectedTicket.ticketable.slug }}
                                </h3>
                                <v-spacer />
                                <v-btn
                                    v-if="selectedTicket.ticketable_type === 'App\\Models\\Wiki'"
                                    :to="`/wiki/${selectedTicket.ticketable.slug}`"
                                    variant="text"
                                    size="small"
                                    color="primary"
                                    prepend-icon="mdi-open-in-new"
                                >
                                    {{ t('tickets.viewRelated') }}
                                </v-btn>
                                <v-btn
                                    v-else-if="selectedTicket.ticketable_type === 'App\\Models\\Page'"
                                    :to="`/pages/${selectedTicket.ticketable.slug}`"
                                    variant="text"
                                    size="small"
                                    color="primary"
                                    prepend-icon="mdi-open-in-new"
                                >
                                    {{ t('tickets.viewRelated') }}
                                </v-btn>
                            </div>

                            <!-- Loading State -->
                            <div v-if="relatedContentLoading" class="text-center py-8">
                                <v-progress-circular indeterminate color="primary" />
                            </div>

                            <!-- Content -->
                            <v-card v-else-if="relatedContentHtml" flat class="related-content-card">
                                <v-card-text>
                                    <div class="wiki-content-body" v-html="relatedContentHtml" />
                                </v-card-text>
                            </v-card>

                            <!-- No Content -->
                            <div v-else-if="!relatedContentLoading" class="text-medium-emphasis text-center py-4">
                                {{ t('tickets.noRelatedContent') }}
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Empty State (no ticket selected) -->
                <div v-else class="d-flex align-center justify-center fill-height">
                    <div class="text-center">
                        <v-icon size="64" color="medium-emphasis" class="mb-4">
                            mdi-ticket-outline
                        </v-icon>
                        <h3 class="text-h5 mb-2">{{ t('tickets.selectTicket') }}</h3>
                        <p class="text-body-1 text-medium-emphasis">
                            {{ t('tickets.selectTicketHint') }}
                        </p>
                    </div>
                </div>
            </v-col>
        </v-row>

        <!-- Ticket Detail Drawer -->
        <TicketSidebar
            v-model:is-drawer-open="isDrawerOpen"
            :edit-mode="editMode"
            :ticket="selectedTicket"
            @add-ticket="handleAddTicket"
            @update-ticket="handleUpdateTicket"
            @remove-ticket="handleRemoveTicket"
            @ticket-updated="handleTicketUpdated"
        />
    </v-container>
</template>

<style scoped>
.ticket-layout {
    height: 100%;
}

.ticket-layout :deep(.v-row) {
    height: 100%;
}

.left-panel {
    border-right: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    height: 100%;
    overflow: hidden;
}

.left-panel-header {
    flex-shrink: 0;
}

.ticket-list-items {
    min-height: 0;
}

.ticket-list-item {
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.06);
    cursor: pointer;
    transition: background-color 0.2s;
}

.ticket-list-item:hover {
    background-color: rgba(var(--v-theme-primary), 0.04);
}

.right-panel {
    height: 100%;
    overflow: hidden;
}

.content-header {
    flex-shrink: 0;
    border-bottom: none;
}

.content-area {
    min-height: 0;
}

.description-content {
    white-space: pre-line;
    line-height: 1.6;
}

.related-content-card {
    border-radius: 12px;
}

.border-t {
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}

.kanban-wrapper {
    height: 100%;
    overflow: hidden;
}

.kanban-header {
    flex-shrink: 0;
}
</style>
