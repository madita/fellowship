<template>
    <div class="flex-grow-1">
        <v-container>
            <div class="d-flex align-center py-3">
                <div>
                    <div class="display-1">{{ state.response.table }}</div>
                    <v-breadcrumbs :items="breadcrumbs" class="pa-0 py-2"></v-breadcrumbs>
                </div>
            </div>


                <v-card>
                    <v-row
                        dense
                        class="pa-2 align-center"
                        no-gutters
                    >
                        <!-- Columns Dropdown -->
                        <v-col cols="12" md="4" class="pr-md-2">
                            <v-select
                                v-model="state.search.column"
                                :items="state.response.displayable"
                                label="Columns"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <!-- Operators Dropdown -->
                        <v-col cols="12" md="2" class="px-md-1">
                            <v-select
                                v-model="state.search.operator"
                                :items="state.searchOperators"
                                item-title="text"
                                item-value="value"
                                label="Operators"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <!-- Search Input and Buttons -->
                        <v-col cols="12" md="6" class="pl-md-2">
                            <div class="d-flex align-center gap-2">
                                <!-- Search Input -->
                                <v-text-field
                                    v-model="state.search.value"
                                    append-inner-icon="mdi-magnify"
                                    label="Search value"
                                    class="flex-grow-1"
                                    hide-details
                                    density="compact"
                                    clearable
                                    @keyup.enter="getRecords"
                                />

                                <!-- Search Button -->
                                <v-btn
                                    color="primary"
                                    size="small"
                                    variant="elevated"
                                    @click="getRecords"
                                    min-width="80"
                                >
                                    Search
                                </v-btn>

                                <!-- Refresh Button -->
                                <v-btn
                                    :loading="state.loading"
                                    size="small"
                                    icon="mdi-refresh"
                                    @click="getRecords"
                                />
                            </div>
                        </v-col>
                    </v-row>


                <v-row class="pa-2 align-center">
                    <AppDataTable
                        v-model="state.page"
                        v-if="getHeaders && getHeaders.length > 0"
                        :items="filteredRecords"
                        :headers="getHeaders"
                        :page="state.page"
                        :links="state.response.records.links"
                        :itemsPerPage="state.response.records.per_page"
                        :pageCount="state.response.records.last_page"
                        :server-items-length="state.response.records.total"
                        :search="state.quickSearchQuery"
                        :loading="state.loading"
                    >
                        <template #top>
                            <v-toolbar flat>
                                <v-dialog
                                    v-if="!state.loading"
                                    v-model="state.dialog"
                                    max-width="500px"
                                >
                                    <template v-slot:activator="{ props: activatorProps }">
                                        <v-row>
                                            <v-col cols="6">
                                                <v-menu offset-y>
                                                    <template v-slot:activator="{ props: menuProps }">
                                                        <transition name="slide-fade" mode="out-in">
                                                            <v-btn
                                                                v-show="state.selected.length > 0"
                                                                class="mb-2 mr-1"
                                                                v-bind="menuProps"
                                                            >
                                                                Actions
                                                                <v-icon>mdi-menu-down</v-icon>
                                                            </v-btn>
                                                        </transition>
                                                    </template>
                                                    <v-list density="compact">
                                                        <v-list-item @click="destroy(state.selected)">
                                                            <v-list-item-title>Delete</v-list-item-title>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-menu>
                                                <v-btn
                                                    v-if="state.response.allow.creation"
                                                    color="primary"
                                                    class="mb-2"
                                                    @click="isSidebarActive = true"
                                                >
                                                    New Item
                                                </v-btn>
                                            </v-col>
                                            <v-col cols="6">
                                                <v-text-field
                                                    class="ml-2"
                                                    v-model="state.quickSearchQuery"
                                                    append-icon="mdi-magnify"
                                                    label="Quick Search"
                                                    single-line
                                                    hide-details
                                                ></v-text-field>
                                            </v-col>
                                        </v-row>
                                    </template>

                                    <v-card>
                                        <v-card-title>
                                            <span class="text-h5">{{ formTitle }}</span>
                                        </v-card-title>

                                        <v-card-text>
                                            <v-container v-if="state.editedItem !== null">
                                                <v-row v-for="column in state.response.updatable" :key="`card-${column}`">
                                                    <v-col cols="12">
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
                                                    </v-col>
                                                </v-row>
                                            </v-container>
                                        </v-card-text>

                                        <v-card-actions>
                                            <v-spacer></v-spacer>
                                            <v-btn
                                                color="blue-darken-1"
                                                variant="text"
                                                @click="close"
                                            >
                                                Cancel
                                            </v-btn>
                                            <v-btn
                                                color="blue-darken-1"
                                                variant="text"
                                                @click="save"
                                            >
                                                Save
                                            </v-btn>
                                        </v-card-actions>
                                    </v-card>
                                </v-dialog>

                                <v-dialog v-model="state.dialogDelete" max-width="500px">
                                    <v-card>
                                        <v-card-title class="text-h5">Are you sure you want to delete this item?</v-card-title>
                                        <v-card-actions>
                                            <v-spacer></v-spacer>
                                            <v-btn color="blue-darken-1" variant="text" @click="closeDelete">Cancel</v-btn>
                                            <v-btn color="blue-darken-1" variant="text" @click="deleteItemConfirm">OK</v-btn>
                                            <v-spacer></v-spacer>
                                        </v-card-actions>
                                    </v-card>
                                </v-dialog>
                            </v-toolbar>
                        </template>

                        <template v-slot:item.created_at="{ item }">
                            {{ new Date(item.created_at).toLocaleString() }}
                        </template>

                        <template #actions="{ item }">
                            <v-icon
                                v-if="state.response.allow.hasForm"
                                size="small"
                                class="mr-2"
                                @click="editItemForm(item)"
                            >
                                mdi-file-document-edit
                            </v-icon>
                            <v-icon
                                size="small"
                                class="mr-2"
                                @click="editItem(item)"
                            >
                                mdi-pencil
                            </v-icon>
                            <v-icon
                                size="small"
                                @click="deleteItem(item)"
                            >
                                mdi-delete
                            </v-icon>
                        </template>
                    </AppDataTable>
                </v-row>
            </v-card>

            <DataTableForm
                v-model:isDrawerOpen="isSidebarActive"
                :item="state.editedItem"
                :defaultItem="state.defaultItem"
                :response="state.response"
                :endpoint="endpoint"
                @add-item="addItem"
                @update-item="updateItem"
                @remove-item="deleteItem(state.selected)"
            />
        </v-container>
    </div>
