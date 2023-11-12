<template>
    <div class="d-flex flex-grow-1 flex-column">
        <v-container class="mb-15">
            <p><v-text-field
                label="Search"
                v-model="searchText"
                hide-details="auto"
                @keyup="onSearchInput"
            ></v-text-field></p>
            <v-row>
                <v-col cols="12">
                    <v-row>
<template v-if="wikiable.length>0">


            <v-col :key="`wiki-${item.data.id}`" v-for="(item, index) in wikiable" cols="12" sm="12" md="12" :lg="index===0 ? 8 : 4" >
                <v-card height="100%" elevation="10" class="overflow-hidden card-hover" rounded="md">
                    <div class="position-relative feature-card">

                            <div class="pa-6 d-flex justify-space-between flex-column h-100 position-relative">
                                <div class="d-flex justify-space-between align-center">
                                    <v-avatar size="40">
<!--                                        <img :src="post?.author.avatar" alt="icon" height="40" />-->
                                    </v-avatar>
                                    <v-chip-group>
                                        <v-chip class="mx-1 text-body-2 font-weight-medium bg-grey" size="small" v-for="term in item.taxonomies" v-text="term?.title"></v-chip>
                                    </v-chip-group>

                                </div>
                                <div>
<!--                                    <h3 class="text-h3 text-20 my-6">-->
<!--                                        <RouterLink class="text-decoration-none color-inherits" :to="item.slug">{{ item?.title }}</RouterLink>-->
<!--                                    </h3>-->
                                    <v-card-title>{{item.title}}</v-card-title>
                                    <v-card-text v-if="item.data.content!=null">
                                        <span v-html="item.data.content.slice(0,200)"></span>
                                    </v-card-text>
                                    <div class="d-flex align-center justify-space-between">

<!--                                        <div class="d-flex align-center">-->
<!--                                            <div class="d-flex align-center">-->
<!--                                                <v-icon>mdi-eye</v-icon>-->
<!--                                                <span class="text-subtitle-1 ml-2" v-text="0"></span>-->
<!--                                            </div>-->
<!--                                            <div class="d-flex align-center">-->
<!--                                                <v-avatar class="ml-6 text-surface" size="18">-->
<!--                                                  message-->
<!--                                                </v-avatar>-->
<!--                                                <span class="text-subtitle-1 ml-2" v-text="0"></span>-->
<!--                                            </div>-->
<!--                                        </div>-->
                                        <div>
                                            <v-avatar size="10" class="text-surface">
                                                circle
                                            </v-avatar>
<!--                                            <span class="text-subtitle-2 ml-2" v-text="format(new Date(post?.createdAt), 'E, MMM d')"></span>-->
                                        </div>
                                    </div>
                                    <v-btn @click="readMore(item.slug)" class="text-right">Read More</v-btn>

                                </div>
                            </div>

                    </div>
                </v-card>
            </v-col>

</template>
                    </v-row>
                </v-col>
            </v-row>

<!--            <v-col cols="12" md="4" sm="4" v-if="wikiable.length>0">-->
<!--                <v-card :key="`wiki-${item.data.id}`" elevation="10"  rounded="md" class="card-hover" v-for="item in wikiable">-->
<!--                    <v-card-title>{{item.title}}</v-card-title>-->
<!--                    <v-card-text v-if="item.data.content!=null">-->
<!--                        <span v-html="item.data.content.slice(0,200)"></span>-->
<!--                    </v-card-text>-->
<!--                    <v-spacer></v-spacer>-->
<!--                    <v-card-actions class="card-actions">-->
<!--                        &lt;!&ndash;                                <v-chip :key="`term-${term.id}`" v-for="term in item.taxonomies">{{term.title}}</v-chip>&ndash;&gt;-->

<!--                        <v-btn @click="readMore(item.slug)" class="text-right">Read More</v-btn>-->
<!--                    </v-card-actions>-->
<!--                </v-card>-->
<!--            </v-col>-->

