<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <h1>{{post.title}}</h1>
                <div v-html="post.body"></div>

                <v-chip :color="tag.color" v-for="tag in tags" :key="`tag-${tag.id}`" @click="goToTerm(tag.slug)">{{ tag.name }}</v-chip>

                <div v-for="(taxonomy, key) in taxonomies" :key="`tax-${key}`">
                    {{key}}: <v-chip :color="tag.color" v-for="tag in taxonomy" :key="`tax-${tag.id}`" @click="goToCategory(tag.slug, key)">{{ tag.name }}</v-chip>
                </div>
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
            tags: [],
            taxonomies: {},
            slug:""
        }
    },

    methods: {
        getPost(){
            this.loading = true
            return axios.get(`/api/posts/${this.slug}`).then((response) => {
                this.post = response.data.data

                let taxonomies = response.data.taxonomies || {}
                this.tags = taxonomies.tags || []
                delete taxonomies.tags
                this.taxonomies = taxonomies

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
        goToTerm(slug) {
            this.$router.push(`/tags/tags:${slug}/post`)
        },
        goToCategory(slug, taxonomy) {
            this.$router.push(`/tags/${taxonomy}:${slug}/post`)
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
