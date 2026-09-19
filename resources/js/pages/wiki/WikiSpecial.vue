<template>
    <div>
        <page-header
            :title="page ? $t(`wiki.special.pages.${page.key}.title`) : $t('wiki.special.title')"
            :subtitle="page ? $t(`wiki.special.pages.${page.key}.description`) : $t('wiki.special.subtitle')"
            :icon="page?.icon || 'mdi-star-four-points-outline'"
            :back-to="page ? { name: 'wiki-special' } : { name: 'wiki-index' }"
        />

        <v-container>
            <!-- The list of special pages -->
            <v-row v-if="!page">
                <v-col v-for="item in specialPages" :key="item.key" cols="12" sm="6" lg="4">
                    <v-card
                        class="special-card h-100"
                        rounded="lg"
                        elevation="1"
                        :to="item.key === 'random' ? undefined : { name: 'wiki-special-page', params: { page: item.key } }"
                        @click="item.key === 'random' ? openRandom() : undefined"
                    >
                        <v-card-text class="d-flex align-center ga-4">
                            <v-avatar :color="item.color" variant="tonal" size="44">
                                <v-icon :icon="item.icon" />
                            </v-avatar>
                            <div>
                                <div class="text-subtitle-1 font-weight-medium">{{ $t(`wiki.special.pages.${item.key}.title`) }}</div>
                                <div class="text-caption text-medium-emphasis">{{ $t(`wiki.special.pages.${item.key}.description`) }}</div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <!-- One special page -->
            <template v-else>
                <loading-state v-if="loading" />

                <template v-else>
                    <!-- Statistics -->
                    <v-row v-if="page.key === 'statistics'" dense>
                        <v-col v-for="stat in statistics" :key="stat.key" cols="6" sm="4" lg="3">
                            <v-card class="special-card h-100" rounded="lg" elevation="1">
                                <v-card-text>
                                    <div class="text-h4 font-weight-bold">{{ stat.value }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ $t(`wiki.special.statistics.${stat.key}`) }}</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>

                    <!-- Categories -->
                    <v-card v-else-if="page.key === 'categories'" class="special-card" rounded="lg" elevation="1">
                        <v-list v-if="items.length" class="py-0">
                            <template v-for="(category, index) in items" :key="category.slug">
                                <v-divider v-if="index > 0" />
                                <v-list-item :to="`/wiki/category/${category.slug}`">
                                    <template #prepend>
                                        <v-icon icon="mdi-folder-outline" color="primary" class="mr-3" />
                                    </template>
                                    <v-list-item-title>{{ category.title }}</v-list-item-title>
                                    <v-list-item-subtitle v-if="category.description" class="plain-text">
                                        {{ stripHtml(category.description) }}
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <v-chip size="small" variant="tonal">
                                            {{ $t('wiki.special.pagesCount', { count: category.pages_count }) }}
                                        </v-chip>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-list>
                        <empty-state v-else compact icon="mdi-folder-outline" :title="$t('wiki.special.empty')" />
                    </v-card>

                    <!-- Lists of pages -->
                    <template v-else>
                        <!-- A–Z, only where it helps -->
                        <div v-if="page.key === 'all-pages' && initials.length" class="d-flex flex-wrap ga-1 mb-4">
                            <v-chip
                                size="small"
                                :variant="letter ? 'tonal' : 'flat'"
                                :color="letter ? undefined : 'primary'"
                                @click="selectLetter(null)"
                            >
                                {{ $t('wiki.special.allLetters') }}
                            </v-chip>
                            <v-chip
                                v-for="initial in initials"
                                :key="initial"
                                size="small"
                                :variant="letter === initial ? 'flat' : 'tonal'"
                                :color="letter === initial ? 'primary' : undefined"
                                @click="selectLetter(initial)"
                            >
                                {{ initial }}
                            </v-chip>
                        </div>

                        <div v-if="page.key === 'by-length'" class="mb-4">
                            <v-btn-toggle v-model="lengthOrder" mandatory density="compact" variant="outlined" divided @update:model-value="load">
                                <v-btn value="long" size="small">{{ $t('wiki.special.longest') }}</v-btn>
                                <v-btn value="short" size="small">{{ $t('wiki.special.shortest') }}</v-btn>
                            </v-btn-toggle>
                        </div>

                        <v-card class="special-card" rounded="lg" elevation="1">
                            <v-list v-if="items.length" class="py-0">
                                <template v-for="(item, index) in items" :key="item.slug">
                                    <v-divider v-if="index > 0" />
                                    <v-list-item :to="`/wiki/${item.slug}`">
                                        <template #prepend>
                                            <v-icon :icon="page.key === 'wanted' ? 'mdi-file-document-plus-outline' : 'mdi-file-document-outline'" class="mr-3" />
                                        </template>
                                        <v-list-item-title>
                                            {{ item.title }}
                                            <v-chip v-if="item.pending" size="x-small" variant="tonal" color="warning" class="ml-2">
                                                {{ $t('wiki.special.pending') }}
                                            </v-chip>
                                        </v-list-item-title>
                                        <template #append>
                                            <span class="text-caption text-medium-emphasis">{{ meta(item) }}</span>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-list>
                            <empty-state v-else compact :icon="page.icon" :title="$t('wiki.special.empty')" />
                        </v-card>
                    </template>
                </template>
            </template>
        </v-container>
    </div>
