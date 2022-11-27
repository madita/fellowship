<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">

                <h2>{{wikipage.title}}</h2>

                <span v-html="wikipage.content"></span>

               <v-chip :key="`term-${term.id}`"   @click="goTo(term.slug, 'wiki-category')" v-for="term in taxonomies">{{term.title}}</v-chip>

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
            wikipage:{},
            parents:[],
            taxonomies:[],
            slug:""
        }
    },
    methods: {
        getWikiPage(){
            this.loading = true
            return axios.get(`/api/wiki/${this.slug}`).then((response) => {
                this.wikipage = response.data.page
                this.parents = response.data.parents
                this.taxonomies = response.data.taxonomies
               console.log(this.taxonomies)
            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
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
            user: 'auth/user',
        })
    },
    mounted() {

        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getWikiPage();
        }


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


