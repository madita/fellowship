<template>
    <div class="wiki-container">
        <!-- Header Section -->
        <div class="wiki-header">
            <v-container>
                <v-row align="center" class="mb-6">
                    <v-col cols="12" md="8">
                        <h1 class="wiki-title text-h3 font-weight-bold mb-2">
                            Knowledge Base
                        </h1>
                        <p class="text-subtitle-1 text-medium-emphasis">
                            Discover and share knowledge with your community
                        </p>
                    </v-col>
                    <v-col cols="12" md="4" class="text-right">
                        <v-btn
                            color="primary"
                            variant="elevated"
                            size="large"
                            prepend-icon="mdi-plus"
                            @click="createWikiPage"
                            class="create-btn"
                        >
                            Create Page
                        </v-btn>
                    </v-col>
                </v-row>

                <!-- Search Section -->
                <div class="search-section mb-8">
                    <v-text-field
                        v-model="searchText"
                        label="Search wiki pages..."
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        clearable
                        class="search-field"
                        @keyup="onSearchInput"
                        @click:clear="clearSearch"
                    >
                        <template v-slot:append>
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
                </div>
            </v-container>
        </div>

        <!-- Content Section -->
        <v-container>
            <!-- Stats Bar -->
            <div class="stats-bar mb-6" v-if="response.total">
                <v-chip
                    color="primary"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-file-document-multiple"
                >
                    {{ response.total }} pages found
                </v-chip>
                <v-chip
                    v-if="searchText"
                    color="success"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-magnify"
                >
                    Results for "{{ searchText }}"
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
                            :class="{ 'featured-card': index === 0 && !searchText }"
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
                                                title="Edit"
                                                @click="editPage(item.slug)"
                                            />
                                            <v-list-item
                                                prepend-icon="mdi-share"
                                                title="Share"
                                                @click="sharePage(item.slug)"
                                            />
                                            <v-list-item
                                                prepend-icon="mdi-delete"
                                                title="Delete"
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
                                </h3>

                                <div
                                    v-if="item.data.content"
                                    class="card-excerpt text-body-2 text-medium-emphasis line-clamp-3"
                                    v-html="stripHtml(item.data.content).slice(0, 150) + '...'"
                                />

                                <div class="content-placeholder text-body-2 text-disabled" v-else>
                                    No content available
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
                      {{ formatDate(item.data.updated_at || item.data.created_at) }} {{item.data.updated_at ? 'Updated' : 'Created'}}
                    </span>
                                    </div>

                                    <v-btn
                                        color="primary"
                                        variant="tonal"
                                        size="small"
                                        append-icon="mdi-arrow-right"
                                        @click.stop="readMore(item.slug)"
                                    >
                                        Read
                                    </v-btn>
                                </div>
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>
            </div>

            <!-- Empty State -->
            <div v-else-if="!loading" class="empty-state text-center py-12">
                <v-icon size="120" color="disabled" class="mb-4">
                    {{ searchText ? 'mdi-magnify' : 'mdi-file-document-plus' }}
                </v-icon>
                <h3 class="text-h5 font-weight-bold mb-2">
                    {{ searchText ? 'No results found' : 'No wiki pages yet' }}
                </h3>
                <p class="text-body-1 text-medium-emphasis mb-6">
                    {{ searchText
                    ? `Try adjusting your search terms or browse all pages`
                    : 'Start building your knowledge base by creating the first page'
                    }}
                </p>
                <v-btn
                    v-if="!searchText"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    size="large"
                    @click="createWikiPage"
                >
                    Create First Page
                </v-btn>
                <v-btn
                    v-else
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-refresh"
                    @click="clearSearch"
                >
                    Clear Search
                </v-btn>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-state text-center py-12">
                <v-progress-circular
                    size="64"
                    width="4"
                    color="primary"
                    indeterminate
                    class="mb-4"
                />
                <p class="text-body-1 text-medium-emphasis">
                    {{ searchText ? 'Searching...' : 'Loading wiki pages...' }}
                </p>
            </div>

            <!-- Load More -->
            <div v-if="hasMorePages" class="text-center mt-8">
                <v-btn
                    color="primary"
                    variant="outlined"
                    :loading="loading"
                    @click="loadMore"
                    size="large"
                >
                    Load More Pages
                </v-btn>
            </div>
        </v-container>
    </div>
</template>

<script>
import { useUserStore } from "@/store/userStore.js";

