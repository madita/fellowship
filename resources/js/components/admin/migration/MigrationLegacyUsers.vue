<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>{{ $t('migrationTool.legacyUsers') }}</span>
            <v-text-field
                v-model="search"
                :label="$t('common.search')"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                style="max-width: 260px;"
            />
        </v-card-title>
        <v-card-text class="pa-0">
            <div class="text-caption text-medium-emphasis px-4 pb-2">
                {{ $t('migrationTool.legacyUsersHint') }}
            </div>
            <v-table v-if="filtered.length" density="comfortable">
                <thead>
                    <tr>
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
                            <strong>{{ row.legacy_username }}</strong>
                            <div v-if="row.email" class="text-caption text-medium-emphasis">{{ row.email }}</div>
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
                                class="mr-1"
                            >
                                {{ type }}: {{ count }}
                            </v-chip>
                        </td>
                        <td>
                            <v-chip
                                v-if="row.claim"
                                size="small"
                                :color="row.claim.email_verified ? 'success' : 'warning'"
                                variant="tonal"
                                :prepend-icon="row.claim.email_verified ? 'mdi-email-check' : 'mdi-ticket-account'"
                                :title="row.claim.email_verified ? $t('migrationTool.claimEmailVerified') : ''"
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
                        </td>
                    </tr>
                </tbody>
            </v-table>
            <div v-else class="text-center text-medium-emphasis py-8">
                {{ $t('migrationTool.noLegacyUsers') }}
            </div>
        </v-card-text>

        <!-- Assign dialog -->
        <v-dialog v-model="dialog" max-width="480">
            <v-card v-if="assigning">
                <v-card-title>{{ $t('migrationTool.assignTitle', { name: `${assigning.legacy_username} (${assigning.legacy_source})` }) }}</v-card-title>
                <v-card-text>
                    <div class="text-body-2 mb-3">{{ $t('migrationTool.assignHint') }}</div>
                    <v-text-field
                        v-model="assignUser"
                        :label="$t('migrationTool.assignUserLabel')"
                        :placeholder="assigning.claim?.user?.username"
                        variant="outlined"
                        density="compact"
                        autofocus
                    />
                    <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn @click="dialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="saving" :disabled="!assignUser" @click="assign">
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

const { t } = useI18n();
const emit = defineEmits(['notify']);

const rows = ref([]);
const search = ref('');
const dialog = ref(false);
const assigning = ref(null);
const assignUser = ref('');
const saving = ref(false);
const error = ref('');

const filtered = computed(() => {
    const query = (search.value || '').toLowerCase();
    if (!query) return rows.value;
    return rows.value.filter(row =>
        row.legacy_username.toLowerCase().includes(query)
        || row.legacy_source.toLowerCase().includes(query)
        || row.assigned_user?.username?.toLowerCase().includes(query)
        || row.claim?.user?.username?.toLowerCase().includes(query)
    );
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
    error.value = '';
    dialog.value = true;
};

const assign = async () => {
    saving.value = true;
    error.value = '';
    try {
        const { data } = await axios.post('/api/admin/migrations/legacy-users/assign', {
            legacy_username: assigning.value.legacy_username,
            legacy_source: assigning.value.legacy_source,
            user: assignUser.value,
        });
        emit('notify', { text: data.message });
        dialog.value = false;
        await fetchAll();
    } catch (e) {
        error.value = e.response?.data?.message || e.message;
    } finally {
        saving.value = false;
    }
};

onMounted(fetchAll);

defineExpose({ fetchAll });
</script>
