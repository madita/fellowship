<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import TaxonomyDialog from '@/components/admin/TaxonomyDialog.vue';
import { useDialog } from '@/composables/useDialog.js';

/**
 * The taxonomy manager.
 *
 * This replaced a generic data table whose rows were a column of numbers:
 * an entry's name lives on its term, which the table did not show, and the
 * parent was a dropdown of repeated vocabulary keys. Here one vocabulary
 * is shown at a time as the tree it actually is — names, nesting, what
 * each entry is used by — and an entry is added where it belongs instead
 * of being given a parent afterwards.
 */

const { t } = useI18n();
const dialog = useDialog();

const groups = ref([]);
const loading = ref(false);
const activeVocabulary = ref(null);
const busyId = ref(null);
const reordering = ref(false);

const editor = ref({ show: false, node: null, parentId: null });

const vocabularies = computed(() => groups.value.map((group) => ({
    taxonomy: group.taxonomy,
    label: vocabularyLabel(group.taxonomy, group.label),
    total: group.total,
})));

const activeGroup = computed(() => groups.value.find((group) => group.taxonomy === activeVocabulary.value) || null);

/**
 * The tree flattened for rendering, so one v-list can show the whole
 * hierarchy with its indentation and still know each row's siblings for
 * the move buttons.
 */
const rows = computed(() => {
    const flat = [];

    const walk = (nodes, depth, siblings) => {
        nodes.forEach((node, index) => {
            flat.push({
                ...node,
                depth,
                isFirst: index === 0,
                isLast: index === nodes.length - 1,
                siblingIds: siblings,
            });

            if (node.children?.length) {
                walk(node.children, depth + 1, node.children.map((child) => child.id));
            }
        });
    };

    const nodes = activeGroup.value?.nodes || [];
    walk(nodes, 0, nodes.map((node) => node.id));

    return flat;
});

/**
 * A known vocabulary gets a proper name from the translations; anything
 * added later falls back to what the server humanised.
 */
function vocabularyLabel(taxonomy, fallback) {
    const key = `admin.taxonomies.types.${taxonomy}`;

    return t(key) === key ? (fallback || taxonomy) : t(key);
}

const fetchTree = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get('/api/admin/taxonomy');
        groups.value = data?.data || [];

        if (!groups.value.some((group) => group.taxonomy === activeVocabulary.value)) {
            activeVocabulary.value = groups.value[0]?.taxonomy ?? null;
        }
    } catch (error) {
        dialog.requestError(error, t('errors.general'));
    } finally {
        loading.value = false;
    }
};

const openCreate = (parentId = null) => {
    editor.value = { show: true, node: null, parentId };
};

const openEdit = (node) => {
    editor.value = { show: true, node, parentId: null };
};

const onSaved = async (saved) => {
    // A new entry may have created a vocabulary, so follow it there.
    if (saved?.taxonomy) activeVocabulary.value = saved.taxonomy;

    await fetchTree();
};

const confirmDelete = async (node) => {
    if (busyId.value !== null) return;

    const message = node.children?.length
        ? t('admin.taxonomies.confirmDeleteWithChildren', { name: node.title, count: node.children.length })
        : t('admin.taxonomies.confirmDelete', { name: node.title });

    if (!(await dialog.confirmDelete(message))) return;

    busyId.value = node.id;

    try {
        const { data } = await axios.delete(`/api/admin/taxonomy/${node.id}`);
        await fetchTree();
        dialog.success(data?.message || t('admin.taxonomies.deleted'));
    } catch (error) {
        dialog.requestError(error, t('errors.general'));
    } finally {
        busyId.value = null;
    }
};

/**
 * Move one entry past its neighbour. Every sibling is renumbered from 1
 * so a run of entries that all sit at sort 0 — which is how most of them
 * start out — still ends up in a definite order.
 */
const move = async (row, offset) => {
    if (reordering.value) return;

    const siblings = [...row.siblingIds];
    const from = siblings.indexOf(row.id);
    const to = from + offset;

    if (from === -1 || to < 0 || to >= siblings.length) return;

    siblings.splice(to, 0, siblings.splice(from, 1)[0]);

    reordering.value = true;

    try {
        await axios.post('/api/admin/taxonomy/reorder', {
            items: siblings.map((id, index) => ({
                id,
                parent_id: row.parent_id ?? null,
                sort: index + 1,
            })),
        });

        await fetchTree();
    } catch (error) {
        dialog.requestError(error, t('errors.general'));
    } finally {
        reordering.value = false;
    }
};

onMounted(fetchTree);
</script>

