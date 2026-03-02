<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import UserAvatar from "../common/UserAvatar.vue";
import axios from "axios";
import ConfirmDialog from '../common/ConfirmDialog.vue';
import { useUserStore } from "@/store/userStore.js";
import { useDateFormat } from '@/plugins/formatDate.js';
import { useRouter } from 'vue-router';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';

const router = useRouter();

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
const {
    statusOptions,
    priorityOptions,
    getStatusColor,
    getPriorityColor,
    getPriorityIcon,
    getStatusLabel,
    getPriorityLabel,
    statusFilterOptions,
    priorityFilterOptions,
} = useTicketHelpers();

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
const isApprovable = ref(false);
const isApproved = ref(false);
const approving = ref(false);

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

const statusSelectOptions = computed(() => statusFilterOptions(false));
const prioritySelectOptions = computed(() => priorityFilterOptions(false));

const ticketableLink = computed(() => {
    const t = localTicket.value?.ticketable;
    if (!t) return null;
    const type = localTicket.value.ticketable_type;

    if (type === 'App\\Models\\Wiki') {
        return { label: t.title || t.slug, icon: 'mdi-book-open-variant', to: `/wiki/${t.slug}` };
    }
    if (type === 'App\\Models\\Page') {
        return { label: t.title || t.slug, icon: 'mdi-file-document-outline', to: `/pages/${t.slug}` };
    }
    // Fallback for unknown types
    const modelName = type ? type.split('\\').pop() : 'Item';
    return { label: `${modelName} #${localTicket.value.ticketable_id}`, icon: 'mdi-link-variant', to: null };
});

const getTicketDetails = async (ticketId) => {
    try {
        loadingTicketDetails.value = true;
        const response = await axios.get(`/api/tickets/${ticketId}`);
        localTicket.value = response.data;
        ticketComments.value = response.data.comments || [];
        isApprovable.value = response.data.is_approvable || false;
        isApproved.value = response.data.is_approved || false;
    } catch (err) {
        console.error('Failed to load ticket details:', err);
    } finally {
        loadingTicketDetails.value = false;
    }
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
        const response = await axios.post('/api/users/search', { query: '' });
        const records = Array.isArray(response.data) ? response.data : [];
        const mapped = records.map(u => ({
            id: u.id,
            name: u.username,
            username: u.username,
            avatar: u.avatar,
            initials: u.initials,
        }));

        // The search endpoint excludes the current user, so add them back
        if (user.value?.id) {
            mapped.unshift({
                id: user.value.id,
                name: user.value.username || user.value.name,
                username: user.value.username || user.value.name,
                avatar: user.value.avatar,
                initials: user.value.initials,
            });
        }

        assignableUsers.value = mapped;
    } catch (err) {
        console.error('Failed to load assignable users:', err);
    }
};

