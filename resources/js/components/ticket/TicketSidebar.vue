<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import UserAvatar from "../common/UserAvatar.vue";
import axios from "axios";
import ConfirmDialog from '../common/ConfirmDialog.vue';
import { useUserStore } from "@/store/userStore.js";
import { useDateFormat } from '@/plugins/formatDate.js';

const props = defineProps({
    isDrawerOpen: Boolean,
    editMode: Boolean,
    ticket: Object,
});

const emit = defineEmits([
    'update:isDrawerOpen',
    'addTicket',
    'updateTicket',
    'removeTicket',
    'ticketUpdated',
]);

const { t } = useI18n();
const userStore = useUserStore();
const { formatDate: formatDateUtil } = useDateFormat();

const showConfirmationDialog = ref(false);
const localEditMode = ref(props.editMode);
const refForm = ref();
const loadingTicketDetails = ref(false);

const localTicket = ref(null);
const ticketTypes = ref([]);
const ticketComments = ref([]);
const newComment = ref('');
const isInternalComment = ref(false);
const assignableUsers = ref([]);

// Status options
const statusOptions = [
    { value: 'open', label: 'Open', color: 'info' },
    { value: 'in_progress', label: 'In Progress', color: 'warning' },
    { value: 'pending', label: 'Pending', color: 'orange' },
    { value: 'resolved', label: 'Resolved', color: 'success' },
    { value: 'closed', label: 'Closed', color: 'grey' },
];

// Priority options
const priorityOptions = [
    { value: 'low', label: 'Low', color: 'grey', icon: 'mdi-arrow-down' },
    { value: 'normal', label: 'Normal', color: 'info', icon: 'mdi-minus' },
    { value: 'high', label: 'High', color: 'warning', icon: 'mdi-arrow-up' },
    { value: 'urgent', label: 'Urgent', color: 'error', icon: 'mdi-alert' },
];

const user = computed(() => userStore.user || { id: null });

const isAdmin = computed(() => user.value?.isAdmin || false);

const canEdit = computed(() => {
    if (!localTicket.value) return false;
    return isAdmin.value || localTicket.value.created_by_user_id === user.value.id;
});

const canComment = computed(() => {
    if (!localTicket.value) return false;
    return isAdmin.value || localTicket.value.created_by_user_id === user.value.id;
});

const getStatusColor = (status) => {
    return statusOptions.find(s => s.value === status)?.color || 'grey';
};

const getPriorityColor = (priority) => {
    return priorityOptions.find(p => p.value === priority)?.color || 'grey';
};

const getPriorityIcon = (priority) => {
    return priorityOptions.find(p => p.value === priority)?.icon || 'mdi-minus';
};

watch(
    () => props.ticket,
    (newTicket) => {
        localTicket.value = newTicket ? JSON.parse(JSON.stringify(newTicket)) : null;
        if (localTicket.value?.id) {
            getTicketDetails(localTicket.value.id);
        }
    },
    { immediate: true }
);

watch(() => props.editMode, () => {
    localEditMode.value = props.editMode;
});

