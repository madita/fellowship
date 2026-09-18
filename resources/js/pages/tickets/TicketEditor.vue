<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue';
import { useUserStore } from '@/store/userStore.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { useAssignableUsers } from '@/composables/useAssignableUsers.js';
import { useDialog } from '@/composables/useDialog.js';
import { rememberedTicketListQuery } from '@/composables/useTicketListQuery.js';
import { hasRichText, toEditorHtml } from '@/utils/richText.js';

/**
 * Create (/admin/tickets/create) or edit (/admin/tickets/:id/edit) a ticket
 * on its own page, laid out like the wiki editor: title and description
 * (with @mentions) on the left, classification and assignment on the right.
 */
const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const dialog = useDialog();
const { statusFilterOptions, priorityFilterOptions } = useTicketHelpers();
const { assignableUsers, loadAssignableUsers } = useAssignableUsers();

const isEdit = computed(() => route.name === 'admin-ticket-edit');
const ticketId = computed(() => (isEdit.value ? Number(route.params.id) : null));

const emptyForm = () => ({
    ticket_type_id: null,
    title: '',
    description: '',
    priority: 'normal',
    status: 'open',
    assigned_to_user_id: null,
    due_date: '',
});

const formRef = ref(null);
const form = ref(emptyForm());
// What was loaded, to know whether leaving would lose changes
const initial = ref(JSON.stringify(form.value));
const types = ref([]);
const loading = ref(false);
const notFound = ref(false);
const saving = ref(false);
const saved = ref(false);

const dirty = computed(() => JSON.stringify(form.value) !== initial.value);
const canSave = computed(() => !!form.value.title?.trim() && !!form.value.ticket_type_id && !saving.value);
const selectedType = computed(() => types.value.find(type => type.id === form.value.ticket_type_id));

const backTo = computed(() => (isEdit.value
    ? { name: 'admin-ticket', params: { id: ticketId.value } }
    : { name: 'admin-tickets', query: rememberedTicketListQuery('admin-tickets') }));

const loadTypes = async () => {
    try {
        const response = await axios.get('/api/ticket-types');
        types.value = response.data;
        if (!isEdit.value && !form.value.ticket_type_id) {
            form.value.ticket_type_id = types.value.find(type => type.slug === 'support')?.id ?? types.value[0]?.id ?? null;
            initial.value = JSON.stringify(form.value);
        }
    } catch (err) {
        console.error('Failed to load ticket types:', err);
    }
};