export default {
    name: 'WikiComponent',
    data() {
        return {
            page: 1,
            loading: false,
            searching: false,
            wikiable: [],
            response: {},
            searchText: "",
            searchRequest: null,
            searchTimeout: null,
        }
    },
    computed: {
        user() {
            const userStore = useUserStore();
            return userStore.user;
        },
        hasMorePages() {
            return this.response.total > this.response.to && !this.loading;
        }
    },
    methods: {
        async getWikiPages() {
            try {
                this.loading = true;
                const response = await fetch(`/api/wiki?page=${this.page}&q=${this.searchText}`);
                const data = await response.json();
                this.response = data;

                if (this.page === 1) {
                    this.wikiable = data.data;
                } else {
                    this.wikiable = [...this.wikiable, ...data.data];
                }

                this.page++;
            } catch (error) {
                console.error('Error loading wiki pages:', error);
                this.$emit('error', 'Failed to load wiki pages');
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
                if (this.searchRequest) {
                    this.searchRequest.abort();
                }

                if (query === '') {
                    this.getWikiPages();
                    return;
                }

                // Make a new search request
                const controller = new AbortController();
                this.searchRequest = controller;

                try {
                    this.searching = true;
                    this.wikiable = await this.searchWithAbort(query, controller.signal);
                    this.page++;
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        console.error('Search error:', err);
                    }
                } finally {
                    this.searching = false;
                }
            }, 300); // 300ms debounce
        },

        async searchWithAbort(query, signal) {
            const response = await fetch(`/api/wiki?page=${this.page}&q=${query}`, { signal });
            const data = await response.json();
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
                this.$emit('success', 'Link copied to clipboard');
            });
        },

        async deletePage(id) {
            if (confirm('Are you sure you want to delete this page?')) {
                try {
                    await fetch(`/api/wiki/${id}`, { method: 'DELETE' });
                    this.wikiable = this.wikiable.filter(item => item.data.id !== id);
                    this.$emit('success', 'Page deleted successfully');
                } catch (error) {
                    console.error('Error deleting page:', error);
                    this.$emit('error', 'Failed to delete page');
                }
            }
        },

        stripHtml(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || '';
        },

        formatDate(dateString) {
            // console.log('dateString',dateString)
            if (!dateString) return 'Unknown';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }
    },

    mounted() {
        this.getWikiPages();
        window.addEventListener("scroll", this.handleScroll);
    },

    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);
        if (this.searchRequest) {
            this.searchRequest.abort();
        }
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
    },
}
</script>

<style scoped>
.wiki-container {
    min-height: 100vh;
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
}

.wiki-header {
    background: rgba(var(--v-theme-primary), 0.1);
    padding: 32px 0;
    backdrop-filter: blur(10px);
}

/* Light mode header gradient */
.v-theme--light .wiki-header {
    background: linear-gradient(135deg, rgba(25, 118, 210, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
}

/* Dark mode header */
.v-theme--dark .wiki-header {
    background: linear-gradient(135deg, rgba(77, 166, 199, 0.15) 0%, rgba(124, 169, 186, 0.15) 100%);
}

.wiki-title {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Ensure text is visible in both modes */
.v-theme--light .wiki-title {
    background: linear-gradient(135deg, #1976d2 0%, #9c27b0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.v-theme--dark .wiki-title {
    background: linear-gradient(135deg, #4da6c7 0%, #7ca9ba 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.search-section {
    max-width: 600px;
    margin: 0 auto;
}

.search-field {
    border-radius: 16px !important;
}

.stats-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.wiki-grid {
    margin-bottom: 32px;
}

.wiki-card {
    border-radius: 20px !important;
    box-shadow: 0 4px 20px rgba(var(--v-theme-on-surface), 0.08) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    backdrop-filter: blur(10px);
    display: flex;
    flex-direction: column;
    background-color: rgb(var(--v-theme-surface));
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
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.card-avatar {
    box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.3);
}

.tags-container {
    min-height: 24px;
}

.card-title {
    line-height: 1.3 !important;
    color: rgb(var(--v-theme-on-surface));
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

.empty-state,
.loading-state {
    max-width: 400px;
    margin: 0 auto;
}

.create-btn {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.3) !important;
}

/* Button styling */
.v-btn {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
}

/* Responsive design */
@media (max-width: 960px) {
    .wiki-header {
        padding: 24px 0;
    }

    .search-section {
        margin-bottom: 24px;
    }

    .stats-bar {
        justify-content: center;
    }

    .text-right {
        text-align: center !important;
    }

    .create-btn {
        width: 100%;
        margin-top: 16px;
    }
}

/* Card animations */
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

.wiki-card {
    animation: slideInUp 0.5s ease-out;
}

/* Loading animation */
.v-progress-circular {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
    100% {
        opacity: 1;
    }
}
</style>
