<template>
    <div class="wiki-container">
        <page-header :title="$t('wiki.title')" :subtitle="$t('wiki.subtitle')" icon="mdi-book-open-page-variant">
            <template #actions>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    @click="createWikiPage"
                >
                    {{ $t('wiki.createPage') }}
                </v-btn>
            </template>

            <v-text-field
                v-model="searchText"
                :label="$t('wiki.searchPlaceholder')"
                prepend-inner-icon="mdi-magnify"
                density="comfortable"
                hide-details
                clearable
                class="search-field"
                @keyup="onSearchInput"
                @click:clear="clearSearch"
            >
                <template v-slot:append-inner>
                    <v-fade-transition>
                        <v-progress-circular
                            v-if="searching"
                            size="24"
                            width="2"
                            color="primary"
                            indeterminate
                        />
                    </v-fade-transition>
                </template>
            </v-text-field>
        </page-header>

        <!-- Content Section -->
        <v-container fluid>
            <!-- Stats Bar -->
            <div class="d-flex flex-wrap align-center ga-3 mb-6" v-if="response.total">
                <v-chip
                    color="primary"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-file-document-multiple"
                >
                    {{ $t('wiki.pagesFound', { count: response.total }) }}
                </v-chip>
                <v-chip
                    v-if="searchText"
                    color="success"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-magnify"
                >
                    {{ $t('wiki.resultsFor', { query: searchText }) }}
                </v-chip>
            </div>

            <!-- Wiki Grid -->
            <div v-if="wikiable.length > 0" class="wiki-grid">
                <v-row>
                    <v-col
                        v-for="(item, index) in wikiable"
                        :key="`wiki-${item.data.id}`"
                        cols="12"
                        sm="6"
                        md="4"
                        lg="4"
                        xl="3"
                    >
                        <v-card
                            class="wiki-card h-100"
                            variant="elevated"
                            rounded="lg"
                            :class="{ 'featured-card': index === 0 && !searchText }"
                            :loading="deletingId === item.data.id"
                            @click="readMore(item.slug)"
                        >
                            <!-- Card Header -->
                            <div class="card-header pa-4">
                                <div class="d-flex justify-space-between align-start mb-3">
                                    <v-avatar
                                        color="primary"
                                        size="40"
                                        class="card-avatar"
                                    >
                                        <v-icon color="white" size="20">mdi-file-document</v-icon>
                                    </v-avatar>

                                    <v-menu>
                                        <template v-slot:activator="{ props }">
                                            <v-btn
                                                icon="mdi-dots-vertical"
                                                size="small"
                                                variant="text"
                                                v-bind="props"
                                                @click.stop
                                            />
                                        </template>
                                        <v-list density="compact">
                                            <v-list-item
                                                prepend-icon="mdi-pencil"
                                                :title="$t('wiki.edit')"
                                                @click="editPage(item.slug)"
                                            />
                                            <v-list-item
                                                prepend-icon="mdi-share"
                                                :title="$t('wiki.share')"
                                                @click="sharePage(item.slug)"
                                            />
                                            <v-list-item
                                                prepend-icon="mdi-delete"
                                                :title="$t('common.delete')"
                                                :disabled="deletingId === item.data.id"
                                                @click="deletePage(item.data.id)"
                                            />
                                        </v-list>
                                    </v-menu>
                                </div>

                                <!-- Categorries/Taxonomies -->
                                <div class="tags-container mb-3" v-if="item.taxonomies?.length">
                                    <v-chip
                                        v-for="term in item.taxonomies.slice(0, 3)"
                                        :key="`term-${term.id}`"
                                        size="x-small"
                                        variant="tonal"
                                        color="secondary"
                                        class="mr-1 mb-1"
                                    >
                                        {{ term.title }}
                                    </v-chip>
                                    <v-chip
                                        v-if="item.taxonomies.length > 3"
                                        size="x-small"
                                        variant="text"
                                        class="mr-1 mb-1"
                                    >
                                        +{{ item.taxonomies.length - 3 }}
                                    </v-chip>
                                </div>

                                <!-- Tags -->
                                <div class="tags-container mb-3" v-if="item.tags?.length">
                                    <v-chip
                                        v-for="tag in item.tags.slice(0, 3)"
                                        :key="`tag-${tag.id}`"
                                        size="x-small"
                                        variant="tonal"
                                        color="secondary"
                                        class="mr-1 mb-1"
                                    >
                                        #{{ tag.title }}
                                    </v-chip>
                                    <v-chip
                                        v-if="item.tags.length > 3"
                                        size="x-small"
                                        variant="text"
                                        class="mr-1 mb-1"
                                    >
                                        +{{ item.tags.length - 3 }}
                                    </v-chip>
                                </div>

                            </div>



                            <!-- Card Content -->
                            <v-card-text class="pa-4 pt-0">
                                <h3 class="card-title text-h6 font-weight-bold mb-3 line-clamp-2">
                                    {{ item.title }}
                                    <v-chip
                                        v-if="isAdmin && item.is_approved === false"
                                        color="warning"
                                        size="x-small"
                                        variant="tonal"
                                        class="ml-1"
                                    >
                                        {{ $t('wiki.pendingApproval') }}
                                    </v-chip>
                                </h3>

                                <div
                                    v-if="item.data.content"
                                    class="card-excerpt text-body-2 text-medium-emphasis line-clamp-3"
                                    v-html="stripHtml(item.data.content).slice(0, 150) + '...'"
                                />

                                <div class="content-placeholder text-body-2 text-disabled" v-else>
                                    {{ $t('wiki.noContent') }}
                                </div>
                            </v-card-text>

                            <!-- Card Footer -->
                            <v-card-actions class="pa-4 pt-0 mt-auto">
                                <div class="d-flex justify-space-between align-center w-100">
                                    <div class="d-flex align-center">
                                        <v-icon size="14" color="medium-emphasis" class="mr-1">
                                            mdi-clock-outline
                                        </v-icon>
                                        <span class="text-caption text-medium-emphasis">
                      {{ formatDate(item.data.updated_at || item.data.created_at) }} {{ item.data.updated_at ? $t('wiki.updated') : $t('wiki.created') }}
                    </span>
                                    </div>

                                    <v-btn
                                        color="primary"
                                        variant="tonal"
                                        size="small"
                                        append-icon="mdi-arrow-right"
                                        @click.stop="readMore(item.slug)"
                                    >
                                        {{ $t('wiki.read') }}
                                    </v-btn>
                                </div>
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>
            </div>

            <!-- Empty State -->
            <empty-state
                v-else-if="!loading"
                :icon="searchText ? 'mdi-magnify' : 'mdi-file-document-plus'"
                :title="searchText ? $t('wiki.noResultsFound') : $t('wiki.noPagesYet')"
                :text="searchText ? $t('wiki.tryAdjustingSearch') : $t('wiki.startBuildingKnowledgeBase')"
            >
                <template #actions>
                    <v-btn
                        v-if="!searchText"
                        color="primary"
                        variant="elevated"
                        prepend-icon="mdi-plus"
                        @click="createWikiPage"
                    >
                        {{ $t('wiki.createFirstPage') }}
                    </v-btn>
                    <v-btn
                        v-else
                        color="primary"
                        variant="tonal"
                        prepend-icon="mdi-refresh"
                        @click="clearSearch"
                    >
                        {{ $t('wiki.clearSearch') }}
                    </v-btn>
                </template>
            </empty-state>

            <!-- Loading State -->
            <loading-state v-if="loading" :text="searchText ? $t('wiki.searching') : $t('wiki.loadingPages')" />

            <!-- Load More -->
            <div v-if="hasMorePages" class="text-center mt-8">
                <v-btn
                    color="primary"
                    variant="tonal"
                    :loading="loading"
                    @click="loadMore"
                >
                    {{ $t('wiki.loadMorePages') }}
                </v-btn>
            </div>
        </v-container>
    </div>
