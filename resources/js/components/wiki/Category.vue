<template>
    <div>

        <v-sheet v-if="slug.length > 0 && !loading">
            <v-alert v-if="categories === null" type="info">Die Kategorie existiert nicht</v-alert>
            <v-container class="py-2 pt-lg-5">
<!--                <v-alert>{{message}}</v-alert>-->
                <v-btn v-if="authenticated" class="text-right mx-1" :to="`/wiki/category/${slug}/${mode}`">
                    {{mode}}
                </v-btn>
                <h1>{{info.term.title}}</h1>
                <p v-html="description"></p>

                <div class="sub-category py-1 pt-lg-1" v-if="info.children.length > 0">
                    <h2>Unterkategorien</h2>
                    <div :style="subCssVars">
                    <v-list-group
                        color="primary"
                        v-for="(category, capital) in $helpers.groupTerms(this.info.children)"
                        :key="`${capital}-${category}`"
                    >{{capital.toUpperCase()}}
                        <v-list-item
                            v-for="(child) in category"
                            :key="`${child.slug}`"
                            @click="goToCategory(child.slug)"
                        >
                                <v-list-item-title v-text="child.title"></v-list-item-title>
                        </v-list-item>
                    </v-list-group>
                    </div>
                </div>
            </v-container>


            <v-container class="category py-6 pt-lg-5">
                <h2>Seiten in der Kategorie "{{info.term.title}}" ({{categories.total}})</h2>
                <div :style="catCssVars">
                <v-list-group
                    color="primary"
                    v-for="(category, capital) in categories.capital"
                    :key="`${capital}-${category}`"
                >{{capital.toUpperCase()}}
                    <v-list-item
                        v-for="(model) in category"
                        :key="`${model.data.slug}`"
                        @click="goTo(model.data.slug, model.taxonomy[0].taxonomy)"
                    >
                        <v-list-item-content>
                            <v-list-item-title v-text="model.taxable_title"></v-list-item-title>
                        </v-list-item-content>
                    </v-list-item>
                </v-list-group>
                </div>
            </v-container>
        </v-sheet>
        <v-sheet v-if="!slug.length && !loading">
            <v-container class="py-2 pt-lg-5">
                <h1>Kategorien</h1>
                <p><v-text-field
                    label="Search"
                    v-model="textSearch"
                    hide-details="auto"
                ></v-text-field></p>
            </v-container>
            <v-container class="category py-6 pt-lg-5">
                <div :style="catCssVars">
                <v-list-group
                    color="primary"
                    v-for="(category, capital) in catFilter"
                    :key="`${capital}-${category}`"
                >{{capital.toUpperCase()}}
                    <v-list-item
                        v-for="(term) in category"
                        :key="`${term.slug}`"
                        @click="goToCategory(term.slug)"
                    >
                        <v-list-item-content>
                            <v-list-item-title v-text="term.title"></v-list-item-title>
                        </v-list-item-content>
                    </v-list-item>
                </v-list-group>
                </div>
            </v-container>
        </v-sheet>
    </div>
</template>

<script>

import {mapGetters} from "vuex";

export default {
    components: {
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
                    this.message = "Die Kategorie existiert nicht..willst du sie erstellen."
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
    },
    computed: {
        ...mapGetters({
            authenticated: 'auth/authenticated',
            user: 'auth/user',
        }),
        catCssVars () {

            return {
                'column-count': (this.catTotal  >= 9) || (Object.keys(this.categories.capital).length >= 5) ? 3: 1
            }
        },
        subCssVars () {
            return {
                'column-count': this.info.children.length >= 5 ? 3: 1
            }
        },
        catFilter: function() {
            var textSearch = this.textSearch;

            // return this.categories.capital.filter(function(el) {
            //     return el.title.toLowerCase().indexOf(textSearch.toLowerCase()) !== -1;
            // });

            let filtered = [];

            console.log('test',this.categories.length)

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
/*.category {*/
/*    column-count: var(--cat-column-count);*/
/*}*/

/*.sub-category {*/
/*    column-count: var(--subcat-column-count);*/
/*}*/

.card-outter {
    position: relative;
    padding-bottom: 50px;
}
.card-actions {
    position: absolute;
    bottom: 0;
}
</style>


