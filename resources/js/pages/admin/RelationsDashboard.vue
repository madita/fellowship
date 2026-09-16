<template>
    <div class="flex-grow-1">
        <page-header
            :title="$t('relatedContent.admin.title')"
            :subtitle="$t('relatedContent.admin.subtitle')"
            icon="mdi-link-variant"
            fluid
        >
            <template #actions>
                <v-btn variant="tonal" prepend-icon="mdi-refresh" :loading="refreshing" :disabled="refreshing" @click="refresh">
                    {{ $t('dashboard.refresh') }}
                </v-btn>
                <v-btn color="primary" variant="elevated" prepend-icon="mdi-link-variant-plus" @click="linkDialog = true">
                    {{ $t('relatedContent.admin.linkContent') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <loading-state v-if="loadingStats && !stats" />

            <template v-if="stats">
                <!-- Numbers -->
                <v-row dense class="mb-4">
                    <v-col v-for="tile in statTiles" :key="tile.key" cols="6" sm="4" md="3" lg>
                        <v-card
                            :variant="tile.kind && filters.kind === tile.kind ? 'flat' : 'tonal'"
                            :color="tile.color"
                            class="h-100"
                            :link="!!tile.kind"
                            @click="onTileClick(tile)"
                        >
                            <v-card-text class="d-flex align-center pa-3">
                                <v-icon size="28" class="mr-3">{{ tile.icon }}</v-icon>
                                <div class="tile-text">
                                    <div class="text-h5 font-weight-bold">{{ tile.value ?? '–' }}</div>
                                    <div class="text-caption text-truncate">{{ tile.label }}</div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-row class="mb-2">
                    <!-- Most linked -->
                    <v-col cols="12" md="7">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="warning">mdi-trophy-outline</v-icon>
                                {{ $t('relatedContent.admin.mostLinked') }}
                            </v-card-title>
                            <v-card-text>
                                <empty-state
                                    v-if="!mostLinked.length"
                                    icon="mdi-link-variant-off"
                                    :title="$t('relatedContent.admin.mostLinkedEmpty')"
                                    compact
                                />
                                <v-list v-else density="compact" class="pa-0">
                                    <v-list-item
                                        v-for="(entry, index) in mostLinked"
                                        :key="itemKey(entry.item)"
                                        class="px-0"
                                        :to="internalLink(entry.item.url)"
                                        :href="externalLink(entry.item.url)"
                                        :target="externalLink(entry.item.url) ? '_blank' : undefined"
                                    >
                                        <template #prepend>
                                            <v-avatar size="24" :color="index === 0 ? 'warning' : 'primary'" class="mr-3">
                                                <span class="text-caption text-white">{{ index + 1 }}</span>
                                            </v-avatar>
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ entry.item.title }}</v-list-item-title>
                                        <v-list-item-subtitle class="text-caption">
                                            <v-icon size="14" class="mr-1">{{ kindIcon(entry.item.kind) }}</v-icon>{{ kindLabel(entry.item.kind) }}
                                        </v-list-item-subtitle>
                                        <template #append>
                                            <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-link-variant">
                                                {{ $t('relatedContent.admin.linksCount', entry.count) }}
                                            </v-chip>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- Kind pairs -->
                    <v-col cols="12" md="5">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-vector-link</v-icon>
                                {{ $t('relatedContent.admin.pairs') }}
                            </v-card-title>
                            <v-card-text>
                                <empty-state
                                    v-if="!pairs.length"
                                    icon="mdi-vector-link"
                                    :title="$t('relatedContent.admin.pairsEmpty')"
                                    compact
                                />
                                <div v-else class="d-flex flex-column ga-3">
                                    <div v-for="pair in pairs" :key="`${pair.source_kind}-${pair.related_kind}`">
                                        <div class="d-flex align-center ga-1">
                                            <v-chip size="x-small" variant="tonal" :color="kindColor(pair.source_kind)" :prepend-icon="kindIcon(pair.source_kind)">
                                                {{ kindLabel(pair.source_kind) }}
                                            </v-chip>
                                            <v-icon size="16" class="text-medium-emphasis">mdi-arrow-right</v-icon>
                                            <v-chip size="x-small" variant="tonal" :color="kindColor(pair.related_kind)" :prepend-icon="kindIcon(pair.related_kind)">
                                                {{ kindLabel(pair.related_kind) }}
                                            </v-chip>
                                            <v-spacer />
                                            <span class="font-weight-bold">{{ pair.count }}</span>
                                        </div>
                                        <v-progress-linear :model-value="pairPercent(pair)" color="primary" height="4" rounded class="mt-1" />
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </template>

            <!-- Filters -->
            <v-card variant="outlined" class="mb-4">
                <v-card-text class="pa-3">
                    <v-row dense align="center">
                        <v-col cols="12" md="5">
                            <v-text-field
                                v-model="filters.search"
                                :placeholder="$t('relatedContent.admin.filters.search')"
                                prepend-inner-icon="mdi-magnify"
                                density="compact"
                                hide-details
                                clearable
                                @update:model-value="onSearchInput"
                            />
                        </v-col>
                        <v-col cols="6" md="3">
                            <v-select
                                v-model="filters.kind"
                                :items="kindOptions"
                                :label="$t('relatedContent.admin.filters.kind')"
                                item-title="label"
                                item-value="value"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>
                        <v-col cols="6" md="4" class="d-flex justify-end">
                            <v-btn
                                variant="text"
                                size="small"
                                prepend-icon="mdi-filter-off-outline"
                                :disabled="!hasActiveFilters"
                                @click="resetFilters"
                            >
                                {{ $t('relatedContent.admin.filters.reset') }}
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- Relations -->
            <v-card variant="outlined">
                <v-data-table-server
                    v-model:items-per-page="itemsPerPage"
                    v-model:page="page"
                    :headers="headers"
                    :items="relations"
                    :items-length="meta.total"
                    :items-per-page-options="[10, 25, 50]"
                    :loading="loadingRelations"
                    density="comfortable"
                    item-value="key"
                    @update:options="onTableOptions"
                >
                    <template #loading>
                        <loading-state compact />
                    </template>

                    <template #no-data>
                        <empty-state
                            icon="mdi-link-variant-off"
                            :title="$t(hasActiveFilters ? 'relatedContent.admin.emptyFiltered' : 'relatedContent.admin.empty')"
                            :text="hasActiveFilters ? '' : $t('relatedContent.admin.emptyHint')"
                        >
                            <template #actions>
                                <v-btn v-if="hasActiveFilters" variant="tonal" size="small" @click="resetFilters">
                                    {{ $t('relatedContent.admin.filters.reset') }}
                                </v-btn>
                                <v-btn v-else color="primary" variant="flat" size="small" prepend-icon="mdi-link-variant-plus" @click="linkDialog = true">
                                    {{ $t('relatedContent.admin.linkContent') }}
                                </v-btn>
                            </template>
                        </empty-state>
                    </template>

                    <template v-for="side in ['source', 'related']" :key="side" #[`item.${side}`]="{ item }">
                        <div class="d-flex align-center ga-2 py-1 item-cell">
                            <v-chip
                                size="small"
                                variant="tonal"
                                class="flex-shrink-0"
                                :color="kindColor(item[side].kind)"
                                :prepend-icon="kindIcon(item[side].kind)"
                            >
                                {{ kindLabel(item[side].kind) }}
                            </v-chip>
                            <a
                                v-if="externalLink(item[side].url)"
                                :href="item[side].url"
                                target="_blank"
                                rel="noopener"
                                class="text-truncate text-primary text-decoration-none font-weight-medium"
                            >{{ item[side].title }}</a>
                            <router-link
                                v-else-if="item[side].url"
                                :to="item[side].url"
                                class="text-truncate text-primary text-decoration-none font-weight-medium"
                            >{{ item[side].title }}</router-link>
                            <span v-else class="text-truncate font-weight-medium">{{ item[side].title }}</span>
                        </div>
                    </template>

                    <template #item.arrow>
                        <v-icon size="18" class="text-medium-emphasis">mdi-arrow-right</v-icon>
                    </template>

                    <template #item.created_at="{ item }">
                        <span :title="$formatDate(item.created_at)">{{ relative(item.created_at) }}</span>
                    </template>

                    <template #item.actions="{ item }">
                        <div class="d-flex justify-end">
                            <v-btn
                                icon="mdi-link-variant-off"
                                size="small"
                                variant="text"
                                color="error"
                                :loading="!!busy[item.key]"
                                :disabled="!!busy[item.key]"
                                :title="$t('relatedContent.list.unlink')"
                                :aria-label="$t('relatedContent.list.unlink')"
                                @click="unlink(item)"
                            />
                        </div>
                    </template>
                </v-data-table-server>
            </v-card>
        </v-container>

        <related-content-dialog v-model="linkDialog" @updated="refresh" />
    </div>
