<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

/**
 * Picks the term a taxonomy is named by.
 *
 * A taxonomy row has no name of its own, so this is the field that gives
 * it one — which is exactly what the admin table used to be missing:
 * term_id was neither shown nor editable, and a row created there came out
 * nameless. Terms already used in the same vocabulary are listed but not
 * selectable, since (term_id, taxonomy) is unique and picking one would
 * only fail on save.
 */

const emit = defineEmits(['update:modelValue']);

const props = defineProps({
    modelValue: { type: [String, Number], default: null },
    label: { type: String, default: '' },
    /** The row being edited: its vocabulary decides what is already taken. */
    row: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
});

const terms = ref([]);
const loading = ref(false);
const search = ref('');

const selected = computed({
    get: () => (props.modelValue === '' || props.modelValue === undefined ? null : props.modelValue),
    set: (value) => emit('update:modelValue', value === undefined || value === '' ? null : value),
});

const fetchTerms = async () => {
    loading.value = true;

    try {
        const { data } = await axios.get('/api/admin/taxonomy/terms', {
            params: {
                q: search.value || undefined,
                taxonomy: props.row?.taxonomy || undefined,
                exclude: props.row?.id || undefined,
            },
        });

        terms.value = data?.data || [];
    } catch (error) {
        console.error('Failed to load terms:', error);
        terms.value = [];
    } finally {
        loading.value = false;
    }
};

/**
 * The selected term stays in the list even when it is "taken" — it is
 * taken by this very row — so the field is never blank on open.
 */
const items = computed(() => terms.value.map((term) => ({
    value: term.id,
    title: term.title,
    slug: term.slug,
    disabled: term.taken && String(term.id) !== String(selected.value),
})));

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchTerms, 250);
});

watch(() => [props.row?.taxonomy, props.row?.id], fetchTerms);

onMounted(fetchTerms);
</script>

<template>
    <VAutocomplete
        v-model="selected"
        v-model:search="search"
        :items="items"
        item-title="title"
        item-value="value"
        item-props
        :label="label || $t('dataTable.term.label')"
        :readonly="readonly"
        :loading="loading"
        clearable
        variant="outlined"
        density="compact"
        :hint="$t('dataTable.term.hint')"
        persistent-hint
        :no-data-text="$t('dataTable.term.noMatch')"
    >
        <template #item="{ props: itemProps, item }">
            <VListItem v-bind="itemProps" :title="undefined" :disabled="item.raw.disabled">
                <VListItemTitle>{{ item.raw.title }}</VListItemTitle>
                <VListItemSubtitle>
                    {{ item.raw.slug }}<template v-if="item.raw.disabled"> — {{ $t('dataTable.term.alreadyUsed') }}</template>
                </VListItemSubtitle>
            </VListItem>
        </template>
    </VAutocomplete>
</template>