const assignToMe = async () => {
    if (!localTicket.value?.id || !user.value?.id) return;
    await handleAssigneeChange(user.value.id);
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

const approveTicket = async () => {
    if (!localTicket.value?.id) return;
    try {
        approving.value = true;
        const response = await axios.post(`/api/tickets/${localTicket.value.id}/approve`);
        localTicket.value = { ...localTicket.value, ...response.data };
        isApproved.value = true;
        ticketComments.value = response.data.comments || ticketComments.value;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to approve:', err);
    } finally {
        approving.value = false;
    }
};

const rejectTicket = async () => {
    if (!localTicket.value?.id) return;
    try {
        approving.value = true;
        const response = await axios.post(`/api/tickets/${localTicket.value.id}/reject`);
        localTicket.value = { ...localTicket.value, ...response.data };
        isApproved.value = false;
        ticketComments.value = response.data.comments || ticketComments.value;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to reject:', err);
    } finally {
        approving.value = false;
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
    title: [v => !!v || t('tickets.validation.titleRequired')],
    ticket_type_id: [v => !!v || t('tickets.validation.typeRequired')],
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
                    {{ localTicket?.id ? t('tickets.editTicket') : t('tickets.createTicket') }}
                </h5>
                <VSpacer/>
                <VBtn
                    v-if="localTicket?.id"
                    color="primary"
                    variant="text"
                    class="me-2"
                    @click="localEditMode = !localEditMode"
                >
                    {{ localEditMode ? t('tickets.view') : t('tickets.edit') }}
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
                        :title="t('tickets.edit')"
                    />

                    <v-btn
                        v-if="isAdmin"
                        icon="mdi-delete"
                        variant="text"
                        color="error"
                        density="comfortable"
                        class="action-btn"
                        @click="showConfirmationDialog = true"
                        :title="t('tickets.delete')"
                    />

                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="comfortable"
                        class="action-btn"
                        @click="dialogModelValueUpdate(false)"
                        :title="t('tickets.close')"
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
                                    :label="t('tickets.fields.ticketType')"
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
                                    :label="t('tickets.fields.title')"
                                    :rules="rules.title"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-format-title"
                                />
                            </VCol>

                            <VCol cols="12">
                                <VTextarea
                                    v-model="localTicket.description"
                                    :label="t('tickets.fields.description')"
                                    variant="outlined"
                                    density="comfortable"
                                    rows="4"
                                    prepend-inner-icon="mdi-text-box-outline"
                                />
                            </VCol>

                            <VCol cols="12">
                                <VSelect
                                    v-model="localTicket.priority"
                                    :label="t('tickets.fields.priority')"
                                    :items="prioritySelectOptions"
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
                                    {{ localTicket?.id ? t('tickets.update') : t('tickets.create') }}
                                </VBtn>
                                <VBtn
                                    variant="outlined"
                                    color="secondary"
                                    @click="onCancel"
                                >
                                    {{ t('tickets.cancel') }}
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
                            <div class="property-label">{{ t('tickets.fields.priority') }}</div>
                            <v-select
                                v-if="isAdmin"
                                :model-value="localTicket?.priority"
                                @update:model-value="handlePriorityChange"
                                :items="prioritySelectOptions"
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
                            <div class="property-label">{{ t('tickets.fields.status') }}</div>
                            <v-select
                                v-if="isAdmin"
                                :model-value="localTicket?.status"
                                @update:model-value="handleStatusChange"
                                :items="statusSelectOptions"
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
                            <div class="d-flex align-center justify-space-between">
                                <div class="property-label">{{ t('tickets.fields.assignee') }}</div>
                                <v-btn
                                    v-if="localTicket?.assigned_to_user_id !== user.id"
                                    variant="text"
                                    density="compact"
                                    size="small"
                                    color="primary"
                                    class="text-caption pa-0"
                                    style="min-width: auto; text-transform: none;"
                                    @click="assignToMe"
                                >
                                    {{ t('tickets.assignToMe') }}
                                </v-btn>
                            </div>
                            <v-select
                                :model-value="localTicket?.assigned_to_user_id"
                                @update:model-value="handleAssigneeChange"
                                :items="assignableUsers"
                                item-title="name"
                                item-value="id"
                                density="compact"
                                variant="outlined"
                                hide-details
                                clearable
                                :placeholder="t('tickets.assign')"
                            >
                                <template #selection="{ item }">
                                    <div class="d-flex align-center">
                                        <UserAvatar
                                            :user="item.raw"
                                            size="24"
                                            class="mr-2"
                                        />
                                        <span>{{ item.raw.name }}</span>
                                    </div>
                                </template>

                                <template #item="{ item, props: itemProps }">
                                    <VListItem v-bind="itemProps">
                                        <template #prepend>
                                            <UserAvatar
                                                :user="item.raw"
                                                size="28"
                                                class="mr-2"
                                            />
                                        </template>
                                    </VListItem>
                                </template>
                            </v-select>
                        </div>

                        <!-- Creator -->
                        <div class="property-item mb-3">
                            <div class="property-label">{{ t('tickets.fields.reporter') }}</div>
                            <div class="d-flex align-center">
                                <UserAvatar
                                    v-if="localTicket?.creator"
                                    :user="localTicket.creator"
                                    size="32"
                                    class="mr-2"
                                />
                                <span>{{ localTicket?.creator?.name || t('tickets.unknown') }}</span>
                            </div>
                        </div>

                        <!-- Created Date -->
                        <div class="property-item">
                            <div class="property-label">{{ t('tickets.fields.created') }}</div>
                            <div class="property-value">
                                {{ formatDateUtil(localTicket?.created_at) }}
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Description -->
                <v-card flat class="description-card mb-4" v-if="localTicket?.description">
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-2">{{ t('tickets.fields.description') }}</h3>
                        <div class="description-content">{{ localTicket.description }}</div>
                    </v-card-text>
                </v-card>

                <!-- Related Content (Ticketable) -->
                <v-card flat class="related-card mb-4" v-if="localTicket?.ticketable && ticketableLink">
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-2">{{ t('tickets.sidebar.relatedTo') }}</h3>
                        <router-link
                            v-if="ticketableLink.to"
                            :to="ticketableLink.to"
                            class="d-flex align-center text-decoration-none related-link"
                        >
                            <v-icon class="mr-2" color="primary">{{ ticketableLink.icon }}</v-icon>
                            <span class="text-primary">{{ ticketableLink.label }}</span>
                            <v-icon size="small" class="ml-1" color="primary">mdi-open-in-new</v-icon>
                        </router-link>
                        <div v-else class="d-flex align-center">
                            <v-icon class="mr-2">{{ ticketableLink.icon }}</v-icon>
                            <span>{{ ticketableLink.label }}</span>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Approval Section -->
                <v-card flat class="approval-card mb-4" v-if="isApprovable && isAdmin">
                    <v-card-text>
                        <v-alert
                            v-if="!isApproved"
                            type="warning"
                            variant="tonal"
                            density="compact"
                            class="mb-3"
                        >
                            {{ t('tickets.sidebar.pendingApproval') }}
                        </v-alert>
                        <v-alert
                            v-else
                            type="success"
                            variant="tonal"
                            density="compact"
                            class="mb-3"
                        >
                            {{ t('tickets.sidebar.approved') }}
                        </v-alert>

                        <div class="d-flex ga-2">
                            <template v-if="!isApproved">
                                <v-btn
                                    color="success"
                                    variant="flat"
                                    size="small"
                                    :loading="approving"
                                    @click="approveTicket"
                                >
                                    <v-icon start>mdi-check</v-icon>
                                    {{ t('tickets.sidebar.approve') }}
                                </v-btn>
                                <v-btn
                                    color="error"
                                    variant="outlined"
                                    size="small"
                                    :loading="approving"
                                    @click="rejectTicket"
                                >
                                    <v-icon start>mdi-close</v-icon>
                                    {{ t('tickets.sidebar.reject') }}
                                </v-btn>
                            </template>
                            <v-btn
                                v-else
                                color="warning"
                                variant="outlined"
                                size="small"
                                :loading="approving"
                                @click="rejectTicket"
                            >
                                <v-icon start>mdi-undo</v-icon>
                                {{ t('tickets.sidebar.revokeApproval') }}
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Comments Section -->
                <v-card flat class="comments-card">
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-3">
                            {{ t('tickets.sidebar.activity', { count: ticketComments.length }) }}
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
                                                    {{ t('tickets.internal') }}
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
                                {{ t('tickets.noComments') }}
                            </div>
                        </div>

                        <!-- Add Comment -->
                        <div v-if="canComment" class="add-comment">
                            <VTextarea
                                v-model="newComment"
                                :placeholder="t('tickets.sidebar.addComment')"
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
                                    :label="t('tickets.sidebar.internalNote')"
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
                                    {{ t('tickets.comment') }}
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
        :title="t('tickets.confirm.deleteTitle')"
        :content="t('tickets.confirm.deleteMessage')"
        :confirmationText="t('tickets.confirm.deleteConfirm')"
        :cancellationText="t('tickets.confirm.deleteCancel')"
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
.approval-card,
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

.related-link {
    padding: 8px 12px;
    border-radius: 8px;
    transition: background-color 0.2s;
}

.related-link:hover {
    background-color: rgba(var(--v-theme-primary), 0.08);
}
</style>