</template>

<script>
import axios from 'axios';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import RelatedContentDialog from '@/components/common/RelatedContentDialog.vue';
import { RELATED_KINDS, fetchRelatedKinds, itemKey, kindMeta, relatedKindMixin } from '@/utils/relatedContent.js';

const SEARCH_DEBOUNCE_MS = 300;

/**
 * Admin overview of every link between wiki pages, pages, posts, events and
 * albums: numbers from /api/admin/relations/stats, a filterable server-side
 * table from /api/admin/relations, unlink per row and a "Link content"
 * dialog that starts by picking the source.
 */
export default {
    name: 'RelationsDashboard',
    components: { PageHeader, EmptyState, LoadingState, RelatedContentDialog },
    mixins: [relatedKindMixin],
    data() {
        return {
            stats: null,
            loadingStats: false,
            relations: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
            loadingRelations: false,
            relationsRequest: 0,
            page: 1,
            itemsPerPage: 25,
            filters: { kind: 'all', search: '' },
            searchTimer: null,
            // row key → true while its unlink request runs
            busy: {},
            kinds: [],
            linkDialog: false,
        };
    },
    computed: {
        refreshing() {
            return this.loadingStats || this.loadingRelations;
        },
        kindKeys() {
            const fromApi = this.kinds.map(option => option.kind).filter(Boolean);
            return fromApi.length ? fromApi : RELATED_KINDS;
        },
        statTiles() {
            if (!this.stats) return [];
            const byKind = this.stats.by_kind || {};
            return [
                { key: 'total', icon: 'mdi-link-variant', color: 'primary', value: this.stats.total, label: this.$t('relatedContent.admin.stats.total') },
                { key: 'recent_7d', icon: 'mdi-new-box', color: 'info', value: this.stats.recent_7d, label: this.$t('relatedContent.admin.stats.recent_7d') },
                ...this.kindKeys.map(kind => ({
                    key: `kind-${kind}`,
                    kind,
                    icon: kindMeta(kind).icon,
                    color: kindMeta(kind).color,
                    value: byKind[kind] ?? 0,
                    label: this.kindLabelPlural(kind),
                })),
            ];
        },
        mostLinked() {
            return (this.stats?.most_linked || []).filter(entry => entry && entry.item);
        },
        pairs() {
            return [...(this.stats?.pairs || [])].sort((a, b) => (b.count || 0) - (a.count || 0));
        },
        maxPairCount() {
            return this.pairs.reduce((max, pair) => Math.max(max, pair.count || 0), 0) || 1;
        },
        headers() {
            return [
                { title: this.$t('relatedContent.admin.headers.source'), key: 'source', sortable: false },
                { title: '', key: 'arrow', sortable: false, align: 'center', width: 40 },
                { title: this.$t('relatedContent.admin.headers.related'), key: 'related', sortable: false },
                { title: this.$t('relatedContent.admin.headers.created'), key: 'created_at', sortable: false },
                { title: '', key: 'actions', sortable: false, align: 'end' },
            ];
        },
        kindOptions() {
            return [
                { value: 'all', label: this.$t('relatedContent.admin.filters.all') },
                ...this.kindKeys.map(kind => ({ value: kind, label: this.kindLabel(kind) })),
            ];
        },
        hasActiveFilters() {
            return this.filters.kind !== 'all' || !!(this.filters.search && this.filters.search.trim());
        },
        queryParams() {
            const params = { page: this.page, per_page: this.itemsPerPage };
            if (this.filters.kind !== 'all') params.kind = this.filters.kind;
            if (this.filters.search && this.filters.search.trim()) params.search = this.filters.search.trim();
            return params;
        },
    },
    methods: {
        async refresh() {
            await Promise.all([this.loadStats(), this.loadRelations()]);
        },
        async loadStats() {
            if (this.loadingStats) return;
            this.loadingStats = true;
            try {
                const { data } = await axios.get('/api/admin/relations/stats');
                this.stats = data.data;
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.loadingStats = false;
            }
        },
        async loadKinds() {
            try {
                this.kinds = await fetchRelatedKinds();
            } catch (e) {
                // The built-in kind list covers the filter and tiles.
                this.kinds = [];
            }
        },
        async loadRelations() {
            const request = ++this.relationsRequest;
            this.loadingRelations = true;
            try {
                const { data } = await axios.get('/api/admin/relations', { params: this.queryParams });
                if (request !== this.relationsRequest) return;
                this.relations = (data.data || [])
                    .filter(row => row && row.source && row.related)
                    .map(row => ({ ...row, key: `${itemKey(row.source)}>${itemKey(row.related)}` }));
                this.meta = { ...this.meta, ...(data.meta || {}) };
                // The server clamps the page when it is past the end; follow it.
                if (this.meta.current_page && this.meta.current_page !== this.page) {
                    this.page = this.meta.current_page;
                }
            } catch (e) {
                if (request === this.relationsRequest) this.$dialog.requestError(e);
            } finally {
                if (request === this.relationsRequest) this.loadingRelations = false;
            }
        },
        // v-data-table-server fires this on mount and whenever page / items-per-page change.
        onTableOptions({ page, itemsPerPage }) {
            this.page = page;
            this.itemsPerPage = itemsPerPage;
            this.loadRelations();
        },
        applyFilters() {
            if (this.page !== 1) {
                // Changing the page triggers onTableOptions → loadRelations.
                this.page = 1;
                return;
            }
            this.loadRelations();
        },
        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(this.applyFilters, SEARCH_DEBOUNCE_MS);
        },
        resetFilters() {
            clearTimeout(this.searchTimer);
            this.filters = { kind: 'all', search: '' };
            this.applyFilters();
        },
        onTileClick(tile) {
            if (!tile.kind) return;
            this.filters.kind = this.filters.kind === tile.kind ? 'all' : tile.kind;
            this.applyFilters();
        },
        async unlink(row) {
            if (this.busy[row.key]) return;
            const confirmed = await this.$dialog.confirmDelete(
                this.$t('relatedContent.admin.confirmUnlink', { source: row.source.title, related: row.related.title }),
                { title: this.$t('relatedContent.list.unlinkTitle'), confirmationText: this.$t('relatedContent.list.unlink') },
            );
            if (!confirmed) return;

            this.busy = { ...this.busy, [row.key]: true };
            try {
                await axios.delete('/api/admin/relations', {
                    data: {
                        source_type: row.source.type,
                        source_id: row.source.id,
                        related_type: row.related.type,
                        related_id: row.related.id,
                    },
                });
                // Step back a page when the last row of the current page is gone.
                if (this.relations.length === 1 && this.page > 1) {
                    this.page -= 1;
                } else {
                    await this.loadRelations();
                }
                this.loadStats();
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                const busy = { ...this.busy };
                delete busy[row.key];
                this.busy = busy;
            }
        },
        pairPercent(pair) {
            return Math.round(((pair.count || 0) / this.maxPairCount) * 100);
        },
        relative(date) {
            return date ? formatDateDistanceToNow(date) : '';
        },
    },
    mounted() {
        // The table loads the relations itself via @update:options.
        this.loadStats();
        this.loadKinds();
    },
    beforeUnmount() {
        clearTimeout(this.searchTimer);
    },
};
</script>

<style scoped>
.tile-text {
    min-width: 0;
}

.item-cell {
    max-width: 360px;
}
</style>
