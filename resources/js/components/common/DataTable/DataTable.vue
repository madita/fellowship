<template>
    <div class="flex-grow-1">
        <page-header :title="pageTitle" :subtitle="subtitle" :icon="icon" :back-to="backTo">
            <template v-if="state.response.allow.creation" #actions>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    :disabled="state.loading"
                    @click="openCreate"
                >
                    {{ $t('dataTable.newItem') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <v-card>
                <!-- Toolbar: search, advanced filter, table options -->
                <v-card-text class="pb-3">
                    <div class="d-flex align-center flex-wrap ga-2">
                        <v-text-field
                            v-model="state.searchText"
                            class="data-table__search"
                            prepend-inner-icon="mdi-magnify"
                            :label="$t('dataTable.searchAll')"
                            :loading="state.searchPending"
                            density="compact"
                            single-line
                            hide-details
                            clearable
                            @keyup.enter="applySearchNow"
                            @click:clear="clearSearch"
                        />
                        <v-btn
                            :variant="state.advancedOpen || hasAdvancedFilter ? 'tonal' : 'text'"
                            :color="hasAdvancedFilter ? 'primary' : undefined"
                            prepend-icon="mdi-filter-cog-outline"
                            :append-icon="state.advancedOpen ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                            :aria-expanded="String(state.advancedOpen)"
                            @click="state.advancedOpen = !state.advancedOpen"
                        >
                            {{ $t('dataTable.advancedFilter') }}
                        </v-btn>

                        <v-spacer />

                        <v-btn
                            icon="mdi-refresh"
                            variant="text"
                            :loading="state.loading"
                            :disabled="state.loading"
                            :title="$t('dataTable.refresh')"
                            :aria-label="$t('dataTable.refresh')"
                            @click="getRecords"
                        />
                        <v-menu :close-on-content-click="false" location="bottom end">
                            <template #activator="{ props: menuProps }">
                                <v-btn
                                    v-bind="menuProps"
                                    icon="mdi-view-column-outline"
                                    variant="text"
                                    :disabled="!columnHeaders.length"
                                    :title="$t('dataTable.columns')"
                                    :aria-label="$t('dataTable.columns')"
                                />
                            </template>
                            <v-card min-width="240">
                                <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('dataTable.columns') }}</v-card-title>
                                <v-divider />
                                <v-card-text class="py-2 data-table__columns">
                                    <v-checkbox
                                        v-for="header in columnHeaders"
                                        :key="header.key"
                                        :model-value="isColumnVisible(header.key)"
                                        :label="header.title"
                                        :disabled="isColumnVisible(header.key) && visibleColumnCount <= 1"
                                        density="compact"
                                        hide-details
                                        @update:model-value="setColumnVisible(header.key, $event)"
                                    />
                                </v-card-text>
                                <v-divider />
                                <v-card-actions>
                                    <v-spacer />
                                    <v-btn variant="text" :disabled="!state.hiddenColumns.length" @click="showAllColumns">
                                        {{ $t('dataTable.showAllColumns') }}
                                    </v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-menu>
                        <v-btn
                            :icon="state.density === 'compact' ? 'mdi-arrow-expand-vertical' : 'mdi-arrow-collapse-vertical'"
                            variant="text"
                            :title="densityLabel"
                            :aria-label="densityLabel"
                            @click="toggleDensity"
                        />
                    </div>

                    <!-- Advanced (column / operator / value) filter -->
                    <v-expand-transition>
                        <div v-show="state.advancedOpen">
                            <v-row dense class="align-center mt-3">
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="state.search.column"
                                        :items="filterColumnItems"
                                        item-title="title"
                                        item-value="key"
                                        :label="$t('dataTable.column')"
                                        density="compact"
                                        hide-details
                                    />
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="state.search.operator"
                                        :items="searchOperators"
                                        item-title="text"
                                        item-value="value"
                                        :label="$t('dataTable.operator')"
                                        density="compact"
                                        hide-details
                                    />
                                </v-col>
                                <v-col cols="12" md="5">
                                    <div class="d-flex align-center ga-2">
                                        <v-text-field
                                            v-model="state.search.value"
                                            :label="$t('dataTable.searchValue')"
                                            class="flex-grow-1"
                                            density="compact"
                                            hide-details
                                            clearable
                                            @keyup.enter="applyAdvancedFilter"
                                        />
                                        <v-btn
                                            color="primary"
                                            variant="tonal"
                                            :loading="state.advancedPending"
                                            :disabled="state.loading || isBlank(state.search.value)"
                                            @click="applyAdvancedFilter"
                                        >
                                            {{ $t('dataTable.apply') }}
                                        </v-btn>
                                    </div>
                                </v-col>
                            </v-row>
                        </div>
                    </v-expand-transition>

                    <!-- Toggle filters -->
                    <div v-if="toggleFilters.length" class="d-flex align-center flex-wrap ga-2 mt-3">
                        <span class="text-caption text-medium-emphasis">{{ $t('dataTable.filters') }}:</span>
                        <v-chip
                            v-for="filter in toggleFilters"
                            :key="filter.key"
                            :prepend-icon="filter.icon"
                            :color="state.activeFilters[filter.key] ? 'primary' : undefined"
                            :variant="state.activeFilters[filter.key] ? 'elevated' : 'tonal'"
                            size="small"
                            @click="toggleFilter(filter.key)"
                        >
                            {{ filter.label }}
                        </v-chip>
                    </div>

                    <!-- Active filters -->
                    <div v-if="hasActiveFilters" class="d-flex align-center flex-wrap ga-2 mt-3">
                        <span class="text-caption text-medium-emphasis">{{ $t('dataTable.activeFilters') }}:</span>
                        <v-chip
                            v-if="state.appliedSearch"
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="mdi-magnify"
                            closable
                            :close-label="$t('dataTable.removeFilter')"
                            @click:close="clearSearch"
                        >
                            {{ $t('dataTable.searchChip', { term: state.appliedSearch }) }}
                        </v-chip>
                        <v-chip
                            v-if="state.appliedAdvanced"
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="mdi-filter-cog-outline"
                            closable
                            :close-label="$t('dataTable.removeFilter')"
                            @click:close="clearAdvancedFilter"
                        >
                            {{ advancedFilterLabel }}
                        </v-chip>
                        <v-chip
                            v-for="filter in activeToggleFilters"
                            :key="`active-${filter.key}`"
                            size="small"
                            variant="tonal"
                            color="primary"
                            :prepend-icon="filter.icon"
                            closable
                            :close-label="$t('dataTable.removeFilter')"
                            @click:close="toggleFilter(filter.key)"
                        >
                            {{ filter.label }}
                        </v-chip>
                        <v-btn variant="text" size="small" prepend-icon="mdi-filter-remove-outline" @click="clearAllFilters">
                            {{ $t('dataTable.clearAll') }}
                        </v-btn>
                    </div>
                </v-card-text>

                <!-- Bulk actions -->
                <v-expand-transition>
                    <div v-if="state.selected.length" class="data-table__bulk d-flex align-center flex-wrap ga-2 px-4 py-2">
                        <v-icon icon="mdi-checkbox-multiple-marked-outline" color="primary" size="small" />
                        <span class="text-body-2 font-weight-medium">{{ $t('dataTable.selected', { count: state.selected.length }) }}</span>
                        <v-spacer />
                        <v-btn variant="text" size="small" :disabled="state.deleting" @click="clearSelection">
                            {{ $t('dataTable.clearSelection') }}
                        </v-btn>
                        <v-btn
                            color="error"
                            variant="flat"
                            size="small"
                            prepend-icon="mdi-delete-outline"
                            :loading="state.deleting"
                            :disabled="state.deleting"
                            @click="deleteSelected"
                        >
                            {{ $t('dataTable.deleteSelected') }}
                        </v-btn>
                    </div>
                </v-expand-transition>

                <v-divider />

                <loading-state v-if="!state.response.table && state.loading" :text="$t('dataTable.loading')" />

                <empty-state
                    v-else-if="!state.response.table && state.loadError"
                    icon="mdi-table-alert"
                    :title="$t('dataTable.loadError')"
                    compact
                >
                    <template #actions>
                        <v-btn variant="tonal" prepend-icon="mdi-refresh" @click="getRecords">{{ $t('common.retry') }}</v-btn>
                    </template>
                </empty-state>

                <v-data-table-server
                    v-else
                    v-model="state.selected"
                    v-model:page="state.page"
                    v-model:items-per-page="state.perPage"
                    v-model:sort-by="state.sortBy"
                    class="data-table"
                    :headers="visibleHeaders"
                    :items="records"
                    :items-length="totalItems"
                    :items-per-page-options="perPageOptions"
                    :loading="state.loading"
                    :density="state.density"
                    :show-select="canDelete"
                    item-value="id"
                    hover
                    @click:row="onRowClick"
                >
                    <template #loading>
                        <v-skeleton-loader :type="skeletonType" class="bg-transparent" />
                    </template>

                    <template #no-data>
                        <empty-state
                            :icon="hasActiveFilters ? 'mdi-filter-off-outline' : 'mdi-table-off'"
                            :title="hasActiveFilters ? $t('dataTable.noResults') : $t('dataTable.noData')"
                            :text="hasActiveFilters ? $t('dataTable.noResultsHint') : ''"
                            compact
                        >
                            <template v-if="hasActiveFilters" #actions>
                                <v-btn variant="tonal" prepend-icon="mdi-filter-remove-outline" @click="clearAllFilters">
                                    {{ $t('dataTable.clearFilters') }}
                                </v-btn>
                            </template>
                        </empty-state>
                    </template>

                    <!-- Cells, rendered by column type -->
                    <template v-for="header in cellHeaders" :key="header.key" v-slot:[cellSlot(header.key)]="{ item }">
                        <span v-if="cellFor(header, item).kind === 'empty'" class="text-disabled">{{ cellFor(header, item).text }}</span>
                        <v-chip
                            v-else-if="cellFor(header, item).kind === 'boolean'"
                            size="small"
                            variant="tonal"
                            :color="cellFor(header, item).value ? 'success' : 'grey'"
                            :prepend-icon="cellFor(header, item).value ? 'mdi-check' : 'mdi-close'"
                        >
                            {{ cellFor(header, item).value ? $t('common.yes') : $t('common.no') }}
                        </v-chip>
                        <span
                            v-else-if="cellFor(header, item).kind === 'date' || cellFor(header, item).kind === 'datetime'"
                            class="text-no-wrap"
                            :title="formatCellDate(cellFor(header, item).value, cellFor(header, item).kind, true)"
                        >
                            {{ formatCellDate(cellFor(header, item).value, cellFor(header, item).kind) }}
                        </span>
                        <span v-else-if="cellFor(header, item).kind === 'number'" class="text-no-wrap">{{ cellFor(header, item).text }}</span>
                        <v-chip
                            v-else-if="cellFor(header, item).kind === 'json'"
                            size="small"
                            variant="tonal"
                            prepend-icon="mdi-code-json"
                            :title="cellFor(header, item).title"
                        >
                            {{ $t('dataTable.entries', cellFor(header, item).count) }}
                        </v-chip>
                        <span
                            v-else-if="cellFor(header, item).kind === 'longtext'"
                            class="data-table__longtext"
                            :title="cellFor(header, item).title"
                        >{{ cellFor(header, item).text }}</span>
                        <v-avatar v-else-if="cellFor(header, item).kind === 'image'" size="32" rounded>
                            <v-img :src="cellFor(header, item).src" cover />
                        </v-avatar>
                        <span v-else-if="cellFor(header, item).kind === 'id'" class="text-medium-emphasis">{{ cellFor(header, item).text }}</span>
                        <span v-else :title="cellFor(header, item).title">{{ cellFor(header, item).text }}</span>
                    </template>

                    <template #item.actions="{ item }">
                        <div class="d-flex align-center justify-end ga-1" @click.stop>
                            <v-btn
                                icon="mdi-pencil-outline"
                                variant="text"
                                size="small"
                                :disabled="isRowBusy(item)"
                                :title="$t('common.edit')"
                                :aria-label="$t('common.edit')"
                                @click="openEdit(item)"
                            />
                            <v-menu v-if="hasRowMenu" location="bottom end">
                                <template #activator="{ props: menuProps }">
                                    <v-btn
                                        v-bind="menuProps"
                                        icon="mdi-dots-vertical"
                                        variant="text"
                                        size="small"
                                        :loading="isRowBusy(item)"
                                        :disabled="isRowBusy(item) || state.deleting"
                                        :title="$t('dataTable.moreActions')"
                                        :aria-label="$t('dataTable.moreActions')"
                                    />
                                </template>
                                <v-list density="compact" min-width="180">
                                    <v-list-item
                                        v-if="state.response.allow.hasForm"
                                        prepend-icon="mdi-lightning-bolt-outline"
                                        :title="$t('dataTable.quickEdit')"
                                        @click="editItem(item)"
                                    />
                                    <v-list-item
                                        v-if="canDelete"
                                        prepend-icon="mdi-delete-outline"
                                        base-color="error"
                                        :title="$t('common.delete')"
                                        @click="deleteItem(item)"
                                    />
                                </v-list>
                            </v-menu>
                        </div>
                    </template>
                </v-data-table-server>
            </v-card>

            <!-- Quick edit dialog -->
            <v-dialog v-model="state.dialog" max-width="600" scrollable>
                <v-card>
                    <v-card-title class="text-h6">{{ formTitle }}</v-card-title>
                    <v-divider />

                    <v-card-text>
                        <template v-if="state.editedItem !== null">
                            <div v-for="column in state.response.updatable" :key="`card-${column}`" class="mb-2">
                                <template v-if="typeof(state.response.column_fields[column]) === 'object'">
                                    <v-select
                                        v-if="'select' in state.response.column_fields[column]"
                                        v-model="state.editedItem[column]"
                                        :items="state.response.column_fields[column]['select']"
                                        :label="fieldLabel(column)"
                                    />
                                </template>
                                <v-textarea
                                    v-else-if="state.response.column_fields[column] === 'textarea'"
                                    :id="column"
                                    v-model="state.editedItem[column]"
                                    :label="fieldLabel(column)"
                                />

                                <template v-else-if="state.response.column_fields[column] === 'wysiwyg'">
                                    <div class="text-caption text-medium-emphasis mb-1">{{ fieldLabel(column) }}</div>
                                    <Tiptap
                                        :id="`text-content-${column}`"
                                        v-model="state.editedItem[column]"
                                        :name="`content-${column}`"
                                    />
                                </template>

                                <v-checkbox
                                    v-else-if="state.response.column_fields[column] === 'checkbox'"
                                    v-model="state.editedItem[column]"
                                    :label="fieldLabel(column)"
                                />

                                <v-text-field
                                    v-else
                                    v-model="state.editedItem[column]"
                                    :label="fieldLabel(column)"
                                />
                            </div>
                        </template>
                    </v-card-text>

                    <v-divider />
                    <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" :disabled="state.saving" @click="close">
                            {{ $t('common.cancel') }}
                        </v-btn>
                        <v-btn
                            color="primary"
                            variant="flat"
                            :loading="state.saving"
                            :disabled="state.saving"
                            @click="save"
                        >
                            {{ $t('common.save') }}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <DataTableForm
                v-model:isDrawerOpen="isSidebarActive"
                :item="state.editedItem"
                :defaultItem="state.defaultItem"
                :response="state.response"
                :endpoint="endpoint"
                @add-item="addItem"
                @update-item="updateItem"
                @remove-item="deleteItem"
            />
        </v-container>
    </div>