</template>

<script>
import { useUserStore } from "@/store/userStore.js";
import { formatDate as formatDateUtil } from '@/plugins/formatDate.js';
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

export default {
    name: 'WikiComponent',
    components: { PageHeader, EmptyState, LoadingState },
    data() {
        return {
            page: 1,
            loading: false,
            searching: false,
            wikiable: [],
            response: {},
            searchText: "",
            searchTimeout: null,
            cancelToken: null,
            deletingId: null,
        }
    },
    computed: {
        user() {
            const userStore = useUserStore();
            return userStore.user;
        },
        isAdmin() {
            return this.user?.isAdmin || false;
        },
        hasMorePages() {
            return this.response.total > this.response.to && !this.loading;
        }
    },
    methods: {
        async getWikiPages() {
            try {
                this.loading = true;
                const response = await axios.get(`/api/wiki`, {
                    params: { page: this.page, q: this.searchText }
                });
                const data = response.data;
                this.response = data;

                if (this.page === 1) {
                    this.wikiable = data.data;
                } else {
                    this.wikiable = [...this.wikiable, ...data.data];
                }

                this.page++;
            } catch (error) {
                console.error('Error loading wiki pages:', error);
                this.$dialog.requestError(error, this.$t('wiki.loadError'));
            } finally {
                this.loading = false;
            }
        },

        async onSearchInput() {
            // Clear previous timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            // Debounce search
            this.searchTimeout = setTimeout(async () => {
                this.wikiable = [];
                this.page = 1;
                const query = this.searchText.trim();

                // Cancel previous search request
                if (this.cancelToken) {
                    this.cancelToken.cancel('New search initiated');
                }

                if (query === '') {
                    this.getWikiPages();
                    return;
                }

                try {
                    this.searching = true;
                    this.wikiable = await this.searchWithAbort(query);
                    this.page++;
                } catch (err) {
                    if (!axios.isCancel(err)) {
                        console.error('Search error:', err);
                    }
                } finally {
                    this.searching = false;
                }
            }, 300); // 300ms debounce
        },

        async searchWithAbort(query) {
            // Create new cancel token for this request
            this.cancelToken = axios.CancelToken.source();

            const response = await axios.get(`/api/wiki`, {
                params: { page: this.page, q: query },
                cancelToken: this.cancelToken.token
            });
            const data = response.data;
            this.response = data;
            return data.data;
        },

        clearSearch() {
            this.searchText = '';
            this.wikiable = [];
            this.page = 1;
            this.getWikiPages();
        },

        loadMore() {
            this.getWikiPages();
        },

        handleScroll() {
            const scrollHeight = document.documentElement.scrollHeight;
            const scrollTop = document.documentElement.scrollTop;
            const clientHeight = document.documentElement.clientHeight;

            if (this.hasMorePages && scrollTop + clientHeight >= scrollHeight - 100) {
                this.loadMore();
            }
        },

        readMore(slug) {
            this.$router.push(`${this.$route.path}/${slug}`);
        },

        createWikiPage() {
            this.$router.push(`${this.$route.path}/create`);
        },

        editPage(slug) {
            this.$router.push(`${this.$route.path}/${slug}/edit`);
        },

        sharePage(slug) {
            const url = `${window.location.origin}${this.$route.path}/${slug}`;
            navigator.clipboard.writeText(url).then(() => {
                this.$dialog.success(this.$t('wiki.linkCopied'));
            });
        },

        async deletePage(id) {
            if (this.deletingId) return;
            const ok = await this.$dialog.confirmDelete(this.$t('wiki.confirmDelete'), {
                title: this.$t('wiki.deletePage')
            });
            if (!ok) return;

            this.deletingId = id;
            try {
                await axios.delete(`/api/wiki/${id}`);
                this.wikiable = this.wikiable.filter(item => item.data.id !== id);
                this.deletingId = null;
                await this.$dialog.success(this.$t('wiki.pageDeleted'));
            } catch (error) {
                console.error('Error deleting page:', error);
                await this.$dialog.requestError(error, this.$t('wiki.deleteError'));
            } finally {
                this.deletingId = null;
            }
        },

        stripHtml(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || '';
        },

        formatDate(dateString) {
            if (!dateString) return this.$t('wiki.unknown');
            return formatDateUtil(dateString, 'M d, Y');
        }
    },

    mounted() {
        this.getWikiPages();
        window.addEventListener("scroll", this.handleScroll);

        // Listen for locale changes to refetch content in new language
        this.onLocaleChange = () => {
            this.wikiable = [];
            this.page = 1;
            this.getWikiPages();
        };
        window.addEventListener('locale-changed', this.onLocaleChange);
    },

    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);

        // Clean up locale change listener
        if (this.onLocaleChange) {
            window.removeEventListener('locale-changed', this.onLocaleChange);
        }

        if (this.cancelToken) {
            this.cancelToken.cancel('Component unmounted');
        }
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
    },
}
</script>

<style scoped>
.search-field {
    max-width: 600px;
    margin: 0 auto;
}

.wiki-grid {
    margin-bottom: 32px;
}

.wiki-card {
    box-shadow: 0 4px 20px rgba(var(--v-theme-on-surface), 0.08) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    display: flex;
    flex-direction: column;
    background-color: rgb(var(--v-theme-surface));
    animation: slideInUp 0.5s ease-out;
}

.wiki-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(var(--v-theme-on-surface), 0.15) !important;
}

.featured-card {
    background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.05) 0%, rgba(var(--v-theme-secondary), 0.05) 100%);
    border: 2px solid rgba(var(--v-theme-primary), 0.2);
}

.card-header {
    background: rgba(var(--v-theme-on-surface), 0.05);
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.card-avatar {
    box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.3);
}

.tags-container {
    min-height: 24px;
}

.card-title {
    line-height: 1.3 !important;
}

.card-excerpt {
    line-height: 1.5;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
