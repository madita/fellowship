<template>
    <v-dialog :model-value="modelValue" max-width="1100" scrollable @update:model-value="close">
        <v-card>
            <v-card-title class="text-h6 d-flex align-center">
                <v-icon class="mr-2">mdi-history</v-icon>
                <span v-if="!compareTo">{{ title || $t('wiki.versions') }}</span>
                <span v-else>{{ $t('wiki.compareTitle') }}</span>
                <v-spacer />
                <v-btn
                    v-if="compareTo"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-arrow-left"
                    @click="closeComparison"
                >
                    {{ $t('wiki.versions') }}
                </v-btn>
            </v-card-title>
            <v-divider />

            <v-card-text style="min-height: 320px;">
                <loading-state v-if="listLoading" compact />

                <!-- Two versions next to each other -->
                <div v-else-if="compareTo">
                    <div class="d-flex flex-wrap ga-3 align-center mb-4">
                        <v-select
                            :model-value="compareFrom"
                            :items="baseOptions"
                            :label="$t('wiki.compareFrom')"
                            density="compact"
                            variant="outlined"
                            hide-details
                            class="compare-select"
                            @update:model-value="selectBase"
                        />
                        <v-icon>mdi-arrow-right</v-icon>
                        <v-select
                            :model-value="compareTo"
                            :items="targetOptions"
                            :label="$t('wiki.compareTo')"
                            density="compact"
                            variant="outlined"
                            hide-details
                            class="compare-select"
                            @update:model-value="selectTarget"
                        />
                        <v-spacer />
                        <v-btn-toggle v-model="detailTab" density="compact" variant="outlined" divided mandatory>
                            <v-btn value="diff" size="small" prepend-icon="mdi-file-compare">
                                {{ $t('wiki.tabChanges') }}
                            </v-btn>
                            <v-btn value="full" size="small" prepend-icon="mdi-text-box-outline">
                                {{ $t('wiki.tabFullText') }}
                            </v-btn>
                        </v-btn-toggle>
                    </div>

                    <loading-state v-if="comparisonLoading" compact />

                    <template v-else-if="detailTab === 'diff'">
                        <!-- The title is a single line and gets its own small diff -->
                        <div v-if="titleChanged" class="mb-4">
                            <div class="text-caption text-medium-emphasis mb-1">{{ $t('wiki.fieldTitle') }}</div>
                            <diff-view
                                :old-value="baseState ? baseState.title : ''"
                                :new-value="targetState ? targetState.title : ''"
                                :old-label="versionLabel(compareFrom)"
                                :new-label="versionLabel(compareTo)"
                                :html="false"
                                :allow-mode-switch="false"
                            />
                        </div>

                        <div class="text-caption text-medium-emphasis mb-1">{{ $t('wiki.fieldContent') }}</div>
                        <diff-view
                            v-model:mode="diffMode"
                            :old-value="baseState ? baseState.content : ''"
                            :new-value="targetState ? targetState.content : ''"
                            :old-label="versionLabel(compareFrom)"
                            :new-label="versionLabel(compareTo)"
                            :empty-text="$t('wiki.noTextChanges')"
                        />
                    </template>

                    <!-- The page as it stood at the version on the right -->
                    <div v-else-if="targetState">
                        <v-alert v-if="!targetState.current" type="info" density="compact" class="mb-3">
                            {{ $t('wiki.viewingOldVersion') }}
                        </v-alert>
                        <div class="text-subtitle-1 font-weight-medium mb-1">{{ targetState.title }}</div>
                        <div class="text-caption text-medium-emphasis mb-3">
                            {{ targetState.author && targetState.author.username || $t('wiki.historyUnknownAuthor') }}
                            &middot;
                            {{ versionDate(targetState.date) }}
                            <span v-if="targetState.current" class="ml-2">({{ $t('wiki.currentVersion') }})</span>
                        </div>
                        <v-divider class="mb-3" />
                        <div class="wiki-version-content" v-html="targetState.content"></div>
                    </div>
                </div>

                <empty-state
                    v-else-if="!entries.length"
                    compact
                    icon="mdi-history"
                    :title="$t('wiki.historyEmpty')"
                    :text="$t('wiki.historyEmptyText')"
                />

                <!-- Every recorded change, newest first -->
                <v-list v-else density="comfortable">
                    <v-list-item
                        v-for="(entry, index) in entries"
                        :key="entry.id"
                        :title="describeChange(entry)"
                        :subtitle="entry.excerpt || ''"
                    >
                        <template #prepend>
                            <v-icon :icon="entry.action === 'created' ? 'mdi-file-plus-outline' : 'mdi-pencil-outline'" />
                        </template>
                        <template #append>
                            <div class="d-flex align-center ga-3">
                                <div class="text-caption text-medium-emphasis text-right">
                                    <div>{{ entry.author && entry.author.username || $t('wiki.historyUnknownAuthor') }}</div>
                                    <div>{{ versionDate(entry.date) }}</div>
                                </div>
                                <v-btn
                                    size="small"
                                    variant="tonal"
                                    prepend-icon="mdi-file-compare"
                                    :loading="opening === entry.id"
                                    :disabled="opening !== null"
                                    @click="openComparison(entry, index)"
                                >
                                    {{ $t('wiki.viewChanges') }}
                                </v-btn>
                            </div>
                        </template>
                    </v-list-item>
                </v-list>
            </v-card-text>

            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="close(false)">{{ $t('common.close') }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios';
import { formatDate } from '@/plugins/formatDate.js';
import { useDialog } from '@/composables/useDialog.js';
import DiffView from './DiffView.vue';
import LoadingState from './LoadingState.vue';
import EmptyState from './EmptyState.vue';

/**
 * The recorded changes of a page, and what any two of them differ by.
 *
 *   <version-history-dialog v-model="showVersions" :history-url="`/api/wiki/${slug}/history`" />
 *
 * The list endpoint returns metadata only; the text of a version is fetched
 * from `<historyUrl>/<revision>` when it is first needed and kept afterwards,
 * because a version never changes once it is written.
 */
export default {
    name: 'VersionHistoryDialog',
    components: { DiffView, LoadingState, EmptyState },
    props: {
        modelValue: { type: Boolean, default: false },
        // Endpoint listing the revisions, e.g. /api/wiki/my-page/history
        historyUrl: { type: String, required: true },
        title: { type: String, default: '' },
    },
    emits: ['update:modelValue'],
    setup() {
        return { dialog: useDialog() };
    },
    data() {
        return {
            entries: [],
            listLoading: false,
            opening: null,
            // The comparison: two revision ids, newest on the right. A null
            // base is the state before the first revision, i.e. an empty page.
            compareFrom: null,
            compareTo: null,
            baseState: null,
            targetState: null,
            comparisonLoading: false,
            detailTab: 'diff',
            diffMode: 'unified',
            cache: new Map(),
        };
    },
    computed: {
        versionOptions() {
            return this.entries.map(entry => ({
                value: entry.id,
                title: `${this.versionDate(entry.date)} · ${entry.author?.username || this.$t('wiki.historyUnknownAuthor')}`,
            }));
        },
        /** Anything older than the right side, plus the empty page it started from. */
        baseOptions() {
            const older = this.versionOptions.filter(option => option.value < this.compareTo);

            return [...older, { value: null, title: this.$t('wiki.emptyPage') }];
        },
        targetOptions() {
            return this.versionOptions.filter(option => (
                this.compareFrom === null || option.value > this.compareFrom
            ));
        },
        titleChanged() {
            return (this.baseState?.title || '') !== (this.targetState?.title || '');
        },
    },
    watch: {
        modelValue(open) {
            if (open) this.load();
        },
        historyUrl() {
            this.cache = new Map();
            this.closeComparison();
            if (this.modelValue) this.load();
        },
    },
    mounted() {
        if (this.modelValue) this.load();
    },
    methods: {
        async load() {
            this.closeComparison();
            this.listLoading = true;
            try {
                const response = await axios.get(this.historyUrl);
                this.entries = response.data.data || [];
            } catch (error) {
                this.dialog.requestError(error, this.$t('wiki.historyLoadFailed'));
                this.entries = [];
            } finally {
                this.listLoading = false;
            }
        },
        close(value) {
            if (value) return;
            this.$emit('update:modelValue', false);
        },
        closeComparison() {
            this.compareFrom = null;
            this.compareTo = null;
            this.baseState = null;
            this.targetState = null;
            this.detailTab = 'diff';
        },
        async fetchVersion(id) {
            if (id === null || id === undefined) return null;
            if (this.cache.has(id)) return this.cache.get(id);

            const response = await axios.get(`${this.historyUrl}/${id}`);
            this.cache.set(id, response.data.data);

            return response.data.data;
        },
        /** Show what one entry changed: itself against the revision before it. */
        async openComparison(entry, index) {
            if (this.opening !== null) return;
            this.opening = entry.id;

            // The list runs newest first, so the older version is one further on.
            const previous = this.entries[index + 1];

            try {
                await this.loadComparison(previous ? previous.id : null, entry.id);
                this.compareFrom = previous ? previous.id : null;
                this.compareTo = entry.id;
                this.detailTab = 'diff';
            } catch (error) {
                this.dialog.requestError(error, this.$t('wiki.historyLoadFailed'));
            } finally {
                this.opening = null;
            }
        },
        async loadComparison(fromId, toId) {
            this.comparisonLoading = true;
            try {
                const [base, target] = await Promise.all([this.fetchVersion(fromId), this.fetchVersion(toId)]);
                this.baseState = base;
                this.targetState = target;
            } finally {
                this.comparisonLoading = false;
            }
        },
        async selectBase(value) {
            const previous = this.compareFrom;
            this.compareFrom = value ?? null;
            try {
                await this.loadComparison(this.compareFrom, this.compareTo);
            } catch (error) {
                this.compareFrom = previous;
                this.dialog.requestError(error, this.$t('wiki.historyLoadFailed'));
            }
        },
        async selectTarget(value) {
            const previous = this.compareTo;
            this.compareTo = value;
            try {
                await this.loadComparison(this.compareFrom, this.compareTo);
            } catch (error) {
                this.compareTo = previous;
                this.dialog.requestError(error, this.$t('wiki.historyLoadFailed'));
            }
        },
        versionLabel(id) {
            if (id === null || id === undefined) return this.$t('wiki.emptyPage');

            return this.versionOptions.find(option => option.value === id)?.title || `#${id}`;
        },
        versionDate(value) {
            return value ? formatDate(value) : '';
        },
        /** A revision records only the fields that changed. */
        describeChange(entry) {
            if (entry.action === 'created') return this.$t('wiki.historyCreated');

            const labels = {
                title: this.$t('wiki.fieldTitle'),
                content: this.$t('wiki.fieldContent'),
                slug: this.$t('wiki.fieldSlug'),
            };
            const fields = (entry.fields || []).map(field => labels[field] || field);

            return this.$t('wiki.historyChanged', { fields: fields.join(', ') });
        },
    },
};
</script>

<style scoped>
.compare-select {
    min-width: 220px;
    max-width: 320px;
    flex: 1 1 220px;
}

.wiki-version-content {
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.wiki-version-content :deep(img) {
    max-width: 100%;
    height: auto;
}
</style>
