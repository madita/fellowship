<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import TicketSidebar from './TicketSidebar.vue';
import UserAvatar from '../common/UserAvatar.vue';
import { useUserStore } from '@/store/userStore.js';
import { useDateFormat } from '@/plugins/formatDate.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';

const { t } = useI18n();
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
const selectedTicket = ref(null);
const isDrawerOpen = ref(false);
const editMode = ref(false);

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

const assigneeFilterOptions = computed(() => [
    { value: null, label: t('tickets.filters.allAssignees') },
    { value: 'me', label: t('tickets.filters.assignedToMe') },
    { value: 'unassigned', label: t('tickets.filters.unassigned') },
]);

const headers = computed(() => [
    { text: t('tickets.fields.id'), value: 'id', width: '80px' },
    { text: t('tickets.fields.type'), value: 'ticket_type', width: '150px' },
    { text: t('tickets.fields.title'), value: 'title', sortable: true },
    { text: t('tickets.fields.status'), value: 'status', width: '120px' },
    { text: t('tickets.fields.priority'), value: 'priority', width: '120px' },
    { text: t('tickets.fields.reporter'), value: 'creator', width: '150px' },
    { text: t('tickets.fields.assignee'), value: 'assignee', width: '150px' },
    { text: t('tickets.fields.created'), value: 'created_at', width: '150px', sortable: true },
]);

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

const openTicket = (event, { item }) => {
    selectedTicket.value = item;
    editMode.value = false;
    isDrawerOpen.value = true;
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
    } catch (err) {
        console.error('Failed to update ticket:', err);
    }
};

const handleRemoveTicket = async (ticketId) => {
    try {
        await axios.delete(`/api/tickets/${ticketId}`);
        tickets.value = tickets.value.filter(t => t.id !== parseInt(ticketId));
        pagination.value.total--;
    } catch (err) {
        console.error('Failed to delete ticket:', err);
    }
};

const handleTicketUpdated = () => {
    loadTickets();
};

