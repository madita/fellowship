<template>
    <div>
        <page-header :title="$t('pages.tagged.pagesIn', { category })" icon="mdi-folder-outline" />

        <v-container>
            <loading-state v-if="loading" />
            <v-list v-else-if="pages.length" class="bg-transparent">
                <v-list-item
                    v-for="(page, index) in pages"
                    :key="index"
                    prepend-icon="mdi-file-document-outline"
                    @click="goToPage(page.slug)"
                >
                    <v-list-item-title v-text="page.title"></v-list-item-title>
                </v-list-item>
            </v-list>
            <empty-state
                v-else
                icon="mdi-file-document-outline"
                :title="$t('pages.tagged.noPages')"
                :text="$t('pages.tagged.noPagesText', { category })"
            />
        </v-container>
    </div>
</template>

<script>
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

export default {
    components: { PageHeader, EmptyState, LoadingState },
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
            }).finally(() => {
                this.loading = false
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
