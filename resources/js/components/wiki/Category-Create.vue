<template>
    <div>
        <page-header
            :title="$t('wiki.createCategory')"
            :subtitle="slug ? $t('wiki.creatingCategory', { slug: slug }) : ''"
            icon="mdi-folder-plus-outline"
            :back-to="slug ? `/wiki/category/${slug}` : '/wiki/category'"
        >
            <template #actions>
                <v-btn
                    variant="tonal"
                    :to="slug ? `/wiki/category/${slug}` : '/wiki/category'"
                    :disabled="saving"
                >
                    {{ $t('common.cancel') }}
                </v-btn>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-content-save"
                    :loading="saving"
                    @click="saveCategory"
                >
                    {{ $t('common.save') }}
                </v-btn>
            </template>

            <v-alert type="info">{{ $t('wiki.categoryNotExistsFillForm') }}</v-alert>
        </page-header>

        <v-container class="mb-15">
            <v-row>
                <v-col cols="12" md="8">
                    <v-text-field
                        :label="$t('common.title')"
                        v-model="newCategory"
                        :disabled="saving"
                    ></v-text-field>

                    <tiptap v-model="content" :model-value="content" id="text-content" name="content"/>
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
import { useAuthStore } from '@/store/authStore.js';

export default {
    components: {
        Tiptap,
        PageHeader
    },
    data() {
        return {
            isDisabled: false,
            saving: false,
            parents:[],
            pages:[],
            slug:"",
            content:"",
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
            rules: {
                required: value => !!value || this.$t('validation.required')
            },
            colors: ['green', 'purple', 'indigo', 'cyan', 'teal', 'orange'],
            nonce: 1
        }
    },
    methods: {
        getWikiCategory(){
            this.loading = true
            return axios.get(`/api/taxables?term=${this.slug}&taxonomy=&model=`).then((response) => {
                //console.log(response)
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
        async saveCategory() {
            if (this.saving) return;
            this.saving = true

            let data = {term: this.newCategory, taxonomy: this.taxonomyValue, parent: this.parentValue, content: this.content}

            try {
                await axios.post(`/api/wiki/category`, data)

                this.getCategories(this.taxonomyValue.taxonomy)
                this.categoryValue.push(this.newCategory);
                await this.$dialog.success(this.$t('wiki.categoryCreated'))
            } catch (error) {
                if (error.response?.status === 401) {
                    this.$router.push('/auth/signin')
                    return
                }
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
            this.getWikiCategory(this.slug)
        }

        this.getCategories()

    }
}
</script>
