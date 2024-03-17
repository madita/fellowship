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

<!--                    <simple-editor v-model="page.body" :value="page.body" id="text-body" name="content"></simple-editor>-->
                        <tiptap v-model="page.content" :value="page.content" id="text-content" name="content"/>

                </v-col>
                <v-col
                    cols="4">
                    <v-select
                        v-model="page.parent"
                        :items="pages"
                        label="Pages"
                        persistent-hint
                        return-object
                        single-line
                    >
                        <template v-slot:selection="{ attrs, item, parent, selected }">
                            {{ item.title }} ({{ item.slug }})
                        </template>
                        <template v-slot:item="{ index, item }">
                            {{ item.title }} ({{ item.slug }})
                        </template>
                    </v-select>

                    <v-combobox
                        v-model="taxonomyValue"
                        :items="taxonomy"
                        item-title="taxonomy"
                        @change="setTaxonomy"
                        :search-input.sync="searchTax"
                        hide-selected
                        :disabled="isDisabled"
                        label="Taxonomy"
                        persistent-hint
                        small-chips
                    >
                    </v-combobox>

                    <v-combobox
                        v-model="categoryValue"
                        :items="categories"
                        item-title="title"
                        label="Category"
                        multiple
                        small-chips
                        deletable-chips
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
                            item-title="title"
                            label="Parent Category"
                            chips
                            clearable
                        ></v-combobox>

                        <v-btn @click="saveCategory">Add New Category</v-btn>

                    </template>

                    <v-combobox
                        v-model="termValue"
                        :items="terms"
                        item-title="title"
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
                                        No results matching "<strong>{{ searchTerm }}</strong>". Press <kbd>enter</kbd> to create a new one
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
            {{ item.title }}
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
                                {{ item.title }}
                            </v-chip>

                        </template>
                    </v-combobox>

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
            </v-row>
        </v-container>

    </div>
</template>

<script>
// import SimpleEditor from '../../components/common/SimpleEditor'
import Tiptap from '../../components/common/tiptap/Tiptap'

export default {
    components: {
        Tiptap
    },
    data() {
        return {
            loading: true,
            addCategory: false,
            page: {title: "", content: "", parent: null, taxonomy: [], terms: [], categories: []},
            pages: [],
            endpoint: '/api/datatable/pages',
            form: "create" | "edit",
            id: null,
            message: "",
            searchTax: null,
            searchTerm: null,
            searchPage: null,
            terms: [],
            taxonomy: null,
            taxonomyValue: "category",
            termValue: [],
            newCategory: "",
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
        termValue(val, prev) {
            if (val.length === prev.length) return

            this.termValue = val.map(v => {
                if (typeof v === 'string') {
                    v = {
                        title: v,
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

                this.page = response.data.page
                this.page.parent = response.data.parent

                let taxonomies = response.data.taxonomies;
                let terms = response.data.terms;

                if (taxonomies.hasOwnProperty('tags')) {
                    this.termValue = taxonomies.tags;
                    delete taxonomies.tags;
                }

                // if(Object.keys(taxonomies).length > 0)
                this.taxonomyValue = taxonomies[0];

                if (this.taxonomyValue.length > 0 && terms.hasOwnProperty(this.taxonomyValue)) {
                    //TODO categorie update needs fixing can only delete all values
                    this.categoryValue = terms[this.taxonomyValue]
                }

                this.getCategories(this.taxonomyValue)

                this.loading = false
            });
        },
        getPages() {
//https://fellowship.test/api/datatable/pages?column=id&operator=equals&value=&page=1&itemsPerPage=72&pageStart=0&pageStop=72&pageCount=1&itemsLength=72
            return axios.get(`/api/datatable/pages?page=1&itemsPerPage=100000&pageStart=-1&pageStop=100000000&pageCount=-1&itemsLength=-1`).then((response) => {
                this.pages = response.data.data.records.data

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
                this.terms = response.data.terms.map(x => {
                    return x.title
                })

                this.loading = false
            });
        },
        setTaxonomy() {
            this.getCategories(this.taxonomyValue.taxonomy)
        },
        getCategories(taxonomy) {
            this.loading = true
            return axios.get(`/api/tag/terms/${taxonomy}`).then((response) => {

                this.categories = this.parents = response.data.terms

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
            this.page.terms = this.termValue;
            this.page.taxonomy = this.taxonomyValue
            this.page.categories = this.categoryValue.map(x => { return x.title ?? x})

            axios.patch(`${this.endpoint}/${this.id}`, this.page).then(() => {
                this.message = "Page updated"
            }).catch((error) => {
                if (error.response.status === 422) {
                    this.editing.errors = error.response.data
                }
            })
        },
        store() {
            this.page.terms = this.termValue
            this.page.taxonomy = this.taxonomyValue
            this.page.categories = this.categoryValue


            axios.post(`${this.endpoint}`, this.page).then(() => {
                this.page = {title: "", content: ""};
                this.message = "Page saved ..link"
            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        },
    },
    computed: {
      isDisabled() {
          return this.id > 0 && this.categoryValue.length > 0
      }
    },

    created() {
        if (this.$route.params.form) {
            this.form = this.$route.params.form;
        }

        if (this.$route.params.id) {
            this.id = this.$route.params.id;
            this.getPage();
        }
         else {
            this.getCategories('category')
        }

        this.getTaxonomy()

        this.getTerms()
        this.getPages()

    }
}
</script>
