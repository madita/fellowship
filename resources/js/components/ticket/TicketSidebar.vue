<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import UserAvatar from "../common/UserAvatar.vue";
import EmptyState from '@/components/common/EmptyState.vue';
import axios from "axios";
import { useUserStore } from "@/store/userStore.js";
import { useDateFormat } from '@/plugins/formatDate.js';
import { useRouter } from 'vue-router';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { useDialog } from '@/composables/useDialog.js';

const router = useRouter();
// Confirmations and failures of the actions are modal
const dialog = useDialog();

const props = defineProps({
    isDrawerOpen: Boolean,
    editMode: Boolean,
    ticket: Object,
    // The parent's create / update / delete request is in flight
    saving: { type: Boolean, default: false },
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
const approving = ref(null);
// Which property (status / priority / assignee) is being saved
const updatingField = ref(null);
const addingComment = ref(false);
const deletingCommentId = ref(null);

const latestTicketRequestId = ref(0);

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
    const requestId = ++latestTicketRequestId.value;
    try {
        loadingTicketDetails.value = true;
        const response = await axios.get(`/api/tickets/${ticketId}`);
        if (requestId !== latestTicketRequestId.value) return;
        localTicket.value = response.data;
        ticketComments.value = response.data.comments || [];
        isApprovable.value = response.data.is_approvable || false;
        isApproved.value = response.data.is_approved || false;
    } catch (err) {
        if (requestId !== latestTicketRequestId.value) return;
        console.error('Failed to load ticket details:', err);
        await dialog.requestError(err, t('tickets.messages.loadFailed'));
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

// The drawer is closed by the parent once a save succeeded; leave the
// edit mode the pencil button switched on locally.
watch(() => props.isDrawerOpen, (open) => {
    if (!open) localEditMode.value = props.editMode;
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

// The parent performs the request and closes the drawer on success.
const removeTicket = async () => {
    if (props.saving) return;
    const confirmed = await dialog.confirmDelete(t('tickets.confirm.deleteMessage'), {
        title: t('tickets.confirm.deleteTitle'),
        confirmationText: t('tickets.confirm.deleteConfirm'),
        cancellationText: t('tickets.confirm.deleteCancel'),
    });
    if (!confirmed) return;
    emit('removeTicket', String(localTicket.value.id));
};

const handleSubmit = async () => {
    if (props.saving) return;
    const valid = await refForm.value?.validate();
    if (valid.valid) {
        if (localTicket.value.id) {
            emit('updateTicket', localTicket.value);
        } else {
            emit('addTicket', localTicket.value);
        }
    }
};

const handleStatusChange = async (newStatus) => {
    if (!localTicket.value?.id || updatingField.value) return;

    updatingField.value = 'status';
    try {
        const response = await axios.patch(`/api/tickets/${localTicket.value.id}`, {
            status: newStatus
        });
        localTicket.value.status = newStatus;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to update status:', err);
        await dialog.requestError(err, t('tickets.messages.updateFailed'));
    } finally {
        updatingField.value = null;
    }
};

const handlePriorityChange = async (newPriority) => {
    if (!localTicket.value?.id || updatingField.value) return;

    updatingField.value = 'priority';
    try {
        const response = await axios.patch(`/api/tickets/${localTicket.value.id}`, {
            priority: newPriority
        });
        localTicket.value.priority = newPriority;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to update priority:', err);
        await dialog.requestError(err, t('tickets.messages.updateFailed'));
    } finally {
        updatingField.value = null;
    }
};

const handleAssigneeChange = async (userId) => {
    if (!localTicket.value?.id || !isAdmin.value || updatingField.value) return;

    updatingField.value = 'assignee';
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
        await dialog.requestError(err, t('tickets.messages.updateFailed'));
    } finally {
        updatingField.value = null;
    }
};

// `approving` holds which of the two actions is in flight
const approveTicket = async () => {
    if (!localTicket.value?.id || approving.value) return;
    try {
        approving.value = 'approve';
        const response = await axios.post(`/api/tickets/${localTicket.value.id}/approve`);
        localTicket.value = { ...localTicket.value, ...response.data };
        isApproved.value = true;
        ticketComments.value = response.data.comments || ticketComments.value;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to approve:', err);
        await dialog.requestError(err, t('tickets.messages.approvalFailed'));
    } finally {
        approving.value = null;
    }
};

const rejectTicket = async () => {
    if (!localTicket.value?.id || approving.value) return;
    try {
        approving.value = 'reject';
        const response = await axios.post(`/api/tickets/${localTicket.value.id}/reject`);
        localTicket.value = { ...localTicket.value, ...response.data };
        isApproved.value = false;
        ticketComments.value = response.data.comments || ticketComments.value;
        emit('ticketUpdated', response.data);
    } catch (err) {
        console.error('Failed to reject:', err);
        await dialog.requestError(err, t('tickets.messages.approvalFailed'));
    } finally {
        approving.value = null;
    }
};

const addComment = async () => {
    if (!newComment.value.trim() || !localTicket.value?.id || addingComment.value) return;

    addingComment.value = true;
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
        await dialog.requestError(err, t('tickets.messages.commentFailed'));
    } finally {
        addingComment.value = false;
    }
};

const deleteComment = async (commentId) => {
    if (deletingCommentId.value) return;
    if (!(await dialog.confirmDelete(t('tickets.confirm.deleteCommentMessage')))) return;

    deletingCommentId.value = commentId;
    try {
        await axios.delete(`/api/ticket-comments/${commentId}`);
        ticketComments.value = ticketComments.value.filter(c => c.id !== commentId);
    } catch (err) {
        console.error('Failed to delete comment:', err);
        await dialog.requestError(err, t('tickets.messages.commentDeleteFailed'));
    } finally {
        deletingCommentId.value = null;
    }
};

const onCancel = () => {
    emit('update:isDrawerOpen', false);
};

const dialogModelValueUpdate = (val) => {
    emit('update:isDrawerOpen', val);
};

// Jump to the migration dashboard's Legacy Users tab, pre-searching the
// claimed identity (username, or the provided member id / e-mail).
const openLegacyUsers = () => {
    const meta = localTicket.value?.metadata || {};
    const search = meta.legacy_username || meta.legacy_user_id || meta.legacy_email || '';
    dialogModelValueUpdate(false);
    router.push({ path: '/admin/migrations', query: { tab: 'legacyUsers', search } });
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
                <h2 class="text-h6 font-weight-medium">
                    {{ localTicket?.id ? t('tickets.editTicket') : t('tickets.createTicket') }}
                </h2>
                <VSpacer/>
                <VBtn
                    v-if="localTicket?.id"
                    color="primary"
                    variant="text"
                    class="me-2"
                    :disabled="saving"
                    @click="localEditMode = !localEditMode"
                >
                    {{ localEditMode ? t('tickets.view') : t('tickets.edit') }}
                </VBtn>
            </div>

            <div v-else class="d-flex align-center py-3 px-4">
                <div class="flex-grow-1">
                    <div class="d-flex align-center flex-wrap ga-2 mb-1">
                        <v-chip
                            v-if="localTicket?.ticket_type"
                            size="small"
                            variant="tonal"
                            :color="localTicket.ticket_type.color"
                        >
                            <v-icon start size="small">{{ localTicket.ticket_type.icon }}</v-icon>
                            {{ localTicket.ticket_type.name }}
                        </v-chip>
                        <v-chip
                            size="small"
                            variant="tonal"
                            :color="getStatusColor(localTicket?.status)"
                        >
                            {{ localTicket?.status_label }}
                        </v-chip>
                    </div>
                    <h2 class="text-h6 font-weight-medium">{{ localTicket?.title }}</h2>
                </div>

                <VSpacer/>

                <div class="d-flex align-center ga-1">
                    <v-btn
                        v-if="canEdit"
                        icon="mdi-pencil"
                        variant="text"
                        color="primary"
                        density="comfortable"
                        @click="localEditMode = true"
                        :title="t('tickets.edit')"
                    />

                    <v-btn
                        v-if="isAdmin"
                        icon="mdi-delete"
                        variant="text"
                        color="error"
                        density="comfortable"
                        :loading="saving"
                        @click="removeTicket"
                        :title="t('tickets.delete')"
                    />

                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="comfortable"
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

                            <VCol cols="12" class="d-flex justify-end ga-2">
                                <VBtn
                                    variant="text"
                                    :disabled="saving"
                                    @click="onCancel"
                                >
                                    {{ t('tickets.cancel') }}
                                </VBtn>
                                <VBtn
                                    type="submit"
                                    color="primary"
                                    variant="flat"
                                    :loading="saving"
                                >
                                    {{ localTicket?.id ? t('tickets.update') : t('tickets.create') }}
                                </VBtn>
                            </VCol>
                        </VRow>
                    </VForm>
                </VCardText>
            </VCard>

            <!-- View Mode Content -->
            <div v-else class="pa-4">
                <!-- Ticket Properties -->
                <v-card flat rounded="lg" class="mb-4">
                    <v-card-text>
                        <!-- Priority -->
                        <div class="py-2 mb-3">
                            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">{{ t('tickets.fields.priority') }}</div>
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
                                :loading="updatingField === 'priority'"
                                :disabled="!!updatingField"
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
                        <div class="py-2 mb-3">
                            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">{{ t('tickets.fields.status') }}</div>
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
                                :loading="updatingField === 'status'"
                                :disabled="!!updatingField"
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
                        <div class="py-2 mb-3" v-if="isAdmin">
                            <div class="d-flex align-center justify-space-between">
                                <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">{{ t('tickets.fields.assignee') }}</div>
                                <v-btn
                                    v-if="localTicket?.assigned_to_user_id !== user.id"
                                    variant="text"
                                    density="compact"
                                    size="small"
                                    color="primary"
                                    class="text-caption pa-0"
                                    style="min-width: auto; text-transform: none;"
                                    :loading="updatingField === 'assignee'"
                                    :disabled="!!updatingField"
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
                                :loading="updatingField === 'assignee'"
                                :disabled="!!updatingField"
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
                        <div class="py-2 mb-3">
                            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">{{ t('tickets.fields.reporter') }}</div>
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
                        <div class="py-2">
                            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">{{ t('tickets.fields.created') }}</div>
                            <div class="text-body-2">
                                {{ formatDateUtil(localTicket?.created_at) }}
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Legacy account claim details -->
                <v-card
                    flat
                    rounded="lg"
                    class="mb-4"
                    v-if="isAdmin && localTicket?.metadata?.legacy_username"
                >
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-2">{{ t('tickets.legacyClaim.title') }}</h3>
                        <div class="d-flex flex-wrap align-center ga-2 mb-3">
                            <v-chip size="small" variant="tonal" prepend-icon="mdi-account-clock">
                                {{ localTicket.metadata.legacy_username }}
                            </v-chip>
                            <v-chip
                                v-if="localTicket.metadata.legacy_email"
                                size="small" variant="tonal" prepend-icon="mdi-email-outline"
                            >
                                {{ localTicket.metadata.legacy_email }}
                            </v-chip>
                            <v-chip
                                v-if="localTicket.metadata.legacy_user_id"
                                size="small" variant="tonal" prepend-icon="mdi-identifier"
                            >
                                {{ localTicket.metadata.legacy_user_id }}
                            </v-chip>
                        </div>
                        <v-btn
                            color="primary"
                            variant="tonal"
                            size="small"
                            prepend-icon="mdi-account-convert"
                            @click="openLegacyUsers"
                        >
                            {{ t('tickets.legacyClaim.open') }}
                        </v-btn>
                    </v-card-text>
                </v-card>

                <!-- Description -->
                <v-card flat rounded="lg" class="mb-4" v-if="localTicket?.description">
                    <v-card-text>
                        <h3 class="text-subtitle-1 font-weight-medium mb-2">{{ t('tickets.fields.description') }}</h3>
                        <div class="description-content">{{ localTicket.description }}</div>
                    </v-card-text>
                </v-card>

                <!-- Related Content (Ticketable) -->
                <v-card flat rounded="lg" class="mb-4" v-if="localTicket?.ticketable && ticketableLink">
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
                <v-card flat rounded="lg" class="mb-4" v-if="isApprovable && isAdmin">
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
                                    :loading="approving === 'approve'"
                                    :disabled="!!approving"
                                    @click="approveTicket"
                                >
                                    <v-icon start>mdi-check</v-icon>
                                    {{ t('tickets.sidebar.approve') }}
                                </v-btn>
                                <v-btn
                                    color="error"
                                    variant="tonal"
                                    size="small"
                                    :loading="approving === 'reject'"
                                    :disabled="!!approving"
                                    @click="rejectTicket"
                                >
                                    <v-icon start>mdi-close</v-icon>
                                    {{ t('tickets.sidebar.reject') }}
                                </v-btn>
                            </template>
                            <v-btn
                                v-else
                                color="warning"
                                variant="tonal"
                                size="small"
                                :loading="approving === 'reject'"
                                @click="rejectTicket"
                            >
                                <v-icon start>mdi-undo</v-icon>
                                {{ t('tickets.sidebar.revokeApproval') }}
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Comments Section -->
                <v-card flat rounded="lg">
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
                                                    size="small"
                                                    variant="tonal"
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
                                                :loading="deletingCommentId === comment.id"
                                                :disabled="deletingCommentId !== null && deletingCommentId !== comment.id"
                                                @click="deleteComment(comment.id)"
                                            />
                                        </div>
                                        <div class="text-body-2 comment-text">{{ comment.comment }}</div>
                                    </div>
                                </div>
                            </div>

                            <empty-state
                                v-if="ticketComments.length === 0"
                                compact
                                icon="mdi-comment-outline"
                                :title="t('tickets.noComments')"
                            />
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
                                :disabled="addingComment"
                            />
                            <div class="d-flex align-center justify-space-between">
                                <v-checkbox
                                    v-if="isAdmin"
                                    v-model="isInternalComment"
                                    :label="t('tickets.sidebar.internalNote')"
                                    density="compact"
                                    hide-details
                                    :disabled="addingComment"
                                />
                                <VSpacer/>
                                <VBtn
                                    color="primary"
                                    variant="flat"
                                    size="small"
                                    @click="addComment"
                                    :loading="addingComment"
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
</template>

<style scoped>
.ticket-drawer {
    max-height: 100%;
    border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.ticket-drawer-header {
    position: sticky;
    top: 0;
    z-index: 10;
}

.ticket-drawer-content {
    height: calc(100vh - 65px);
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
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.comment-item.internal-comment {
    background-color: rgba(var(--v-theme-warning), 0.08);
    border-left: 3px solid rgb(var(--v-theme-warning));
}

.comment-text {
    line-height: 1.5;
    white-space: pre-line;
}

.add-comment {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
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
