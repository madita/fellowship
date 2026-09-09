<template>
    <div class="flex-grow-1">
        <page-header :title="pageTitle" :subtitle="subtitle" :icon="icon">
            <template v-if="state.response.allow.creation" #actions>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    :disabled="state.loading"
                    @click="isSidebarActive = true"
                >
                    {{ $t('dataTable.newItem') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <v-card>
                <v-card-text>
                    <v-row dense class="align-center">
                        <!-- Columns Dropdown -->
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="state.search.column"
                                :items="state.response.displayable"
                                :label="$t('dataTable.columns')"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <!-- Operators Dropdown -->
                        <v-col cols="12" md="2">
                            <v-select
                                v-model="state.search.operator"
                                :items="state.searchOperators"
                                item-title="text"
                                item-value="value"
                                :label="$t('dataTable.operators')"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <!-- Search Input and Buttons -->
                        <v-col cols="12" md="6">
                            <div class="d-flex align-center flex-wrap ga-2">
                                <v-text-field
                                    v-model="state.search.value"
                                    append-inner-icon="mdi-magnify"
                                    :label="$t('dataTable.searchValue')"
                                    class="flex-grow-1"
                                    hide-details
                                    density="compact"
                                    clearable
                                    @keyup.enter="getRecords"
                                />

                                <v-btn
                                    color="primary"
                                    variant="tonal"
                                    :loading="state.loading"
                                    :disabled="state.loading"
                                    @click="getRecords"
                                >
                                    {{ $t('dataTable.search') }}
                                </v-btn>

                                <v-btn
                                    icon="mdi-refresh"
                                    variant="text"
                                    :disabled="state.loading"
                                    :aria-label="$t('dataTable.refresh')"
                                    :title="$t('dataTable.refresh')"
                                    @click="getRecords"
                                />
                            </div>
                        </v-col>
                    </v-row>

                    <!-- Toggle Filters -->
                    <div
                        v-if="state.response.toggle_filters && state.response.toggle_filters.length"
                        class="d-flex align-center flex-wrap ga-2 mt-3"
                    >
                        <span class="text-caption text-medium-emphasis">{{ $t('dataTable.filters') }}:</span>
                        <v-chip
                            v-for="filter in state.response.toggle_filters"
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
                </v-card-text>

                <v-divider />

                <!-- Table toolbar -->
                <div class="d-flex align-center flex-wrap ga-2 pa-4">
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <transition name="slide-fade" mode="out-in">
                                <v-btn
                                    v-show="state.selected.length > 0"
                                    variant="tonal"
                                    append-icon="mdi-menu-down"
                                    v-bind="menuProps"
                                >
                                    {{ $t('dataTable.actions') }}
                                </v-btn>
                            </transition>
                        </template>
                        <v-list density="compact">
                            <v-list-item :disabled="state.deleting" @click="deleteItem(state.selected)">
                                <template v-if="state.deleting" v-slot:prepend>
                                    <v-progress-circular indeterminate size="16" width="2" />
                                </template>
                                <v-list-item-title>{{ $t('common.delete') }}</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                    <v-spacer />
                    <v-text-field
                        v-model="state.quickSearchQuery"
                        append-inner-icon="mdi-magnify"
                        :label="$t('dataTable.quickSearch')"
                        density="compact"
                        single-line
                        hide-details
                        clearable
                        class="data-table__quick-search"
                    />
                </div>

                <v-progress-linear v-if="state.loading && state.response.table" indeterminate color="primary" />

                <loading-state v-if="state.loading && !state.response.table" :text="$t('dataTable.loading')" />

                <empty-state
                    v-else-if="!state.loading && filteredRecords.length === 0"
                    icon="mdi-table-off"
                    :title="hasActiveSearch ? $t('dataTable.noResults') : $t('dataTable.noData')"
                    compact
                >
                    <template v-if="hasActiveSearch" #actions>
                        <v-btn variant="tonal" prepend-icon="mdi-filter-remove-outline" @click="resetRecords">
                            {{ $t('dataTable.clearFilter') }}
                        </v-btn>
                    </template>
                </empty-state>

                <AppDataTable
                    v-else-if="getHeaders && getHeaders.length > 0"
                    v-model="state.page"
                    :items="filteredRecords"
                    :headers="getHeaders"
                    :page="state.page"
                    :links="state.response.records.links"
                    :itemsPerPage="state.response.records.per_page"
                    :pageCount="state.response.records.last_page"
                    :server-items-length="state.response.records.total"
                    :search="state.quickSearchQuery"
                    :loading="state.loading || state.deleting"
                >
                    <template v-slot:item.created_at="{ item }">
                        {{ $formatDate(item.created_at, 'Y-m-d H:i') }}
                    </template>

                    <template #actions="{ item }">
                        <div class="d-flex align-center ga-1">
                            <v-btn
                                v-if="state.response.allow.hasForm"
                                icon="mdi-file-document-edit"
                                variant="text"
                                size="small"
                                :aria-label="$t('dataTable.editItem')"
                                :title="$t('dataTable.editItem')"
                                @click="editItemForm(item)"
                            />
                            <v-btn
                                icon="mdi-pencil"
                                variant="text"
                                size="small"
                                :aria-label="$t('common.edit')"
                                :title="$t('common.edit')"
                                @click="editItem(item)"
                            />
                            <v-btn
                                icon="mdi-delete"
                                variant="text"
                                size="small"
                                color="error"
                                :disabled="state.deleting"
                                :aria-label="$t('common.delete')"
                                :title="$t('common.delete')"
                                @click="deleteItem(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </v-card>

            <!-- Quick edit dialog -->
            <v-dialog v-model="state.dialog" max-width="600">
                <v-card>
                    <v-card-title class="text-h6">{{ formTitle }}</v-card-title>
                    <v-divider />

                    <v-card-text>
                        <template v-if="state.editedItem !== null">
                            <div v-for="column in state.response.updatable" :key="`card-${column}`" class="mb-2">
                                <template v-if="typeof(state.response.column_fields[column]) === 'object'">
                                    <v-select
                                        v-if="'select' in state.response.column_fields[column]"
                                        :items="state.response.column_fields[column]['select']"
                                        v-model="state.editedItem[column]"
                                        :label="column"
                                    ></v-select>
                                </template>
                                <v-textarea
                                    v-else-if="state.response.column_fields[column]==='textarea'"
                                    :label="column"
                                    :id="column"
                                    v-model="state.editedItem[column]"
                                ></v-textarea>

                                <Tiptap
                                    v-else-if="state.response.column_fields[column]==='wysiwyg'"
                                    v-model="state.editedItem[column]"
                                    :id="`text-content-${column}`"
                                    :name="`content-${column}`"
                                />

                                <v-checkbox
                                    v-else-if="state.response.column_fields[column]==='checkbox'"
                                    v-model="state.editedItem[column]"
                                    :label="column"
                                ></v-checkbox>

                                <v-text-field
                                    v-else
                                    v-model="state.editedItem[column]"
                                    :label="column"
                                ></v-text-field>
                            </div>
                        </template>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn
                            variant="text"
                            :disabled="state.saving"
                            @click="close"
                        >
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
import { ref, reactive, computed, nextTick, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useApi } from '@/api/useAPI.js'
import { useDialog } from '@/composables/useDialog.js'
import AppDataTable from '../AppDataTable.vue'
import DataTableForm from './DataTableForm.vue'
import Tiptap from '../tiptap/Tiptap.vue'
import PageHeader from '../PageHeader.vue'
import EmptyState from '../EmptyState.vue'
import LoadingState from '../LoadingState.vue'

export default {
    name: 'DataTable',
    components: {
        Tiptap,
        AppDataTable,
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
        }
    },
    setup(props) {
        const { t } = useI18n()
        const api = useApi()
        const dialog = useDialog()
        const isSidebarActive = ref(false)

        const pageTitle = computed(() => {
            if (props.title) return props.title
            const table = state.response.table
            if (!table) return t('dataTable.list')
            return table.replace(/[_-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        })

        const hasActiveSearch = computed(() => !!(state.quickSearchQuery || state.search.value))

        const state = reactive({
            dialog: false,
            loading: true,
            saving: false,
            deleting: false,
            formTitle: '',
            creating: {
                active: false,
                form: {},
                errors: []
            },
            editing: {
                id: null,
                form: {},
                errors: []
            },
            pagination: {
                itemsPerPage: 10
            },
            editedItem: {},
            editedIndex: -1,
            defaultItem: {},
            page: 0,
            numberOfPages: 0,
            sort: {
                key: 'id',
                order: 'asc'
            },
            search: {
                column: 'id',
                operator: 'equals',
                value: null,
            },
            searchOperators: [],
            quickSearchQuery: '',
            response: {
                table: null,
                records: {
                    data: [],
                    current_page: 1,
                    per_page: 10,
                    last_page: 1,
                    total: 0,
                    links: []
                },
                displayable: [],
                updatable: [],
                allow: {},
                headers: [],
                column_fields: {}
            },
            activeFilters: {},
            selected: [],
            rules: {
                required: value => !!value || t('dataTable.required'),
                email: value => {
                    const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
                    return pattern.test(value) || t('dataTable.invalidEmail')
                },
            }
        })

        // Initialize search operators with translations
        const initSearchOperators = () => {
            state.searchOperators = [
                { text: t('dataTable.operatorEquals'), value: 'equals' },
                { text: t('dataTable.operatorContains'), value: 'contains' },
                { text: t('dataTable.operatorStartsWith'), value: 'starts_with' },
                { text: t('dataTable.operatorEndsWith'), value: 'ends_with' },
                { text: t('dataTable.operatorGreaterThan'), value: 'greater_than' },
                { text: t('dataTable.operatorLessThan'), value: 'less_than' },
                { text: t('dataTable.operatorGreaterThanOrEqual'), value: 'greater_than_or_equal_to' },
                { text: t('dataTable.operatorLessThanOrEqual'), value: 'less_than_or_equal_to' },
            ]
        }

        const formTitle = computed(() => {
            return state.editedIndex === -1 ? t('dataTable.newItem') : t('dataTable.editItem')
        })

        const buildQueryString = (params) => {
            const cleanParams = Object.entries(params)
                .filter(([key, value]) => value !== null && value !== undefined && value !== '')
                .reduce((acc, [key, value]) => {
                    acc[key] = value
                    return acc
                }, {})

            const searchParams = new URLSearchParams()
            Object.entries(cleanParams).forEach(([key, value]) => {
                searchParams.append(key, value)
            })

            return searchParams.toString()
        }

        watch(isSidebarActive, val => {
            isSidebarActive.value = val
        })

        const filteredRecords = computed(() => {
            let data = state.response.records.data || []

            if (state.quickSearchQuery) {
                data = data.filter((row) => {
                    return Object.keys(row).some((key) => {
                        return String(row[key]).toLowerCase().indexOf(state.quickSearchQuery.toLowerCase()) > -1
                    })
                })
            }

            return data
        })

        const canSelectItems = computed(() => {
            return filteredRecords.value.length <= 500
        })

        const getHeaders = computed(() => {
            return state.response.headers || []
        })

        const getRecords = async () => {
            state.loading = true
            try {
                const response = await api.get(`${props.endpoint}?${getQueryParameters()}`)
                state.response = response.data.data

                // Initialize default item
                state.response.updatable.forEach(item => {
                    state.defaultItem[item] = ''
                })
                state.editedItem = { ...state.defaultItem }

                state.pagination = {
                    page: state.response.records.current_page,
                    itemsPerPage: state.response.records.per_page,
                    total: state.response.records.total,
                }
            } catch (error) {
                console.error('Error fetching records:', error)
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.loading = false
            }
        }

        const getQueryParameters = () => {
            // Collect active toggle filters (only send keys that are true)
            const filterParams = {}
            Object.entries(state.activeFilters).forEach(([key, active]) => {
                if (active) filterParams[key] = 1
            })

            return buildQueryString({
                ...state.search,
                ...state.pagination,
                ...filterParams
            })
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
                console.error('Error adding item:', error)
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.saving = false
            }
        }

        const editItem = (item) => {
            state.editedIndex = state.response.records.data.indexOf(item)
            state.editing.id = item.id
            state.editedItem = Object.assign({}, item)
            state.dialog = true
        }

        const editItemForm = (item) => {
            state.editedIndex = state.response.records.data.indexOf(item)
            state.editing.id = item.id
            state.editedItem = Object.assign({}, item)
            isSidebarActive.value = true
        }

        // Confirms, then deletes one record (object or id) or a list of them.
        const deleteItem = async (record) => {
            if (state.deleting) return
            const isList = Array.isArray(record)
            if (isList && record.length === 0) return

            const ok = await dialog.confirmDelete(
                isList && record.length > 1
                    ? t('dataTable.deleteSelectedConfirm', { count: record.length })
                    : t('dataTable.deleteConfirm')
            )
            if (!ok) return

            await destroy(record)
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

        const updateItem = async (item) => {
            if (state.saving) return
            state.saving = true
            try {
                await axios.patch(`/api${props.endpoint}/${item.id}`, item)
                state.saving = false
                dialog.success(t('success.updated'))
                await getRecords()
                state.editing.id = null
                state.editing.form = {}
                state.editedItem = Object.assign({}, state.defaultItem)
            } catch (error) {
                console.error('Error updating item from form sidebar:', error);
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.saving = false
            }
        };

        const update = async () => {
            state.saving = true
            try {
                await axios.patch(`/api${props.endpoint}/${state.editing.id}`, state.editedItem)
                state.saving = false
                close()
                dialog.success(t('success.updated'))
                await getRecords()
                state.editing.id = null
                state.editing.form = {}
                state.editedItem = Object.assign({}, state.defaultItem)
            } catch (error) {
                console.error('Error updating item:', error)
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
                state.editedItem = Object.assign({}, state.defaultItem)
                state.creating.errors = []
            } catch (error) {
                if (error.response?.status === 422) {
                    state.editing.errors = error.response.data
                }
                console.error('Error storing item:', error)
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.saving = false
            }
        }

        const destroy = async (record) => {
            const recordIds = Array.isArray(record)
                ? record.map(item => item.id || item)
                : [record.id || record]

            state.deleting = true
            try {
                await axios.delete(`/api${props.endpoint}/${recordIds.join(',')}`)
                state.selected = []
                state.deleting = false
                dialog.success(t('success.deleted'))
                await getRecords()
            } catch (error) {
                console.error('Error deleting item(s):', error)
                dialog.requestError(error, t('errors.general'))
            } finally {
                state.deleting = false
            }
        }

        const isUpdatable = (column) => {
            return state.response.updatable.includes(column)
        }

        const toggleFilter = (key) => {
            state.activeFilters[key] = !state.activeFilters[key]
            getRecords()
        }

        const resetRecords = () => {
            state.search.value = ''
            state.quickSearchQuery = ''
            getRecords()
        }

        const paginationChange = (pagination) => {
            state.pagination = pagination
            getRecords()
        }

        watch(() => state.page, (value, oldValue) => {
            // Only trigger if page actually changed (not on initial mount)
            if (oldValue !== undefined && value !== oldValue) {
                const pagination = {
                    itemsLength: 10,
                    itemsPerPage: 10,
                    page: value,
                }
                paginationChange(pagination)
            }
        })

        onMounted(() => {
            initSearchOperators()
            getRecords()
        })

        return {
            pageTitle,
            hasActiveSearch,
            state,
            formTitle,
            getRecords,
            getQueryParameters,
            filteredRecords,
            getHeaders,
            canSelectItems,
            addItem,
            editItem,
            updateItem,
            editItemForm,
            deleteItem,
            close,
            save,
            update,
            store,
            destroy,
            isUpdatable,
            resetRecords,
            paginationChange,
            isSidebarActive,
            buildQueryString,
            toggleFilter
        }
    }
}
</script>

<style scoped>
.data-table__quick-search {
    flex: 1 1 240px;
    max-width: 360px;
}
</style>
