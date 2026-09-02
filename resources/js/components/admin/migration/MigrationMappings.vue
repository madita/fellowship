<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>{{ $t('migrationTool.mappings') }}</span>
            <v-btn color="primary" size="small" prepend-icon="mdi-plus" @click="openEditor()">
                {{ $t('migrationTool.addMapping') }}
            </v-btn>
        </v-card-title>
        <v-card-text class="pa-0">
            <v-table v-if="mappings.length" density="comfortable">
                <thead>
                    <tr>
                        <th>{{ $t('common.name') }}</th>
                        <th>{{ $t('migrationTool.source') }}</th>
                        <th>{{ $t('migrationTool.mappingFlow') }}</th>
                        <th class="text-right">{{ $t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="mapping in mappings" :key="mapping.id">
                        <td>{{ mapping.name }}</td>
                        <td>{{ mapping.source?.name }}</td>
                        <td class="text-caption">
                            <code>{{ mapping.source_table }}</code>
                            <v-icon size="x-small" class="mx-1">mdi-arrow-right</v-icon>
                            <v-chip size="x-small" variant="tonal" color="primary">{{ targetLabel(mapping.target) }}</v-chip>
                        </td>
                        <td class="text-right">
                            <v-btn
                                icon size="small" variant="text" color="success"
                                :title="$t('migrationTool.runImport')"
                                :loading="runningId === mapping.id"
                                @click="run(mapping)"
                            >
                                <v-icon size="small">mdi-play</v-icon>
                            </v-btn>
                            <v-btn icon size="small" variant="text" @click="openEditor(mapping)">
                                <v-icon size="small">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn icon size="small" variant="text" color="error" @click="deleteMapping(mapping)">
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
            <div v-else class="text-center text-medium-emphasis py-8">
                {{ $t('migrationTool.noMappings') }}
            </div>
        </v-card-text>

        <!-- Mapping editor dialog -->
        <v-dialog v-model="editor" max-width="980" scrollable>
            <v-card>
                <v-card-title>
                    {{ editing ? $t('migrationTool.editMapping') : $t('migrationTool.addMapping') }}
                </v-card-title>
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="form.name" :label="$t('common.name')" variant="outlined" density="compact" />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="form.migration_source_id"
                                :items="sources"
                                item-title="name"
                                item-value="id"
                                :label="$t('migrationTool.source')"
                                variant="outlined"
                                density="compact"
                                @update:model-value="onSourceChanged"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="form.target"
                                :items="targets"
                                item-title="label"
                                item-value="key"
                                :label="$t('migrationTool.target')"
                                variant="outlined"
                                density="compact"
                                @update:model-value="onTargetChanged"
                            />
                        </v-col>
                    </v-row>

                    <v-select
                        v-model="form.source_table"
                        :items="tables"
                        :label="$t('migrationTool.sourceTable')"
                        :loading="loadingTables"
                        :disabled="!form.migration_source_id"
                        variant="outlined"
                        density="compact"
                        class="mb-1"
                        @update:model-value="onTableChanged"
                    />
                    <div v-if="rowCount !== null" class="text-caption text-medium-emphasis mb-3">
                        {{ $t('migrationTool.rowsInTable', { count: rowCount }) }}
                    </div>

                    <!-- Joins (relations) -->
                    <template v-if="form.source_table">
                        <div class="d-flex align-center mb-1">
                            <span class="text-subtitle-2">{{ $t('migrationTool.joins') }}</span>
                            <v-btn size="x-small" variant="text" color="primary" prepend-icon="mdi-plus" class="ml-2" @click="addJoin">
                                {{ $t('migrationTool.addJoin') }}
                            </v-btn>
                        </div>
                        <div v-if="!form.options.joins.length" class="text-caption text-medium-emphasis mb-3">
                            {{ $t('migrationTool.joinsHint') }}
                        </div>
                        <v-row v-for="(join, index) in form.options.joins" :key="`join-${index}`" dense class="align-center">
                            <v-col cols="6" md="2">
                                <v-select
                                    v-model="join.type"
                                    :items="joinTypes"
                                    :label="$t('migrationTool.joinType')"
                                    hide-details variant="outlined" density="compact"
                                />
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-select
                                    v-model="join.table"
                                    :items="tables"
                                    :label="$t('migrationTool.joinTable')"
                                    hide-details variant="outlined" density="compact"
                                    @update:model-value="loadJoinColumns(join.table)"
                                />
                            </v-col>
                            <v-col cols="5" md="3">
                                <v-text-field
                                    v-model="join.first"
                                    :label="$t('migrationTool.joinFirst')"
                                    :placeholder="`${join.table || 'table'}.column`"
                                    hide-details variant="outlined" density="compact"
                                />
                            </v-col>
                            <v-col cols="2" md="1">
                                <v-select
                                    v-model="join.operator"
                                    :items="operators"
                                    hide-details variant="outlined" density="compact"
                                />
                            </v-col>
                            <v-col cols="5" md="2">
                                <v-text-field
                                    v-model="join.second"
                                    :label="$t('migrationTool.joinSecond')"
                                    :placeholder="`${form.source_table}.column`"
                                    hide-details variant="outlined" density="compact"
                                />
                            </v-col>
                            <v-col cols="12" md="1" class="text-right">
                                <v-btn icon size="small" variant="text" color="error" @click="form.options.joins.splice(index, 1)">
                                    <v-icon size="small">mdi-close</v-icon>
                                </v-btn>
                            </v-col>
                        </v-row>

                        <!-- Row filters -->
                        <div class="d-flex align-center mb-1 mt-2">
                            <span class="text-subtitle-2">{{ $t('migrationTool.filters') }}</span>
                            <v-btn size="x-small" variant="text" color="primary" prepend-icon="mdi-plus" class="ml-2" @click="addFilter">
                                {{ $t('migrationTool.addFilter') }}
                            </v-btn>
                        </div>
                        <div v-if="!form.options.wheres.length" class="text-caption text-medium-emphasis mb-3">
                            {{ $t('migrationTool.filtersHint') }}
                        </div>
                        <v-row v-for="(where, index) in form.options.wheres" :key="`where-${index}`" dense class="align-center">
                            <v-col cols="5" md="4">
                                <v-text-field
                                    v-model="where.column"
                                    :label="$t('migrationTool.filterColumn')"
                                    hide-details variant="outlined" density="compact"
                                />
                            </v-col>
                            <v-col cols="2" md="2">
                                <v-select
                                    v-model="where.operator"
                                    :items="operators"
                                    hide-details variant="outlined" density="compact"
                                />
                            </v-col>
                            <v-col cols="5" md="5">
                                <v-text-field
                                    v-model="where.value"
                                    :label="$t('migrationTool.filterValue')"
                                    hide-details variant="outlined" density="compact"
                                />
                            </v-col>
                            <v-col cols="12" md="1" class="text-right">
                                <v-btn icon size="small" variant="text" color="error" @click="form.options.wheres.splice(index, 1)">
                                    <v-icon size="small">mdi-close</v-icon>
                                </v-btn>
                            </v-col>
                        </v-row>
                        <div class="mb-3" />
                    </template>

                    <!-- Field mapping grid -->
                    <template v-if="currentTarget && form.source_table">
                        <div class="text-subtitle-2 mb-2">{{ $t('migrationTool.fieldMapping') }}</div>
                        <v-table density="compact" class="mapping-grid mb-3">
                            <thead>
                                <tr>
                                    <th style="width: 22%">{{ $t('migrationTool.targetField') }}</th>
                                    <th style="width: 26%">{{ $t('migrationTool.sourceColumn') }}</th>
                                    <th style="width: 18%">{{ $t('migrationTool.transform') }}</th>
                                    <th style="width: 14%">{{ $t('migrationTool.format') }}</th>
                                    <th style="width: 20%">{{ $t('migrationTool.defaultValue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="field in currentTarget.fields" :key="field.key">
                                    <td>
                                        <strong>{{ field.label }}</strong>
                                        <span v-if="field.required" class="text-error">*</span>
                                        <div v-if="field.hint" class="text-caption text-medium-emphasis">{{ field.hint }}</div>
                                    </td>
                                    <td>
                                        <v-select
                                            v-model="form.field_map[field.key].source"
                                            :items="columnItems"
                                            :loading="loadingColumns"
                                            clearable
                                            hide-details
                                            variant="outlined"
                                            density="compact"
                                        />
                                        <div v-if="sampleFor(form.field_map[field.key].source)" class="text-caption text-medium-emphasis text-truncate" style="max-width: 220px;">
                                            {{ $t('migrationTool.sample') }}: {{ sampleFor(form.field_map[field.key].source) }}
                                        </div>
                                    </td>
                                    <td>
                                        <v-select
                                            v-model="form.field_map[field.key].transform"
                                            :items="transforms"
                                            hide-details
                                            variant="outlined"
                                            density="compact"
                                        />
                                    </td>
                                    <td>
                                        <v-text-field
                                            v-model="form.field_map[field.key].format"
                                            :disabled="!['date', 'time', 'datetime'].includes(form.field_map[field.key].transform)"
                                            placeholder="Ymd"
                                            hide-details
                                            variant="outlined"
                                            density="compact"
                                        />
                                    </td>
                                    <td>
                                        <v-text-field
                                            v-model="form.field_map[field.key].default"
                                            hide-details
                                            variant="outlined"
                                            density="compact"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </template>

                    <!-- Preview -->
                    <template v-if="preview">
                        <div class="text-subtitle-2 mb-1">
                            {{ $t('migrationTool.previewTitle', { total: preview.total }) }}
                        </div>
                        <v-table density="compact" class="preview-table">
                            <thead>
                                <tr>
                                    <th v-for="field in currentTarget.fields" :key="field.key">{{ field.key }}</th>
                                    <th>{{ $t('migrationTool.issues') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in preview.rows" :key="i" :class="{ 'bg-error-row': row.errors.length }">
                                    <td v-for="field in currentTarget.fields" :key="field.key" class="text-caption">
                                        {{ displayValue(row.mapped[field.key]) }}
                                    </td>
                                    <td class="text-caption text-error">{{ row.errors.join('; ') }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </template>

                    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-btn
                        variant="tonal"
                        prepend-icon="mdi-eye-outline"
                        :loading="previewing"
                        :disabled="!canSave"
                        @click="doPreview"
                    >
                        {{ $t('migrationTool.preview') }}
                    </v-btn>
                    <v-spacer />
                    <v-btn @click="editor = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="saving" :disabled="!canSave" @click="save">{{ $t('common.save') }}</v-btn>
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
const emit = defineEmits(['notify', 'run']);

const mappings = ref([]);
const sources = ref([]);
const targets = ref([]);
const transforms = ref(['none']);
const tables = ref([]);
const columns = ref([]);
const sample = ref({});
const rowCount = ref(null);
const joinColumns = ref({});
const joinSamples = ref({});

const joinTypes = ['left', 'inner'];
const operators = ['=', '!=', '<', '>', '<=', '>=', 'like'];

const editor = ref(false);
const editing = ref(null);
const saving = ref(false);
const previewing = ref(false);
const runningId = ref(null);
const loadingTables = ref(false);
const loadingColumns = ref(false);
const preview = ref(null);
const error = ref('');

const emptyForm = () => ({
    name: '', migration_source_id: null, target: null, source_table: null,
    field_map: {}, options: { joins: [], wheres: [] },
});

const form = ref(emptyForm());

const currentTarget = computed(() => targets.value.find(target => target.key === form.value.target));
const activeJoinTables = computed(() =>
    form.value.options.joins.map(join => join.table).filter(Boolean)
);
const columnItems = computed(() => {
    const items = columns.value.map(column => ({ title: `${column.name} (${column.type || '?'})`, value: column.name }));
    for (const table of activeJoinTables.value) {
        for (const column of joinColumns.value[table] || []) {
            if (!items.some(item => item.value === column.name)) {
                items.push({ title: `${column.name} (${table})`, value: column.name });
            }
        }
    }
    return items;
});
const canSave = computed(() =>
    form.value.name && form.value.migration_source_id && form.value.target && form.value.source_table
);

const targetLabel = (key) => targets.value.find(target => target.key === key)?.label || key;
const sampleFor = (column) => {
    if (!column) return null;
    if (sample.value && sample.value[column] !== undefined) return sample.value[column];
    for (const table of activeJoinTables.value) {
        const joined = joinSamples.value[table];
        if (joined && joined[column] !== undefined) return joined[column];
    }
    return null;
};
const displayValue = (value) => {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};

const fetchAll = async () => {
    try {
        const [mappingsRes, sourcesRes, targetsRes] = await Promise.all([
            axios.get('/api/admin/migrations/mappings'),
            axios.get('/api/admin/migrations/sources'),
            axios.get('/api/admin/migrations/targets'),
        ]);
        mappings.value = mappingsRes.data;
        sources.value = sourcesRes.data;
        targets.value = targetsRes.data.targets;
        transforms.value = targetsRes.data.transforms;
    } catch (e) {
        emit('notify', { text: t('migrationTool.loadFailed'), color: 'error' });
    }
};

const ensureFieldMap = () => {
    const map = {};
    for (const field of currentTarget.value?.fields || []) {
        map[field.key] = {
            source: null,
            transform: 'none',
            format: '',
            default: '',
            ...(form.value.field_map[field.key] || {}),
        };
    }
    form.value.field_map = map;
};

const openEditor = async (mapping = null) => {
    editing.value = mapping;
    preview.value = null;
    error.value = '';
    rowCount.value = null;
    tables.value = [];
    columns.value = [];
    sample.value = {};

    joinColumns.value = {};
    joinSamples.value = {};

    form.value = mapping
        ? {
            name: mapping.name,
            migration_source_id: mapping.migration_source_id,
            target: mapping.target,
            source_table: mapping.source_table,
            field_map: JSON.parse(JSON.stringify(mapping.field_map || {})),
            options: {
                joins: JSON.parse(JSON.stringify(mapping.options?.joins || [])),
                wheres: JSON.parse(JSON.stringify(mapping.options?.wheres || [])),
            },
        }
        : emptyForm();

    ensureFieldMap();
    editor.value = true;

    if (mapping) {
        await loadTables();
        await loadColumns();
        await Promise.all(form.value.options.joins.map(join => loadJoinColumns(join.table)));
    }
};

const addJoin = () => {
    form.value.options.joins.push({ type: 'left', table: null, first: '', operator: '=', second: '' });
};

const addFilter = () => {
    form.value.options.wheres.push({ column: '', operator: '=', value: '' });
};

const loadJoinColumns = async (table) => {
    if (!table || !form.value.migration_source_id || joinColumns.value[table]) return;
    try {
        const { data } = await axios.get(
            `/api/admin/migrations/sources/${form.value.migration_source_id}/tables/${encodeURIComponent(table)}/columns`
        );
        joinColumns.value = { ...joinColumns.value, [table]: data.columns };
        joinSamples.value = { ...joinSamples.value, [table]: data.sample || {} };
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    }
};

const onSourceChanged = async () => {
    form.value.source_table = null;
    form.value.options.joins = [];
    form.value.options.wheres = [];
    columns.value = [];
    joinColumns.value = {};
    joinSamples.value = {};
    await loadTables();
};

const onTargetChanged = () => {
    ensureFieldMap();
    preview.value = null;
};

const onTableChanged = async () => {
    preview.value = null;
    await loadColumns();
};

const loadTables = async () => {
    if (!form.value.migration_source_id) return;
    loadingTables.value = true;
    try {
        const { data } = await axios.get(`/api/admin/migrations/sources/${form.value.migration_source_id}/tables`);
        tables.value = data.tables;
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loadingTables.value = false;
    }
};

const loadColumns = async () => {
    if (!form.value.migration_source_id || !form.value.source_table) return;
    loadingColumns.value = true;
    try {
        const { data } = await axios.get(
            `/api/admin/migrations/sources/${form.value.migration_source_id}/tables/${encodeURIComponent(form.value.source_table)}/columns`
        );
        columns.value = data.columns;
        sample.value = data.sample || {};
        rowCount.value = data.rowCount ?? null;
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loadingColumns.value = false;
    }
};

const cleanedForm = () => ({
    ...form.value,
    options: {
        joins: form.value.options.joins
            .filter(join => join.table && join.first && join.second)
            .map(join => ({ ...join, operator: join.operator || '=' })),
        wheres: form.value.options.wheres
            .filter(where => where.column)
            .map(where => ({ ...where, operator: where.operator || '=', value: where.value ?? '' })),
    },
    field_map: Object.fromEntries(
        Object.entries(form.value.field_map).map(([key, spec]) => {
            const cleaned = { ...spec };
            if (!cleaned.source) delete cleaned.source;
            if (!cleaned.format) delete cleaned.format;
            if (cleaned.default === '' || cleaned.default === null) delete cleaned.default;
            if (!cleaned.transform || cleaned.transform === 'none') delete cleaned.transform;
            return [key, cleaned];
        })
    ),
});

const save = async () => {
    saving.value = true;
    error.value = '';
    try {
        if (editing.value) {
            await axios.patch(`/api/admin/migrations/mappings/${editing.value.id}`, cleanedForm());
        } else {
            const { data } = await axios.post('/api/admin/migrations/mappings', cleanedForm());
            editing.value = data;
        }
        await fetchAll();
        emit('notify', { text: t('migrationTool.mappingSaved') });
    } catch (e) {
        error.value = e.response?.data?.message || e.message;
    } finally {
        saving.value = false;
    }
};

const doPreview = async () => {
    previewing.value = true;
    error.value = '';
    try {
        // Preview runs against the saved state — persist first.
        await save();
        if (!editing.value) return;
        const { data } = await axios.post(`/api/admin/migrations/mappings/${editing.value.id}/preview`);
        preview.value = data;
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        previewing.value = false;
    }
};

const run = async (mapping) => {
    if (!confirm(t('migrationTool.confirmRun', { name: mapping.name }))) return;
    runningId.value = mapping.id;
    try {
        const { data } = await axios.post(`/api/admin/migrations/mappings/${mapping.id}/run`);
        emit('run', data.batchId);
        emit('notify', { text: t('migrationTool.importStarted') });
    } catch (e) {
        emit('notify', { text: e.response?.data?.message || e.message, color: 'error' });
    } finally {
        runningId.value = null;
    }
};

const deleteMapping = async (mapping) => {
    if (!confirm(t('migrationTool.confirmDeleteMapping', { name: mapping.name }))) return;
    try {
        await axios.delete(`/api/admin/migrations/mappings/${mapping.id}`);
        await fetchAll();
    } catch (e) {
        emit('notify', { text: e.response?.data?.message || e.message, color: 'error' });
    }
};

onMounted(fetchAll);

defineExpose({ fetchAll });
</script>

<style scoped>
.mapping-grid :deep(td) {
    padding-top: 6px;
    padding-bottom: 6px;
    vertical-align: top;
}

.preview-table {
    max-height: 260px;
    overflow-y: auto;
}

.bg-error-row {
    background: rgba(var(--v-theme-error), 0.06);
}
</style>
