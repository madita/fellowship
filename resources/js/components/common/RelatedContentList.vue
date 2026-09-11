<template>
    <section v-if="visible" class="related-content-list">
        <div class="d-flex align-center flex-wrap ga-2 mb-3">
            <v-icon color="primary" size="22">mdi-link-variant</v-icon>
            <h2 class="text-h6">{{ $t('relatedContent.list.title') }}</h2>
            <v-chip v-if="loaded" size="small" variant="tonal">{{ sortedRelations.length }}</v-chip>
            <v-spacer />
            <v-btn
                v-if="canEdit"
                variant="tonal"
                color="primary"
                size="small"
                prepend-icon="mdi-link-variant-plus"
                :disabled="!hasSource"
                @click="openDialog"
            >
                {{ $t('relatedContent.list.link') }}
            </v-btn>
        </div>

        <loading-state v-if="loading && !loaded" compact />

        <empty-state
            v-else-if="loadError"
            compact
            icon="mdi-alert-circle-outline"
            :title="$t('relatedContent.list.loadError')"
        >
            <template #actions>
                <v-btn variant="tonal" size="small" :loading="loading" :disabled="loading" @click="reload">
                    {{ $t('relatedContent.list.retry') }}
                </v-btn>
            </template>
        </empty-state>

        <empty-state
            v-else-if="!sortedRelations.length"
            compact
            icon="mdi-link-variant-off"
            :title="$t('relatedContent.list.empty')"
            :text="canEdit ? $t('relatedContent.list.emptyHint') : ''"
        />

        <v-row v-else dense>
            <v-col v-for="relation in sortedRelations" :key="relationKey(relation)" v-bind="colProps">
                <v-card
                    variant="outlined"
                    rounded="lg"
                    class="h-100"
                    :to="internalLink(relation.item.url)"
                    :href="externalLink(relation.item.url)"
                    :target="externalLink(relation.item.url) ? '_blank' : undefined"
                >
                    <div class="d-flex align-center ga-3 pa-2">
                        <v-avatar
                            :size="compact ? 40 : 48"
                            rounded="lg"
                            :color="relation.item.image ? undefined : kindColor(relation.item.kind)"
                            :variant="relation.item.image ? 'flat' : 'tonal'"
                        >
                            <v-img v-if="relation.item.image" :src="relation.item.image" cover />
                            <v-icon v-else>{{ kindIcon(relation.item.kind) }}</v-icon>
                        </v-avatar>

                        <div class="flex-grow-1 related-text">
                            <div class="text-body-2 font-weight-medium text-truncate">{{ relation.item.title }}</div>
                            <div class="d-flex align-center ga-1 text-caption text-medium-emphasis">
                                <v-chip size="x-small" variant="tonal" :color="kindColor(relation.item.kind)" class="flex-shrink-0">
                                    {{ kindLabel(relation.item.kind) }}
                                </v-chip>
                                <v-icon
                                    v-if="relation.direction === 'incoming'"
                                    size="14"
                                    :title="$t('relatedContent.list.linksHere')"
                                    :aria-label="$t('relatedContent.list.linksHere')"
                                >
                                    mdi-arrow-left-bottom
                                </v-icon>
                                <span v-if="relation.item.subtitle" class="text-truncate">{{ relation.item.subtitle }}</span>
                            </div>
                        </div>

                        <v-btn
                            v-if="canEdit"
                            icon="mdi-link-variant-off"
                            size="small"
                            variant="text"
                            color="error"
                            class="flex-shrink-0"
                            :loading="!!busy[relationKey(relation)]"
                            :disabled="!!busy[relationKey(relation)]"
                            :title="$t('relatedContent.list.unlink')"
                            :aria-label="$t('relatedContent.list.unlink')"
                            @click.prevent.stop="unlink(relation)"
                        />
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <related-content-dialog
            v-if="canEdit"
            v-model="dialog"
            :source-type="type"
            :source-id="id"
            :source-title="title"
            @updated="onUpdated"
        />
    </section>
</template>

