<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits(['update:modelValue']);
const props = defineProps({
    config: {
        type: Object,
        required: true,
    },
    modelValue: {
        type: Array,
        default: () => [],
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

const items = ref([]);
const selected = ref([...props.modelValue]);
const loading = ref(false);

const fetchTerms = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(props.config.endpoint);
        items.value = data.terms || [];
    } catch (err) {
        console.error('Error fetching taxonomy terms:', err);
    } finally {
        loading.value = false;
    }
};

watch(selected, (newVal) => {
    emit('update:modelValue', newVal);
}, { deep: true });

watch(() => props.modelValue, (newVal) => {
    if (JSON.stringify(newVal) !== JSON.stringify(selected.value)) {
        selected.value = [...(newVal || [])];
    }
}, { deep: true });

onMounted(() => {
    fetchTerms();
});
</script>

<template>
    <v-combobox
        v-model="selected"
        :items="items"
        item-title="title"
        item-value="title"
        :label="config.label"
        :multiple="config.multiple !== false"
        :readonly="readonly"
        :loading="loading"
        chips
        closable-chips
        variant="outlined"
        density="compact"
    />
</template>
