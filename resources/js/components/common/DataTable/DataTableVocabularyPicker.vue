<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

/**
 * Picks which vocabulary a row belongs to — `forum_cat`, `achievement_type`
 * and so on.
 *
 * Vocabularies have no table of their own: one comes into being with its
 * first row and disappears with its last. So this both lists the ones in
 * use and takes a new name, which is why it is a combobox and not a
 * select. Free text is normalised to the shape the column actually holds.
 */

const emit = defineEmits(['update:modelValue']);

const props = defineProps({
    modelValue: { type: String, default: null },
    label: { type: String, default: '' },
    readonly: { type: Boolean, default: false },
});

const types = ref([]);
const loading = ref(false);

const items = computed(() => {
    const known = types.value.map((type) => ({
        value: type.taxonomy,
        title: type.taxonomy,
        label: type.label,
        total: type.total,
    }));

    // A value typed in a previous edit is kept on the list so the field
    // does not look empty while the row is still on that vocabulary.
    if (props.modelValue && !known.some((item) => item.value === props.modelValue)) {
        known.push({ value: props.modelValue, title: props.modelValue, label: props.modelValue, total: 0 });
    }

    return known;
});

const selected = computed({
    get: () => props.modelValue || null,
    set: (value) => {
        const key = typeof value === 'object' && value !== null ? value.value : value;

        emit('update:modelValue', normalize(key));
    },
});

/** letters, digits, underscore and dash — what the column holds today. */
const normalize = (value) => {
    if (value === null || value === undefined || value === '') return null;

    return String(value).trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '');
};

onMounted(async () => {
    loading.value = true;

    try {
        const { data } = await axios.get('/api/admin/taxonomy/types');
        types.value = data?.data || [];
    } catch (error) {
        console.error('Failed to load vocabularies:', error);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <VCombobox
        v-model="selected"
        :items="items"
        item-title="title"
        item-value="value"
        :return-object="false"
        :label="label || $t('dataTable.vocabulary.label')"
        :readonly="readonly"
        :loading="loading"
        variant="outlined"
        density="compact"
        :hint="$t('dataTable.vocabulary.hint')"
        persistent-hint
    >
        <template #item="{ props: itemProps, item }">
            <VListItem v-bind="itemProps" :title="undefined">
                <VListItemTitle>{{ item.raw.label }}</VListItemTitle>
                <VListItemSubtitle>
                    <code>{{ item.raw.title }}</code>
                    <template v-if="item.raw.total"> · {{ $t('dataTable.vocabulary.entries', { count: item.raw.total }) }}</template>
                </VListItemSubtitle>
            </VListItem>
        </template>
    </VCombobox>
</template>
