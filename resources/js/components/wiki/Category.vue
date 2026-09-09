<template>
    <div>
        <loading-state v-if="loading" />

        <template v-else-if="slug.length > 0">
            <page-header
                :title="info.term?.title || slug"
                icon="mdi-folder-outline"
                back-to="/wiki/category"
            >
                <template v-if="authenticated" #actions>
                    <v-btn
                        color="primary"
                        variant="elevated"
                        :prepend-icon="mode === 'edit' ? 'mdi-pencil' : 'mdi-plus'"
                        :to="`/wiki/category/${slug}/${mode}`"
                    >
                        {{ mode === 'edit' ? $t('wiki.editCategory') : $t('wiki.createCategory') }}
                    </v-btn>
                </template>

                <v-alert v-if="categories === null" type="info" class="mb-4">{{ $t('wiki.categoryNotExists') }}</v-alert>
                <p v-if="description" class="text-body-1 mb-0" v-html="description"></p>
            </page-header>

            <v-container fluid>
                <div class="sub-category mb-6" v-if="info.children?.length">
                    <h2 class="text-h6 mb-2">{{ $t('wiki.subcategories') }}</h2>
                    <div class="category-columns" :style="subCssVars">
                        <div
                            v-for="(category, capital) in $helpers.groupTerms(info.children)"
                            :key="capital"
                            class="category-group"
                        >
                            <v-list density="compact" class="bg-transparent">
                                <v-list-subheader>{{ capital.toUpperCase() }}</v-list-subheader>
                                <v-list-item
                                    v-for="child in category"
                                    :key="child.slug"
                                    @click="goToCategory(child.slug)"
                                >
                                    <v-list-item-title v-text="child.title"></v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </div>
                    </div>
                </div>

                <div class="category">
                    <h2 class="text-h6 mb-2">{{ $t('wiki.pagesInCategory', { category: info.term?.title, count: categories.total }) }}</h2>
                    <empty-state
                        v-if="!categories.total"
                        icon="mdi-file-document-outline"
                        :title="$t('wiki.noPages')"
                        :text="$t('wiki.noPagesHint')"
                    />
                    <div v-else class="category-columns" :style="catCssVars">
                        <div
                            v-for="(category, capital) in categories.capital"
                            :key="capital"
                            class="category-group"
                        >
                            <v-list density="compact" class="bg-transparent">
                                <v-list-subheader>{{ capital.toUpperCase() }}</v-list-subheader>
                                <v-list-item
                                    v-for="model in category"
                                    :key="model.data.slug"
                                    @click="goTo(model.data.slug, model.taxonomy[0]?.taxonomy)"
                                >
                                    <v-list-item-title v-text="model.taxable_title"></v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </div>
                    </div>
                </div>
            </v-container>
        </template>

        <template v-else>
            <page-header
                :title="$t('wiki.categories')"
                :subtitle="$t('wiki.categoriesSubtitle')"
                icon="mdi-folder-multiple-outline"
                back-to="/wiki"
            >
                <v-text-field
                    :label="$t('common.search')"
                    v-model="textSearch"
                    prepend-inner-icon="mdi-magnify"
                    hide-details="auto"
                ></v-text-field>
            </page-header>

            <v-container fluid>
                <empty-state
                    v-if="!Object.keys(catFilter).length"
                    icon="mdi-folder-search-outline"
                    :title="$t('wiki.noCategories')"
                    :text="textSearch ? $t('wiki.noCategoriesHint') : ''"
                />
                <div v-else class="category-columns" :style="catCssVars">
                    <div
                        v-for="(category, capital) in catFilter"
                        :key="capital"
                        class="category-group"
                    >
                        <v-list density="compact" class="bg-transparent">
                            <v-list-subheader>{{ capital.toUpperCase() }}</v-list-subheader>
                            <v-list-item
                                v-for="term in category"
                                :key="term.slug"
                                @click="goToCategory(term.slug)"
                            >
                                <v-list-item-title v-text="term.title"></v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </div>
                </div>
            </v-container>
        </template>
    </div>
</template>

