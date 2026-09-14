<template>
    <div class="flex-grow-1">
        <page-header :title="$t('admin.polls.title')" :subtitle="$t('admin.polls.subtitle')" icon="mdi-poll">
            <template #actions>
                <v-btn variant="tonal" prepend-icon="mdi-refresh" :loading="refreshing" :disabled="refreshing" @click="refresh">
                    {{ $t('dashboard.refresh') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <loading-state v-if="loadingStats && !stats" />

            <template v-if="stats">
                <!-- Numbers -->
                <v-row dense class="mb-4">
                    <v-col v-for="tile in statTiles" :key="tile.key" cols="6" sm="4" md="2">
                        <v-card variant="tonal" :color="tile.color" class="h-100">
                            <v-card-text class="d-flex align-center pa-3">
                                <v-icon size="28" class="mr-3">{{ tile.icon }}</v-icon>
                                <div>
                                    <div class="text-h5 font-weight-bold">{{ stats[tile.key] ?? '–' }}</div>
                                    <div class="text-caption">{{ $t(`admin.polls.stats.${tile.key}`) }}</div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-row class="mb-2">
                    <!-- Where polls live -->
                    <v-col cols="12" md="5">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-map-marker-multiple-outline</v-icon>
                                {{ $t('admin.polls.byType') }}
                            </v-card-title>
                            <v-card-text>
                                <v-list density="compact" class="pa-0">
                                    <v-list-item
                                        v-for="kind in pollableKinds"
                                        :key="kind"
                                        class="px-0"
                                        :active="filters.pollable === kind"
                                        @click="setPollableFilter(kind)"
                                    >
                                        <template v-slot:prepend>
                                            <v-icon size="18" :color="pollableMeta[kind].color">{{ pollableMeta[kind].icon }}</v-icon>
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ $t(`admin.polls.pollable.${kind}`) }}</v-list-item-title>
                                        <template v-slot:append>
                                            <span class="font-weight-bold">{{ stats.by_type?.[kind] ?? 0 }}</span>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- Most voted -->
                    <v-col cols="12" md="7">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="warning">mdi-trophy-outline</v-icon>
                                {{ $t('admin.polls.mostVoted') }}
                            </v-card-title>
                            <v-card-text>
                                <empty-state
                                    v-if="!stats.most_voted || stats.most_voted.length === 0"
                                    icon="mdi-poll"
                                    :title="$t('admin.polls.mostVotedEmpty')"
                                    compact
                                />
                                <v-list v-else density="compact" class="pa-0">
                                    <v-list-item
                                        v-for="(poll, index) in stats.most_voted"
                                        :key="poll.id"
                                        class="px-0"
                                        @click="openResultsById(poll.id)"
                                    >
                                        <template v-slot:prepend>
                                            <v-avatar size="24" :color="index === 0 ? 'warning' : 'primary'">
                                                <span class="text-caption text-white">{{ index + 1 }}</span>
                                            </v-avatar>
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ poll.title }}</v-list-item-title>
                                        <v-list-item-subtitle v-if="poll.pollable" class="text-caption">
                                            {{ $t(`admin.polls.pollable.${poll.pollable.type}`) }} · {{ poll.pollable.title }}
                                        </v-list-item-subtitle>
                                        <template v-slot:append>
                                            <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-vote-outline">
                                                {{ $t('poll.votesCount', poll.total_votes) }}
                                            </v-chip>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </template>

            <!-- Filters -->
            <v-card variant="outlined" class="mb-4">
                <v-card-text class="pa-3">
                    <v-row dense align="center">
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="filters.search"
                                :placeholder="$t('admin.polls.filters.search')"
                                prepend-inner-icon="mdi-magnify"
                                density="compact"
                                hide-details
                                clearable
                                @update:model-value="onSearchInput"
                            />
                        </v-col>
                        <v-col cols="6" sm="4" md="2">
                            <v-select
                                v-model="filters.status"
                                :items="statusOptions"
                                :label="$t('admin.polls.filters.status')"
                                item-title="label"
                                item-value="value"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>
                        <v-col cols="6" sm="4" md="2">
                            <v-select
                                v-model="filters.type"
                                :items="typeOptions"
                                :label="$t('admin.polls.filters.type')"
                                item-title="label"
                                item-value="value"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>
                        <v-col cols="6" sm="4" md="2">
                            <v-select
                                v-model="filters.pollable"
                                :items="pollableOptions"
                                :label="$t('admin.polls.filters.pollable')"
                                item-title="label"
                                item-value="value"
                                density="compact"
                                hide-details
                                @update:model-value="applyFilters"
                            />
                        </v-col>
                        <v-col cols="6" md="2" class="d-flex justify-end">
                            <v-btn
                                variant="text"
                                size="small"
                                prepend-icon="mdi-filter-off-outline"
                                :disabled="!hasActiveFilters"
                                @click="resetFilters"
                            >
                                {{ $t('admin.polls.filters.reset') }}
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- Poll list -->
            <v-card variant="outlined">
                <v-data-table-server
                    v-model:items-per-page="itemsPerPage"
                    v-model:page="page"
                    :headers="headers"
                    :items="polls"
                    :items-length="meta.total"
                    :items-per-page-options="[10, 25, 50]"
                    :loading="loadingPolls"
                    density="comfortable"
                    item-value="id"
                    @update:options="onTableOptions"
                >
                    <template #loading>
                        <loading-state compact />
                    </template>

                    <template #no-data>
                        <empty-state
                            icon="mdi-poll"
                            :title="$t(hasActiveFilters ? 'admin.polls.emptyFiltered' : 'admin.polls.empty')"
                            :text="hasActiveFilters ? '' : $t('admin.polls.emptyHint')"
                        >
                            <template v-if="hasActiveFilters" #actions>
                                <v-btn variant="tonal" size="small" @click="resetFilters">{{ $t('admin.polls.filters.reset') }}</v-btn>
                            </template>
                        </empty-state>
                    </template>

                    <template #item.title="{ item }">
                        <div class="py-1">
                            <div class="font-weight-medium">{{ item.title }}</div>
                            <div v-if="item.description" class="text-caption text-medium-emphasis text-truncate poll-description">
                                {{ item.description }}
                            </div>
                        </div>
                    </template>

                    <template #item.pollable="{ item }">
                        <v-chip
                            v-if="item.pollable"
                            size="small"
                            variant="tonal"
                            :color="pollableMeta[item.pollable.type]?.color || 'secondary'"
                            :prepend-icon="pollableMeta[item.pollable.type]?.icon || 'mdi-link-variant'"
                            :to="internalLink(item.pollable.url)"
                            :href="externalLink(item.pollable.url)"
                            :target="externalLink(item.pollable.url) ? '_blank' : undefined"
                            :title="item.pollable.title"
                        >
                            <span class="text-truncate pollable-title">
                                {{ $t(`admin.polls.pollable.${item.pollable.type}`) }}<template v-if="item.pollable.title"> · {{ item.pollable.title }}</template>
                            </span>
                        </v-chip>
                        <v-chip v-else size="small" variant="tonal" prepend-icon="mdi-link-off">
                            {{ $t('admin.polls.pollable.unknown') }}
                        </v-chip>
                    </template>

                    <template #item.creator="{ item }">
                        <div v-if="item.creator" class="d-flex align-center">
                            <v-avatar size="24" color="primary" class="mr-2">
                                <span class="text-caption text-white">{{ initial(item.creator.name || item.creator.username) }}</span>
                            </v-avatar>
                            <div>
                                <div class="text-body-2">{{ item.creator.name || item.creator.username }}</div>
                                <div v-if="item.creator.username && item.creator.username !== item.creator.name" class="text-caption text-medium-emphasis">
                                    @{{ item.creator.username }}
                                </div>
                            </div>
                        </div>
                        <span v-else>–</span>
                    </template>

                    <template #item.type="{ item }">
                        <v-chip
                            size="small"
                            variant="tonal"
                            :color="item.type === 'multiple' ? 'info' : 'primary'"
                            :prepend-icon="item.type === 'multiple' ? 'mdi-checkbox-multiple-marked-outline' : 'mdi-radiobox-marked'"
                        >
                            {{ $t(`admin.polls.type.${item.type}`) }}
                        </v-chip>
                        <v-chip v-if="item.anonymous" size="x-small" variant="tonal" class="ml-1" prepend-icon="mdi-incognito">
                            {{ $t('admin.polls.anonymous') }}
                        </v-chip>
                    </template>

                    <template #item.total_votes="{ item }">
                        <span class="font-weight-bold">{{ item.total_votes }}</span>
                    </template>

                    <template #item.is_open="{ item }">
                        <v-chip
                            size="small"
                            variant="tonal"
                            :color="item.is_open ? 'success' : 'default'"
                            :prepend-icon="item.is_open ? 'mdi-lock-open-variant-outline' : 'mdi-lock-outline'"
                        >
                            {{ $t(item.is_open ? 'admin.polls.status.open' : 'admin.polls.status.closed') }}
                        </v-chip>
                        <div v-if="item.closes_at" class="text-caption text-medium-emphasis mt-1">
                            {{ $t(item.is_open ? 'admin.polls.closesAt' : 'admin.polls.closedAt', { time: relative(item.closes_at) }) }}
                        </div>
                    </template>

                    <template #item.created_at="{ item }">
                        <span :title="$formatDate(item.created_at)">{{ relative(item.created_at) }}</span>
                    </template>

                    <template #item.actions="{ item }">
                        <div class="d-flex justify-end ga-1">
                            <v-btn
                                icon="mdi-chart-bar"
                                size="small"
                                variant="text"
                                :title="$t('admin.polls.actions.results')"
                                :aria-label="$t('admin.polls.actions.results')"
                                @click="openResults(item)"
                            />
                            <v-btn
                                :icon="item.is_open ? 'mdi-lock-outline' : 'mdi-lock-open-variant-outline'"
                                size="small"
                                variant="text"
                                :color="item.is_open ? 'warning' : 'success'"
                                :loading="busy[item.id] === 'toggle'"
                                :disabled="!!busy[item.id]"
                                :title="$t(item.is_open ? 'admin.polls.actions.close' : 'admin.polls.actions.reopen')"
                                :aria-label="$t(item.is_open ? 'admin.polls.actions.close' : 'admin.polls.actions.reopen')"
                                @click="toggleOpen(item)"
                            />
                            <v-btn
                                icon="mdi-delete-outline"
                                size="small"
                                variant="text"
                                color="error"
                                :loading="busy[item.id] === 'delete'"
                                :disabled="!!busy[item.id]"
                                :title="$t('admin.polls.actions.delete')"
                                :aria-label="$t('admin.polls.actions.delete')"
                                @click="remove(item)"
                            />
                        </div>
                    </template>
                </v-data-table-server>
            </v-card>
        </v-container>

        <!-- Results dialog -->
        <v-dialog v-model="resultsDialog" max-width="600">
            <v-card v-if="resultsPoll">
                <v-card-title class="d-flex align-center">
                    <v-icon class="mr-2" color="primary">mdi-poll</v-icon>
                    <span class="text-truncate">{{ resultsPoll.title }}</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" size="small" @click="resultsDialog = false" />
                </v-card-title>
                <v-card-subtitle class="d-flex align-center flex-wrap ga-2 pb-2">
                    <span>{{ $t('poll.votesCount', resultsPoll.total_votes) }}</span>
                    <v-chip size="x-small" variant="tonal" :color="resultsPoll.is_open ? 'success' : 'default'">
                        {{ $t(resultsPoll.is_open ? 'admin.polls.status.open' : 'admin.polls.status.closed') }}
                    </v-chip>
                    <v-chip size="x-small" variant="tonal">{{ $t(`admin.polls.type.${resultsPoll.type}`) }}</v-chip>
                    <v-chip v-if="resultsPoll.anonymous" size="x-small" variant="tonal" prepend-icon="mdi-incognito">
                        {{ $t('admin.polls.anonymous') }}
                    </v-chip>
                </v-card-subtitle>
                <v-card-text>
                    <p v-if="resultsPoll.description" class="text-body-2 text-medium-emphasis mb-4">{{ resultsPoll.description }}</p>
                    <poll-results :poll="resultsPoll" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="resultsDialog = false">{{ $t('common.close') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import PollResults from '@/components/poll/PollResults.vue';

const SEARCH_DEBOUNCE_MS = 300;

/**
 * Admin overview of every poll attached to forum threads and timeline
 * posts: numbers from /api/admin/polls/stats, a filterable server-side
 * table from /api/admin/polls, and close / reopen / delete per row.
 */
export default {
    name: 'PollsDashboard',
    components: { PageHeader, EmptyState, LoadingState, PollResults },
    data() {
        return {
            stats: null,
            loadingStats: false,
            polls: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
            loadingPolls: false,
            page: 1,
            itemsPerPage: 25,
            filters: { status: 'all', type: 'all', pollable: 'all', search: '' },
            searchTimer: null,
            // poll id → 'toggle' | 'delete' while a row request is running
            busy: {},
            resultsDialog: false,
            resultsPoll: null,
            pollableKinds: ['thread', 'status', 'page', 'ticket'],
            pollableMeta: {
                thread: { icon: 'mdi-forum-outline', color: 'primary' },
                status: { icon: 'mdi-timeline-text-outline', color: 'info' },
                page: { icon: 'mdi-file-document-outline', color: 'warning' },
                ticket: { icon: 'mdi-ticket-outline', color: 'secondary' },
            },
        };
    },
    computed: {
        refreshing() {
            return this.loadingStats || this.loadingPolls;
        },
        statTiles() {
            return [
                { key: 'total', icon: 'mdi-poll', color: 'primary' },
                { key: 'open', icon: 'mdi-lock-open-variant-outline', color: 'success' },
                { key: 'closed', icon: 'mdi-lock-outline', color: 'secondary' },
                { key: 'votes', icon: 'mdi-vote-outline', color: 'info' },
                { key: 'votes_7d', icon: 'mdi-chart-timeline-variant', color: 'info' },
                { key: 'polls_7d', icon: 'mdi-new-box', color: 'warning' },
            ];
        },
        headers() {
            return [
                { title: this.$t('admin.polls.headers.title'), key: 'title', sortable: false },
                { title: this.$t('admin.polls.headers.pollable'), key: 'pollable', sortable: false },
                { title: this.$t('admin.polls.headers.creator'), key: 'creator', sortable: false },
                { title: this.$t('admin.polls.headers.type'), key: 'type', sortable: false },
                { title: this.$t('admin.polls.headers.votes'), key: 'total_votes', sortable: false, align: 'end' },
                { title: this.$t('admin.polls.headers.status'), key: 'is_open', sortable: false },
                { title: this.$t('admin.polls.headers.created'), key: 'created_at', sortable: false },
                { title: '', key: 'actions', sortable: false, align: 'end' },
            ];
        },
        statusOptions() {
            return [
                { value: 'all', label: this.$t('admin.polls.filters.all') },
                { value: 'open', label: this.$t('admin.polls.status.open') },
                { value: 'closed', label: this.$t('admin.polls.status.closed') },
            ];
        },
        typeOptions() {
            return [
                { value: 'all', label: this.$t('admin.polls.filters.all') },
                { value: 'single', label: this.$t('admin.polls.type.single') },
                { value: 'multiple', label: this.$t('admin.polls.type.multiple') },
            ];
        },
        pollableOptions() {
            return [
                { value: 'all', label: this.$t('admin.polls.filters.all') },
                ...this.pollableKinds.map(kind => ({ value: kind, label: this.$t(`admin.polls.pollable.${kind}`) })),
            ];
        },
        hasActiveFilters() {
            const f = this.filters;
            return f.status !== 'all' || f.type !== 'all' || f.pollable !== 'all' || !!(f.search && f.search.trim());
        },
        queryParams() {
            const params = { page: this.page, per_page: this.itemsPerPage };
            const f = this.filters;
            if (f.status !== 'all') params.status = f.status;
            if (f.type !== 'all') params.type = f.type;
            if (f.pollable !== 'all') params.pollable = f.pollable;
            if (f.search && f.search.trim()) params.search = f.search.trim();
            return params;
        },
    },
    methods: {
        async refresh() {
            await Promise.all([this.loadStats(), this.loadPolls()]);
        },
        async loadStats() {
            if (this.loadingStats) return;
            this.loadingStats = true;
            try {
                const { data } = await axios.get('/api/admin/polls/stats');
                this.stats = data.data;
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.loadingStats = false;
            }
        },
        async loadPolls() {
            this.loadingPolls = true;
            try {
                const { data } = await axios.get('/api/admin/polls', { params: this.queryParams });
                this.polls = data.data || [];
                this.meta = { ...this.meta, ...(data.meta || {}) };
                // The server clamps the page when it is past the end; follow it.
                if (this.meta.current_page && this.meta.current_page !== this.page) {
                    this.page = this.meta.current_page;
                }
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.loadingPolls = false;
            }
        },
        // v-data-table-server fires this on mount and whenever page / items-per-page change.
        onTableOptions({ page, itemsPerPage }) {
            this.page = page;
            this.itemsPerPage = itemsPerPage;
            this.loadPolls();
        },
        applyFilters() {
            if (this.page !== 1) {
                // Changing the page triggers onTableOptions → loadPolls.
                this.page = 1;
                return;
            }
            this.loadPolls();
        },
        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(this.applyFilters, SEARCH_DEBOUNCE_MS);
        },
        resetFilters() {
            clearTimeout(this.searchTimer);
            this.filters = { status: 'all', type: 'all', pollable: 'all', search: '' };
            this.applyFilters();
        },
        setPollableFilter(kind) {
            this.filters.pollable = this.filters.pollable === kind ? 'all' : kind;
            this.applyFilters();
        },
        openResults(poll) {
            this.resultsPoll = { ...poll, results: poll.results || [], user_votes: poll.user_votes || [] };
            this.resultsDialog = true;
        },
        async openResultsById(id) {
            const row = this.polls.find(poll => poll.id === id);
            if (row) {
                this.openResults(row);
                return;
            }
            // Not on the current page — fetch it through the regular poll endpoint.
            try {
                const { data } = await axios.get(`/api/polls/${id}`);
                this.openResults(data.data || data.poll || data);
            } catch (e) {
                this.$dialog.requestError(e);
            }
        },
        async toggleOpen(poll) {
            if (this.busy[poll.id]) return;
            const closing = poll.is_open;
            const confirmed = await this.$dialog.confirm({
                title: this.$t(closing ? 'admin.polls.confirm.closeTitle' : 'admin.polls.confirm.reopenTitle'),
                content: this.$t(closing ? 'admin.polls.confirm.close' : 'admin.polls.confirm.reopen', { title: poll.title }),
                confirmationText: this.$t(closing ? 'admin.polls.actions.close' : 'admin.polls.actions.reopen'),
                color: closing ? 'warning' : 'success',
            });
            if (!confirmed) return;

            this.busy = { ...this.busy, [poll.id]: 'toggle' };
            try {
                const { data } = await axios.patch(`/api/admin/polls/${poll.id}/${closing ? 'close' : 'reopen'}`);
                this.replaceRow(poll.id, data.poll);
                if (this.resultsPoll?.id === poll.id) {
                    this.openResults(this.polls.find(row => row.id === poll.id) || this.resultsPoll);
                }
                this.loadStats();
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.clearBusy(poll.id);
            }
        },
        async remove(poll) {
            if (this.busy[poll.id]) return;
            const confirmed = await this.$dialog.confirmDelete(this.$t('admin.polls.confirm.delete', { title: poll.title }));
            if (!confirmed) return;

            this.busy = { ...this.busy, [poll.id]: 'delete' };
            try {
                await axios.delete(`/api/admin/polls/${poll.id}`);
                if (this.resultsPoll?.id === poll.id) this.resultsDialog = false;
                // Step back a page when the last row of the current page is gone.
                if (this.polls.length === 1 && this.page > 1) {
                    this.page -= 1;
                } else {
                    await this.loadPolls();
                }
                this.loadStats();
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.clearBusy(poll.id);
            }
        },
        replaceRow(id, fresh) {
            if (!fresh) return;
            this.polls = this.polls.map(row => (row.id === id ? { ...row, ...fresh, pollable: fresh.pollable ?? row.pollable } : row));
        },
        clearBusy(id) {
            const busy = { ...this.busy };
            delete busy[id];
            this.busy = busy;
        },
        relative(date) {
            return date ? formatDateDistanceToNow(date) : '';
        },
        initial(name) {
            return (name || '?').charAt(0).toUpperCase();
        },
        isExternal(url) {
            return typeof url === 'string' && /^https?:\/\//i.test(url);
        },
        internalLink(url) {
            return url && !this.isExternal(url) ? url : undefined;
        },
        externalLink(url) {
            return this.isExternal(url) ? url : undefined;
        },
    },
    mounted() {
        // The table loads the list itself via @update:options.
        this.loadStats();
    },
    beforeUnmount() {
        clearTimeout(this.searchTimer);
    },
};
</script>

<style scoped>
.poll-description {
    max-width: 320px;
}

.pollable-title {
    max-width: 260px;
    display: inline-block;
    vertical-align: middle;
}
</style>
