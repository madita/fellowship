<template>
    <div>
        <page-header
            :title="$t('wiki.editCategory')"
            :subtitle="info?.term?.title ? $t('wiki.editing', { title: info.term.title }) : slug"
            icon="mdi-folder-edit-outline"
            :breadcrumbs="breadcrumbs"
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
                    <!-- Indented and with the branch written out, and this
                         category's own branch left out: a category under
                         itself takes everything below it out of reach -->
                    <v-autocomplete
                        v-model="parentValue"
                        :items="parentOptions"
                        item-title="title"
                        item-value="id"
                        :label="$t('wiki.parentCategory')"
                        :placeholder="$t('wiki.categoryDialog.noParent')"
                        :hint="$t('wiki.categoryDialog.parentHint')"
                        persistent-hint
                        :no-data-text="$t('wiki.categoryDialog.noCategories')"
                        clearable
                        :disabled="saving"
                    >
                        <template #item="{ props: itemProps, item }">
                            <v-list-item
                                v-bind="itemProps"
                                :title="undefined"
                                :style="{ paddingInlineStart: `${12 + (item.raw.depth || 0) * 18}px` }"
                            >
                                <template #prepend>
                                    <v-icon size="16" class="me-2">
                                        {{ item.raw.depth ? 'mdi-subdirectory-arrow-right' : 'mdi-folder-outline' }}
                                    </v-icon>
                                </template>
                                <v-list-item-title>{{ item.raw.title }}</v-list-item-title>
                                <v-list-item-subtitle v-if="item.raw.path && item.raw.path.length > 1">
                                    {{ item.raw.path.join(' › ') }}
                                </v-list-item-subtitle>
                            </v-list-item>
                        </template>
                    </v-autocomplete>
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
import Tiptap from '@/components/common/tiptap/Tiptap.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import { useAuthStore } from '@/store/authStore.js';
import { section } from '@/utils/crumbs.js';

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

                // The id of the parent category, which is what the picker
                // and the server both speak. It used to be the parent's
                // term, so saving an untouched form quietly dropped it.
                if(this.info.parent_id > 0) {
                    this.parentValue = this.info.parent_id
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
        // The categories as a tree, each with the path above it: a flat
        // list of names could not say where a parent actually sits.
        getCategories() {
            this.loading = true
            return axios.get(`/api/wiki/category-options`).then((response) => {
                this.parents = response.data?.data || []

                this.loading = false
            });
        },
        async updateCategory() {
            if (this.saving) return;
            this.saving = true

            // parent_id, not the picked row: the row's own "parent_id" used
            // to be read as the choice, which it never was.
            let data = {term: this.newCategory, category: this.info, old: this.infoOld, taxonomy: this.taxonomyValue, parent_id: this.parentValue ?? null, content: this.info.description}

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
        /**
         * The categories this one may sit under: everything but itself and
         * what is already below it. The list arrives in reading order, so a
         * parent is always seen before its children and one pass is enough.
         */
        parentOptions() {
            const selfId = this.info?.id;

            if (!selfId) {
                return this.parents;
            }

            const branch = new Set([selfId]);

            this.parents.forEach((option) => {
                if (branch.has(option.parent_id)) {
                    branch.add(option.id);
                }
            });

            return this.parents.filter((option) => !branch.has(option.id));
        },
        // The category being edited, which the form links back to
        breadcrumbs() {
            return [
                section('wiki'),
                section('wikiCategories'),
                { title: this.info?.term?.title || this.slug, to: `/wiki/category/${this.slug}` },
                this.$t('wiki.editCategory'),
            ];
        },
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