<script>
import { useAuthStore } from '@/store/authStore.js';
import PageHeader from '../common/PageHeader.vue';
import EmptyState from '../common/EmptyState.vue';
import LoadingState from '../common/LoadingState.vue';

export default {
    components: {
        PageHeader,
        EmptyState,
        LoadingState,
    },
    data() {
        return {
            loading:true,
            categories:[],
            catTotal:0,
            children:[],
            info:{},
            slug:"",
            mode:"edit",
            description: "",
            textSearch: "",
        }
    },
    methods: {
        getWikiCategory(){
            this.loading = true
            return axios.get(`/api/taxables?term=${this.slug}&taxonomy=&model=`).then((response) => {
                this.categories = response.data.data
                this.info = response.data.category
                // console.log('dsfksdfdsf', this.info)


                if(this.info !== null) {
                    this.description = (this.info.description !== this.info.term.title) ? this.info.description:"";
                }

                // this.children = $helpers.groupTerms(this.info.children)

                // this.children = this.info.children.reduce((acc,cur)=> {
                //     // console.log(cur,acc)
                //     const firstLetter = cur.term.slug[0].toLowerCase();
                //     return { ...acc.term, [firstLetter]: [...(acc.term[firstLetter] || []), cur] };
                // })





                if(this.categories === null) {
                    this.message = this.$t('wiki.categoryNotExistsCreate')
                    this.mode = "create"
                    this.info = {term: this.slug, children:[]}
                    this.categories = {total:0}
                    this.$router.push(`/wiki/category/${this.slug}/create`)
                }
                this.loading = false



            }).catch((error) => {
                console.log(error)
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
                this.loading = false
                this.categories = response.data
                this.catTotal = this.categories.total
                console.log('tast',Object.keys(this.categories.capital).length)
                // console.log('tast1',this.categories.capital.length)
                this.info = {term: this.slug}
            });
        },
        goTo(slug, type) {
            this.$router.push({ name: type, params: { slug: slug } })
        },
        goToCategory(slug) {
            this.$router.push({ name: 'wiki-category', params: { slug: slug } })
        },
        // 1–4 columns depending on how many entries and letter groups there are.
        columnCount(items, letters) {
            if (items >= 30 || letters >= 12) return 4;
            if (items >= 9 || letters >= 5) return 3;
            if (items >= 4 || letters >= 2) return 2;
            return 1;
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
        catCssVars () {
            const letters = Object.keys(this.categories?.capital || {}).length;
            const total = this.catTotal || this.categories?.total || 0;

            return { 'column-count': this.columnCount(total, letters) }
        },
        subCssVars () {
            const children = this.info.children?.length || 0;

            return { 'column-count': this.columnCount(children, Math.ceil(children / 2)) }
        },
        catFilter: function() {
            var textSearch = this.textSearch;

            // return this.categories.capital.filter(function(el) {
            //     return el.title.toLowerCase().indexOf(textSearch.toLowerCase()) !== -1;
            // });

            let filtered = [];

            // console.log('test',this.categories.length)

            const capitals = this.categories.capital;
            const categories = Object.keys(this.categories.capital);

            let total = 0;
            categories.forEach(function(capital) {

                // console.log(capitals[capital])
                let filteredCapital = [];
                capitals[capital].forEach(function(cat) {
                    if (cat.title.toLowerCase().indexOf(textSearch.toLowerCase()) !== -1) {
                        filteredCapital.push(cat);
                        total++;
                    }
                })
                if(filteredCapital.length > 0) {
                    filtered[capital] = filteredCapital;
                }

            })
            this.catTotal = total;
            return Object.assign({}, filtered); // {0:"a", 1:"b", 2:"c"};
        },
    },
    mounted() {

        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getWikiCategory();
        } else {
            this.getCategories()
        }


    }
}
</script>

<style>
.category-columns {
    column-gap: 24px;
}

/* Keep each letter group (header + its entries) in one column. */
.category-columns .category-group {
    break-inside: avoid;
    -webkit-column-break-inside: avoid;
}

@media (max-width: 960px) {
    .category-columns {
        column-count: 2 !important;
    }
}

@media (max-width: 600px) {
    .category-columns {
        column-count: 1 !important;
    }
}
</style>