</template>

<script>
import { ref, reactive, computed, nextTick, onMounted, onBeforeUnmount, watch, toRaw } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useApi } from '@/api/useAPI.js'
import { useDialog } from '@/composables/useDialog.js'
import { formatDate } from '@/plugins/formatDate.js'
import { useSettingsStore } from '@/store/settingStore.js'
import { useUserStore } from '@/store/userStore.js'
import {
    DEFAULT_PER_PAGE_OPTIONS,
    describeCell,
    normalizeHeaders,
    fieldLabel as resolveFieldLabel,
    humanizeKey,
    parseTableQuery,
    buildTableQuery,
    sameQuery,
} from '@/utils/dataTableCells.js'
import DataTableForm from './DataTableForm.vue'
import Tiptap from '../tiptap/Tiptap.vue'
import PageHeader from '../PageHeader.vue'
import EmptyState from '../EmptyState.vue'
import LoadingState from '../LoadingState.vue'

const SEARCH_DEBOUNCE_MS = 350
const DEFAULT_PER_PAGE = 10

const emptyResponse = () => ({
    table: null,
    headers: [],
    records: { data: [], current_page: 1, per_page: DEFAULT_PER_PAGE, total: 0, last_page: 1 },
    displayable: [],
    updatable: [],
    column_map: {},
    column_fields: {},
    toggle_filters: [],
    searchable: [],
    per_page_options: DEFAULT_PER_PAGE_OPTIONS,
    allow: {},
})

