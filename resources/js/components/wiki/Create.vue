<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">

                <h2>{{wikipage.title}}</h2>

                <span v-html="wikipage.content"></span>

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
            slug:""
        }
    },
    methods: {
        getWikiPage(){
            this.loading = true
            return axios.get(`/api/wiki/${this.slug}`).then((response) => {
                this.wikipage = response.data.page
               console.log(response)
            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
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


