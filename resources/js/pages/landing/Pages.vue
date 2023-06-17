<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <h1>{{page.title}}</h1>
                <div v-html="page.body"></div>
            </v-container>
        </v-sheet>

    </div>
</template>

<script>

export default {
    components: {

    },
    data() {
        return {
            loading: true,
            page: [],
            slug:""
        }
    },

    methods: {
        getPage(){
            this.loading = true
            return axios.get(`/api/pages/${this.slug}`).then((response) => {
                this.page = response.data.data

                this.loading = false
            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        }
    },

    created() {
        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getPage();
        }

    }

}
</script>
