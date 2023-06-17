<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">
            <v-progress-circular v-if="loading"
                :size="150"
                color="primary"
                indeterminate
            ></v-progress-circular>
            <template v-if="!loading">
                <v-alert>{{message}}</v-alert>
                <v-btn v-if="authenticated" class="text-right mx-1" :to="`/wiki/${slug}/${mode}`">
                    {{mode}}
                </v-btn>
                <h2>{{wikipage.title}}</h2>
                <span class="mb-1" v-if="redirect.length > 0 && redirect!=='no'">
                    (Weitergeleitet von <a :href="`/wiki/${redirect}?redirect=no`">{{redirect}}</a>)
                </span>

                <span v-html="wikipage.content"></span>
                <span v-html="wikipage.description"></span>

                <v-chip class="ml-1" :key="`term-${term.id}`"   @click="goTo(term.slug, 'wiki-category')" v-for="term in terms">{{term.title}}</v-chip>
                <v-chip class="ml-1" :key="`tag-${tag.id}`"  v-for="tag in tags">#{{tag.title}}</v-chip>

            </template>

        </v-container>

    </div>
</template>

<script>

import {mapGetters} from "vuex";

export default {
    components: {
    },
    data() {
        return {
            loading:false,
            wikipage:{},
            wiki:{},
            redirect:"",
            mode:"",
            message:"",
            parents:[],
            terms:[{title:""}],
            tags:[{title:""}],
            slug:""
        }
    },
    methods: {
        getWikiPage(){
            return axios.get(`/api/wiki/${this.slug}`).then((response) => {
                // this.data = response.data
                this.wikipage = response.data.page
                this.wiki = response.data.wiki

                console.log('redirect', this.redirect)

                if(this.wiki.status === "redirect" && this.redirect!="no") {

                    const pattern = /href="([^"]+)"/;
                    const match = this.wikipage.content.match(pattern);
                    const link = match ? match[1] : '';

                    this.$router.push(`${link}?redirect=${this.slug}`)
                }

                this.parents = response.data.parents
                this.terms = response.data.terms
                this.tags = response.data.tags
                this.mode = "edit"
                this.loading = false
            }).catch((error) => {
                if (error.response.status === 404) {
                    //this.$router.push('/error/not-found')
                    this.wikipage = error.response.data.page
                    this.loading = false
                    this.message = "Die Seite existiert nicht..willst du sie erstellen."
                    this.mode = "create"
                    this.$router.push(`/wiki/${this.wikipage.slug}/create`)
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },
        goTo(slug, type) {
            this.$router.push({ name: type, params: { slug: slug } })
        },
    },
    computed: {
        ...mapGetters({
            authenticated: 'auth/authenticated',
            user: 'auth/user',
        })
    },
    mounted() {

        // console.log('lastlink', eventBus.lastLink)

        this.loading = true
        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getWikiPage();
        }

        if(this.$route.query.redirect) {
           this.redirect = this.$route.query.redirect
        }



    }
}
</script>

<style>
.new {
    color: red!important;
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


