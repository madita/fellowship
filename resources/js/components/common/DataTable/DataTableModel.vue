<script setup>
import {ref, watch, computed, onMounted} from 'vue';

// Reactive state
const showModal = ref(false);
const items = ref([]);
const model = ref(null);
const loading = ref(false);
const error = ref(null);
const localFilterKey = ref('');
const localFilterValue = ref('');
const apiUrl = ref('');

// Props and Emits
const emit = defineEmits(['update:modelValue']);
const props = defineProps({
    column: {type: String, required: true},
    filter_key: {type: String, default: ''},
    filter_value: {type: [String, Number], default: ''},
    endPoint: {type: String, required: true},
});

// Fetch items from API
const fetchItems = async () => {
    loading.value = true;
    error.value = null;
    items.value = [];

    try {
        const response = await fetch(apiUrl.value, {
            method: 'GET',
            headers: {'Content-Type': 'application/json'},
        });

        // if (!response.ok) throw new Error('Failed to fetch items');

        const data = await response.json();
        console.log('Fetched items:', data);

        items.value = props.column === 'parent_id' ? data.data.records : data;
    } catch (err) {
        error.value = err.message;
        console.error('Error fetching items:', err);
    } finally {
        loading.value = false;
    }
};

// Compute item title dynamically
const itemTitle = computed(() => {
    if (items.value.length > 0) {
        const possibleKeys = ['name', 'title', 'label', 'description', 'taxonomy'];
        return possibleKeys.find((key) => key in items.value[0]) || '';
    }
    return '';
});

// Filter items based on local filter value
const filteredItems = computed(() => {
    if (!localFilterValue.value) return items.value;
    return items.value.filter((row) => row[localFilterKey.value] === localFilterValue.value);
});

// Watch `model` and emit value updates
watch(model, (newValue) => {
    emit('update:modelValue', newValue);
});

// Watch `filter_value` prop to update local filter value
watch(() => props.filter_value, (newValue) => {
    localFilterValue.value = newValue;
});

// Initialize component
onMounted(() => {
    localFilterKey.value = props.filter_key;
    apiUrl.value = props.column === 'parent_id' ? `/api${props.endPoint}` : `/api/common/items?foreign_key=${props.column}`;
    fetchItems();
});
</script>

<template>
    <v-select
        v-model="model"
        :items="filteredItems"
        :item-title="itemTitle"
        item-value="id"
        :label="column"
        clearable
        :loading="loading"
    />
</template>

<style scoped>
/* Add styles if needed */
</style>