const loadTicket = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/tickets/${ticketId.value}`);
        form.value = {
            ticket_type_id: data.ticket_type_id,
            title: data.title,
            description: toEditorHtml(data.description),
            priority: data.priority,
            status: data.status,
            assigned_to_user_id: data.assigned_to_user_id,
            due_date: data.due_date?.slice(0, 10) || '',
        };
        initial.value = JSON.stringify(form.value);
    } catch (err) {
        if ([403, 404].includes(err.response?.status)) {
            notFound.value = true;
        } else {
            await dialog.requestError(err, t('tickets.messages.loadFailed'));
        }
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    if (!canSave.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    saving.value = true;
    try {
        const payload = {
            ...form.value,
            description: hasRichText(form.value.description) ? form.value.description : null,
            due_date: form.value.due_date || null,
        };
        if (!isEdit.value) delete payload.status;

        const { data } = isEdit.value
            ? await axios.patch(`/api/tickets/${ticketId.value}`, payload)
            : await axios.post('/api/tickets', payload);

        saved.value = true;
        router.replace({ name: 'admin-ticket', params: { id: data.id } });
    } catch (err) {
        console.error('Failed to save ticket:', err);
        await dialog.requestError(err, t(isEdit.value ? 'tickets.messages.updateFailed' : 'tickets.messages.createFailed'));
    } finally {
        saving.value = false;
    }
};

onBeforeRouteLeave(async () => {
    if (saved.value || !dirty.value) return true;
    return dialog.confirm({
        title: t('tickets.editor.leaveTitle'),
        content: t('tickets.editor.leaveMessage'),
        confirmationText: t('tickets.editor.leave'),
        color: 'warning',
    });
});

const warnBeforeUnload = (event) => {
    if (dirty.value && !saved.value) event.preventDefault();
};

onMounted(() => {
    loadTypes();
    loadAssignableUsers();
    if (isEdit.value) loadTicket();
    window.addEventListener('beforeunload', warnBeforeUnload);
});

onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeUnload));
</script>

<template>
    <div>
        <page-header
            :title="isEdit ? t('tickets.editor.editTitle') : t('tickets.createTicket')"
            :subtitle="isEdit ? `#${ticketId} · ${form.title}` : t('tickets.editor.createSubtitle')"
            :icon="isEdit ? 'mdi-pencil-outline' : 'mdi-ticket-confirmation-outline'"
            :back-to="backTo"
        >
            <template #actions>
                <v-btn variant="tonal" :to="backTo" :disabled="saving">
                    {{ t('tickets.cancel') }}
                </v-btn>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-content-save"
                    :loading="saving"
                    :disabled="!canSave || notFound"
                    @click="save"
                >
                    {{ isEdit ? t('tickets.editor.save') : t('tickets.createTicket') }}
                </v-btn>
            </template>
        </page-header>

        <loading-state v-if="loading" />

        <v-container v-else-if="notFound">
            <empty-state icon="mdi-ticket-outline" :title="t('tickets.detail.notFound')" />
        </v-container>

        <v-container v-else fluid>
            <v-form ref="formRef" @submit.prevent="save">
                <v-row>
                    <!-- Content -->
                    <v-col cols="12" lg="8">
                        <v-card class="editor-card" elevation="2" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-file-document-edit</v-icon>
                                {{ t('tickets.editor.content') }}
                            </v-card-title>
                            <v-card-text class="pa-6">
                                <v-text-field
                                    v-model="form.title"
                                    :label="t('tickets.fields.title')"
                                    :placeholder="t('tickets.editor.titlePlaceholder')"
                                    :rules="[v => !!v?.trim() || t('tickets.validation.titleRequired')]"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-format-title"
                                    counter="255"
                                    maxlength="255"
                                    :autofocus="!isEdit"
                                    class="mb-4"
                                />

                                <div class="d-flex align-center mb-3">
                                    <v-icon class="mr-2" size="20" color="primary">mdi-text</v-icon>
                                    <span class="text-subtitle-1 font-weight-medium">{{ t('tickets.fields.description') }}</span>
                                </div>
                                <simple-editor
                                    v-model="form.description"
                                    :placeholder="t('tickets.editor.descriptionPlaceholder')"
                                    :limit="10000"
                                    :disabled="saving"
                                    min-height="280px"
                                    @submit="save"
                                />
                                <div class="text-caption text-medium-emphasis mt-2">{{ t('tickets.editor.saveHint') }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- Settings -->
                    <v-col cols="12" lg="4">
                        <v-card class="settings-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-shape-outline</v-icon>
                                {{ t('tickets.editor.classification') }}
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-select
                                    v-model="form.ticket_type_id"
                                    :items="types"
                                    item-title="name"
                                    item-value="id"
                                    :label="t('tickets.fields.ticketType')"
                                    :rules="[v => !!v || t('tickets.validation.typeRequired')]"
                                    :hint="selectedType?.description"
                                    persistent-hint
                                    variant="outlined"
                                    density="compact"
                                    class="mb-3"
                                >
                                    <template #selection="{ item }">
                                        <v-icon size="18" :color="item.raw.color" :icon="item.raw.icon || 'mdi-ticket-outline'" class="mr-2" />
                                        {{ item.raw.name }}
                                    </template>
                                    <template #item="{ item, props: itemProps }">
                                        <v-list-item v-bind="itemProps">
                                            <template #prepend>
                                                <v-icon :color="item.raw.color" :icon="item.raw.icon || 'mdi-ticket-outline'" />
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-select>
                                <v-select
                                    v-model="form.priority"
                                    :items="priorityFilterOptions(false)"
                                    item-title="label"
                                    item-value="value"
                                    :label="t('tickets.fields.priority')"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                >
                                    <template #selection="{ item }">
                                        <v-icon size="18" :color="item.raw.color" :icon="item.raw.icon" class="mr-2" />
                                        {{ item.raw.label }}
                                    </template>
                                    <template #item="{ item, props: itemProps }">
                                        <v-list-item v-bind="itemProps">
                                            <template #prepend>
                                                <v-icon :color="item.raw.color" :icon="item.raw.icon" />
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-select>
                            </v-card-text>
                        </v-card>

                        <v-card class="settings-card" elevation="1" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-account-arrow-right-outline</v-icon>
                                {{ t('tickets.editor.assignment') }}
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-select
                                    v-if="isEdit"
                                    v-model="form.status"
                                    :items="statusFilterOptions(false)"
                                    item-title="label"
                                    item-value="value"
                                    :label="t('tickets.fields.status')"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    class="mb-3"
                                >
                                    <template #selection="{ item }">
                                        <v-icon size="10" :color="item.raw.color" icon="mdi-circle" class="mr-2" />
                                        {{ item.raw.label }}
                                    </template>
                                </v-select>
                                <v-autocomplete
                                    v-model="form.assigned_to_user_id"
                                    :items="assignableUsers"
                                    item-title="username"
                                    item-value="id"
                                    :label="t('tickets.fields.assignee')"
                                    :placeholder="t('tickets.unassigned')"
                                    persistent-placeholder
                                    variant="outlined"
                                    density="compact"
                                    clearable
                                    hide-details
                                />
                                <div class="d-flex justify-end mb-2">
                                    <v-btn
                                        v-if="form.assigned_to_user_id !== userStore.user?.id"
                                        variant="text"
                                        size="small"
                                        color="primary"
                                        @click="form.assigned_to_user_id = userStore.user?.id"
                                    >
                                        {{ t('tickets.assignToMe') }}
                                    </v-btn>
                                </div>
                                <v-text-field
                                    v-model="form.due_date"
                                    type="date"
                                    :label="t('tickets.fields.dueDate')"
                                    variant="outlined"
                                    density="compact"
                                    clearable
                                    hide-details
                                />
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-form>
        </v-container>
    </div>
</template>

<style scoped>
.editor-card,
.settings-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
