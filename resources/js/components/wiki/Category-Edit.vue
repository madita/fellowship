<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">
<!--            <template v-if="authenticated">-->
<!--                <v-btn text class="mx-1" :to="`/wiki/${slug}`">-->
<!--                    Cancel-->
<!--                </v-btn>-->
<!--            </template>-->

            <v-row v-if="!loading && info != null">
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
                        v-model="info.term.title"
                    ></v-text-field>

                    <tiptap v-model="info.description" :value="info.description" id="text-content" name="content"/>

                </v-col>
                <v-col
                    cols="4">


                    <template>
                        <v-combobox
                            v-model="parentValue"
                            :items="parents"
                            item-title="title"
                            label="Parent Category"
                            chips
                            clearable
                        ></v-combobox>
                        <v-combobox
                            v-model="colorsValue"
                            :items="colors"
                            item-title="title"
                            label="Colors"
                            chips
                            clearable
                        ></v-combobox>

                    </template>

                    <v-btn @click="updateCategory">Save</v-btn>
                </v-col>
            </v-row>

        </v-container>

    </div>
</template>

<script>

// import {mapGetters} from "vuex";
import Tiptap from '../common/tiptap/Tiptap'

export default {
    components: {
        Tiptap
    },
    data() {
        return {
            loading: true,
            isDisabled: false,
            parents:[],
            pages:[],
            slug:"",
            content:"",
            message:"",
            colorsValue:"green",
            searchTax: null,
            searchTerm: null,
            searchPage: null,
            info: null,
            infoOld: null,
            // terms: [],
            // taxonomy: null,
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

        goTo(slug, type) {
            this.$router.push({ name: type, params: { slug: slug } })
        },
        // setTaxonomy() {
        //     this.getCategories(this.taxonomyValue.taxonomy)
        // },
        getWikiCategory(){
            this.loading = true
            return axios.get(`/api/taxables?term=${this.slug}&taxonomy=&model=`).then((response) => {
                this.categories = response.data.data
                this.info = response.data.category
                this.infoOld = response.data.category

                if(this.info.parent_id > 0) {
                    this.parentValue = this.info.parent.term
                }

                // if(this.info !== null) {
                //     this.description = (this.info.description !== this.info.term.title) ? this.info.description:"";
                // }



                if(this.info == null) {
                    this.message = "Die Kategorie existiert nicht..willst du sie erstellen."
                    this.mode = "create"
                    this.info = {term: this.slug, children:[]}
                    this.categories = {total:0}
                    // this.$router.push(`/wiki/category/${this.slug}/create`)
                }

                this.loading = false




                // if(this.categories === null) {
                //     console.log('null')
                //     this.message = "Die Kategorie existiert nicht..willst du sie erstellen."
                //     this.mode = "create"
                //     this.$router.push(`/wiki/category/${this.slug}/create`)
                // }

            }).catch((error) => {
                // if (error.response.status === 404) {
                //     this.$router.push('/error/not-found')
                // }
                // if (error.response.status === 401) {
                //     this.$router.push('/auth/signin')
                // }
            });
        },
        getCategories() {
            this.loading = true
            return axios.get(`/api/tag/terms/wiki`).then((response) => {
                //console.log(response)

                this.categories = this.parents = response.data.terms

                this.loading = false
            });
        },
        updateCategory() {

            // this.loading = true
            let data = {term: this.newCategory, category: this.info, old: this.infoOld, taxonomy: this.taxonomyValue, parent: this.parentValue, content: this.info.description}

            axios.patch(`/api/wiki/category/${this.slug}`, data).then(() => {

                this.getCategories(this.taxonomyValue.taxonomy)
                this.infoValue.push(this.newCategory);
                // this.categories = this.parents = response.data

                // this.loading = false
            });
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
            this.newCategory = this.slug.charAt(0).toUpperCase() + this.slug.slice(1)
            this.getWikiCategory()
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


