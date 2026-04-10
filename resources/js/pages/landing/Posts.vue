<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <h1>{{post.title}}</h1>
                <div v-html="post.body"></div>
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
            post: [],
            slug:""
        }
    },

    methods: {
        getPost(){
            this.loading = true
            return axios.get(`/api/posts/${this.slug}`).then((response) => {
                this.post = response.data.data

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
            this.getPost();
        }

        // Listen for locale changes to refetch content in new language
        this.onLocaleChange = () => {
            if (this.slug) {
                this.getPost();
            }
        };
        window.addEventListener('locale-changed', this.onLocaleChange);
    },

    beforeUnmount() {
        // Clean up locale change listener
        if (this.onLocaleChange) {
            window.removeEventListener('locale-changed', this.onLocaleChange);
        }
    }

}
</script>