// Paginator (or, from older endpoints, a plain array / an index-keyed object) → paginator shape
const normalizeRecords = (records, perPage) => {
    if (Array.isArray(records)) {
        return { data: records, current_page: 1, per_page: perPage, total: records.length, last_page: 1 }
    }
    const r = records || {}
    const data = Array.isArray(r.data) ? r.data : (r.data && typeof r.data === 'object' ? Object.values(r.data) : [])
    return {
        ...r,
        data,
        current_page: Number(r.current_page) || 1,
        per_page: Number(r.per_page) || perPage,
        total: Number(r.total ?? data.length) || 0,
        last_page: Number(r.last_page) || 1,
    }
}

export default {
    name: 'DataTable',
    components: {
        Tiptap,
        DataTableForm,
        PageHeader,
        EmptyState,
        LoadingState
    },
    props: {
        endpoint: {
            type: String,
            required: true
        },
        // Page title; falls back to a humanised table name from the response
        title: {
            type: String,
            default: ''
        },
        subtitle: {
            type: String,
            default: ''
        },
        icon: {
            type: String,
            default: 'mdi-table'
        },
        // Route of the settings category (or page) this table belongs to
        backTo: {
            type: [String, Object],
            default: null
        }
    },
    setup(props) {
        const { t } = useI18n()
        const api = useApi()
        const dialog = useDialog()
        const route = useRoute()
        const router = useRouter()
        const settingsStore = useSettingsStore()
        const userStore = useUserStore()
        const isSidebarActive = ref(false)

        // --- Per-endpoint view preferences (column visibility, density) -------------
        const prefsKey = `datatable:${props.endpoint}`
        const loadPrefs = () => {
            try {
                const stored = JSON.parse(localStorage.getItem(prefsKey) || '{}') || {}
                return {
                    hidden: Array.isArray(stored.hidden) ? stored.hidden.filter(k => typeof k === 'string') : [],
                    density: stored.density === 'compact' ? 'compact' : 'comfortable',
                }
            } catch {
                return { hidden: [], density: 'comfortable' }
            }
        }
        const savePrefs = () => {
            try {
                localStorage.setItem(prefsKey, JSON.stringify({ hidden: state.hiddenColumns, density: state.density }))
            } catch {
                // Storage unavailable (private mode, quota) — the view just isn't remembered
            }
        }

        // --- State (page / size / sort / search restored from the URL) -------------
        const initial = parseTableQuery(route.query, { perPageOptions: DEFAULT_PER_PAGE_OPTIONS, defaultPerPage: DEFAULT_PER_PAGE })
        const prefs = loadPrefs()
        const tablePath = route.path

        const state = reactive({
            dialog: false,
            loading: true,
            loadError: false,
            saving: false,
            deleting: false,
            editing: {
                id: null,
                errors: []
            },
            editedItem: {},
            editedIndex: -1,
            defaultItem: {},
            page: initial.page,
            perPage: initial.perPage,
            sortBy: initial.sortBy ? [{ key: initial.sortBy, order: initial.sortDir }] : [],
            searchText: initial.search,
            appliedSearch: initial.search,
            searchPending: false,
            advancedOpen: false,
            advancedPending: false,
            // Draft of the advanced filter; `appliedAdvanced` is what is sent
            search: {
                column: 'id',
                operator: 'equals',
                value: null,
            },
            appliedAdvanced: null,
            activeFilters: {},
            selected: [],
            rowBusy: {},
            hiddenColumns: prefs.hidden,
            density: prefs.density,
            response: emptyResponse(),
        })

        const pageTitle = computed(() => {
            if (props.title) return props.title
            const table = state.response.table
            if (!table) return t('dataTable.list')
            return table.replace(/[_-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        })

        const searchOperators = computed(() => [
            { text: t('dataTable.operatorEquals'), value: 'equals' },
            { text: t('dataTable.operatorContains'), value: 'contains' },
            { text: t('dataTable.operatorStartsWith'), value: 'starts_with' },
            { text: t('dataTable.operatorEndsWith'), value: 'ends_with' },
            { text: t('dataTable.operatorGreaterThan'), value: 'greater_than' },
            { text: t('dataTable.operatorLessThan'), value: 'less_than' },
            { text: t('dataTable.operatorGreaterThanOrEqual'), value: 'greater_than_or_equal_to' },
            { text: t('dataTable.operatorLessThanOrEqual'), value: 'less_than_or_equal_to' },
        ])

        const formTitle = computed(() => {
            return state.editedIndex === -1 ? t('dataTable.newItem') : t('dataTable.editItem')
        })

        // --- Columns ---------------------------------------------------------------
        const allHeaders = computed(() => normalizeHeaders(state.response.headers, {
            actionsTitle: t('dataTable.actions'),
            columnMap: state.response.column_map || {},
        }))
        const columnHeaders = computed(() => allHeaders.value.filter(h => h.key !== 'actions'))
        const visibleHeaders = computed(() => allHeaders.value.filter(h => h.key === 'actions' || !state.hiddenColumns.includes(h.key)))
        const cellHeaders = computed(() => visibleHeaders.value.filter(h => h.key !== 'actions'))
        const visibleColumnCount = computed(() => cellHeaders.value.length)

        const isColumnVisible = key => !state.hiddenColumns.includes(key)
        const setColumnVisible = (key, visible) => {
            const hidden = state.hiddenColumns.filter(k => k !== key)
            if (!visible) hidden.push(key)
            state.hiddenColumns = hidden
            savePrefs()
        }
        const showAllColumns = () => {
            state.hiddenColumns = []
            savePrefs()
        }

        const densityLabel = computed(() => `${t('dataTable.density')}: ${t(state.density === 'compact' ? 'dataTable.compact' : 'dataTable.comfortable')}`)
        const toggleDensity = () => {
            state.density = state.density === 'compact' ? 'comfortable' : 'compact'
            savePrefs()
        }

        const fieldLabel = column => resolveFieldLabel(column, {
            columnMap: state.response.column_map || {},
            headers: columnHeaders.value,
        })

        // --- Records & cells -------------------------------------------------------
        const records = computed(() => state.response.records.data || [])
        const totalItems = computed(() => state.response.records.total || 0)
        const perPageOptions = computed(() => {
            const options = (state.response.per_page_options || [])
                .map(Number)
                .filter(n => Number.isInteger(n) && n > 0)
            return options.length ? options : DEFAULT_PER_PAGE_OPTIONS
        })
        const skeletonType = computed(() => `table-row-divider@${Math.min(state.perPage, 10)}`)

        const cellRows = computed(() => {
            const rows = new Map()
            records.value.forEach(record => {
                const row = {}
                cellHeaders.value.forEach(header => {
                    row[header.key] = describeCell(header, record[header.key])
                })
                rows.set(toRaw(record), row)
            })
            return rows
        })
        const cellFor = (header, item) => {
            const row = cellRows.value.get(toRaw(item))
            return (row && row[header.key]) || describeCell(header, item[header.key])
        }
        const cellSlot = key => `item.${key}`

        const formatCellDate = (value, kind, full = false) => {
            const dateFormat = userStore.userDateFormat || settingsStore.defaultDateFormat || 'Y-m-d'
            const timeFormat = userStore.userTimeFormat || settingsStore.defaultTimeFormat || 'H:i:s'
            try {
                const formatted = kind === 'date'
                    // Date-only values: no timezone shift, or midnight UTC turns into "yesterday"
                    ? formatDate(String(value).slice(0, 10), dateFormat, { useTimezone: false })
                    : formatDate(value, `${dateFormat} ${full ? timeFormat : timeFormat.replace(/:s/, '')}`)
                return formatted === 'Invalid Date' ? String(value) : formatted
            } catch {
                return String(value)
            }
        }

        // --- Permissions / flags ---------------------------------------------------
        const canDelete = computed(() => !!state.response.allow.deletion)
        const hasRowMenu = computed(() => !!state.response.allow.hasForm || canDelete.value)
        const isRowBusy = item => !!(item && state.rowBusy[item.id])

        // --- Filters ---------------------------------------------------------------
        const toggleFilters = computed(() => state.response.toggle_filters || [])
        const activeToggleFilters = computed(() => toggleFilters.value.filter(f => state.activeFilters[f.key]))
        const hasAdvancedFilter = computed(() => !!state.appliedAdvanced)
        const hasActiveFilters = computed(() => !!state.appliedSearch || hasAdvancedFilter.value || activeToggleFilters.value.length > 0)

        const filterColumnItems = computed(() => {
            if (columnHeaders.value.length) return columnHeaders.value.map(h => ({ key: h.key, title: h.title }))
            return (state.response.displayable || []).map(c => ({ key: c, title: humanizeKey(c) }))
        })

        const advancedFilterLabel = computed(() => {
            const f = state.appliedAdvanced
            if (!f) return ''
            const column = filterColumnItems.value.find(c => c.key === f.column)
            const operator = searchOperators.value.find(o => o.value === f.operator)
            return `${column ? column.title : humanizeKey(f.column)} ${operator ? operator.text : f.operator} "${f.value}"`
        })

        const isBlank = value => value === null || value === undefined || String(value).trim() === ''

        // Filters narrow the result set, so they reset the page and the selection
        const resetPaging = () => {
            state.page = 1
            state.selected = []
        }

        let searchTimer = null
        const cancelSearchTimer = () => {
            if (searchTimer) clearTimeout(searchTimer)
            searchTimer = null
        }

        const applySearch = (term) => {
            cancelSearchTimer()
            if (term === state.appliedSearch) {
                state.searchPending = false
                return
            }
            state.searchPending = true
            state.appliedSearch = term
            resetPaging()
        }

        watch(() => state.searchText, (value) => {
            const term = (value || '').trim()
            cancelSearchTimer()
            if (term === state.appliedSearch) {
                state.searchPending = false
                return
            }
            state.searchPending = true
            searchTimer = setTimeout(() => applySearch(term), SEARCH_DEBOUNCE_MS)
        })

        const applySearchNow = () => applySearch((state.searchText || '').trim())

        const clearSearch = () => {
            state.searchText = ''
            applySearch('')
        }

        const applyAdvancedFilter = () => {
            if (isBlank(state.search.value)) {
                clearAdvancedFilter()
                return
            }
            const next = {
                column: state.search.column,
                operator: state.search.operator,
                value: String(state.search.value).trim(),
            }
            state.advancedPending = true
            if (JSON.stringify(next) === JSON.stringify(state.appliedAdvanced)) {
                getRecords()
                return
            }
            state.appliedAdvanced = next
            resetPaging()
        }

        const clearAdvancedFilter = () => {
            state.search.value = null
            if (!state.appliedAdvanced) return
            state.appliedAdvanced = null
            resetPaging()
        }

        const toggleFilter = (key) => {
            state.activeFilters = { ...state.activeFilters, [key]: !state.activeFilters[key] }
            resetPaging()
        }

        const clearAllFilters = () => {
            cancelSearchTimer()
            state.searchText = ''
            state.appliedSearch = ''
            state.search.value = null
            state.appliedAdvanced = null
            state.activeFilters = {}
            resetPaging()
        }

        // --- Loading -----------------------------------------------------------------
        const requestParams = computed(() => {
            const params = { page: state.page, per_page: state.perPage }
            const sort = state.sortBy[0]
            if (sort && sort.key) {
                params.sort_by = sort.key
                params.sort_dir = sort.order === 'desc' ? 'desc' : 'asc'
            }
            if (state.appliedSearch) params.search = state.appliedSearch
            if (state.appliedAdvanced) Object.assign(params, state.appliedAdvanced)
            Object.entries(state.activeFilters).forEach(([key, active]) => {
                if (active) params[key] = 1
            })
            return params
        })

        const applyResponse = (data) => {
            const response = { ...emptyResponse(), ...data }
            response.records = normalizeRecords(data.records, state.perPage)
            response.allow = data.allow || {}
            response.column_fields = data.column_fields || {}
            state.response = response

            const defaults = {}
            ;(response.updatable || []).forEach(column => { defaults[column] = '' })
            state.defaultItem = defaults
            if (!state.dialog && !isSidebarActive.value) {
                state.editedItem = { ...defaults }
                state.editedIndex = -1
            }

            // Keep the advanced filter's column pointing at a real column
            const columns = filterColumnItems.value.map(c => c.key)
            if (columns.length && !columns.includes(state.search.column)) {
                state.search.column = columns[0]
            }

            // The page ran empty (e.g. after deleting its last rows): step back
            const lastPage = response.records.last_page || 1
            if (!response.records.data.length && state.page > lastPage) {
                state.page = lastPage
            }
        }

        let requestSeq = 0
        const getRecords = async () => {
            const seq = ++requestSeq
            state.loading = true
            try {
                const { data } = await api.get(props.endpoint, { params: requestParams.value })
                if (seq !== requestSeq) return
                applyResponse((data && data.data) || {})
                state.loadError = false
            } catch (error) {
                if (seq !== requestSeq) return
                state.loadError = true
                dialog.requestError(error, t('errors.general'))
            } finally {
                if (seq === requestSeq) {
                    state.loading = false
                    state.advancedPending = false
                    if (!searchTimer) state.searchPending = false
                }
            }
        }

        // --- URL state -----------------------------------------------------------------
        const syncUrl = () => {
            if (route.path !== tablePath) return
            const sort = state.sortBy[0]
            const query = buildTableQuery(route.query, {
                page: state.page,
                perPage: state.perPage,
                sortBy: sort ? sort.key : null,
                sortDir: sort ? sort.order : 'asc',
                search: state.appliedSearch,
            }, { defaultPerPage: DEFAULT_PER_PAGE })
            if (sameQuery(query, route.query)) return
            router.replace({ path: route.path, query, hash: route.hash }).catch(() => {})
        }

        // A new sort starts at page 1 (registered before the fetch watcher so both land in one request)
        watch(() => JSON.stringify(state.sortBy), () => {
            state.page = 1
        })

        // One request per change of page, size, sort, search or filters
        watch(() => JSON.stringify(requestParams.value), () => {
            syncUrl()
            getRecords()
        })

        // The URL changed from outside (menu link, manual edit): adopt it
        watch(() => route.query, (query) => {
            if (route.path !== tablePath) return
            const next = parseTableQuery(query, { perPageOptions: perPageOptions.value, defaultPerPage: DEFAULT_PER_PAGE })
            const sort = state.sortBy[0]
            const sameSort = (next.sortBy || null) === (sort ? sort.key : null)
                && (!next.sortBy || next.sortDir === (sort.order === 'desc' ? 'desc' : 'asc'))
            if (next.page === state.page && next.perPage === state.perPage && next.search === state.appliedSearch && sameSort) return

            cancelSearchTimer()
            state.perPage = next.perPage
            state.sortBy = next.sortBy ? [{ key: next.sortBy, order: next.sortDir }] : []
            state.searchText = next.search
            state.appliedSearch = next.search
            // After the sort watcher's reset to page 1
            nextTick(() => { state.page = next.page })
        })

        // --- Create / edit -------------------------------------------------------------
        const openCreate = () => {
            state.editedIndex = -1
            state.editing.id = null
            state.editedItem = { ...state.defaultItem }
            isSidebarActive.value = true
        }

        const indexOfRecord = item => records.value.findIndex(r => r.id === item.id)

        // Quick edit dialog
        const editItem = (item) => {
            state.editedIndex = Math.max(0, indexOfRecord(item))
            state.editing.id = item.id
            state.editedItem = Object.assign({}, item)
            state.dialog = true
        }

        // Form drawer
        const editItemForm = (item) => {
            state.editedIndex = Math.max(0, indexOfRecord(item))
            state.editing.id = item.id
            state.editedItem = Object.assign({}, item)
            isSidebarActive.value = true
        }

        // Primary row action: the full form when the table has one, the quick edit otherwise
        const openEdit = (item) => {
            if (state.response.allow.hasForm) {
                editItemForm(item)
            } else {
                editItem(item)
            }
        }

        const onRowClick = (event, { item } = {}) => {
            if (!item || state.deleting || isRowBusy(item)) return
            // Selecting text in a cell is not a click on the row
            const selection = typeof window !== 'undefined' && window.getSelection ? String(window.getSelection() || '') : ''
            if (selection) return
            openEdit(item)
        }

        const close = () => {
            state.dialog = false
            nextTick(() => {
                state.editedItem = Object.assign({}, state.defaultItem)
                state.editedIndex = -1
            })
        }

        const save = () => {
            if (state.saving) return
            if (state.editedIndex > -1) {
                update()
            } else {
                store()
            }
        }

        const addItem = async (newItem) => {
            if (state.saving) return
            state.saving = true
            try {
                await axios.post(`/api${props.endpoint}`, newItem)
                state.saving = false
                dialog.success(t('success.created'))
                await getRecords()
            } catch (error) {
                if (error.response?.status === 422) {
                    state.editing.errors = error.response.data
                }
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.saving = false
            }
        }

        const updateItem = async (item) => {
            if (state.saving) return
            state.saving = true
            try {
                await axios.patch(`/api${props.endpoint}/${item.id}`, item)
                state.saving = false
                dialog.success(t('success.updated'))
                state.editing.id = null
                state.editedIndex = -1
                await getRecords()
                state.editedItem = Object.assign({}, state.defaultItem)
            } catch (error) {
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.saving = false
            }
        }

        const update = async () => {
            state.saving = true
            try {
                await axios.patch(`/api${props.endpoint}/${state.editing.id}`, state.editedItem)
                state.saving = false
                close()
                dialog.success(t('success.updated'))
                await getRecords()
                state.editing.id = null
            } catch (error) {
                if (error.response?.status === 422) {
                    state.editing.errors = error.response.data
                }
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.saving = false
            }
        }

        const store = async () => {
            state.saving = true
            try {
                await axios.post(`/api${props.endpoint}`, state.editedItem)
                state.saving = false
                close()
                dialog.success(t('success.created'))
                await getRecords()
            } catch (error) {
                if (error.response?.status === 422) {
                    state.editing.errors = error.response.data
                }
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.saving = false
            }
        }

        // --- Delete ----------------------------------------------------------------------
        const setRowBusy = (id, busy) => {
            const next = { ...state.rowBusy }
            if (busy) next[id] = busy
            else delete next[id]
            state.rowBusy = next
        }

        const destroy = async (ids, rowId = null) => {
            state.deleting = true
            if (rowId !== null) setRowBusy(rowId, 'delete')
            try {
                const { data } = await axios.delete(`/api${props.endpoint}/${ids.join(',')}`)
                const removed = ids.map(String)
                state.selected = state.selected.filter(id => !removed.includes(String(id)))
                dialog.success((data && typeof data.message === 'string' && data.message) || t('success.deleted'))
                await getRecords()
            } catch (error) {
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.deleting = false
                if (rowId !== null) setRowBusy(rowId, null)
            }
        }

        // Confirms, then deletes one record (object or id) or a list of them in one request.
        const deleteItem = async (record) => {
            if (state.deleting) return
            const list = Array.isArray(record) ? record : [record]
            const ids = list.map(entry => (entry && typeof entry === 'object' ? entry.id : entry)).filter(id => id !== null && id !== undefined && id !== '')
            if (!ids.length) return

            const ok = await dialog.confirmDelete(
                ids.length > 1
                    ? t('dataTable.deleteSelectedConfirm', { count: ids.length })
                    : t('dataTable.deleteConfirm')
            )
            if (!ok) return

            await destroy(ids, Array.isArray(record) ? null : ids[0])
        }

        const deleteSelected = () => deleteItem([...state.selected])
        const clearSelection = () => {
            state.selected = []
        }

        onMounted(() => {
            getRecords()
        })

        onBeforeUnmount(() => {
            cancelSearchTimer()
        })

        return {
            // state & labels
            state,
            pageTitle,
            formTitle,
            searchOperators,
            densityLabel,
            isSidebarActive,
            // columns
            columnHeaders,
            visibleHeaders,
            cellHeaders,
            visibleColumnCount,
            isColumnVisible,
            setColumnVisible,
            showAllColumns,
            toggleDensity,
            fieldLabel,
            // records & cells
            records,
            totalItems,
            perPageOptions,
            skeletonType,
            cellFor,
            cellSlot,
            formatCellDate,
            // flags
            canDelete,
            hasRowMenu,
            isRowBusy,
            // filters
            toggleFilters,
            activeToggleFilters,
            hasAdvancedFilter,
            hasActiveFilters,
            filterColumnItems,
            advancedFilterLabel,
            isBlank,
            applySearchNow,
            clearSearch,
            applyAdvancedFilter,
            clearAdvancedFilter,
            toggleFilter,
            clearAllFilters,
            // actions
            getRecords,
            openCreate,
            openEdit,
            onRowClick,
            editItem,
            editItemForm,
            close,
            save,
            addItem,
            updateItem,
            deleteItem,
            deleteSelected,
            clearSelection,
        }
    }
}
</script>

<style scoped>
.data-table__search {
    flex: 1 1 260px;
    max-width: 420px;
}

.data-table__bulk {
    background: rgba(var(--v-theme-primary), 0.08);
}

.data-table__columns {
    max-height: 320px;
    overflow-y: auto;
}

.data-table__longtext {
    display: block;
    min-width: 160px;
    max-width: 420px;
}
</style>