<!--            <v-flex d-flex v-if="wikiable.length>0">-->
<!--                <v-layout wrap>-->
<!--                    <v-flex md4 :key="`wiki-${item.data.id}`" v-for="item in wikiable">-->
<!--                        <v-card class="pa-2 ma-1 card-outter"-->
<!--                                min-height="374" max-height="374" max-width="374">-->
<!--                            <v-card-title>{{item.title}}</v-card-title>-->
<!--                            <v-card-text v-if="item.data.content!=null">-->
<!--                                <span v-html="item.data.content.slice(0,200)"></span>-->
<!--                            </v-card-text>-->
<!--                            <v-spacer></v-spacer>-->
<!--                            <v-card-actions class="card-actions">-->
<!--                                &lt;!&ndash;                                <v-chip :key="`term-${term.id}`" v-for="term in item.taxonomies">{{term.title}}</v-chip>&ndash;&gt;-->

<!--                                <v-btn @click="readMore(item.slug)" class="text-right">Read More</v-btn>-->
<!--                            </v-card-actions>-->
<!--                        </v-card>-->
<!--                    </v-flex>-->
<!--                </v-layout>-->
<!--            </v-flex>-->

            <v-progress-circular v-if="loading"
                                 :size="150"
                                 color="bg-primary"
                                 indeterminate
            ></v-progress-circular>

        </v-container>
        <v-container></v-container>
    </div>
</template>

<script>

// import {mapGetters} from "vuex";

import {useUserStore} from "@/store/userStore.js";

export default {
    components: {
    },
    data() {
        return {
            page:1,
            loading: false,
            wikiable:[],
            response:[],
            wikiableTotal:0,
            searchText:"",
            searchRequest: null,
        }
    },
    methods: {
        async getWikiPages(){
            try {
                this.loading = true;
                const response = await fetch(`/api/wiki?page=${this.page}&q=${this.searchText}`);
                const data = await response.json();
                this.response = data;
                // console.log(Object.entries(data));
                this.wikiable = [...this.wikiable, ...data.data];

                this.page++;
                this.loading = false;
            } catch (error) {
                console.error(error);
                this.loading = false;
            }
        },
        // async getSearchWiki() {
        //     this.wikiable = [];
        //     this.page = 1;
        //     this.loading = true;
        //
        //     const query = this.searchText.trim();
        //
        //     // Cancel previous search request
        //     if (this.searchRequest) {
        //         this.searchRequest.abort();
        //     }
        //
        //     // Make a new search request
        //     this.searchRequest = new AbortController();
        //
        //     try {
        //         const response = await fetch(`/api/wiki?page=${this.page}&q=${this.searchText}`);
        //         const data = await response.json();
        //         this.wikiable = [...data.data];
        //         this.loading = false;
        //     } catch (error) {
        //         console.error(error);
        //         this.loading = false;
        //     }
        //
        // },
        async search(query) {
            const response = await fetch(`/api/wiki?page=${this.page}&q=${query}`);
            const data = await response.json();
            this.response = data;
            return data.data;
        },
        async onSearchInput() {
            this.wikiable = [];
            this.page = 1;
            const query = this.searchText.trim();

            // Cancel previous search request
            if (this.searchRequest) {
                this.searchRequest.abort();
            }

            // Make a new search request
            const controller = new AbortController();
            this.searchRequest = controller;
            try {
                this.wikiable = await this.searchWithAbort(query, controller.signal);
                this.page++;
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error(err);
                }
            }
        },
        async searchWithAbort(query, signal) {
            const response = await fetch(`/api/wiki?page=${this.page}&q=${query}`, { signal });
            const data = await response.json();
            this.response = data;
            return data.data;
        },
        handleScroll() {

            const scrollHeight = document.documentElement.scrollHeight;
            const scrollTop = document.documentElement.scrollTop;
            const clientHeight = document.documentElement.clientHeight;

            if (this.response.total > this.response.to && scrollTop + clientHeight >= scrollHeight && !this.loading) {
                // console.log('endofline')
                this.getWikiPages();
            }
        },
        getWikiPage(){
            //TODO loader
            this.loading = true
            return axios.get(`/api/wiki/${this.slug}`).then(() => {
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
        // ...mapGetters({
        //     user: 'auth/user',
        // }),
        user() {
            const userStore = useUserStore()
            return userStore.user
        }

    },

    mounted() {
        this.getWikiPages();
        window.addEventListener("scroll", this.handleScroll);
        // document.addEventListener('keyup', this.search);
    },
    beforeDestroy() {
        window.removeEventListener("scroll", this.handleScroll);
        if (this.searchRequest) {
            this.searchRequest.abort();
        }
        // document.removeEventListener('keyup', this.search);
    },
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


