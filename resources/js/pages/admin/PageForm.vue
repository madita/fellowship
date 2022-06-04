<template>
    <div class="flex-grow-1">
        <v-container>
            <v-row>
                <v-col
                cols="8">
                    <v-alert v-if="message" type="success">
                        {{ message }}
                    </v-alert>

                    <v-text-field
                        label="Title"
                        v-model="page.title"
                    ></v-text-field>

                    <simple-editor v-model="page.body" :value="page.body" id="text-body" name="content"></simple-editor>

                    <v-checkbox
                        v-model="page.published"
                        label="Published"
                    ></v-checkbox>

                    <v-checkbox
                        v-model="page.sign_in_only"
                        label="Sign in only"
                    ></v-checkbox>


                    <v-btn @click="save">{{ form }}</v-btn>
                </v-col>
                <v-col
                cols="4">
                    <v-combobox
                        v-model="taxonomyValue"
                        :items="taxonomy"
                        item-text="taxonomy"
                        @change="setTaxonomy"
                        :search-input.sync="searchTax"
                        hide-selected
                        label="Taxonomy"
                        persistent-hint
                        small-chips
                    >
                    </v-combobox>

                    <v-combobox
                        v-model="categoryValue"
                        :items="categories"
                        item-text="name"
                        label="Category"
                        multiple
                        chips
                        clearable
                    ></v-combobox>

                    <a href="#" @click="addCategory=!addCategory">Add new category</a>


                    <template v-if="addCategory">
                        <v-text-field
                            required
                            v-model="newCategory"
                            :rules="[rules.required]"
                        ></v-text-field>
                        <v-combobox
                            v-model="parentValue"
                            :items="parents"
                            item-text="name"
                            label="Parent Category"
                            chips
                            clearable
                        ></v-combobox>

                        <v-btn @click="saveCategory">Add New Category</v-btn>

                    </template>

                    <v-combobox
                        v-model="termValue"
                        :items="terms"
                        item-text="name"
                        :search-input.sync="searchTerm"
                        hide-selected
                        label="Terms"
                        multiple
                        persistent-hint
                        small-chips
                        clearable
                        deletable-chips
                    >
                        <template v-slot:no-data>
                            <v-list-item>
                                <v-list-item-content>
                                    <v-list-item-title>
                                        No results matching "<strong>{{ searchTax }}</strong>". Press <kbd>enter</kbd> to create a new one
                                    </v-list-item-title>
                                </v-list-item-content>
                            </v-list-item>
                        </template>
                        <template v-slot:selection="{ attrs, item, parent, selected }">
                            <v-chip
                                v-if="item === Object(item)"
                                v-bind="attrs"
                                :color="`${item.color} lighten-3`"
                                :input-value="selected"
                                label
                                small
                            >
          <span class="pr-2">
            {{ item.name }}
          </span>
                                <v-icon
                                    small
                                    :color="`${item.color} lighten`"
                                    @click="parent.selectItem(item)"
                                >
                                    $delete
                                </v-icon>
                            </v-chip>
                        </template>
                        <template v-slot:item="{ index, item }">
                            <v-chip
                                :color="`${item.color} lighten-3`"
                                dark
                                label
                                small
                            >
                                {{ item.name }}
                            </v-chip>

                        </template>
                    </v-combobox>
                </v-col>
            </v-row>
        </v-container>

    </div>
</template>

<script>
import SimpleEditor from '../../components/common/SimpleEditor'

export default {
    components: {
        SimpleEditor
    },
    data() {
        return {
            loading: true,
            addCategory: false,
            page: {title: "", body: "", taxonomy:[], terms:[], categories:[]},
            endpoint: '/api/datatable/pages',
            form: "create" | "edit",
            id: null,
            message: "",
            searchTax: null,
            searchTerm: null,
            terms: [],
            taxonomy: null,
            taxonomyValue: "category",
            termValue: [],
            newCategory:"",
            categories: [],
            categoryValue: [],
            parents: [],
            parentValue: null,
            rules: {
                required: value => !!value || 'Required.'
            },
            colors: ['green', 'purple', 'indigo', 'cyan', 'teal', 'orange'],
            nonce: 1
        }
    },
    watch: {
        termValue (val, prev) {
            if (val.length === prev.length) return

            this.termValue = val.map(v => {
                if (typeof v === 'string') {
                    v = {
                        name: v,
                        color: this.colors[this.nonce - 1],
                    }

                    this.search = null
                    this.terms.push(v)

                    this.nonce++
                }

                return v
            })
        },
    },
    methods: {
        getPage() {
            this.loading = true
            return axios.get(`/api/pages/${this.id}/edit`).then((response) => {
                this.page = response.data.data

                this.loading = false
            });
        },
        //https://fellowship.test/api/datatable/pages?column=id&operator=equals&value=&page=1&itemsPerPage=-1&pageStart=0&pageStop=6&pageCount=1&itemsLength=6
        getTaxonomy() {
            this.loading = true
            return axios.get(`/api/tag/taxonomies`).then((response) => {
                this.taxonomy = response.data

                this.loading = false
            });
        },
        getTerms() {
            this.loading = true
            return axios.get(`/api/tag/terms/tags`).then((response) => {
                this.terms = response.data.map(x => {return {name:x.name, color: x.color}})

                this.loading = false
            });
        },
        setTaxonomy() {
            this.getCategories(this.taxonomyValue.taxonomy)
        },
        getCategories(taxonomy) {
            this.loading = true
            return axios.get(`/api/tag/terms/${taxonomy}`).then((response) => {

                this.categories = this.parents = response.data

                this.loading = false
            });
        },
        saveCategory() {

            // this.loading = true
            let data = {term: this.newCategory, taxonomy: this.taxonomyValue, parent: this.parentValue}

            axios.post(`/api/tag/terms`, data).then(() => {

                this.getCategories(this.taxonomyValue.taxonomy)
                this.categoryValue.push(this.newCategory);
                // this.categories = this.parents = response.data

                // this.loading = false
            });
        },
        save() {
            if (this.form === "edit") {
                this.update()
            } else {
                this.store()
            }
        },
        update() {
            axios.patch(`${this.endpoint}/${this.id}`, this.page).then(() => {
                this.message = "Page updated"
            }).catch((error) => {
                if (error.response.status === 422) {
                    this.editing.errors = error.response.data
                }
            })
        },
        store() {
            this.page.terms = this.termValue;
            this.page.taxonomy = this.taxonomyValue
            this.page.categories = this.categoryValue


            axios.post(`${this.endpoint}`, this.page).then(() => {
                this.page = {title: "", body: ""};
                this.message = "Page saved ..link"
            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        },
    },

    created() {
        if (this.$route.params.form) {
            this.form = this.$route.params.form;
        }

        if (this.$route.params.id) {
            this.id = this.$route.params.id;
            this.getPage();
        }

        this.getTaxonomy()
        this.getCategories('category')
        this.getTerms()

    }
}
</script>