const applyFilters = () => {
    pagination.value.page = 1;
    loadTickets();
};

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
    <div class="ticket-list-container">
        <!-- Header -->
        <div class="ticket-list-header pa-4">
            <div class="d-flex align-center justify-space-between mb-4">
                <div>
                    <h1 class="text-h4 font-weight-bold">{{ t('tickets.title') }}</h1>
                    <p class="text-subtitle-1 text-medium-emphasis">
                        {{ t('tickets.subtitle') }}
                    </p>
                </div>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    size="large"
                    @click="createNewTicket"
                >
                    {{ t('tickets.createTicket') }}
                </v-btn>
            </div>

            <!-- Filters -->
            <v-card flat class="filter-card">
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="filters.search"
                                :placeholder="t('tickets.filters.searchPlaceholder')"
                                prepend-inner-icon="mdi-magnify"
                                variant="outlined"
                                density="compact"
                                hide-details
                                clearable
                                @update:model-value="applyFilters"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-select
                                v-model="filters.status"
                                :items="statusFilterOptions()"
                                item-title="label"
                                item-value="value"
                                :label="t('tickets.fields.status')"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-select
                                v-model="filters.type"
                                :items="ticketTypes"
                                item-title="name"
                                item-value="slug"
                                :label="t('tickets.fields.type')"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-select
                                v-model="filters.priority"
                                :items="priorityFilterOptions()"
                                item-title="label"
                                item-value="value"
                                :label="t('tickets.fields.priority')"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>

                        <v-col cols="12" md="2" v-if="isAdmin">
                            <v-select
                                v-model="filters.assigned_to"
                                :items="assigneeFilterOptions"
                                item-title="label"
                                item-value="value"
                                :label="t('tickets.fields.assignee')"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>

                        <v-col cols="12" md="1">
                            <v-btn
                                variant="outlined"
                                color="secondary"
                                block
                                @click="resetFilters"
                            >
                                {{ t('tickets.reset') }}
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </div>

        <!-- Tickets Table -->
        <v-card flat>
            <v-data-table
                :headers="headers"
                :items="tickets"
                :loading="loading"
                :items-per-page="pagination.per_page"
                hide-default-footer
                class="tickets-table"
                @click:row="openTicket"
            >
                <template #item.id="{ item }">
                    <span class="text-caption font-weight-medium">#{{ item.id }}</span>
                </template>

                <template #item.ticket_type="{ item }">
                    <v-chip
                        v-if="item.ticket_type"
                        size="small"
                        :color="item.ticket_type.color"
                    >
                        <v-icon start size="small">{{ item.ticket_type.icon }}</v-icon>
                        {{ item.ticket_type.name }}
                    </v-chip>
                </template>

                <template #item.title="{ item }">
                    <div class="ticket-title">
                        <div class="font-weight-medium">{{ item.title }}</div>
                        <div v-if="item.description" class="text-caption text-medium-emphasis text-truncate">
                            {{ item.description.substring(0, 60) }}...
                        </div>
                    </div>
                </template>

                <template #item.status="{ item }">
                    <v-chip
                        size="small"
                        :color="getStatusColor(item.status)"
                    >
                        {{ item.status_label }}
                    </v-chip>
                </template>

                <template #item.priority="{ item }">
                    <v-chip
                        size="small"
                        :color="getPriorityColor(item.priority)"
                    >
                        <v-icon start size="x-small">{{ getPriorityIcon(item.priority) }}</v-icon>
                        {{ item.priority_label }}
                    </v-chip>
                </template>

                <template #item.creator="{ item }">
                    <div class="d-flex align-center" v-if="item.creator">
                        <UserAvatar
                            :user="item.creator"
                            size="28"
                            class="mr-2"
                        />
                        <span class="text-caption">{{ item.creator.name }}</span>
                    </div>
                </template>

                <template #item.assignee="{ item }">
                    <div class="d-flex align-center" v-if="item.assignee">
                        <UserAvatar
                            :user="item.assignee"
                            size="28"
                            class="mr-2"
                        />
                        <span class="text-caption">{{ item.assignee.name }}</span>
                    </div>
                    <span v-else class="text-caption text-medium-emphasis">{{ t('tickets.unassigned') }}</span>
                </template>

                <template #item.created_at="{ item }">
                    <span class="text-caption">{{ formatDateUtil(item.created_at) }}</span>
                </template>

                <template #no-data>
                    <div class="text-center pa-8">
                        <v-icon size="64" color="grey-lighten-2" class="mb-4">
                            mdi-ticket-outline
                        </v-icon>
                        <h3 class="text-h6 mb-2">{{ t('tickets.noTickets') }}</h3>
                        <p class="text-body-2 text-medium-emphasis mb-4">
                            {{ filters.status ? t('tickets.noTicketsFilterHint') : t('tickets.noTicketsCreateHint') }}
                        </p>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-plus"
                            @click="createNewTicket"
                        >
                            {{ t('tickets.createTicket') }}
                        </v-btn>
                    </div>
                </template>
            </v-data-table>

            <!-- Pagination -->
            <v-card-actions class="justify-center pa-4" v-if="pagination.total > pagination.per_page">
                <v-pagination
                    v-model="pagination.page"
                    :length="pagination.last_page"
                    :total-visible="7"
                    rounded="circle"
                />
            </v-card-actions>
        </v-card>

        <!-- Ticket Sidebar -->
        <TicketSidebar
            v-model:is-drawer-open="isDrawerOpen"
            :edit-mode="editMode"
            :ticket="selectedTicket"
            @add-ticket="handleAddTicket"
            @update-ticket="handleUpdateTicket"
            @remove-ticket="handleRemoveTicket"
            @ticket-updated="handleTicketUpdated"
        />
    </div>
</template>

<style scoped>
.ticket-list-container {
    height: 100%;
    overflow-y: auto;
}

.ticket-list-header {
    background-color: rgb(var(--v-theme-surface));
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}

.filter-card {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 12px;
}

.tickets-table :deep(tbody tr) {
    cursor: pointer;
    transition: background-color 0.2s;
}

.tickets-table :deep(tbody tr:hover) {
    background-color: rgba(var(--v-theme-primary), 0.04);
}

.ticket-title {
    max-width: 300px;
}

.ticket-title .text-truncate {
    max-width: 100%;
}
</style>
