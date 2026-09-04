<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <v-list color="primary" class="bg-transparent">
                    <v-list-item
                        v-for="(page, index) in pages"
                        :key="index"
                        @click="goToPage(page.slug)"
                    >
                        <v-list-item-title v-text="page.title"></v-list-item-title>
                    </v-list-item>
                </v-list>
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
            pages: [],
            taxonomy:"",
            category:""
        }
    },

    methods: {
        getPages(){
            this.loading = true
            return axios.get(`/api/pages/${this.taxonomy}/${this.category}`).then((response) => {
                this.pages = response.data.pages

            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },
        goToPage(slug) {
            this.$router.push(`/${slug}`)
        }

    },

    created() {
        // if(this.$route.params.term) {
        //     this.term = this.$route.params.term;
        //     this.getPages()
        // }

        if(this.$route.params.taxonomy && this.$route.params.category) {
            this.taxonomy = this.$route.params.taxonomy;
            this.category = this.$route.params.category;
            this.getPages()
        }

    }

}
</script>
