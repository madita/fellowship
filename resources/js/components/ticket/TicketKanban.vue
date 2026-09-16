<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';
import axios from 'axios';
import UserAvatar from '../common/UserAvatar.vue';
import { useUserStore } from '@/store/userStore.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { useDialog } from '@/composables/useDialog.js';

const { t } = useI18n();
const userStore = useUserStore();
// Failures of the drag & drop status change are reported as a modal
const dialog = useDialog();
const {
    statusOptions,
    getStatusColor,
    getStatusLabel,
    getPriorityColor,
    getPriorityIcon,
} = useTicketHelpers();

const emit = defineEmits(['open-ticket', 'ticket-updated']);

const loading = ref(false);
// Ids of tickets whose status change is being saved
const updatingIds = ref([]);
const columns = ref([]);

const user = computed(() => userStore.user || { id: null });
const isAdmin = computed(() => user.value?.isAdmin || false);

const initColumns = () => {
    columns.value = statusOptions.map(s => ({
        status: s.value,
        label: getStatusLabel(s.value),
        color: s.color,
        tickets: [],
    }));
};

const loadTickets = async () => {
    loading.value = true;
    try {
        initColumns();
        let page = 1;
        let lastPage = 1;

        do {
            const response = await axios.get('/api/tickets', {
                params: { per_page: 200, page }
            });

            for (const ticket of response.data.data) {
                const col = columns.value.find(c => c.status === ticket.status);
                if (col) {
                    col.tickets.push(ticket);
                }
            }

            lastPage = response.data.last_page ?? 1;
            page++;
        } while (page <= lastPage);
    } catch (err) {
        console.error('Failed to load tickets:', err);
        await dialog.requestError(err, t('tickets.messages.loadFailed'));
    } finally {
        loading.value = false;
    }
};

const onDragChange = async (evt, targetStatus) => {
    if (!evt.added) return;

    const ticket = evt.added.element;
    const oldStatus = ticket.status;

    if (oldStatus === targetStatus || updatingIds.value.includes(ticket.id)) return;

    updatingIds.value = [...updatingIds.value, ticket.id];
    try {
        // Update status
        await axios.patch(`/api/tickets/${ticket.id}`, {
            status: targetStatus
        });
        ticket.status = targetStatus;

        // Auto-assign to current user if unassigned
        if (!ticket.assigned_to_user_id && user.value?.id) {
            await axios.post(`/api/tickets/${ticket.id}/assign`, {
                user_id: user.value.id
            });
            // Refresh ticket data to get assignee info
            const response = await axios.get(`/api/tickets/${ticket.id}`);
            Object.assign(ticket, response.data);
        }

        emit('ticket-updated');
    } catch (err) {
        console.error('Failed to update ticket:', err);
        await dialog.requestError(err, t('tickets.messages.updateFailed'));
        // Put the card back where the server has it
        loadTickets();
    } finally {
        updatingIds.value = updatingIds.value.filter(id => id !== ticket.id);
    }
};

const openTicket = (ticket) => {
    emit('open-ticket', ticket);
};

defineExpose({ loadTickets });

onMounted(() => {
    loadTickets();
});
</script>

<template>
    <div class="kanban-board">
        <v-progress-linear v-if="loading || updatingIds.length > 0" indeterminate color="primary" />

        <div class="kanban-columns d-flex ga-3 pa-4 flex-grow-1 overflow-x-auto">
            <div
                v-for="col in columns"
                :key="col.status"
                class="kanban-column d-flex flex-column flex-shrink-0 rounded-lg"
            >
                <!-- Column Header -->
                <div class="flex-shrink-0 d-flex align-center pa-3 border-b">
                    <v-chip
                        :color="col.color"
                        size="small"
                        variant="tonal"
                        label
                    >
                        {{ col.label }}
                    </v-chip>
                    <span class="text-caption text-medium-emphasis ml-2">
                        {{ col.tickets.length }}
                    </span>
                </div>

                <!-- Draggable Cards -->
                <draggable
                    v-model="col.tickets"
                    group="tickets"
                    item-key="id"
                    class="kanban-column-body pa-2"
                    ghost-class="kanban-ghost"
                    drag-class="kanban-drag"
                    @change="(evt) => onDragChange(evt, col.status)"
                >
                    <template #item="{ element }">
                        <v-card
                            class="kanban-card mb-2"
                            :class="{ 'kanban-card--busy': updatingIds.includes(element.id) }"
                            variant="outlined"
                            rounded="lg"
                            role="button"
                            tabindex="0"
                            :aria-label="t('tickets.openTicket', { title: element.title })"
                            :loading="updatingIds.includes(element.id)"
                            @click="openTicket(element)"
                            @keydown.enter="openTicket(element)"
                            @keydown.space.prevent="openTicket(element)"
                        >
                            <v-card-text class="pa-3">
                                <!-- Title row -->
                                <div class="d-flex align-start mb-2">
                                    <v-icon
                                        :color="getPriorityColor(element.priority)"
                                        size="small"
                                        class="mr-2 mt-1 flex-shrink-0"
                                    >
                                        {{ getPriorityIcon(element.priority) }}
                                    </v-icon>
                                    <div class="text-body-2 font-weight-medium kanban-card-title">
                                        {{ element.title }}
                                    </div>
                                </div>

                                <!-- Meta row -->
                                <div class="d-flex align-center justify-space-between">
                                    <div class="d-flex align-center ga-1">
                                        <span class="text-caption text-medium-emphasis">#{{ element.id }}</span>
                                        <v-chip
                                            v-if="element.ticket_type"
                                            size="small"
                                            variant="tonal"
                                            :color="element.ticket_type.color"
                                        >
                                            {{ element.ticket_type.name }}
                                        </v-chip>
                                    </div>

                                    <!-- Assignee -->
                                    <UserAvatar
                                        v-if="element.assignee"
                                        :user="element.assignee"
                                        size="24"
                                    />
                                    <v-icon
                                        v-else
                                        size="20"
                                        color="medium-emphasis"
                                    >
                                        mdi-account-outline
                                    </v-icon>
                                </div>
                            </v-card-text>
                        </v-card>
                    </template>
                </draggable>
            </div>
        </div>
    </div>
</template>

<style scoped>
.kanban-board {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.kanban-columns {
    min-height: 0;
}

.kanban-column {
    width: 260px;
    min-width: 260px;
    background-color: rgba(var(--v-theme-on-surface), 0.03);
    max-height: 100%;
}

.kanban-column-body {
    flex-grow: 1;
    overflow-y: auto;
    min-height: 60px;
}

.kanban-card {
    cursor: grab;
    transition: box-shadow 0.2s, transform 0.1s;
}

.kanban-card:hover {
    box-shadow: 0 2px 8px rgba(var(--v-theme-on-surface), 0.1);
}

.kanban-card:active {
    cursor: grabbing;
}

.kanban-card--busy {
    opacity: 0.6;
    pointer-events: none;
}

.kanban-card-title {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}

.kanban-ghost {
    opacity: 0.4;
    border: 2px dashed rgba(var(--v-theme-primary), 0.5) !important;
}

.kanban-drag {
    transform: rotate(2deg);
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.15);
}
</style>
