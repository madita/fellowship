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
            <v-row dense class="pa-2 align-center">
                <v-col md="4">
                    <v-select
                        v-model="state.search.column"
                        :items="state.response.displayable"
                        label="Columns"
                    ></v-select>
                </v-col>
                    <v-col md="4">
                    <v-select
                        v-model="state.search.operator"
                        :items="state.searchOperators"
                        item-title="text"
                        item-value="value"
                        label="Operators"
                    ></v-select>
                    </v-col>
                        <v-col md="4" class="d-flex text-right align-center">
                    <v-text-field
                        v-model="state.search.value"
                        append-icon="mdi-magnify"
                        class="flex-grow-1 mr-md-2"
                        hide-details
                        dense
                        clearable
                    ></v-text-field>
                            <v-btn
                                small
                                class="ml-2"
                                @click="getRecords"
                            >
                                Search
                            </v-btn>
                    <v-btn
                        :loading="state.loading"
                        small
                        class="ml-2"
                        @click="getRecords"
                    >
                        <v-icon>mdi-refresh</v-icon>
                    </v-btn>
                </v-col>
            </v-row>

            <v-row class="pa-2 align-center">


                <AppDataTable v-model:modelValue="state.page"
                              v-if="getHeaders && getHeaders.length>0"
                              :items="filteredRecords"
                              :headers="getHeaders"
                              :page="state.page"
                              :links="state.response.records.links"
                              :page.sync="state.response.records.current_page"
                              :itemsPerPage="state.response.records.per_page"
                              :pageCount="state.response.records.last_page"
                              :server-items-length="state.response.records.total"
                              :search="state.quickSearchQuery"
                              :loading="state.loading">
<!--                    <template #actions="{ item }">-->
<!--                        &lt;!&ndash; Add your custom actions here. For instance: &ndash;&gt;-->
<!--                        <button @click="editItem(item)">Edit</button>-->
<!--                        <button @click="deleteItem(item)">Delete</button>-->
<!--                    </template>-->
                    <template #top>
                        <v-toolbar
                            flat
                        >
                            <v-dialog
                                v-if="!state.loading"
                                v-model="state.dialog"
                                max-width="500px"
                            >
                                <template v-slot:activator="{ probs }">
                                    <v-row>
                                        <v-col cols="6">
                                            <v-menu offset-y left>
                                                <template v-slot:activator="{ probs }">
                                                    <transition name="slide-fade" mode="out-in">
                                                        <v-btn v-show="state.selected.length > 0" class="mb-2 mr-1">
                                                            Actions
                                                            <v-icon right>mdi-menu-down</v-icon>
                                                        </v-btn>
                                                    </transition>
                                                </template>
                                                <v-list dense>
                                                    <v-list-item @click="destroy(selected)">
                                                        <v-list-item-title>Delete</v-list-item-title>
                                                    </v-list-item>
                                                </v-list>
                                            </v-menu>
                                            <v-btn
                                                v-if="state.response.allow.creation"
                                                color="primary"
                                                dark
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
                                                <v-col
                                                    cols="12"
                                                >
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
                                                        :value="state.editedItem[column]"
                                                    ></v-textarea>

<!--                                                    <simple-editor-->
<!--                                                        v-else-if="state.response.column_fields[column]==='wysiwyg'"-->
<!--                                                        v-model="state.editedItem[column]"-->
<!--                                                        :value="state.editedItem[column]">-->
<!--                                                    </simple-editor>-->
                                                    <tiptap
                                                        v-else-if="state.response.column_fields[column]==='wysiwyg'"
                                                        v-model:modelValue="state.editedItem[column]"
                                                        :value="state.editedItem[column]"
                                                        id="text-content" name="content"/>

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


                                                    <!--                                                        <span class="help-block" v-if="creating.errors[column]">-->
                                                    <!--                                <strong>{{ creating.errors[column][0] }}</strong>-->
                                                </v-col>
                                            </v-row>
                                        </v-container>
                                    </v-card-text>

                                    <v-card-actions>
                                        <v-spacer></v-spacer>
                                        <v-btn
                                            color="blue darken-1"
                                            @click="close"
                                        >
                                            Cancel
                                        </v-btn>
                                        <v-btn
                                            color="blue darken-1"
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
                                        <v-btn color="blue darken-1" @click="closeDelete">Cancel</v-btn>
                                        <v-btn color="blue darken-1" @click="deleteItemConfirm">OK</v-btn>
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
                            small
                            class="mr-2"
                            @click="editItemForm(item)"
                        >
                            mdi-file-document-edit
                        </v-icon>
                        <v-icon
                            small
                            class="mr-2"
                            @click="editItem(item)"
                        >
                            mdi-pencil
                        </v-icon>
                        <v-icon
                            small
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
                :item="state.selected"
                :defaultItem="state.defaultItem"
                :response="state.response"
                @add-item="addItem"
                @update-item="editItem(state.selected)"
                @remove-item="deleteItem(state.selected)"
            />
        </v-container>
    </div>
