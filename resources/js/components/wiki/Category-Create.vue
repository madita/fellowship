<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">
<!--            <template v-if="authenticated">-->
<!--                <v-btn text class="mx-1" :to="`/wiki/${slug}`">-->
<!--                    Cancel-->
<!--                </v-btn>-->
<!--            </template>-->

            <v-alert type="info" v-if="message.length === 0">Die Kategorie existiert nicht, wenn du sie erstellen willst fülle das Formular aus.</v-alert>
            <v-alert type="success" v-if="message.length > 0">{{ message }}</v-alert>
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
                        v-model="newCategory"
                    ></v-text-field>

                    <!--                    <simple-editor v-model="page.body" :value="page.body" id="text-body" name="content"></simple-editor>-->
                    <tiptap v-model="content" :model-value="content" id="text-content" name="content"/>

                </v-col>
                <v-col
                    cols="4">


                    <template>
                        <v-combobox
                            v-model="parentValue"
                            :items="parents"
                            item-text="title"
                            label="Parent Category"
                            chips
                            clearable
                        ></v-combobox>
                        <v-combobox
                            v-model="colorsValue"
                            :items="colors"
                            item-text="title"
                            label="Colors"
                            chips
                            clearable
                        ></v-combobox>

                    </template>

                    <v-btn @click="saveCategory">Save</v-btn>
                </v-col>
            </v-row>

        </v-container>

    </div>
</template>

<script>

import {mapGetters} from "vuex";
import Tiptap from '../common/tiptap/Tiptap'

export default {
    components: {
        Tiptap
    },
    data() {
        return {
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
        getWikiCategory(){
            this.loading = true
            return axios.get(`/api/taxables?term=${this.slug}&taxonomy=&model=`).then((response) => {
                console.log(response)
                if(response.data.data !== null) {

                    // this.$router.push(`/wiki/category/${this.slug}/edit`)
                }

            }).catch((error) => {
                // if (error.response.status === 404) {
                //     this.$router.push('/error/not-found')
                // }
                // if (error.response.status === 401) {
                //     this.$router.push('/auth/signin')
                // }
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
                //console.log(response)

                this.categories = this.parents = response.data.terms

                this.loading = false
            });
        },
        saveCategory() {

            // this.loading = true
            let data = {term: this.newCategory, taxonomy: this.taxonomyValue, parent: this.parentValue, content: this.content}

            axios.post(`/api/wiki/category`, data).then(() => {

                this.getCategories(this.taxonomyValue.taxonomy)
                this.categoryValue.push(this.newCategory);
                this.message = "Page created"
                // this.categories = this.parents = response.data

                // this.loading = false
            }).catch((error) => {
                console.log(error)

                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },


    },

    computed: {
        ...mapGetters({
            authenticated: 'auth/authenticated',
            user: 'auth/user',
        })
    },
    mounted() {

        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.newCategory = this.slug.charAt(0).toUpperCase() + this.slug.slice(1)
            this.getWikiCategory(this.slug)
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


