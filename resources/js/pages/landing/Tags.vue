<template>
    <div>
        <page-header
            :title="$t('pages.tagged.title')"
            :subtitle="$t('pages.tagged.subtitle', { term })"
            icon="mdi-tag-multiple-outline"
        />

        <v-container>
            <loading-state v-if="loading" />
            <v-list v-else-if="hasResults" class="bg-transparent">
                <template
                    v-for="(taxable, type) in taxables.type"
                    :key="type"
                >
                    <v-list-subheader>{{ type }}</v-list-subheader>
                    <v-list-item
                        v-for="model in taxable"
                        :key="model.data.slug"
                        @click="goTo(model.data.slug, type)"
                    >
                        <v-list-item-title v-text="model.taxable_title"></v-list-item-title>
                    </v-list-item>
                </template>
            </v-list>
            <empty-state
                v-else
                icon="mdi-tag-off-outline"
                :title="$t('pages.tagged.noResults')"
                :text="$t('pages.tagged.noResultsText', { term })"
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
            taxables: [],
            taxonomy:"",
            term:"",
            model:""
        }
    },

    computed: {
        hasResults() {
            const types = this.taxables?.type || {}
            return Object.values(types).some(list => list && list.length)
        }
    },

    methods: {
        getTaxables(){
            this.loading = true
            return axios.get(`/api/taxables?term=${this.term}&taxonomy=${this.taxonomy}&model=${this.model}`).then((response) => {
                this.taxables = response.data.data

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
        goTo(slug, type) {
            this.$router.push({ name: type, params: { slug: slug } })
        }

    },

    created() {

        if(this.$route.params.model) {
            this.model = this.$route.params.model;
        }

        if(this.$route.params.term) {
            let term = this.$route.params.term;
            term = term.split(":")
            if(term.length > 1) {
                this.term = term[1]
                this.taxonomy = term[0]
            } else {
                this.term = term[0]
            }

            this.getTaxables()
        }



    }

}
</script>
