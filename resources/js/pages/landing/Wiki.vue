<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">
            TODO Filter and Loader pagination? links zu wichtigsten seiten...
            <v-progress-circular v-if="loading"
                                 :size="150"
                                 color="primary"
                                 indeterminate
            ></v-progress-circular>
            <template v-for="(wiki, type) in wikiable">
                <h2>{{type}}</h2>
            <v-flex d-flex>
                <v-layout wrap>
                    <v-flex md4 :key="`wiki-${item.data.id}`" v-for="item in wiki">
                        <v-card class="pa-2 ma-1 card-outter"
                                 min-height="374" max-height="374" max-width="374">
                            <v-card-title>{{item.title}}</v-card-title>
                            <v-card-text v-if="item.data.content!=null">
                                <span v-html="item.data.content.slice(0,200)"></span>
                            </v-card-text>
                            <v-spacer></v-spacer>
                            <v-card-actions class="card-actions">
<!--                                <v-chip :key="`term-${term.id}`" v-for="term in item.taxonomies">{{term.title}}</v-chip>-->

                                <v-btn @click="readMore(item.slug)" class="text-right">Read More</v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-flex>
                </v-layout>
            </v-flex>
            </template>
        </v-container>
        <v-container></v-container>

    </div>
</template>

<script>

import {mapGetters} from "vuex";

export default {
    components: {
    },
    data() {
        return {
            loading: false,
            wikiable:[]
        }
    },
    methods: {
        getWikiPages(){
            return axios.get(`/api/wiki`).then((response) => {
                this.wikiable = response.data;
                this.loading = false
            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },
        getWikiPage(){
            //TODO loader
            this.loading = true
            return axios.get(`/api/wiki/${this.slug}`).then((response) => {
            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },
        readMore (slug) {
            this.$router.push(`${this.$route.path}/${slug}`)
        },
    },
    computed: {
        ...mapGetters({
            user: 'auth/user',
        })
    },
    mounted() {
        this.loading = true
        this.getWikiPages();
    }
}
</script>

<style>
img{
    width: 50%;
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