</template>

<script>
import {ref, reactive, computed, nextTick, onMounted, watch} from 'vue'
import queryString from 'querystringify'
// import SimpleEditor from './SimpleEditor.vue'
import axios from 'axios'
// import _ from 'lodash'
import { useApi } from '@/api/useAPI.js'
import AppDataTable from '../AppDataTable.vue';
import DataTableForm from './DataTableForm.vue';
// import {
//     VDataTable,
//     VDataTableServer,
//     VDataTableVirtual,
// } from "vuetify/labs/VDataTable";
// import Tiptap from "@/components/common/tiptap/Tiptap.vue";
// import CalendarEventHandler from "../event/CalendarEventHandler.vue";
import Tiptap from '../tiptap/Tiptap.vue'
// import {eventBus} from "../eventBus.js";

//TODO fix forms and data formats
//fix edit and crea
//fix deelte

export default {
    name: 'datat-table',
    components: {
        // CalendarEventHandler,
        Tiptap,
        AppDataTable,
        DataTableForm
    },
    props: ['endpoint'],
    setup(props) {
        const api = useApi()

        // const options = ref ({
        //     answers: { yes: 'Yes', maybe: 'Maybe' },
        //     permissions: ['edit', 'view'],
        //     profil: ['user', 'admin'],
        //     showAttributtes: ['AllDay', 'startDate', 'endDate'],
        //     location: ['custom', 'real', 'virtual']
        // })

        const isSidebarActive = ref(false);

        const breadcrumbs = ref([{
            text: '',
            disabled: false,
            href: '#'
        }, {
            text: 'List'
        }])

        let state = reactive({
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
                records: { data: [] },
                displayable: [],
                updatable: [],
                allow: [],
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

        watch(isSidebarActive, val => {
            isSidebarActive.value = val
            console.log('watchval', val)
            // if (!val) {
            //     editMode.value = true
            //     selectedEvent.value = structuredClone(blankEvent)
            // }

        })
        //
        // eventBus.on('openSidebarWithEvent', (event) => {
        //     selectedEvent.value = event;
        //     isSidebarActive.value = true;
        // });


        const filteredRecords = computed(() => {
            let data = state.response.records.data

            // console.log('letdata', data)

            data = data.filter((row) => {
                return Object.keys(row).some((key) => {
                    return String(row[key]).toLowerCase().indexOf(state.quickSearchQuery.toLowerCase()) > -1
                })
            })

            /*if (state.sort.key) {
                data = _.orderBy(data, (i) => {
                    let value = i[state.sort.key]

                    if (!isNaN(parseFloat(value)) && isFinite(value)) {
                        return parseFloat(value)
                    }

                    return String(i[state.sort.key]).toLowerCase()
                }, state.sort.order)
            }*/

            // console.log('data', data)

            return data
        })

        const canSelectItems = computed(() => {
            return filteredRecords.value.length <= 500
        })

        const getRecords = () => {
            state.loading = true
            return api.get(`${props.endpoint}?${getQueryParameters()}`).then((response) => {
                state.response = response.data.data
                console.log('response',response)
                state.response.updatable.forEach(item => {
                    console.log('item',item)
                    state.defaultItem[item] = ''
                    }
                )
                state.editedItem = state.defaultItem
                state.pagination = {
                    page:state.response.records.current_page,
                    itemsPerPage:state.response.records.per_page,
                    total:state.response.records.total,
                }
                state.loading = false
            })
        }

        const getQueryParameters = () => {
            return queryString.stringify({
                ...state.search,
                ...state.pagination
            })
        }

        // const addItem = () => {
        //     console.log('addItem')
        // }

        const addItem = async (addItem) => {

            axios.post(`/api${props.endpoint}`, addItem).then(() => {

            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        }

        const editItem = (item) => {
            state.editedIndex = state.response.records.data.indexOf(item)
            state.editing.id = item.id
            // state.editedItem = Object.assign({}, item)
            // state.editedItem = _.pick(item, state.response.updatable)
            // console.log('state.editedItem',state.editedItem)

            state.dialog = true
        }

        // const editItemForm = (item) => {
        //     state.$router.push(`${state.$route.path}/edit/${item.id}`)
        // }

        const deleteItem = (item) => {
            state.editedIndex = state.response.records.data.indexOf(item)
            state.editing.id = item.id
            state.editedItem.value = Object.assign({}, item)
            state.dialogDelete = true
        }

        const deleteItemConfirm = () => {
            filteredRecords.value.splice(state.editedIndex, 1)
            destroy(state.editing.id)
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
            console.log('save?')
            if (state.editedIndex > -1) {
                // state.update()
            } else {
                // state.store()
            }
        }

        const update = () => {
            axios.patch(`${state.endpoint}/${state.editing.id}`, state.editedItem).then(() => {
                Object.assign(state.response.records.data[state.editedIndex], state.editedItem)
                state.close()
                state.getRecords().then(() => {
                    state.editing.id = null
                    state.editing.form = null
                    state.editedItem = Object.assign({}, state.defaultItem)
                })
            }).catch((error) => {
                if (error.response.status === 422) {
                    state.editing.errors = error.response.data
                }
            })
        }

        const store = () => {
            axios.post(`${state.endpoint}`, state.editedItem).then(() => {
                state.response.records.data.push(state.editedItem)
                state.close()
                state.getRecords().then(() => {
                    state.editedItem = Object.assign({}, state.defaultItem)
                    // state.creating.active = false
                    // state.creating.form = {}
                    state.creating.errors = []
                })
            }).catch((error) => {
                if (error.response.status === 422) {
                    // state.creating.errors = error.response.data
                    state.editing.errors = error.response.data
                }
            })
        }

        const destroy = (record) => {
            //make sure only get the ids as array not the whole objects
            if(Array.isArray(record)) {
                record = record.map(item => item.id)
            }

            axios.delete(`${state.endpoint}/${record}`).then(() => {
                state.getRecords()

            })
        }

        const isUpdatable = (column) => {
            return state.response.updatable.includes(column)
        }

        const resetRecords = () => {
            state.search.value = ''
            state.quickSearchQuery = ''
            getRecords()
        }

        //$pagination = (int) $request->get('itemsPerPage') <= 0 ? (int) $request->get('itemsLength') : (int) $request->get('itemsPerPage');
        //itemsLength
        //itemsPerPage
        //itemsPerPage
        const paginationChange = (e) => {
            console.log('e',e)
            state.pagination = e
            console.log('search',state.search)
            getRecords()
        }

        const getHeaders = computed(() => {
            console.log(state.response.headers)
           return  state.response.headers
        })

        // ... [repeat for all methods]
        // Use state.property to access or modify the properties inside methods.

        watch(() => state.page, (value) => {
            // console.log('statepage', value)
            let pagination = {
                itemsLength:10,
                itemsPerPage:10,
                page:value,
            }
            paginationChange(pagination)
        });


        onMounted(() => {
            getRecords()
        })

        return {
            breadcrumbs,
            state,
            getRecords,
            getQueryParameters,
            filteredRecords,
            getHeaders,
            canSelectItems,
            addItem,
            editItem,
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
            isSidebarActive
            // ... [repeat for all methods]
        }
    }
}
</script>

