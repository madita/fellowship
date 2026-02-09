<template>
    <v-card class="mb-4">
        <v-card-text>
            <v-row dense>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field
                        v-model="filters.search"
                        :label="$t('mediaFilters.searchByFilename')"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                        @update:model-value="debouncedEmit"
                    />
                </v-col>

                <v-col cols="12" sm="6" md="2">
                    <v-select
                        v-model="filters.context"
                        :items="contextOptions"
                        item-title="label"
                        item-value="value"
                        :label="$t('mediaFilters.context')"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                        @update:model-value="emitFilters"
                    />
                </v-col>

                <v-col cols="12" sm="6" md="2">
                    <v-select
                        v-model="filters.collection"
                        :items="collectionOptions"
                        item-title="label"
                        item-value="value"
                        :label="$t('mediaFilters.collection')"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                        @update:model-value="emitFilters"
                    />
                </v-col>

                <v-col cols="12" sm="6" md="2">
                    <v-select
                        v-model="filters.mime_type"
                        :items="fileTypeOptions"
                        item-title="label"
                        item-value="value"
                        :label="$t('mediaFilters.fileType')"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                        @update:model-value="emitFilters"
                    />
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <v-row dense>
                        <v-col cols="6">
                            <v-text-field
                                v-model="filters.date_from"
                                :label="$t('mediaFilters.from')"
                                type="date"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="emitFilters"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="filters.date_to"
                                :label="$t('mediaFilters.to')"
                                type="date"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="emitFilters"
                            />
                        </v-col>
                    </v-row>
                </v-col>
            </v-row>

            <v-row dense class="mt-2">
                <v-col cols="12" sm="6" md="2">
                    <v-select
                        v-model="filters.sort_by"
                        :items="sortOptions"
                        item-title="label"
                        item-value="value"
                        :label="$t('mediaFilters.sortBy')"
                        variant="outlined"
                        density="compact"
                        hide-details
                        @update:model-value="emitFilters"
                    />
                </v-col>

                <v-col cols="12" sm="6" md="2">
                    <v-btn-toggle
                        v-model="filters.sort_dir"
                        mandatory
                        density="compact"
                        @update:model-value="emitFilters"
                    >
                        <v-btn value="desc" size="small">
                            <v-icon icon="mdi-sort-descending" />
                            {{ $t('mediaFilters.newest') }}
                        </v-btn>
                        <v-btn value="asc" size="small">
                            <v-icon icon="mdi-sort-ascending" />
                            {{ $t('mediaFilters.oldest') }}
                        </v-btn>
                    </v-btn-toggle>
                </v-col>

                <v-col cols="12" sm="6" md="2">
                    <v-select
                        v-model="filters.per_page"
                        :items="perPageOptions"
                        :label="$t('mediaFilters.perPage')"
                        variant="outlined"
                        density="compact"
                        hide-details
                        @update:model-value="emitFilters"
                    />
                </v-col>

                <v-spacer />

                <v-col cols="auto">
                    <v-btn
                        variant="text"
                        color="secondary"
                        @click="clearFilters"
                    >
                        <v-icon icon="mdi-filter-off" start />
                        {{ $t('mediaFilters.clearFilters') }}
                    </v-btn>
                </v-col>
            </v-row>
        </v-card-text>
    </v-card>
</template>

<script setup>
import { reactive, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { debounce } from 'lodash';

const { t } = useI18n();

const props = defineProps({
    contexts: {
        type: Array,
        default: () => [],
    },
    collections: {
        type: Array,
        default: () => [],
    },
    modelValue: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue']);

const filters = reactive({
    search: '',
    context: null,
    collection: null,
    mime_type: null,
    date_from: null,
    date_to: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 24,
});

// Initialize filters from props
watch(
    () => props.modelValue,
    (newValue) => {
        Object.assign(filters, newValue);
    },
    { immediate: true }
);

const contextOptions = computed(() => [
    { label: t('mediaFilters.allContexts'), value: null },
    ...props.contexts,
]);

const collectionOptions = computed(() => [
    { label: t('mediaFilters.allCollections'), value: null },
    ...props.collections,
]);

const fileTypeOptions = computed(() => [
    { label: t('mediaFilters.allTypes'), value: null },
    { label: t('mediaFilters.images'), value: 'image' },
    { label: t('mediaFilters.videos'), value: 'video' },
    { label: t('mediaFilters.audio'), value: 'audio' },
    { label: t('mediaFilters.pdfs'), value: 'application/pdf' },
    { label: t('mediaFilters.documents'), value: 'application' },
]);

const sortOptions = computed(() => [
    { label: t('mediaFilters.date'), value: 'created_at' },
    { label: t('mediaFilters.name'), value: 'file_name' },
    { label: t('mediaFilters.size'), value: 'size' },
]);

const perPageOptions = [12, 24, 48, 96];

const emitFilters = () => {
    const cleanFilters = {};
    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== '') {
            cleanFilters[key] = value;
        }
    }
    emit('update:modelValue', cleanFilters);
};

const debouncedEmit = debounce(emitFilters, 300);

const clearFilters = () => {
    filters.search = '';
    filters.context = null;
    filters.collection = null;
    filters.mime_type = null;
    filters.date_from = null;
    filters.date_to = null;
    filters.sort_by = 'created_at';
    filters.sort_dir = 'desc';
    filters.per_page = 24;
    emitFilters();
};
</script>