<template>
    <div class="flex-grow-1">
        <PageHeader
            :title="$t('admin.taxonomies.title')"
            :subtitle="$t('admin.taxonomies.subtitle')"
            icon="mdi-file-tree"
            :breadcrumbs="[$crumb('admin'), $crumb('settings'), $t('admin.taxonomies.title')]"
            :back-to="{ name: 'admin-settings-category', params: { category: 'community' } }"
            fluid
        >
            <template #actions>
                <VBtn
                    variant="text"
                    prepend-icon="mdi-table"
                    :to="{ name: 'admin-taxonomie-table' }"
                >
                    {{ $t('admin.taxonomies.rawTable') }}
                </VBtn>
                <VBtn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    @click="openCreate()"
                >
                    {{ $t('admin.taxonomies.create') }}
                </VBtn>
            </template>
        </PageHeader>

        <VContainer fluid>
            <LoadingState v-if="loading" />

            <EmptyState
                v-else-if="!groups.length"
                icon="mdi-file-tree-outline"
                :title="$t('admin.taxonomies.noTaxonomies')"
                :text="$t('admin.taxonomies.noTaxonomiesHint')"
            />

            <template v-else>
                <!-- Which vocabulary is being looked at. Entries of
                     different vocabularies never nest, so mixing them in
                     one list is what made the old screen unreadable. -->
                <VCard variant="outlined" class="mb-4">
                    <VTabs
                        v-model="activeVocabulary"
                        density="comfortable"
                        show-arrows
                    >
                        <VTab
                            v-for="vocabulary in vocabularies"
                            :key="vocabulary.taxonomy"
                            :value="vocabulary.taxonomy"
                        >
                            {{ vocabulary.label }}
                            <VChip size="x-small" variant="tonal" class="ms-2">{{ vocabulary.total }}</VChip>
                        </VTab>
                    </VTabs>
                </VCard>

                <VCard variant="outlined">
                    <VList density="comfortable" class="py-0">
                        <template v-for="(row, index) in rows" :key="row.id">
                            <VDivider v-if="index > 0" />

                            <VListItem
                                :style="{ paddingInlineStart: `${16 + row.depth * 28}px` }"
                                :class="{ 'taxonomy-row--hidden': !row.visible }"
                            >
                                <template #prepend>
                                    <VIcon
                                        class="me-3"
                                        :color="row.color || (row.depth ? 'medium-emphasis' : 'primary')"
                                    >
                                        {{ row.depth ? 'mdi-subdirectory-arrow-right' : 'mdi-folder-outline' }}
                                    </VIcon>
                                </template>

                                <div class="d-flex flex-wrap align-center ga-2">
                                    <span class="font-weight-medium">
                                        {{ row.title || $t('admin.taxonomies.unnamed') }}
                                    </span>

                                    <VChip
                                        v-if="!row.has_term"
                                        size="x-small"
                                        variant="tonal"
                                        color="error"
                                        prepend-icon="mdi-alert-outline"
                                    >
                                        {{ $t('admin.taxonomies.noTerm') }}
                                    </VChip>
                                    <VChip v-if="!row.visible" size="x-small" variant="tonal" color="warning">
                                        {{ $t('admin.taxonomies.hidden') }}
                                    </VChip>
                                    <VChip v-if="!row.searchable" size="x-small" variant="tonal" color="info">
                                        {{ $t('admin.taxonomies.notSearchable') }}
                                    </VChip>
                                    <VChip v-if="row.usage_count" size="x-small" variant="tonal">
                                        {{ $t('admin.taxonomies.usedBy', { count: row.usage_count }) }}
                                    </VChip>
                                </div>

                                <VListItemSubtitle class="mt-1">
                                    <code class="text-caption">{{ row.slug || '—' }}</code>
                                    <template v-if="row.description">
                                        · {{ row.description }}
                                    </template>
                                </VListItemSubtitle>

                                <template #append>
                                    <div class="d-flex align-center ga-1">
                                        <span class="text-caption text-medium-emphasis me-2">#{{ row.sort }}</span>

                                        <VBtn
                                            icon="mdi-arrow-up"
                                            size="x-small"
                                            variant="text"
                                            :disabled="row.isFirst || reordering || busyId !== null"
                                            :title="$t('admin.taxonomies.moveUp')"
                                            @click="move(row, -1)"
                                        />
                                        <VBtn
                                            icon="mdi-arrow-down"
                                            size="x-small"
                                            variant="text"
                                            :disabled="row.isLast || reordering || busyId !== null"
                                            :title="$t('admin.taxonomies.moveDown')"
                                            @click="move(row, 1)"
                                        />
                                        <VBtn
                                            icon="mdi-plus"
                                            size="small"
                                            variant="text"
                                            :disabled="busyId !== null"
                                            :title="$t('admin.taxonomies.addChild')"
                                            @click="openCreate(row.id)"
                                        />
                                        <VBtn
                                            icon="mdi-pencil"
                                            size="small"
                                            variant="text"
                                            :disabled="busyId !== null"
                                            :title="$t('common.edit')"
                                            @click="openEdit(row)"
                                        />
                                        <VBtn
                                            icon="mdi-delete"
                                            size="small"
                                            variant="text"
                                            color="error"
                                            :loading="busyId === row.id"
                                            :disabled="busyId !== null"
                                            :title="$t('common.delete')"
                                            @click="confirmDelete(row)"
                                        />
                                    </div>
                                </template>
                            </VListItem>
                        </template>
                    </VList>

                    <EmptyState
                        v-if="!rows.length"
                        compact
                        icon="mdi-tag-outline"
                        :title="$t('admin.taxonomies.emptyVocabulary')"
                    />
                </VCard>

                <div class="mt-3">
                    <VBtn
                        variant="tonal"
                        size="small"
                        prepend-icon="mdi-plus"
                        @click="openCreate()"
                    >
                        {{ $t('admin.taxonomies.addToVocabulary', { vocabulary: activeGroup ? vocabularyLabel(activeGroup.taxonomy, activeGroup.label) : '' }) }}
                    </VBtn>
                </div>
            </template>
        </VContainer>

        <TaxonomyDialog
            v-model="editor.show"
            :node="editor.node"
            :parent-id="editor.parentId"
            :vocabulary="activeVocabulary || ''"
            :vocabularies="vocabularies"
            @saved="onSaved"
        />
    </div>
</template>

<style scoped>
.taxonomy-row--hidden {
    opacity: 0.6;
}

code {
    background: rgba(var(--v-theme-on-surface), 0.06);
    border-radius: 3px;
    padding: 1px 5px;
}
</style>
