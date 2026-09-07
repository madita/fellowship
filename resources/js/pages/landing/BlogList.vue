<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-5">
                <h1 class="text-h4 mb-6">{{ $t('blog.title') || 'Blog' }}</h1>

                <v-row v-if="loading">
                    <v-col v-for="i in 6" :key="i" cols="12" sm="6" md="4">
                        <v-skeleton-loader type="article" />
                    </v-col>
                </v-row>

                <v-row v-else-if="posts.length">
                    <v-col
                        v-for="post in posts"
                        :key="post.id"
                        cols="12"
                        sm="6"
                        md="4"
                    >
                        <v-card
                            hover
                            class="h-100 d-flex flex-column"
                            @click="$router.push(`/blog/${post.slug}`)"
                        >
                            <v-card-title class="text-subtitle-1 font-weight-bold">
                                {{ post.title }}
                            </v-card-title>
                            <v-card-text class="flex-grow-1">
                                <div class="text-body-2 text-medium-emphasis post-excerpt">
                                    {{ stripHtml(post.body).substring(0, 150) }}{{ stripHtml(post.body).length > 150 ? '...' : '' }}
                                </div>
                            </v-card-text>
                            <v-card-actions>
                                <v-spacer />
                                <span class="text-caption text-medium-emphasis">
                                    {{ formatDate(post.created_at) }}
                                </span>
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>

                <div v-else class="text-center py-12">
                    <v-icon size="64" color="grey-lighten-1">mdi-post-outline</v-icon>
                    <p class="text-h6 mt-4 text-medium-emphasis">No blog posts yet</p>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1" class="d-flex justify-center mt-6">
                    <v-pagination
                        v-model="currentPage"
                        :length="totalPages"
                        @update:model-value="fetchPosts"
                    />
                </div>
            </v-container>
        </v-sheet>
    </div>
</template>

<script>
export default {
    data() {
        return {
            loading: true,
            posts: [],
            currentPage: 1,
            totalPages: 1,
        };
    },
    methods: {
        fetchPosts() {
            this.loading = true;
            axios.get('/api/posts', { params: { page: this.currentPage } })
                .then((response) => {
                    this.posts = response.data.data;
                    this.totalPages = response.data.last_page;
                    this.loading = false;
                })
                .catch(() => {
                    this.loading = false;
                });
        },
        stripHtml(html) {
            if (!html) return '';
            const div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        },
        formatDate(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
    },
    created() {
        this.fetchPosts();

        this.onLocaleChange = () => this.fetchPosts();
        window.addEventListener('locale-changed', this.onLocaleChange);
    },
    beforeUnmount() {
        if (this.onLocaleChange) {
            window.removeEventListener('locale-changed', this.onLocaleChange);
        }
    },
};
</script>

<style scoped>
.post-excerpt {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
