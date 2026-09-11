<template>
    <v-dialog
        :model-value="modelValue"
        max-width="600"
        :persistent="saving"
        @update:model-value="onDialogToggle"
    >
        <v-card>
            <v-card-title class="text-h6">{{ $t('relatedContent.dialog.title') }}</v-card-title>
            <v-divider />

            <v-card-text class="pt-4">
                <!-- What is being linked -->
                <div class="d-flex align-center flex-wrap ga-2 mb-4">
                    <template v-if="source">
                        <span class="text-body-2 text-medium-emphasis">{{ $t('relatedContent.dialog.linking') }}</span>
                        <v-chip
                            v-if="source.kind"
                            size="small"
                            variant="tonal"
                            :color="kindColor(source.kind)"
                            :prepend-icon="kindIcon(source.kind)"
                        >
                            {{ kindLabel(source.kind) }}
                        </v-chip>
                        <span class="text-body-1 font-weight-medium text-truncate source-title">{{ source.title || '–' }}</span>
                        <v-btn
                            v-if="!hasFixedSource"
                            variant="text"
                            size="small"
                            prepend-icon="mdi-swap-horizontal"
                            :disabled="saving"
                            @click="clearSource"
                        >
                            {{ $t('relatedContent.dialog.changeSource') }}
                        </v-btn>
                    </template>
                    <span v-else class="text-body-2 text-medium-emphasis">{{ $t('relatedContent.dialog.pickSource') }}</span>
                </div>

                <!-- Which kind to search -->
                <loading-state v-if="loadingKinds && !kinds.length" compact />
                <v-chip-group
                    v-else
                    v-model="kind"
                    mandatory
                    column
                    color="primary"
                    class="mb-2"
                    @update:model-value="onKindChange"
                >
                    <v-chip
                        v-for="option in kinds"
                        :key="option.kind"
                        :value="option.kind"
                        :prepend-icon="option.icon || kindIcon(option.kind)"
                        :disabled="saving"
                        variant="tonal"
                        size="small"
                        filter
                    >
                        {{ kindLabel(option.kind, option.label) }}
                    </v-chip>
                </v-chip-group>

                <v-text-field
                    v-model="search"
                    :placeholder="$t('relatedContent.dialog.search')"
                    prepend-inner-icon="mdi-magnify"
                    density="compact"
                    hide-details
                    clearable
                    :loading="searching"
                    :disabled="saving || !kind"
                    class="mb-3"
                />

                <!-- Search results -->
                <div class="related-results">
                    <loading-state v-if="searching && !results.length" compact />
                    <empty-state
                        v-else-if="!results.length"
                        compact
                        icon="mdi-magnify-close"
                        :title="$t('relatedContent.dialog.noResults')"
                        :text="$t('relatedContent.dialog.noResultsHint')"
                    />
                    <v-list v-else density="compact" class="py-0" :disabled="saving">
                        <v-list-item
                            v-for="item in results"
                            :key="itemKey(item)"
                            :disabled="isDisabled(item)"
                            :active="isSelected(item)"
                            color="primary"
                            rounded="lg"
                            @click="pick(item)"
                        >
                            <template #prepend>
                                <v-avatar
                                    size="36"
                                    rounded="lg"
                                    class="mr-3"
                                    :color="item.image ? undefined : kindColor(item.kind)"
                                    :variant="item.image ? 'flat' : 'tonal'"
                                >
                                    <v-img v-if="item.image" :src="item.image" cover />
                                    <v-icon v-else size="20">{{ kindIcon(item.kind) }}</v-icon>
                                </v-avatar>
                            </template>
                            <v-list-item-title class="text-body-2 font-weight-medium">{{ item.title }}</v-list-item-title>
                            <v-list-item-subtitle v-if="item.subtitle" class="text-caption">{{ item.subtitle }}</v-list-item-subtitle>
                            <template #append>
                                <v-icon
                                    v-if="isLinked(item)"
                                    size="20"
                                    color="success"
                                    :title="$t('relatedContent.dialog.alreadyLinked')"
                                >
                                    mdi-check-circle
                                </v-icon>
                                <v-icon v-else-if="isSelected(item)" size="20" color="primary">mdi-checkbox-marked-circle</v-icon>
                                <v-icon v-else size="20" class="text-medium-emphasis">
                                    {{ pickingSource ? 'mdi-chevron-right' : 'mdi-plus-circle-outline' }}
                                </v-icon>
                            </template>
                        </v-list-item>
                    </v-list>
                </div>

                <!-- Picked targets -->
                <div v-if="selected.length" class="mt-4">
                    <div class="text-caption text-medium-emphasis mb-2">
                        {{ $t('relatedContent.dialog.selected', { n: selected.length }) }}
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="item in selected"
                            :key="`selected-${itemKey(item)}`"
                            size="small"
                            variant="tonal"
                            :color="kindColor(item.kind)"
                            :prepend-icon="kindIcon(item.kind)"
                            :disabled="saving"
                            closable
                            @click:close="unselect(item)"
                        >
                            <span class="text-truncate chip-title">{{ item.title }}</span>
                        </v-chip>
                    </div>
                </div>
            </v-card-text>

            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" :disabled="saving" @click="close">{{ $t('common.cancel') }}</v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    :loading="saving"
                    :disabled="!canSave"
                    @click="save"
                >
                    {{ $t('relatedContent.dialog.link', selected.length) }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { fetchRelatedKinds, itemKey, relatedKindMixin } from '@/utils/relatedContent.js';

const SEARCH_DEBOUNCE_MS = 300;
const SEARCH_LIMIT = 20;

/**
 * Links content (wiki pages, pages, posts, events, albums) to a source item.
 *
 *   <related-content-dialog v-model="open" source-type="App\Models\Page" :source-id="page.id" :source-title="page.title" @updated="reload" />
 *
 * Without a source the user first picks one (kind chips + search), then the
 * targets. Saving posts every selected item at once and emits `updated`
 * with the relations the API returns.
 */
export default {
    name: 'RelatedContentDialog',
    components: { EmptyState, LoadingState },
    mixins: [relatedKindMixin],
    props: {
        modelValue: { type: Boolean, default: false },
        sourceType: { type: String, default: '' },
        sourceId: { type: [Number, String], default: null },
        sourceTitle: { type: String, default: '' },
    },
    emits: ['update:modelValue', 'updated'],
    data() {
        return {
            kinds: [],
            loadingKinds: false,
            kind: null,
            search: '',
            // Term of the last search sent, so the debounce skips no-op changes
            lastTerm: '',
            results: [],
            searching: false,
            searchTimer: null,
            searchRequest: 0,
            // Source chosen inside the dialog when none is passed in
            pickedSource: null,
            // itemKey()s of everything already linked to the source
            linkedKeys: [],
            loadingLinked: false,
            linkedRequest: 0,
            selected: [],
            saving: false,
        };
    },
    computed: {
        hasFixedSource() {
            return !!this.sourceType && this.sourceId !== null && this.sourceId !== '';
        },
        source() {
            if (this.hasFixedSource) {
                return {
                    type: this.sourceType,
                    id: this.sourceId,
                    title: this.sourceTitle,
                    kind: this.kinds.find(option => option.type === this.sourceType)?.kind || null,
                };
            }
            return this.pickedSource;
        },
        sourceKey() {
            return itemKey(this.source);
        },
        pickingSource() {
            return !this.source;
        },
        selectedKeys() {
            return this.selected.map(itemKey);
        },
        canSave() {
            return !!this.source && this.selected.length > 0 && !this.saving && !this.loadingLinked;
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(open) {
                if (open) this.open();
                else this.reset();
            },
        },
        search(value) {
            if (!this.modelValue) return;
            clearTimeout(this.searchTimer);
            if ((value || '').trim() === this.lastTerm) return;
            this.searchTimer = setTimeout(this.runSearch, SEARCH_DEBOUNCE_MS);
        },
        // A source picked (or changed) inside the dialog: start over for its targets.
        sourceKey(key) {
            if (!this.modelValue) return;
            this.selected = [];
            this.linkedKeys = [];
            if (key) this.loadLinked();
            this.runSearch();
        },
    },
    methods: {
        async open() {
            if (this.source) this.loadLinked();
            await this.loadKinds();
            if (this.modelValue && this.kind) this.runSearch();
        },
        async loadKinds() {
            if (this.kinds.length || this.loadingKinds) return;
            this.loadingKinds = true;
            try {
                this.kinds = await fetchRelatedKinds();
                if (!this.kind && this.kinds.length) this.kind = this.kinds[0].kind;
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.loadingKinds = false;
            }
        },
        onKindChange(value) {
            if (value && this.modelValue) this.runSearch();
        },
        async runSearch() {
            clearTimeout(this.searchTimer);
            if (!this.kind) return;
            const request = ++this.searchRequest;
            const term = (this.search || '').trim();
            this.lastTerm = term;
            const params = { kind: this.kind, limit: SEARCH_LIMIT };
            if (term) params.search = term;
            if (this.source) {
                params.exclude_type = this.source.type;
                params.exclude_id = this.source.id;
            }

            this.searching = true;
            try {
                const { data } = await axios.get('/api/relateable/items', { params });
                if (request !== this.searchRequest) return;
                this.results = data.data || [];
            } catch (e) {
                if (request !== this.searchRequest) return;
                this.results = [];
                this.$dialog.requestError(e);
            } finally {
                if (request === this.searchRequest) this.searching = false;
            }
        },
        async loadLinked() {
            const source = this.source;
            if (!source) return;
            const request = ++this.linkedRequest;
            this.loadingLinked = true;
            try {
                const { data } = await axios.get('/api/relateable/related', { params: { type: source.type, id: source.id } });
                if (request !== this.linkedRequest) return;
                this.linkedKeys = (data.data || []).map(relation => itemKey(relation.item));
                this.selected = this.selected.filter(item => !this.linkedKeys.includes(itemKey(item)));
            } catch (e) {
                if (request !== this.linkedRequest) return;
                this.linkedKeys = [];
                this.$dialog.requestError(e);
            } finally {
                if (request === this.linkedRequest) this.loadingLinked = false;
            }
        },
        isLinked(item) {
            return !this.pickingSource && this.linkedKeys.includes(itemKey(item));
        },
        isSelected(item) {
            return this.selectedKeys.includes(itemKey(item));
        },
        isDisabled(item) {
            return this.isLinked(item) || (!this.pickingSource && itemKey(item) === this.sourceKey);
        },
        pick(item) {
            if (this.saving || this.isDisabled(item)) return;
            if (this.pickingSource) {
                // The sourceKey watcher loads its links and searches for targets.
                this.pickedSource = item;
                this.search = '';
                return;
            }
            if (this.isSelected(item)) {
                this.unselect(item);
            } else {
                this.selected = [...this.selected, item];
            }
        },
        unselect(item) {
            const key = itemKey(item);
            this.selected = this.selected.filter(entry => itemKey(entry) !== key);
        },
        clearSource() {
            if (this.saving) return;
            this.pickedSource = null;
        },
        async save() {
            if (!this.canSave) return;
            const source = this.source;
            const count = this.selected.length;
            this.saving = true;
            try {
                const { data } = await axios.post('/api/relateable/relations', {
                    source_type: source.type,
                    source_id: source.id,
                    items: this.selected.map(item => ({ type: item.type, id: item.id })),
                });
                this.$emit('updated', data.data || []);
                this.saving = false;
                this.close();
                this.$dialog.success(data.message || this.$t('relatedContent.dialog.linked', count));
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.saving = false;
            }
        },
        close() {
            if (this.saving) return;
            this.$emit('update:modelValue', false);
        },
        onDialogToggle(value) {
            if (value) this.$emit('update:modelValue', true);
            else this.close();
        },
        reset() {
            clearTimeout(this.searchTimer);
            // Invalidate requests still in flight
            this.searchRequest++;
            this.linkedRequest++;
            this.searching = false;
            this.loadingLinked = false;
            this.kind = this.kinds[0]?.kind || null;
            this.search = '';
            this.lastTerm = '';
            this.results = [];
            this.pickedSource = null;
            this.linkedKeys = [];
            this.selected = [];
        },
    },
    beforeUnmount() {
        clearTimeout(this.searchTimer);
    },
};
</script>

<style scoped>
.related-results {
    max-height: 320px;
    overflow-y: auto;
}

.source-title {
    min-width: 0;
    max-width: 100%;
}

.chip-title {
    max-width: 220px;
    display: inline-block;
    vertical-align: middle;
}
</style>
