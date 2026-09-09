<template>
    <div>
        <page-header
            :title="$t('wiki.editCategory')"
            :subtitle="info?.term?.title ? $t('wiki.editing', { title: info.term.title }) : slug"
            icon="mdi-folder-edit-outline"
            :back-to="`/wiki/category/${slug}`"
        >
            <template #actions>
                <v-btn
                    variant="tonal"
                    :to="`/wiki/category/${slug}`"
                    :disabled="saving"
                >
                    {{ $t('common.cancel') }}
                </v-btn>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-content-save"
                    :loading="saving"
                    :disabled="loading || info == null"
                    @click="updateCategory"
                >
                    {{ $t('common.save') }}
                </v-btn>
            </template>

            <v-alert v-if="message" type="info">
                {{ message }}
            </v-alert>
        </page-header>

        <v-container class="mb-15">
            <loading-state v-if="loading" />

            <v-row v-else-if="info != null">
                <v-col cols="12" md="8">
                    <v-text-field
                        :label="$t('common.title')"
                        v-model="info.term.title"
                        :disabled="saving"
                    ></v-text-field>

                    <tiptap v-model="info.description" :value="info.description" id="text-content" name="content"/>
                </v-col>
                <v-col cols="12" md="4">
                    <v-combobox
                        v-model="parentValue"
                        :items="parents"
                        item-title="title"
                        :label="$t('wiki.parentCategory')"
                        chips
                        clearable
                    ></v-combobox>
                    <v-combobox
                        v-model="colorsValue"
                        :items="colors"
                        item-title="title"
                        :label="$t('wiki.colors')"
                        chips
                        clearable
                    ></v-combobox>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script>

// import {mapGetters} from "vuex";
import Tiptap from '../common/tiptap/Tiptap.vue'
import PageHeader from '../common/PageHeader.vue'
import LoadingState from '../common/LoadingState.vue'
import { useAuthStore } from '@/store/authStore.js';

export default {
    components: {
        Tiptap,
        PageHeader,
        LoadingState
    },
    data() {
        return {
            loading: true,
            saving: false,
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
            rules: {
                required: value => !!value || this.$t('validation.required')
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
                    this.message = this.$t('wiki.categoryNotExistsCreate')
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
        async updateCategory() {
            if (this.saving) return;
            this.saving = true

            let data = {term: this.newCategory, category: this.info, old: this.infoOld, taxonomy: this.taxonomyValue, parent: this.parentValue, content: this.info.description}

            try {
                await axios.patch(`/api/wiki/category/${this.slug}`, data)

                this.getCategories(this.taxonomyValue.taxonomy)
                await this.$dialog.success(this.$t('wiki.categoryUpdated'))
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('wiki.errorSavingCategory'))
            } finally {
                this.saving = false
            }
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
