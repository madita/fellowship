<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">
<!--            <template v-if="authenticated">-->
<!--                <v-btn text class="mx-1" :to="`/wiki/${slug}`">-->
<!--                    Cancel-->
<!--                </v-btn>-->
<!--            </template>-->

            <v-alert v-if="slug.length>0" type="info">Die Seite existiert nicht, wenn du sie erstellen willst fülle das Formular aus.</v-alert>
            <v-row>
                <v-col
                    cols="8">
                    <v-alert v-if="message" type="success">
                        {{ message }}
                    </v-alert>

                    <v-alert v-if="editing.errors.length > 0" type="error">
                        {{ editing.errors }}
                    </v-alert>

                    <v-text-field
                        label="Title"
                        v-model="wikipage.title"
                    ></v-text-field>

                    <!--                    <simple-editor v-model="page.body" :value="page.body" id="text-body" name="content"></simple-editor>-->
                    <tiptap v-model="wikipage.content" :value="wikipage.content" id="text-content" name="content"/>

                </v-col>
                <v-col
                    cols="4">
                    <v-select
                        v-model="wikipage.parent"
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

                    <!--                    <v-combobox-->
                    <!--                        v-model="taxonomyValue"-->
                    <!--                        :items="taxonomies"-->
                    <!--                        item-text="taxonomy"-->
                    <!--                        @change="setTaxonomy"-->
                    <!--                        :search-input.sync="searchTax"-->
                    <!--                        hide-selected-->
                    <!--                        :disabled="isDisabled"-->
                    <!--                        label="Taxonomy"-->
                    <!--                        persistent-hint-->
                    <!--                        small-chips-->
                    <!--                    >-->
                    <!--                    </v-combobox>-->

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




                    <v-btn @click="store">Save</v-btn>
                </v-col>
            </v-row>

        </v-container>

    </div>
</template>

<script>

// import {mapGetters} from "vuex";
import Tiptap from '../common/tiptap/Tiptap.vue'

export default {
    components: {
        Tiptap
    },
    data() {
        return {
            addCategory: false,
            isDisabled: false,
            wikipage:{},
            parents:[],
            pages:[],
            taxonomies:[],
            slug:"",
            message:"",
            searchTax: null,
            searchTerm: null,
            searchPage: null,
            terms: [],
            taxonomy: null,
            taxonomyValue: "wiki",
            termValue: [],
            newCategory: "",
            categories: [],
            categoryValue: [],
            parentValue: null,
            editing: {
                id: null,
                form: {},
                errors: []
            },
            rules: {
                required: value => !!value || 'Required.'
            },
            colors: ['green', 'purple', 'indigo', 'cyan', 'teal', 'orange'],
            nonce: 1
        }
    },
    methods: {
        getWikiPage(){
            this.loading = true
            return axios.get(`/api/wiki/${this.slug}`).then((response) => {
                this.wikipage = response.data.page
                this.parents = response.data.parents
                this.taxonomies = response.data.terms

                this.categoryValue = this.taxonomies;
            }).catch((error) => {

                if (error.response.status === 404) {
                    // this.$router.push('/error/not-found')
                    this.wikipage = error.response.data.page
                    this.parents = error.response.data.parents
                    this.taxonomies = error.response.data.terms

                    this.categoryValue = this.taxonomies;
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },
        goTo(slug, type) {
            this.$router.push({ name: type, params: { slug: slug } })
        },
        // setTaxonomy() {
        //     this.getCategories(this.taxonomyValue.taxonomy)
        // },
        getCategories() {
            this.loading = true
            return axios.get(`/api/tag/terms/wiki`).then((response) => {

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

        store() {
            this.wikipage.terms = this.termValue
            this.wikipage.taxonomy = this.taxonomyValue
            this.wikipage.categories = this.categoryValue


            axios.post(`/api/wiki`, this.wikipage).then(() => {
                this.wikipage = {title: "", content: ""};
                this.message = "Wiki page created"
                // this.$router.push(`/wiki/${this.slug}`)
            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        },
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
    computed: {
        authenticated() {
            const authStore = useAuthStore();
            return authStore.isLoggedIn;
        },
        user() {
            const authStore = useAuthStore();
            return authStore.user;
        },
    },
    mounted() {

        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getWikiPage();
        }

        this.getCategories()


    }
}
</script>

<style>
.card-outter {
    position: relative;
    padding-bottom: 50px;
}
.card-actions {
    position: absolute;
    bottom: 0;
}
</style>


