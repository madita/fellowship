<template>
    <div>
        <page-header :title="page.title || ''">
            <template #actions>
                <v-btn
                    v-if="!showHistory && !showHistoryItem"
                    variant="tonal"
                    prepend-icon="mdi-history"
                    :loading="loadingHistory"
                    @click="loadHistory"
                >
                    {{ $t('pages.show.history') }}
                </v-btn>
                <v-btn
                    v-if="showHistory || showHistoryItem"
                    variant="tonal"
                    prepend-icon="mdi-file-document-outline"
                    @click="showPage"
                >
                    {{ $t('pages.show.showPage') }}
                </v-btn>
            </template>
            <v-breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" class="pa-0" density="compact"></v-breadcrumbs>
        </page-header>

        <v-container fluid>
            <div v-if="showHistory">
                <v-list v-if="history.length" lines="two" class="bg-transparent">
                    <v-list-item
                        v-for="(item, index) in history"
                        :key="`history-${index}`"
                        prepend-icon="mdi-history"
                        @click="loadHistoryItem(index)"
                    >
                        <v-list-item-title>{{ item.action }} by {{ item.user.username }}</v-list-item-title>
                        <v-list-item-subtitle>{{ $formatDistanceToNow(item.created_at) }}</v-list-item-subtitle>
                    </v-list-item>
                </v-list>
                <empty-state
                    v-else
                    icon="mdi-history"
                    :title="$t('pages.show.noHistory')"
                    :text="$t('pages.show.noHistoryText')"
                />
            </div>
            <div v-else-if="showHistoryItem">
                <v-table>
                    <template v-slot:default>
                        <thead>
                        <tr>
                            <th class="text-left">{{ $t('pages.show.field') }}</th>
                            <th class="text-left">{{ $t('pages.show.oldValue') }}</th>
                            <th class="text-left">{{ $t('pages.show.newValue') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr
                            v-for="(item, key) in historyItem.diff"
                            :key="key"
                        >
                            <td>{{ key }}</td>
                            <td v-html="item.old_value"></td>
                            <td v-html="item.new_value"></td>
                        </tr>
                        </tbody>
                    </template>
                </v-table>
            </div>
            <div v-else v-html="page.content"></div>
            <div v-for="child in page.children" :key="`child-${child.id || child.slug}`"><router-link :to="`/${child.slug}`" class="font-weight-bold">
                {{child.title}}
            </router-link></div>

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
                    <div class="text-caption text-medium-emphasis text-uppercase mb-2">{{ $t('pageForm.terms') }}</div>
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
    </div>
</template>

<script>
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';

export default {
    components: { PageHeader, EmptyState },
    data() {
        return {
            loading: true,
            loadingHistory: false,
            showHistory: false,
            showHistoryItem: false,
            page: [],
            tags: [],
            taxonomies: {},
            history: [],
            historyItem: [],
            slug:"",
            parents:[],
            breadcrumbs: [],
        }
    },

    methods: {
        getPage(){
            this.loading = true
            return axios.get(`/api/pages/${this.slug}`).then((response) => {
                this.page = response.data.page
                this.parents = Object.values(response.data.parents)

                let taxonomies = response.data.taxonomies || {}
                this.tags = taxonomies.tags || [];

                this.breadcrumbs = this.parents.map(x =>  ({
                    title:x.title, to:'/'+x.slug
                })).reverse();

                this.breadcrumbs.push({title:this.page.title, disabled: true})

                delete taxonomies.tags;
                this.taxonomies = taxonomies;

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
        showPage(){
            this.showHistory = false
            this.showHistoryItem = false
        },
        loadHistory() {
            if (this.loadingHistory) return;
            if(!this.page) {
                this.getPage();
            }

            this.loading = true
            this.loadingHistory = true
            return axios.get(`/api/pages/${this.page.id}/history`).then((response) => {
                this.history = response.data

                this.loading = false
                this.showHistory = true
            }).catch((error) => {
                if (error.response?.status === 404) {
                    this.$router.push('/error/not-found')
                    return
                }
                if (error.response?.status === 403) {
                    this.$router.push('/auth/signin')
                    return
                }
                this.$dialog.requestError(error)
            }).finally(() => {
                this.loadingHistory = false
            });

        },
        loadHistoryItem(index) {
            this.showHistory = false
            this.showHistoryItem = true
            this.historyItem = this.history[index]
        },
        goToTerm(slug) {
            this.$router.push(`/tags/tags:${slug}/page`)
        },
        goToCategory(slug, taxonomy) {
            this.$router.push(`/tags/${taxonomy}:${slug}/page`)
        }
    },

    created() {
        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getPage();
        }

        // Listen for locale changes to refetch content in new language
        this.onLocaleChange = () => {
            if (this.slug) {
                this.getPage();
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
