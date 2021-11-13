<template>
    <div class="flex-grow-1">

        <div class="d-flex align-center py-3">
            <div>
                <div class="display-1">{{ response.table }}</div>
                <v-breadcrumbs :items="breadcrumbs" class="pa-0 py-2"></v-breadcrumbs>
            </div>
        </div>

        <v-card>
            <v-row dense class="pa-2 align-center">
                <v-col md="4">
                    <v-select
                        v-model="search.column"
                        :items="response.displayable"
                        label="Columns"
                    ></v-select>
                </v-col>
                    <v-col md="4">
                    <v-select
                        v-model="search.operator"
                        :items="searchOperators"
                        item-text="text"
                        item-value="value"
                        label="Columns"
                    ></v-select>
                    </v-col>
                        <v-col md="4" class="d-flex text-right align-center">
                    <v-text-field
                        v-model="search.value"
                        append-icon="mdi-magnify"
                        class="flex-grow-1 mr-md-2"
                        hide-details
                        dense
                        clearable
                    ></v-text-field>
                            <v-btn
                                icon
                                small
                                class="ml-2"
                                @click="getRecords"
                            >
                                Search
                            </v-btn>
                    <v-btn
                        :loading="loading"
                        icon
                        small
                        class="ml-2"
                        @click="getRecords"
                    >
                        <v-icon>mdi-refresh</v-icon>
                    </v-btn>
                </v-col>
            </v-row>

            <v-row dense class="pa-2 align-center">
                <v-data-table
                    v-model="selected"
                    :page="page"
                    :page.sync="response.records.current_page"
                    :itemsPerPage="response.records.per_page"
                    :pageCount="response.records.last_page"
                    :headers="response.headers"
                    :items="response.records.data"
                    :server-items-length="response.records.total"
                    :search="quickSearchQuery"
                    :loading="loading"
                    loading-text="Loading... Please wait"
                    class="flex-grow-1"
                    show-select
                    @pagination="paginationChange"
                >
                    <template v-slot:top>
                        <v-toolbar
                            flat
                        >
                            <v-dialog
                                v-if="!loading"
                                v-model="dialog"
                                max-width="500px"
                            >
                                <template v-slot:activator="{ on, attrs }">
                                    <v-row>
                                        <v-col cols="6">
                                            <v-menu offset-y left>
                                                <template v-slot:activator="{ on }">
                                                    <transition name="slide-fade" mode="out-in">
                                                        <v-btn v-show="selected.length > 0" v-on="on" class="mb-2 mr-1">
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
                                                v-if="response.allow.creation"
                                                color="primary"
                                                dark
                                                class="mb-2"
                                                v-bind="attrs"
                                                v-on="on"
                                            >
                                                New Item
                                            </v-btn>
                                        </v-col>
                                        <v-col cols="6">
                                            <v-text-field
                                                class="ml-2"
                                                v-model="quickSearchQuery"
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
                                        <v-container v-if="!_.isEmpty(editedItem)">
                                            <v-row v-for="column in response.updatable" :key="`card-${column}`">
                                                <v-col
                                                    cols="12"
                                                >
                                                    <v-textarea
                                                        v-if="response.column_fields[column]=='textarea'"
                                                        :label="column"
                                                        :id="column"
                                                        v-model="editedItem[column]"
                                                        :value="editedItem[column]"
                                                    ></v-textarea>

                                                    <v-checkbox
                                                        v-else-if="response.column_fields[column]=='checkbox'"
                                                        v-model="editedItem[column]"
                                                        :label="column"
                                                    ></v-checkbox>

                                                    <v-text-field
                                                        v-else
                                                        v-model="editedItem[column]"

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
                                            text
                                            @click="close"
                                        >
                                            Cancel
                                        </v-btn>
                                        <v-btn
                                            color="blue darken-1"
                                            text
                                            @click="save"
                                        >
                                            Save
                                        </v-btn>
                                    </v-card-actions>
                                </v-card>
                            </v-dialog>
                            <v-dialog v-model="dialogDelete" max-width="500px">
                                <v-card>
                                    <v-card-title class="text-h5">Are you sure you want to delete this item?</v-card-title>
                                    <v-card-actions>
                                        <v-spacer></v-spacer>
                                        <v-btn color="blue darken-1" text @click="closeDelete">Cancel</v-btn>
                                        <v-btn color="blue darken-1" text @click="deleteItemConfirm">OK</v-btn>
                                        <v-spacer></v-spacer>
                                    </v-card-actions>
                                </v-card>
                            </v-dialog>
                        </v-toolbar>
                    </template>
                    <template v-slot:item.created_at="{ item }">
                        {{ new Date(item.created_at).toLocaleString() }}
                    </template>
                    <template v-slot:item.updated_at="{ item }">
                        {{ new Date(item.updated_at).toLocaleString() }}
                    </template>
                    <template v-slot:item.actions="{ item }">
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
                    <template v-slot:no-data>
                        No Records found
                        <v-btn
                            v-if="response.records.total > 0"
                            color="primary"
                            @click="resetRecords"
                        >
                            Reset
                        </v-btn>
                    </template>
                </v-data-table>
            </v-row>

        </v-card>
    </div>