<script>
import axios from 'axios';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import RelatedContentDialog from '@/components/common/RelatedContentDialog.vue';
import { itemKey, kindOrder, relatedKindMixin } from '@/utils/relatedContent.js';

/**
 * "Related content" section for a wiki page, page, post, event or album.
 *
 *   <related-content-list type="App\Models\Page" :id="page.id" :title="page.title" :can-edit="isAdmin" />
 *
 * Loads both link directions from /api/relateable/related. Editors get a
 * "Link content" button (RelatedContentDialog with this item as source)
 * and an unlink button per entry. Visitors only see the section when
 * something is linked. Exposes reload() and openDialog().
 */
export default {
    name: 'RelatedContentList',
    components: { EmptyState, LoadingState, RelatedContentDialog },
    mixins: [relatedKindMixin],
    props: {
        type: { type: String, required: true },
        id: { type: [Number, String], default: null },
        title: { type: String, default: '' },
        canEdit: { type: Boolean, default: false },
        // One column and smaller thumbnails (drawers, sidebars)
        compact: { type: Boolean, default: false },
    },
    emits: ['changed'],
    expose: ['reload', 'openDialog'],
    data() {
        return {
            relations: [],
            loading: false,
            loaded: false,
            loadError: false,
            request: 0,
            // relationKey → true while its unlink request runs
            busy: {},
            dialog: false,
        };
    },
    computed: {
        hasSource() {
            return !!this.type && this.id !== null && this.id !== undefined && this.id !== '';
        },
        sourceKey() {
            return this.hasSource ? `${this.type}:${this.id}` : '';
        },
        visible() {
            return this.canEdit || this.sortedRelations.length > 0;
        },
        sortedRelations() {
            return this.relations
                .filter(relation => relation && relation.item)
                .sort((a, b) => kindOrder(a.item.kind) - kindOrder(b.item.kind)
                    || String(a.item.title || '').localeCompare(String(b.item.title || '')));
        },
        colProps() {
            return this.compact ? { cols: 12 } : { cols: 12, sm: 6, lg: 4 };
        },
    },
    watch: {
        sourceKey: {
            immediate: true,
            handler() {
                this.relations = [];
                this.loaded = false;
                this.loadError = false;
                this.reload();
            },
        },
    },
    methods: {
        async reload() {
            const request = ++this.request;
            if (!this.hasSource) {
                this.loading = false;
                return;
            }
            this.loading = true;
            this.loadError = false;
            try {
                const { data } = await axios.get('/api/relateable/related', { params: { type: this.type, id: this.id } });
                if (request !== this.request) return;
                this.relations = data.data || [];
                this.loaded = true;
            } catch (e) {
                if (request !== this.request) return;
                this.relations = [];
                this.loadError = true;
            } finally {
                if (request === this.request) this.loading = false;
            }
        },
        openDialog() {
            if (!this.canEdit || !this.hasSource) return;
            this.dialog = true;
        },
        onUpdated() {
            this.reload();
            this.$emit('changed');
        },
        relationKey(relation) {
            return `${relation.direction}:${itemKey(relation.item)}`;
        },
        async unlink(relation) {
            const key = this.relationKey(relation);
            if (this.busy[key]) return;
            const confirmed = await this.$dialog.confirmDelete(
                this.$t('relatedContent.list.confirmUnlink', { title: relation.item.title }),
                { title: this.$t('relatedContent.list.unlinkTitle'), confirmationText: this.$t('relatedContent.list.unlink') },
            );
            if (!confirmed) return;

            // The API removes the link in whichever direction it is stored and
            // checks edit rights on the source, so this item is always the source.

            this.busy = { ...this.busy, [key]: true };
            try {
                await axios.delete('/api/relateable/relations', {
                    data: {
                        source_type: this.type,
                        source_id: this.id,
                        related_type: relation.item.type,
                        related_id: relation.item.id,
                    },
                });
                await this.reload();
                this.$emit('changed');
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                const busy = { ...this.busy };
                delete busy[key];
                this.busy = busy;
            }
        },
    },
};
</script>

<style scoped>
.related-text {
    min-width: 0;
}
</style>