</template>

<script>
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * The wiki's special pages: the hub at /wiki/special and each list under
 * /wiki/special/{page}, in the spirit of MediaWiki's Special: pages.
 */
export const SPECIAL_PAGES = [
    { key: 'all-pages', icon: 'mdi-format-list-bulleted', color: 'primary', endpoint: '/api/wiki/special/all-pages' },
    { key: 'categories', icon: 'mdi-folder-multiple-outline', color: 'indigo', endpoint: '/api/wiki/special/categories' },
    { key: 'recent-changes', icon: 'mdi-history', color: 'teal', endpoint: '/api/wiki/recent-changes?limit=20' },
    { key: 'wanted', icon: 'mdi-file-document-plus-outline', color: 'error', endpoint: '/api/wiki/special/wanted' },
    { key: 'orphaned', icon: 'mdi-link-off', color: 'warning', endpoint: '/api/wiki/special/orphaned' },
    { key: 'dead-end', icon: 'mdi-arrow-collapse-right', color: 'warning', endpoint: '/api/wiki/special/dead-end' },
    { key: 'uncategorised', icon: 'mdi-folder-remove-outline', color: 'orange', endpoint: '/api/wiki/special/uncategorised' },
    { key: 'by-length', icon: 'mdi-format-line-weight', color: 'blue', endpoint: '/api/wiki/special/by-length' },
    { key: 'statistics', icon: 'mdi-chart-box-outline', color: 'green', endpoint: '/api/wiki/special/statistics' },
    { key: 'random', icon: 'mdi-shuffle-variant', color: 'purple', endpoint: '/api/wiki/special/random' },
];

export default {
    name: 'WikiSpecial',
    components: { PageHeader, EmptyState, LoadingState },
    data() {
        return {
            specialPages: SPECIAL_PAGES,
            items: [],
            initials: [],
            stats: null,
            loading: false,
            letter: null,
            lengthOrder: 'long',
        };
    },
    computed: {
        page() {
            return SPECIAL_PAGES.find(item => item.key === this.$route.params.page) || null;
        },
        statistics() {
            return Object.entries(this.stats || {}).map(([key, value]) => ({ key, value }));
        },
    },
    watch: {
        '$route.params.page'() {
            this.letter = null;
            this.load();
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        stripHtml(value) {
            return (value || '').replace(/<[^>]*>/g, '').trim();
        },
        // The number that matters for this list
        meta(item) {
            if (this.page.key === 'wanted') return this.$t('wiki.special.wantedCount', { count: item.count });
            if (this.page.key === 'by-length') return this.$t('wiki.special.words', { count: item.words });
            if (this.page.key === 'recent-changes') {
                return [item.author?.username, formatDateDistanceToNow(item.date)].filter(Boolean).join(' · ');
            }
            return '';
        },
        selectLetter(letter) {
            this.letter = letter;
            this.load();
        },
        async load() {
            if (!this.page) return;

            this.loading = true;
            try {
                const params = {};
                if (this.page.key === 'all-pages' && this.letter) params.letter = this.letter;
                if (this.page.key === 'by-length') params.order = this.lengthOrder;

                const { data } = await axios.get(this.page.endpoint, { params });

                if (this.page.key === 'statistics') {
                    this.stats = data.data;
                } else {
                    this.items = data.data || [];
                    this.initials = data.initials || [];
                }
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('wiki.special.loadFailed'));
            } finally {
                this.loading = false;
            }
        },
        async openRandom() {
            try {
                const { data } = await axios.get('/api/wiki/special/random');
                if (data.data?.slug) {
                    this.$router.push(`/wiki/${data.data.slug}`);
                } else {
                    await this.$dialog.info(this.$t('wiki.special.empty'));
                }
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('wiki.special.loadFailed'));
            }
        },
    },
};
</script>

<style scoped>
.special-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.plain-text {
    overflow-wrap: anywhere;
}
</style>