</template>

<script>
import { ref, reactive, computed, nextTick, onMounted, watch } from 'vue'
import axios from 'axios'
import { useApi } from '@/api/useAPI.js'
import AppDataTable from '../AppDataTable.vue'
import DataTableForm from './DataTableForm.vue'
import Tiptap from '../tiptap/Tiptap.vue'

export default {
    name: 'DataTable',
    components: {
        Tiptap,
        AppDataTable,
        DataTableForm
    },
    props: {
        endpoint: {
            type: String,
            required: true
        }
    },
    setup(props) {
        const api = useApi()
        const isSidebarActive = ref(false)

        const breadcrumbs = ref([
            {
                text: '',
                disabled: false,
                href: '#'
            },
            {
                text: 'List'
            }
        ])

        const state = reactive({
            dialog: false,
            dialogDelete: false,
            loading: true,
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
            searchOperators: [
                { text: '=', value: 'equals' },
                { text: 'contains', value: 'contains' },
                { text: 'starts with', value: 'starts_with' },
                { text: 'ends with', value: 'ends_with' },
                { text: '>', value: 'greater_than' },
                { text: '<', value: 'less_than' },
                { text: '>=', value: 'greater_than_or_equal_to' },
                { text: '<=', value: 'less_than_or_equal_to' },
            ],
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
            selected: [],
            rules: {
                required: value => !!value || 'Required.',
                email: value => {
                    const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
                    return pattern.test(value) || 'Invalid e-mail.'
                },
            }
        })

        const formTitle = computed(() => {
            return state.editedIndex === -1 ? 'New Item' : 'Edit Item'
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
            } finally {
                state.loading = false
            }
        }

        const getQueryParameters = () => {
            return buildQueryString({
                ...state.search,
                ...state.pagination
            })
        }

        const addItem = async (newItem) => {
            try {
                await axios.post(`/api${props.endpoint}`, newItem)
                await getRecords()
            } catch (error) {
                if (error.response?.status === 422) {
                    state.editing.errors = error.response.data
                }
                console.error('Error adding item:', error)
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

        const deleteItem = (item) => {
            state.editedIndex = state.response.records.data.indexOf(item)
            state.editing.id = item.id
            state.editedItem = Object.assign({}, item)
            state.dialogDelete = true
        }

        const deleteItemConfirm = async () => {
            await destroy(state.editing.id)
            closeDelete()
        }

        const close = () => {
            state.dialog = false
            nextTick(() => {
                state.editedItem = Object.assign({}, state.defaultItem)
                state.editedIndex = -1
            })
        }

        const closeDelete = () => {
            state.dialogDelete = false
            nextTick(() => {
                state.editedItem = Object.assign({}, state.defaultItem)
                state.editedIndex = -1
            })
        }

        const save = () => {
            if (state.editedIndex > -1) {
                update()
            } else {
                store()
            }
        }

        const updateItem = async (item) => {
            console.log('update');
            try {
                // await axios.patch(`${endpoint}/${event.id}`, event);
                // await calendarStore.fetchEvents();
                await axios.patch(`/api${props.endpoint}/${item.id}`, item)
                await getRecords()
                state.editing.id = null
                state.editing.form = {}
                state.editedItem = Object.assign({}, state.defaultItem)
            } catch (error) {
                console.error('Error updating item from form sidebar:', error);
            }
        };

        const update = async () => {
            try {
                await axios.patch(`/api${props.endpoint}/${state.editing.id}`, state.editedItem)
                close()
                await getRecords()
                state.editing.id = null
                state.editing.form = {}
                state.editedItem = Object.assign({}, state.defaultItem)
            } catch (error) {
                console.error('Error updating item:', error)
            }
        }

        const store = async () => {
            try {
                await axios.post(`/api${props.endpoint}`, state.editedItem)
                close()
                await getRecords()
                state.editedItem = Object.assign({}, state.defaultItem)
                state.creating.errors = []
            } catch (error) {
                if (error.response?.status === 422) {
                    state.editing.errors = error.response.data
                }
                console.error('Error storing item:', error)
            }
        }

        const destroy = async (record) => {
            const recordIds = Array.isArray(record)
                ? record.map(item => item.id || item)
                : [record.id || record]

            try {
                await axios.delete(`/api${props.endpoint}/${recordIds.join(',')}`)
                await getRecords()
            } catch (error) {
                console.error('Error deleting item(s):', error)
            }
        }

        const isUpdatable = (column) => {
            return state.response.updatable.includes(column)
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

        watch(() => state.page, (value) => {
            const pagination = {
                itemsLength: 10,
                itemsPerPage: 10,
                page: value,
            }
            paginationChange(pagination)
        })

        onMounted(() => {
            getRecords()
        })

        return {
            breadcrumbs,
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
            deleteItemConfirm,
            close,
            closeDelete,
            save,
            update,
            store,
            destroy,
            isUpdatable,
            resetRecords,
            paginationChange,
            isSidebarActive,
            buildQueryString
        }
    }
}
</script>

<style scoped>
/* Ensure consistent spacing */
.v-row {
    margin: 0;
}

.v-col {
    padding: 4px;
}

/* Better button alignment */
.d-flex.gap-2 {
    gap: 8px;
}

/* Responsive adjustments */
@media (max-width: 960px) {
    .v-col {
        margin-bottom: 8px;
    }

    .d-flex {
        flex-wrap: wrap;
    }

    .v-btn {
        flex-shrink: 0;
    }
}
</style>