const getTicketDetails = async (ticketId) => {
    try {
        loadingTicketDetails.value = true;
        const response = await axios.get(`/api/tickets/${ticketId}`);
        localTicket.value = response.data;
        ticketComments.value = response.data.comments || [];
    } catch (err) {
        console.error('Failed to load ticket details:', err);
    } finally {
        loadingTicketDetails.value = false;
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

const loadAssignableUsers = async () => {
    try {
        // This endpoint would need to be created to list users who can be assigned
        // For now, we'll leave it empty or you can implement it
        assignableUsers.value = [];
    } catch (err) {
        console.error('Failed to load assignable users:', err);
    }
};

const removeTicket = () => {
    emit('removeTicket', String(localTicket.value.id));
    emit('update:isDrawerOpen', false);
};

const handleSubmit = async () => {
    const valid = await refForm.value?.validate();
    if (valid.valid) {
        localEditMode.value = false;

        if (localTicket.value.id) {
            emit('updateTicket', localTicket.value);
        } else {
            emit('addTicket', localTicket.value);
        }

        emit('update:isDrawerOpen', false);
    }
};

const handleStatusChange = async (newStatus) => {
    if (!localTicket.value?.id) return;
    
    try {
        const response = await axios.patch(`/api/tickets/${localTicket.value.id}`, {
            status: newStatus
        });
        localTicket.value.status = newStatus;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to update status:', err);
    }
};

const handlePriorityChange = async (newPriority) => {
    if (!localTicket.value?.id) return;
    
    try {
        const response = await axios.patch(`/api/tickets/${localTicket.value.id}`, {
            priority: newPriority
        });
        localTicket.value.priority = newPriority;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to update priority:', err);
    }
};

const handleAssigneeChange = async (userId) => {
    if (!localTicket.value?.id || !isAdmin.value) return;
    
    try {
        if (userId) {
            await axios.post(`/api/tickets/${localTicket.value.id}/assign`, {
                user_id: userId
            });
        } else {
            await axios.post(`/api/tickets/${localTicket.value.id}/unassign`);
        }
        await getTicketDetails(localTicket.value.id);
        emit('ticketUpdated');
    } catch (err) {
        console.error('Failed to update assignee:', err);
    }
};

const addComment = async () => {
    if (!newComment.value.trim() || !localTicket.value?.id) return;

    try {
        const response = await axios.post(`/api/tickets/${localTicket.value.id}/comments`, {
            comment: newComment.value,
            is_internal: isInternalComment.value
        });
        
        ticketComments.value.push(response.data);
        newComment.value = '';
        isInternalComment.value = false;
    } catch (err) {
        console.error('Failed to add comment:', err);
    }
};

const deleteComment = async (commentId) => {
    try {
        await axios.delete(`/api/ticket-comments/${commentId}`);
        ticketComments.value = ticketComments.value.filter(c => c.id !== commentId);
    } catch (err) {
        console.error('Failed to delete comment:', err);
    }
};

const onCancel = () => {
    emit('update:isDrawerOpen', false);
};

const dialogModelValueUpdate = (val) => {
    emit('update:isDrawerOpen', val);
};

const handleConfirmation = (isConfirmed) => {
    if (isConfirmed) {
        removeTicket();
    }
};

const rules = {
    title: [v => !!v || 'Title is required'],
    ticket_type_id: [v => !!v || 'Type is required'],
};

onMounted(() => {
    loadTicketTypes();
    loadAssignableUsers();
});
</script>

<template>
    <VNavigationDrawer
        temporary
        location="end"
        :model-value="props.isDrawerOpen"
        width="420"
        class="ticket-drawer"
        @update:model-value="dialogModelValueUpdate"
    >
        <!-- Header Section -->
        <div class="ticket-drawer-header" :class="{ 'edit-mode': localEditMode }">
            <div v-if="localEditMode" class="d-flex align-center py-3 px-4">
                <h5 class="text-h5 font-weight-medium">
                    {{ localTicket?.id ? 'Edit Ticket' : 'Create Ticket' }}
                </h5>
                <VSpacer/>
                <VBtn
                    v-if="localTicket?.id"
                    color="primary"
                    variant="text"
                    class="me-2"
                    @click="localEditMode = !localEditMode"
                >
                    {{ localEditMode ? 'View' : 'Edit' }}
                </VBtn>
            </div>

            <div v-else class="d-flex align-center py-3 px-4">
                <div class="flex-grow-1">
                    <div class="d-flex align-center mb-1">
                        <v-chip
                            v-if="localTicket?.ticket_type"
                            size="small"
                            :color="localTicket.ticket_type.color"
                            class="mr-2"
                        >
                            <v-icon start size="small">{{ localTicket.ticket_type.icon }}</v-icon>
                            {{ localTicket.ticket_type.name }}
                        </v-chip>
                        <v-chip
                            size="small"
                            :color="getStatusColor(localTicket?.status)"
                        >
                            {{ localTicket?.status_label }}
                        </v-chip>
                    </div>
                    <h5 class="text-h6 font-weight-medium">{{ localTicket?.title }}</h5>
                </div>

                <VSpacer/>

                <div class="action-buttons">
                    <v-btn
                        v-if="canEdit"
                        icon="mdi-pencil"
                        variant="text"
                        color="primary"
                        density="comfortable"
                        class="action-btn"
                        @click="localEditMode = true"
                        title="Edit"
                    />

                    <v-btn
                        v-if="isAdmin"
                        icon="mdi-delete"
                        variant="text"
                        color="error"
                        density="comfortable"
                        class="action-btn"
                        @click="showConfirmationDialog = true"
                        title="Delete"
                    />

                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="comfortable"
                        class="action-btn"
                        @click="dialogModelValueUpdate(false)"
                        title="Close"
                    />
                </div>
            </div>
        </div>

        <VDivider/>

        <PerfectScrollbar :options="{ wheelPropagation: false }" class="ticket-drawer-content">
            <!-- Edit Mode Form -->
            <VCard flat class="px-2" v-if="localEditMode">
                <VCardText>
                    <VForm ref="refForm" @submit.prevent="handleSubmit">
                        <VRow>
                            <VCol cols="12">
                                <VSelect
                                    v-model="localTicket.ticket_type_id"
                                    label="Ticket Type"
                                    :items="ticketTypes"
                                    item-title="name"
                                    item-value="id"
                                    :rules="rules.ticket_type_id"
                                    variant="outlined"
                                    density="comfortable"
                                >
                                    <template #selection="{ item }">
                                        <div class="d-flex align-center">
                                            <v-icon :color="item.raw.color" class="me-2">
                                                {{ item.raw.icon }}
                                            </v-icon>
                                            <span>{{ item.raw.name }}</span>
                                        </div>
                                    </template>

                                    <template #item="{ item, props: itemProps }">
                                        <VListItem v-bind="itemProps">
                                            <template #prepend>
                                                <v-icon :color="item.raw.color">{{ item.raw.icon }}</v-icon>
                                            </template>
                                        </VListItem>
                                    </template>
                                </VSelect>
                            </VCol>

                            <VCol cols="12">
                                <VTextField
                                    v-model="localTicket.title"
                                    label="Title"
                                    :rules="rules.title"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-format-title"
                                />
                            </VCol>

                            <VCol cols="12">
                                <VTextarea
                                    v-model="localTicket.description"
                                    label="Description"
                                    variant="outlined"
                                    density="comfortable"
                                    rows="4"
                                    prepend-inner-icon="mdi-text-box-outline"
                                />
                            </VCol>

                            <VCol cols="12">
                                <VSelect
                                    v-model="localTicket.priority"
                                    label="Priority"
                                    :items="priorityOptions"
                                    item-title="label"
                                    item-value="value"
                                    variant="outlined"
                                    density="comfortable"
                                >
                                    <template #selection="{ item }">
                                        <div class="d-flex align-center">
                                            <v-icon :color="item.raw.color" class="me-2">
                                                {{ item.raw.icon }}
                                            </v-icon>
                                            <span>{{ item.raw.label }}</span>
                                        </div>
                                    </template>

                                    <template #item="{ item, props: itemProps }">
                                        <VListItem v-bind="itemProps">
                                            <template #prepend>
                                                <v-icon :color="item.raw.color">{{ item.raw.icon }}</v-icon>
                                            </template>
                                        </VListItem>
                                    </template>
                                </VSelect>
                            </VCol>

                            <VCol cols="12" class="d-flex justify-end">
                                <VBtn
                                    type="submit"
                                    color="primary"
                                    class="me-3"
                                >
                                    {{ localTicket?.id ? 'Update' : 'Create' }}
                                </VBtn>
                                <VBtn
                                    variant="outlined"
                                    color="secondary"
                                    @click="onCancel"
                                >
                                    Cancel
                                </VBtn>
                            </VCol>
                        </VRow>
                    </VForm>
                </VCardText>
            </VCard>

            <!-- View Mode Content -->
            <div v-else class="ticket-view-content">
                <!-- Ticket Properties -->
                <v-card flat class="ticket-info-card mb-4">
                    <v-card-text>
                        <!-- Priority -->
                        <div class="property-item mb-3">
                            <div class="property-label">Priority</div>
                            <v-select
                                v-if="isAdmin"
                                :model-value="localTicket?.priority"
                                @update:model-value="handlePriorityChange"
                                :items="priorityOptions"
                                item-title="label"
                                item-value="value"
                                density="compact"
                                variant="outlined"
                                hide-details
                            >
                                <template #selection="{ item }">
                                    <v-chip :color="item.raw.color" size="small">
                                        <v-icon start size="small">{{ item.raw.icon }}</v-icon>
                                        {{ item.raw.label }}
                                    </v-chip>
                                </template>
                            </v-select>
                            <v-chip v-else :color="getPriorityColor(localTicket?.priority)" size="small">
                                <v-icon start size="small">{{ getPriorityIcon(localTicket?.priority) }}</v-icon>
                                {{ localTicket?.priority_label }}
                            </v-chip>
                        </div>

                        <!-- Status -->
                        <div class="property-item mb-3">
                            <div class="property-label">Status</div>
                            <v-select
                                v-if="isAdmin"
                                :model-value="localTicket?.status"
                                @update:model-value="handleStatusChange"
                                :items="statusOptions"
                                item-title="label"
                                item-value="value"
                                density="compact"
                                variant="outlined"
                                hide-details
                            >
                                <template #selection="{ item }">
                                    <v-chip :color="item.raw.color" size="small">
                                        {{ item.raw.label }}
                                    </v-chip>
                                </template>
                            </v-select>
                            <v-chip v-else :color="getStatusColor(localTicket?.status)" size="small">
                                {{ localTicket?.status_label }}
                            </v-chip>
                        </div>

                        <!-- Assignee -->
                        <div class="property-item mb-3" v-if="isAdmin">
                            <div class="property-label">Assignee</div>
                            <div class="d-flex align-center">
                                <UserAvatar
                                    v-if="localTicket?.assignee"
                                    :user="localTicket.assignee"
                                    size="32"
                                    class="mr-2"
                                />
                                <span v-if="localTicket?.assignee">{{ localTicket.assignee.name }}</span>
                                <v-btn
                                    v-else
                                    variant="outlined"
                                    size="small"
                                    prepend-icon="mdi-account-plus"
                                >
                                    Assign
                                </v-btn>
                            </div>
                        </div>

                        <!-- Creator -->
                        <div class="property-item mb-3">
                            <div class="property-label">Reporter</div>
                            <div class="d-flex align-center">
                                <UserAvatar
                                    v-if="localTicket?.creator"
                                    :user="localTicket.creator"
                                    size="32"
                                    class="mr-2"
                                />
                                <span>{{ localTicket?.creator?.name || 'Unknown' }}</span>
                            </div>
                        </div>

                        <!-- Created Date -->
                        <div class="property-item">
                            <div class="property-label">Created</div>
                            <div class="property-value">
                                {{ formatDateUtil(localTicket?.created_at) }}
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Description -->
                <v-card flat class="description-card mb-4" v-if="localTicket?.description">
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-2">Description</h3>
                        <div class="description-content">{{ localTicket.description }}</div>
                    </v-card-text>
                </v-card>

                <!-- Related Content (Ticketable) -->
                <v-card flat class="related-card mb-4" v-if="localTicket?.ticketable">
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-2">Related To</h3>
                        <div class="d-flex align-center">
                            <v-icon class="mr-2">mdi-link-variant</v-icon>
                            <span>{{ localTicket.ticketable_type }} #{{ localTicket.ticketable_id }}</span>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Comments Section -->
                <v-card flat class="comments-card">
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-3">
                            Activity ({{ ticketComments.length }})
                        </h3>

                        <!-- Comment List -->
                        <div class="comments-list mb-4">
                            <div
                                v-for="comment in ticketComments"
                                :key="comment.id"
                                class="comment-item mb-3"
                                :class="{ 'internal-comment': comment.is_internal }"
                            >
                                <div class="d-flex">
                                    <UserAvatar
                                        :user="comment.user"
                                        size="32"
                                        class="mr-3"
                                    />
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-center justify-space-between mb-1">
                                            <div>
                                                <span class="font-weight-medium">{{ comment.user.name }}</span>
                                                <span class="text-caption text-medium-emphasis ml-2">
                                                    {{ formatDateUtil(comment.created_at) }}
                                                </span>
                                                <v-chip
                                                    v-if="comment.is_internal"
                                                    size="x-small"
                                                    color="warning"
                                                    class="ml-2"
                                                >
                                                    Internal
                                                </v-chip>
                                            </div>
                                            <v-btn
                                                v-if="isAdmin || comment.user_id === user.id"
                                                icon="mdi-delete"
                                                size="x-small"
                                                variant="text"
                                                @click="deleteComment(comment.id)"
                                            />
                                        </div>
                                        <div class="comment-text">{{ comment.comment }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="ticketComments.length === 0" class="text-center text-medium-emphasis py-4">
                                No comments yet
                            </div>
                        </div>

                        <!-- Add Comment -->
                        <div v-if="canComment" class="add-comment">
                            <VTextarea
                                v-model="newComment"
                                placeholder="Add a comment..."
                                variant="outlined"
                                density="compact"
                                rows="3"
                                hide-details
                                class="mb-2"
                            />
                            <div class="d-flex align-center justify-space-between">
                                <v-checkbox
                                    v-if="isAdmin"
                                    v-model="isInternalComment"
                                    label="Internal note"
                                    density="compact"
                                    hide-details
                                />
                                <VSpacer/>
                                <VBtn
                                    color="primary"
                                    size="small"
                                    @click="addComment"
                                    :disabled="!newComment.trim()"
                                >
                                    Comment
                                </VBtn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </div>
        </PerfectScrollbar>
    </VNavigationDrawer>

    <!-- Confirm Delete Dialog -->
    <ConfirmDialog
        v-model="showConfirmationDialog"
        title="Delete Ticket"
        content="Are you sure you want to delete this ticket? This action cannot be undone."
        confirmationText="Delete"
        cancellationText="Cancel"
        :resolve="handleConfirmation"
    />
</template>

<style scoped>
.ticket-drawer {
    max-height: 100%;
    border-left: 1px solid rgba(0, 0, 0, 0.12);
}

.ticket-drawer-header {
    position: sticky;
    top: 0;
    z-index: 10;
}

.ticket-drawer-content {
    height: calc(100vh - 65px);
}

.action-buttons {
    display: flex;
    align-items: center;
}

.action-btn {
    margin-left: 4px;
}

.ticket-view-content {
    padding: 16px;
}

.ticket-info-card,
.description-card,
.related-card,
.comments-card {
    border-radius: 12px;
    overflow: hidden;
}

.property-item {
    padding: 8px 0;
}

.property-label {
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    opacity: 0.7;
}

.property-value {
    font-size: 0.875rem;
}

.description-content {
    white-space: pre-line;
    line-height: 1.6;
}

.comments-list {
    max-height: 400px;
    overflow-y: auto;
}

.comment-item {
    padding: 12px;
    border-radius: 8px;
    transition: background-color 0.2s;
}

.comment-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.comment-item.internal-comment {
    background-color: rgba(255, 193, 7, 0.05);
    border-left: 3px solid rgb(var(--v-theme-warning));
}

.comment-text {
    font-size: 0.875rem;
    line-height: 1.5;
    white-space: pre-line;
}

.add-comment {
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    padding-top: 16px;
}
</style>
