<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { humanizeKey } from '@/utils/dataTableCells.js';

/**
 * The relationship pickers of the admin drawer: a `parent` field, which
 * picks another row of the same table, and a `model` field, which picks a
 * row of a related one.
 *
 * A parent used to be unreadable. The options were labelled with whatever
 * key the first record happened to have, which for taxonomies meant every
 * entry in the list read "forum_cat"; the current value was never loaded
 * into the select, so an existing parent showed up blank; and only the
 * first page of candidates was offered. The parent endpoint now returns
 * names, depth and the full path, and the value is bound straight through
 * so what is stored is what is shown.
 */


const emit = defineEmits(['update:modelValue', 'error']);

const props = defineProps({
    column: { type: String, required: true },
    /** 'parent' picks a row of this same table, 'model' a related one. */
    fieldType: { type: String, default: 'model' },
    label: { type: String, default: '' },
    filter_key: { type: String, default: '' },
    filter_value: { type: [String, Number], default: '' },
    endPoint: { type: String, required: true },
    modelValue: { type: [String, Number], default: null },
    /** The row being edited — its id is excluded, its vocabulary scopes the list. */
    row: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
});

const items = ref([]);
const loading = ref(false);
const failed = ref(false);

const isParent = computed(() => props.fieldType === 'parent');
const fieldLabel = computed(() => props.label || humanizeKey(props.column));

/**
 * Bound straight to the prop rather than mirrored in a ref, so the field
 * always shows the stored value instead of starting empty.
 */
const selected = computed({
    get: () => (props.modelValue === '' || props.modelValue === undefined ? null : props.modelValue),
    set: (value) => emit('update:modelValue', value === undefined || value === '' ? null : value),
});

const fetchParents = async () => {
    const params = {};
    if (props.row?.id) params.exclude = props.row.id;
    // Vocabularies are kept apart: a forum category under a game world
    // would be unreachable from either screen.
    if (props.row?.taxonomy) params.taxonomy = props.row.taxonomy;

    const { data } = await axios.get(`/api${props.endPoint}/parent-options`, { params });

    items.value = (data?.data || []).map((option) => ({
        value: option.id,
        title: option.title,
        depth: option.depth || 0,
        path: option.path || [option.title],
        group: option.group || null,
    }));
};

/** The legacy shape: a flat list of rows from the related table. */
const fetchRelated = async () => {
    const { data } = await axios.get('/api/common/items', { params: { foreign_key: props.column } });
    const rows = Array.isArray(data) ? data : (data?.data ?? []);

    const titleKey = ['title', 'name', 'label', 'description'].find(
        (key) => rows.length > 0 && key in rows[0],
    );

    items.value = rows.map((row) => ({
        value: row.id,
        title: titleKey ? String(row[titleKey] ?? '') : `#${row.id}`,
        depth: 0,
        path: [titleKey ? String(row[titleKey] ?? '') : `#${row.id}`],
        group: null,
        raw: row,
    }));
};

const fetchItems = async () => {
    loading.value = true;
    failed.value = false;

    try {
        await (isParent.value ? fetchParents() : fetchRelated());
    } catch (error) {
        failed.value = true;
        items.value = [];
        console.error(`Failed to load options for ${props.column}:`, error);
        emit('error', { source: 'fetchItems', error });
    } finally {
        loading.value = false;
    }
};

/** A `model` field may narrow its options by another field of the row. */
const visibleItems = computed(() => {
    if (isParent.value || !props.filter_key || !props.filter_value) return items.value;

    return items.value.filter((item) => item.raw?.[props.filter_key] === props.filter_value);
});

/**
 * A stored value that is not on offer — a parent in another vocabulary, or
 * one that was removed. Shown rather than silently blanked, so nobody has
 * to guess why the field looks empty.
 */
const danglingValue = computed(() => {
    if (selected.value === null) return null;

    return visibleItems.value.some((item) => String(item.value) === String(selected.value))
        ? null
        : selected.value;
});

const selectableItems = computed(() => (
    danglingValue.value === null
        ? visibleItems.value
        : [
            ...visibleItems.value,
            { value: danglingValue.value, title: `#${danglingValue.value}`, depth: 0, path: [], group: null, dangling: true },
        ]
));

// Changing the vocabulary of the row changes which parents are possible.
watch(() => [props.row?.taxonomy, props.row?.id, props.fieldType], () => {
    if (isParent.value) fetchItems();
});

onMounted(fetchItems);
</script>

<template>
    <div>
        <VAutocomplete
            v-model="selected"
            :items="selectableItems"
            item-title="title"
            item-value="value"
            :label="fieldLabel"
            :readonly="readonly"
            :loading="loading"
            clearable
            variant="outlined"
            density="compact"
            :no-data-text="failed ? $t('dataTable.model.loadFailed') : $t('dataTable.model.noOptions')"
            :hint="isParent ? $t('dataTable.model.parentHint') : undefined"
            :persistent-hint="isParent"
        >
            <!-- The hierarchy has to be visible, or "which one did I pick?"
                 has no answer when two branches share a name. -->
            <template #item="{ props: itemProps, item }">
                <VListItem
                    v-bind="itemProps"
                    :title="undefined"
                    :style="{ paddingInlineStart: `${12 + (item.raw.depth || 0) * 18}px` }"
                >
                    <template #prepend>
                        <VIcon
                            size="16"
                            class="me-2"
                            :color="item.raw.dangling ? 'warning' : undefined"
                        >
                            {{ item.raw.dangling ? 'mdi-alert-outline' : (item.raw.depth ? 'mdi-subdirectory-arrow-right' : 'mdi-folder-outline') }}
                        </VIcon>
                    </template>

                    <VListItemTitle>{{ item.raw.title }}</VListItemTitle>
                    <VListItemSubtitle v-if="item.raw.path.length > 1">
                        {{ item.raw.path.join(' › ') }}
                    </VListItemSubtitle>
                    <VListItemSubtitle v-else-if="item.raw.dangling">
                        {{ $t('dataTable.model.danglingOption') }}
                    </VListItemSubtitle>
                </VListItem>
            </template>

            <!-- The closed field shows the full path, so the answer to
                 "what is it under?" does not need the list reopened. -->
            <template #selection="{ item }">
                <span v-if="item.raw.path?.length > 1" class="text-truncate">
                    <span class="text-medium-emphasis">{{ item.raw.path.slice(0, -1).join(' › ') }} › </span>{{ item.raw.title }}
                </span>
                <span v-else>{{ item.raw.title }}</span>
            </template>
        </VAutocomplete>

        <VAlert
            v-if="danglingValue !== null"
            type="warning"
            variant="tonal"
            density="compact"
            class="mt-1"
            :text="$t('dataTable.model.danglingWarning', { id: danglingValue })"
        />
    </div>
</template>
