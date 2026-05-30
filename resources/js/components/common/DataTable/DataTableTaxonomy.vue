<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits(['update:modelValue']);
const props = defineProps({
    config: {
        type: Object,
        required: true,
    },
    modelValue: {
        type: [Array, String, Number, Object],
        default: () => [],
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

const items = ref([]);
const loading = ref(false);
const search = ref('');
const showAddInline = ref(false);
const newCategory = ref('');
const saving = ref(false);

const isTagLike = computed(() => (props.config.taxonomy || '').toLowerCase() === 'tags');
const chipColor = computed(() => isTagLike.value ? 'secondary' : 'primary');
const chipIcon = computed(() => isTagLike.value ? 'mdi-pound' : 'mdi-folder');

const toTitle = (v) => (typeof v === 'string' ? v : (v?.title ?? String(v ?? '')));

const multiple = computed(() => props.config.multiple !== false);

const normalizeIn = (val) => {
    if (multiple.value) {
        if (Array.isArray(val)) return val.map(toTitle);
        if (val == null || val === '') return [];
        return [toTitle(val)];
    }
    const first = Array.isArray(val) ? val[0] : val;
    return first == null || first === '' ? null : toTitle(first);
};

const selected = ref(normalizeIn(props.modelValue));

const fetchTerms = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(props.config.endpoint);
        items.value = (data.terms || []).map(t => (typeof t === 'string' ? { title: t } : t));
    } catch (err) {
        console.error('Error fetching taxonomy terms:', err);
    } finally {
        loading.value = false;
    }
};

const saveCategory = async () => {
    const title = newCategory.value.trim();
    if (!title) return;
    saving.value = true;
    try {
        await axios.post('/api/tag/terms', {
            term: title,
            taxonomy: props.config.taxonomy,
        });
        await fetchTerms();
        if (multiple.value) {
            if (!selected.value.includes(title)) {
                selected.value = [...selected.value, title];
            }
        } else {
            selected.value = title;
        }
        newCategory.value = '';
        showAddInline.value = false;
    } catch (e) {
        console.error('Failed to create category:', e);
    } finally {
        saving.value = false;
    }
};

watch(selected, (newVal) => {
    if (multiple.value) {
        emit('update:modelValue', (newVal || []).map(toTitle));
    } else {
        emit('update:modelValue', newVal == null || newVal === '' ? null : toTitle(newVal));
    }
}, { deep: true });

watch(() => props.modelValue, (newVal) => {
    const incoming = normalizeIn(newVal);
    if (JSON.stringify(incoming) !== JSON.stringify(selected.value)) {
        selected.value = incoming;
    }
}, { deep: true });

onMounted(() => {
    fetchTerms();
});
</script>

<template>
    <div>
        <v-combobox
            v-model="selected"
            :items="items"
            v-model:search="search"
            item-title="title"
            item-value="title"
            :return-object="false"
            :label="config.label"
            :multiple="config.multiple !== false"
            :readonly="readonly"
            :loading="loading"
            chips
            closable-chips
            clearable
            variant="outlined"
            density="compact"
            :prepend-inner-icon="isTagLike ? 'mdi-tag-plus' : 'mdi-folder-plus'"
            :placeholder="`Type to search or create new ${config.label?.toLowerCase() || 'item'}`"
            hint="Press Enter to add a new entry"
            persistent-hint
        >
            <template #no-data>
                <v-list-item>
                    <v-list-item-title>
                        <span v-if="search">No results matching "<strong>{{ search }}</strong>". Press Enter to create.</span>
                        <span v-else>Start typing to search or create.</span>
                    </v-list-item-title>
                </v-list-item>
            </template>

            <template #chip="{ props: chipProps, item }">
                <v-chip
                    v-bind="chipProps"
                    :color="item.raw?.color || chipColor"
                    variant="tonal"
                    size="small"
                    closable
                >
                    <v-icon start size="16">{{ chipIcon }}</v-icon>
                    {{ item.title || item.raw || item }}
                </v-chip>
            </template>
        </v-combobox>

        <div v-if="!readonly" class="mt-2">
            <v-btn
                variant="text"
                size="small"
                :prepend-icon="showAddInline ? 'mdi-chevron-up' : 'mdi-plus'"
                @click="showAddInline = !showAddInline"
            >
                Add new {{ config.label?.toLowerCase().replace(/s$/, '') || 'item' }}
            </v-btn>

            <v-expand-transition>
                <div v-if="showAddInline" class="mt-2 d-flex gap-2 align-start">
                    <v-text-field
                        v-model="newCategory"
                        :label="`New ${config.label?.toLowerCase() || 'item'}`"
                        variant="outlined"
                        density="compact"
                        hide-details
                        @keyup.enter="saveCategory"
                    />
                    <v-btn
                        color="primary"
                        variant="elevated"
                        size="small"
                        :loading="saving"
                        :disabled="!newCategory.trim()"
                        prepend-icon="mdi-plus"
                        @click="saveCategory"
                    >
                        Add
                    </v-btn>
                </div>
            </v-expand-transition>
        </div>
    </div>
</template>