</template>

<script>
    import queryString from 'querystring'
    import VSelectList from "../../../../public/dist/js/8f7a4f442a011bfbb59a";

    export default {
        components: {VSelectList},
        props: [
            'endpoint'
        ],
        data () {
            return {
                breadcrumbs: [{
                    text: '',
                    disabled: false,
                    href: '#'
                }, {
                    text: 'List'
                }],
                dialog: false,
                dialogDelete: false,
                loading: true,
                formTitle:'',
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
                pagination: {},
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
                // limit: 50,
                response: {
                    table: null,
                    records: {data:[]},
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
            }
        },
        computed: {
            // filteredRecords () {
            //     let data = this.response.records.data
            //
            //     data = data.filter((row) => {
            //         return Object.keys(row).some((key) => {
            //             return String(row[key]).toLowerCase().indexOf(this.quickSearchQuery.toLowerCase()) > -1
            //         })
            //     })
            //
            //     if (this.sort.key) {
            //         data = _.orderBy(data, (i) => {
            //             let value = i[this.sort.key]
            //
            //             if (!isNaN(parseFloat(value)) && isFinite(value)) {
            //                 return parseFloat(value)
            //             }
            //
            //             return String(i[this.sort.key]).toLowerCase()
            //         }, this.sort.order)
            //     }
            //
            //     return data
            // },
            canSelectItems () {
                return this.filteredRecords.length <= 500
            }
        },
        methods: {
            getRecords () {
                this.loading = true
                return axios.get(`${this.endpoint}?${this.getQueryParameters()}`).then((response) => {
                    this.response = response.data.data

                    this.response.updatable.forEach(item =>
                        this.defaultItem[item] = this.response.column_fields[item]=='checkbox'?0:''
                    )
                    this.editedItem = this.defaultItem
                    this.loading = false
                });
            },
            getQueryParameters() {
                return queryString.stringify({
                    // limit: this.limit,
                    ...this.search,
                    ...this.pagination
                })
            },
            editItem (item) {
                this.editedIndex = this.response.records.data.indexOf(item)
                this.editing.id = item.id
                // this.editedItem = Object.assign({}, item)
                this.editedItem = _.pick(item, this.response.updatable)
                // console.log('this.editedItem',this.editedItem)

                this.dialog = true
            },

            deleteItem (item) {
                this.editedIndex = this.response.records.data.indexOf(item)
                this.editing.id = item.id
                this.editedItem = Object.assign({}, item)
                this.dialogDelete = true
            },

            deleteItemConfirm() {
                this.filteredRecords.splice(this.editedIndex, 1)
                this.destroy(this.editing.id)
                this.closeDelete()
            },

            close () {
                this.dialog = false
                this.$nextTick(() => {
                    this.editedItem = Object.assign({}, this.defaultItem)
                    this.editedIndex = -1
                })
            },

            closeDelete () {
                this.dialogDelete = false
                this.$nextTick(() => {
                    this.editedItem = Object.assign({}, this.defaultItem)
                    this.editedIndex = -1
                })
            },

            save () {
                if (this.editedIndex > -1) {
                    this.update()
                } else {
                    this.store()
                }
            },
            update () {
                axios.patch(`${this.endpoint}/${this.editing.id}`, this.editedItem).then(() => {
                    Object.assign(this.response.records.data[this.editedIndex], this.editedItem)
                    this.close()
                    this.getRecords().then(() => {
                        this.editing.id = null
                        this.editing.form = null
                        this.editedItem = Object.assign({}, this.defaultItem)
                    })
                }).catch((error) => {
                    if (error.response.status === 422) {
                        this.editing.errors = error.response.data
                    }
                })
            },
            store () {
                axios.post(`${this.endpoint}`, this.editedItem).then(() => {
                    this.response.records.data.push(this.editedItem)
                    this.close()
                    this.getRecords().then(() => {
                        this.editedItem = Object.assign({}, this.defaultItem)
                        // this.creating.active = false
                        // this.creating.form = {}
                        this.creating.errors = []
                    })
                }).catch((error) => {
                    if (error.response.status === 422) {
                        // this.creating.errors = error.response.data
                        this.editing.errors = error.response.data
                    }
                })
            },
             destroy(record) {
                //make sure only get the ids as array not the whole objects
                if(Array.isArray(record)) {
                    record = record.map(item => item.id)
                }

                axios.delete(`${this.endpoint}/${record}`).then(() => {
                    this.getRecords()

                })
            },
            isUpdatable (column) {
                return this.response.updatable.includes(column)
            },
            resetRecords() {
                this.search.value = ''
                this.quickSearchQuery = ''
                this.getRecords()
            },
            paginationChange(e){
                this.pagination = e
                this.getRecords()
            }
        },
        mounted () {
            this.getRecords()
        }
    }
</script>
