<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <h1>{{post.title}}</h1>
                <div v-html="post.body"></div>

                <div v-if="tags.length || Object.keys(taxonomies).length" class="mt-6">
                    <div v-for="(terms, key) in taxonomies" :key="`tax-${key}`" class="mb-3">
                        <div class="text-caption text-medium-emphasis text-uppercase mb-2">{{ key }}</div>
                        <v-chip
                            v-for="term in terms"
                            :key="`tax-${key}-${term.id}`"
                            :color="term.color || 'primary'"
                            class="me-2 mb-2"
                            variant="tonal"
                            size="small"
                            @click="goToCategory(term.slug, key)"
                        >
                            <v-icon start size="14">mdi-folder-outline</v-icon>
                            {{ term.name }}
                        </v-chip>
                    </div>

                    <div v-if="tags.length" class="mb-3">
                        <div class="text-caption text-medium-emphasis text-uppercase mb-2">Tags</div>
                        <v-chip
                            v-for="tag in tags"
                            :key="`tag-${tag.id}`"
                            :color="tag.color || 'secondary'"
                            class="me-2 mb-2"
                            variant="tonal"
                            size="small"
                            @click="goToTerm(tag.slug)"
                        >
                            <v-icon start size="14">mdi-pound</v-icon>
                            {{ tag.name }}
                        </v-chip>
                    </div>
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
