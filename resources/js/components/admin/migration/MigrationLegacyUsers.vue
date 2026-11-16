<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
            <div class="d-flex align-center ga-3">
                <span>{{ $t('migrationTool.legacyUsers') }}</span>
                <v-btn
                    v-if="selected.length"
                    size="small"
                    color="error"
                    variant="tonal"
                    prepend-icon="mdi-delete"
                    :loading="deleting"
                    :disabled="deleting"
                    @click="removeSelected"
                >
                    {{ $t('migrationTool.deleteSelected', { count: selected.length }) }}
                </v-btn>
            </div>
            <div class="d-flex align-center ga-2">
                <v-select
                    v-model="importFilter"
                    :items="typeOptions"
                    :label="$t('migrationTool.importFilter')"
                    prepend-inner-icon="mdi-filter-variant"
                    density="compact"
                    hide-details
                    clearable
                    style="min-width: 210px;"
                />
                <v-text-field
                    v-model="search"
                    :label="$t('common.search')"
                    prepend-inner-icon="mdi-magnify"
                    density="compact"
                    hide-details
                    clearable
                    style="max-width: 260px;"
                />
            </div>
        </v-card-title>
        <v-card-text class="pa-0">
            <div class="text-caption text-medium-emphasis px-4 pb-2">
                {{ $t('migrationTool.legacyUsersHint') }}
            </div>
            <v-table v-if="filtered.length" density="comfortable">
                <thead>
                    <tr>
                        <th style="width: 48px;">
                            <v-checkbox
                                :model-value="allSelected"
                                :indeterminate="someSelected"
                                :disabled="deleting"
                                hide-details
                                density="compact"
                                @update:model-value="toggleAll"
                            />
                        </th>
                        <th>{{ $t('migrationTool.legacyUsername') }}</th>
                        <th>{{ $t('migrationTool.legacySource') }}</th>
                        <th>{{ $t('migrationTool.legacyItems') }}</th>
                        <th>{{ $t('migrationTool.legacyClaim') }}</th>
                        <th>{{ $t('migrationTool.assignedTo') }}</th>
                        <th class="text-right">{{ $t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in filtered" :key="`${row.legacy_source}|${row.legacy_username}`">
                        <td>
                            <v-checkbox
                                :model-value="selected.includes(rowKey(row))"
                                :disabled="deleting"
                                hide-details
                                density="compact"
                                @update:model-value="toggleRow(row, $event)"
                            />
                        </td>
                        <td>
                            <strong>{{ row.legacy_username }}</strong>
                            <div v-if="row.email" class="text-caption text-medium-emphasis">{{ row.email }}</div>
                            <div v-if="row.legacy_user_id" class="text-caption text-medium-emphasis">ID: {{ row.legacy_user_id }}</div>
                        </td>
                        <td>
                            <v-chip size="x-small" variant="outlined" prepend-icon="mdi-database-outline">
                                {{ row.legacy_source }}
                            </v-chip>
                        </td>
                        <td class="text-caption">
                            <v-chip
                                v-for="(count, type) in row.types"
                                :key="type"
                                size="x-small"
                                variant="tonal"
                                class="me-1"
                            >
                                {{ type }}: {{ count }}
                            </v-chip>
                        </td>
                        <td>
                            <v-chip
                                v-if="row.claim"
                                size="small"
                                :color="(row.claim.email_verified || row.claim.id_verified) ? 'success' : 'warning'"
                                variant="tonal"
                                :prepend-icon="row.claim.id_verified ? 'mdi-card-account-details-outline' : (row.claim.email_verified ? 'mdi-email-check' : 'mdi-ticket-account')"
                                :title="row.claim.id_verified ? $t('migrationTool.claimIdVerified') : (row.claim.email_verified ? $t('migrationTool.claimEmailVerified') : '')"
                            >
                                {{ row.claim.user?.username }}
                            </v-chip>
                            <v-chip
                                v-else-if="row.suggested_user && !row.assigned_user"
                                size="small"
                                color="info"
                                variant="tonal"
                                prepend-icon="mdi-email-check-outline"
                                :title="$t('migrationTool.suggestedByEmail')"
                            >
                                {{ row.suggested_user.username }}
                            </v-chip>
                        </td>
                        <td>
                            <v-chip
                                v-if="row.assigned_user"
                                size="small"
                                color="success"
                                variant="tonal"
                                prepend-icon="mdi-check"
                            >
                                {{ row.assigned_user.username }}
                            </v-chip>
                        </td>
                        <td class="text-right">
                            <v-btn
                                size="small"
                                variant="tonal"
                                color="primary"
                                :disabled="!!row.assigned_user"
                                @click="openAssign(row)"
                            >
                                {{ $t('migrationTool.assign') }}
                            </v-btn>
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                color="error"
                                class="ms-1"
                                :title="$t('migrationTool.deleteLegacyUser')"
                                :disabled="deleting"
                                @click="removeRow(row)"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
            <empty-state v-else compact icon="mdi-account-convert-outline" :title="$t('migrationTool.noLegacyUsers')" />
        </v-card-text>

        <!-- Assign dialog -->
        <v-dialog v-model="dialog" max-width="480">
            <v-card v-if="assigning">
                <v-card-title class="text-h6">{{ $t('migrationTool.assignTitle', { name: `${assigning.legacy_username} (${assigning.legacy_source})` }) }}</v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="text-body-2 mb-3">{{ $t('migrationTool.assignHint') }}</div>
                    <v-text-field
                        v-model="assignUser"
                        :label="$t('migrationTool.assignUserLabel')"
                        :placeholder="assigning.claim?.user?.username"
                        density="compact"
                        autofocus
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="saving" @click="dialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" :loading="saving" :disabled="!assignUser || saving" @click="assign">
                        {{ $t('migrationTool.assign') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';
import EmptyState from '../../common/EmptyState.vue';

const { t } = useI18n();
const feedback = useDialog();
const emit = defineEmits(['notify', 'changed']);
const props = defineProps({
    // Pre-fill the search, e.g. deep-linked from a claim ticket.
    initialSearch: { type: String, default: '' },
});

const rows = ref([]);
const search = ref(props.initialSearch || '');
const dialog = ref(false);
const assigning = ref(null);
const assignUser = ref('');
const saving = ref(false);

// Accounts that brought nothing with them: the spam and bot registrations
// that come along with an old user table. Filtering to these and using the
// select-all box is the way to clear them out in one pass.
const NO_IMPORTS = '__none__';

const importFilter = ref(null);

const importTypesOf = (row) => Object.keys(row.types || {});

// Built from the rows themselves, so the list only ever offers types that
// are actually there. Counts are identities, which is what gets selected
// and deleted, not the number of imported items.
const typeOptions = computed(() => {
    const counts = {};
    let nothing = 0;

    for (const row of rows.value) {
        const types = importTypesOf(row);
        if (! types.length) {
            nothing++;
            continue;
        }
        for (const type of types) counts[type] = (counts[type] || 0) + 1;
    }

    const options = Object.keys(counts).sort().map(type => ({
        value: type,
        title: t('migrationTool.importFilterCount', { type, count: counts[type] }),
    }));

    if (nothing) {
        options.unshift({
            value: NO_IMPORTS,
            title: t('migrationTool.importFilterNoneCount', { count: nothing }),
        });
    }

    return options;
});

const matchesImports = (row) => {
    if (! importFilter.value) return true;

    return importFilter.value === NO_IMPORTS
        ? importTypesOf(row).length === 0
        : importTypesOf(row).includes(importFilter.value);
};

const filtered = computed(() => {
    const query = (search.value || '').toLowerCase();

    return rows.value.filter((row) => {
        if (! matchesImports(row)) return false;
        if (! query) return true;

        return row.legacy_username.toLowerCase().includes(query)
            || row.legacy_source.toLowerCase().includes(query)
            || row.email?.toLowerCase().includes(query)
            || String(row.legacy_user_id || '').toLowerCase() === query
            || row.assigned_user?.username?.toLowerCase().includes(query)
            || row.claim?.user?.username?.toLowerCase().includes(query)
            || row.claim?.legacy_email?.toLowerCase().includes(query)
            || String(row.claim?.legacy_user_id || '').toLowerCase() === query;
    });
});

const fetchAll = async () => {
    try {
        const { data } = await axios.get('/api/admin/migrations/legacy-users');
        rows.value = data.legacyUsers;
    } catch (e) {
        emit('notify', { text: t('migrationTool.loadFailed'), color: 'error' });
    }
};

const openAssign = (row) => {
    assigning.value = row;
    assignUser.value = row.claim?.user?.username || row.suggested_user?.username || '';
    dialog.value = true;
};

const assign = async () => {
    if (saving.value) return;
    saving.value = true;
    try {
        const { data } = await axios.post('/api/admin/migrations/legacy-users/assign', {
            legacy_username: assigning.value.legacy_username,
            legacy_source: assigning.value.legacy_source,
            user: assignUser.value,
        });
        emit('notify', { text: data.message });
        dialog.value = false;
        await fetchAll();
        emit('changed');
    } catch (e) {
        feedback.requestError(e);
    } finally {
        saving.value = false;
    }
};

// Selection travels by identity, never by position: the list is filtered and
// re-fetched, so an index means nothing between renders.
const rowKey = (row) => row.legacy_source + '|' + row.legacy_username;

const selected = ref([]);
const deleting = ref(false);

const filteredKeys = computed(() => filtered.value.map(rowKey));
const allSelected = computed(() =>
    filteredKeys.value.length > 0 && filteredKeys.value.every(key => selected.value.includes(key))
);
const someSelected = computed(() =>
    ! allSelected.value && filteredKeys.value.some(key => selected.value.includes(key))
);
const selectedRows = computed(() => rows.value.filter(row => selected.value.includes(rowKey(row))));

const toggleRow = (row, on) => {
    const key = rowKey(row);
    selected.value = on
        ? [...new Set([...selected.value, key])]
        : selected.value.filter(item => item !== key);
};

// Select-all covers what the search is showing, which is how a few thousand
// spam registrations get cleared in one pass.
const toggleAll = (on) => {
    selected.value = on
        ? [...new Set([...selected.value, ...filteredKeys.value])]
        : selected.value.filter(key => ! filteredKeys.value.includes(key));
};

const itemsOf = (row) => Number(row.items) || 0;

// Removing an identity never deletes what it wrote. One that is credited with
// imported content is kept, unless it is deleted on its own and on purpose.
const remove = async (list, withContent = false) => {
    if (! list.length || deleting.value) return;

    deleting.value = true;
    try {
        const { data } = await axios.post('/api/admin/migrations/legacy-users/delete', {
            users: list.map(row => ({ legacy_source: row.legacy_source, legacy_username: row.legacy_username })),
            with_content: withContent,
        });

        emit('notify', {
            text: data.skipped?.length
                ? t('migrationTool.legacyDeletedKept', { count: data.removed, kept: data.skipped.length })
                : t('migrationTool.legacyDeleted', { count: data.removed }),
        });

        selected.value = [];
        await fetchAll();
        emit('changed');
    } catch (e) {
        feedback.requestError(e);
    } finally {
        deleting.value = false;
    }
};

const removeRow = async (row) => {
    const items = itemsOf(row);
    const ok = await feedback.confirmDelete(items
        ? t('migrationTool.confirmDeleteLegacyWithContent', { name: row.legacy_username, count: items })
        : t('migrationTool.confirmDeleteLegacy', { name: row.legacy_username }));
    if (! ok) return;

    await remove([row], items > 0);
};

const removeSelected = async () => {
    const list = selectedRows.value;
    const kept = list.filter(row => itemsOf(row) > 0).length;

    const ok = await feedback.confirmDelete(kept
        ? t('migrationTool.confirmDeleteLegacyBulkKept', { count: list.length - kept, kept })
        : t('migrationTool.confirmDeleteLegacyBulk', { count: list.length }));
    if (! ok) return;

    await remove(list);
};

onMounted(fetchAll);

defineExpose({ fetchAll });
</script>
