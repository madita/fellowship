<template>
    <div>
        <v-sheet v-if="slug.length > 0 && !loading">
            <v-container class="py-2 pt-lg-5">
                <h1>{{info.term.title}}</h1>
                <p v-html="description"></p>

                <div :style="cssVars" class="sub-category py-1 pt-lg-1" v-if="info.children.length > 0">
                    <h2>Unterkategorien</h2>
                    <v-list-item-group
                        color="primary"
                        v-for="(category, capital) in $helpers.groupTerms(this.info.children)"
                        :key="`${capital}-${category}`"
                    >{{capital.toUpperCase()}}
                        <v-list-item
                            v-for="(child) in category"
                            :key="`${child.slug}`"
                            @click="goToCategory(child.slug)"
                        >
                            <v-list-item-content>
                                <v-list-item-title v-text="child.title"></v-list-item-title>
                            </v-list-item-content>
                        </v-list-item>
                    </v-list-item-group>
                </div>
            </v-container>


            <v-container :style="cssVars" class="category py-6 pt-lg-5">
                <v-list-item-group
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
                </v-list-item-group>
            </v-container>
        </v-sheet>
        <v-sheet v-if="!slug.length && !loading">
            <v-container class="py-2 pt-lg-5">
                <h1>Kategorien</h1>
                <p>Todo Suche</p>
            </v-container>
            <v-container :style="cssVars" class="category py-6 pt-lg-5">
                <v-list-item-group
                    color="primary"
                    v-for="(category, capital) in categories.capital"
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
                </v-list-item-group>
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
            children:[],
            info:{},
            slug:"",
            description: "",
        }
    },
    methods: {
        getWikiCategory(){

            this.loading = true
            return axios.get(`/api/taxables?term=${this.slug}&taxonomy=&model=`).then((response) => {
                this.categories = response.data.data
                this.info = response.data.category


                this.description = (this.info.description !== this.info.term.title) ? this.info.description:"";

                // this.children = $helpers.groupTerms(this.info.children)

                // this.children = this.info.children.reduce((acc,cur)=> {
                //     // console.log(cur,acc)
                //     const firstLetter = cur.term.slug[0].toLowerCase();
                //     return { ...acc.term, [firstLetter]: [...(acc.term[firstLetter] || []), cur] };
                // })


                this.loading = false


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
                this.loading = false
                this.categories = response.data
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
            user: 'auth/user',
        }),
        cssVars () {
            return {
                '--cat-column-count': this.categories.length >= 9 ? 3: 1,
                '--subcat-column-count': this.info.children.length >= 5 ? 3: 1
            }
        }
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
.category {
    column-count: var(--cat-column-count);
}

.sub-category {
    column-count: var(--subcat-column-count);
}

.card-outter {
    position: relative;
    padding-bottom: 50px;
}
.card-actions {
    position: absolute;
    bottom: 0;
}
</style>


