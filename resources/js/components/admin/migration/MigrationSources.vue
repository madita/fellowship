<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
            <span>{{ $t('migrationTool.sources') }}</span>
            <v-btn color="primary" variant="tonal" size="small" prepend-icon="mdi-plus" @click="openDialog()">
                {{ $t('migrationTool.addSource') }}
            </v-btn>
        </v-card-title>
        <v-card-text class="pa-0">
            <v-table v-if="sources.length" density="comfortable">
                <thead>
                    <tr>
                        <th>{{ $t('common.name') }}</th>
                        <th>{{ $t('migrationTool.driver') }}</th>
                        <th>{{ $t('migrationTool.database') }}</th>
                        <th>{{ $t('migrationTool.mappings') }}</th>
                        <th class="text-right">{{ $t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="source in sources" :key="source.id">
                        <td>{{ source.name }}</td>
                        <td><v-chip size="x-small" variant="tonal">{{ source.driver }}</v-chip></td>
                        <td class="text-caption">
                            <template v-if="source.driver !== 'sqlite'">{{ source.host }}:{{ source.port || '·' }}/</template>{{ source.database }}
                        </td>
                        <td>{{ source.mappings_count }}</td>
                        <td class="text-right">
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                :loading="testingId === source.id"
                                :disabled="rowBusy"
                                :title="$t('migrationTool.testConnection')"
                                @click="testSource(source)"
                            >
                                <v-icon size="small">mdi-connection</v-icon>
                            </v-btn>
                            <v-btn icon size="small" variant="text" :disabled="rowBusy" @click="openDialog(source)">
                                <v-icon size="small">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon size="small" variant="text" color="error"
                                :loading="deletingId === source.id"
                                :disabled="rowBusy"
                                @click="deleteSource(source)"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
            <empty-state v-else compact icon="mdi-database-off-outline" :title="$t('migrationTool.noSources')" />
        </v-card-text>

        <!-- Source form dialog -->
        <v-dialog v-model="dialog" max-width="600">
            <v-card>
                <v-card-title class="text-h6">
                    {{ editing ? $t('migrationTool.editSource') : $t('migrationTool.addSource') }}
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-text-field v-model="form.name" :label="$t('common.name')" density="compact" class="mb-2" />
                    <v-select
                        v-model="form.driver"
                        :items="drivers"
                        :label="$t('migrationTool.driver')"
                        density="compact"
                        class="mb-2"
                    />
                    <template v-if="form.driver !== 'sqlite'">
                        <v-row dense>
                            <v-col cols="8">
                                <v-text-field v-model="form.host" label="Host" density="compact" />
                            </v-col>
                            <v-col cols="4">
                                <v-text-field v-model.number="form.port" label="Port" type="number" density="compact" />
                            </v-col>
                        </v-row>
                        <v-text-field v-model="form.username" :label="$t('migrationTool.username')" density="compact" class="mb-2" />
                        <v-text-field
                            v-model="form.password"
                            :label="$t('migrationTool.password')"
                            :hint="editing ? $t('migrationTool.passwordKeepHint') : ''"
                            persistent-hint
                            type="password"
                            density="compact"
                            class="mb-2"
                        />
                    </template>
                    <v-text-field
                        v-model="form.database"
                        :label="form.driver === 'sqlite' ? $t('migrationTool.sqlitePath') : $t('migrationTool.database')"
                        density="compact"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="saving" @click="dialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" :loading="saving" :disabled="saving" @click="save">{{ $t('common.save') }}</v-btn>
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

const drivers = ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'];
const sources = ref([]);
const dialog = ref(false);
const editing = ref(null);
const saving = ref(false);
const testingId = ref(null);
const deletingId = ref(null);

const rowBusy = computed(() => testingId.value !== null || deletingId.value !== null);

const blankForm = () => ({
    name: '',
    driver: 'mysql',
    host: '',
    port: null,
    database: '',
    username: '',
    password: '',
});
const form = ref(blankForm());

const fetchSources = async () => {
    try {
        const { data } = await axios.get('/api/admin/migrations/sources');
        sources.value = data;
    } catch (e) {
        emit('notify', { text: t('migrationTool.loadFailed'), color: 'error' });
    }
};

const openDialog = (source = null) => {
    editing.value = source;
    form.value = source
        ? { name: source.name, driver: source.driver, host: source.host, port: source.port, database: source.database, username: source.username, password: '' }
        : blankForm();
    dialog.value = true;
};

const save = async () => {
    if (saving.value) return;
    saving.value = true;
    try {
        if (editing.value) {
            await axios.patch(`/api/admin/migrations/sources/${editing.value.id}`, form.value);
        } else {
            await axios.post('/api/admin/migrations/sources', form.value);
        }
        dialog.value = false;
        await fetchSources();
        emit('changed');
        emit('notify', { text: t('migrationTool.sourceSaved') });
    } catch (e) {
        feedback.requestError(e);
    } finally {
        saving.value = false;
    }
};

const testSource = async (source) => {
    if (rowBusy.value) return;
    testingId.value = source.id;
    try {
        const { data } = await axios.post(`/api/admin/migrations/sources/${source.id}/test`);
        emit('notify', { text: t('migrationTool.connectionOk', { tables: data.tables }) });
    } catch (e) {
        emit('notify', { text: t('migrationTool.connectionFailed', { error: e.response?.data?.error || e.message }), color: 'error' });
    } finally {
        testingId.value = null;
    }
};

const deleteSource = async (source) => {
    if (rowBusy.value) return;
    const ok = await feedback.confirmDelete(t('migrationTool.confirmDeleteSource', { name: source.name }));
    if (!ok) return;
    deletingId.value = source.id;
    try {
        await axios.delete(`/api/admin/migrations/sources/${source.id}`);
        await fetchSources();
        emit('changed');
    } catch (e) {
        emit('notify', { text: e.response?.data?.message || e.message, color: 'error' });
    } finally {
        deletingId.value = null;
    }
};

onMounted(fetchSources);

defineExpose({ fetchSources });
</script>
