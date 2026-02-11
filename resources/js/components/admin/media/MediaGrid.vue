<template>
    <div>
        <div v-if="loading" class="d-flex justify-center pa-8">
            <v-progress-circular indeterminate color="primary" />
        </div>

        <div v-else-if="!media.length" class="text-center pa-8">
            <v-icon icon="mdi-image-off" size="64" color="grey" />
            <div class="text-h6 text-grey mt-4">{{ $t('mediaCenter.noMediaFound') }}</div>
            <div class="text-body-2 text-grey">
                {{ $t('mediaCenter.adjustFiltersHint') }}
            </div>
        </div>

        <v-row v-else>
            <v-col
                v-for="item in media"
                :key="item.id"
                cols="6"
                sm="4"
                md="3"
                lg="2"
            >
                <MediaCard
                    :media="item"
                    :selected="isSelected(item.id)"
                    @click="$emit('click', item)"
                    @delete="$emit('delete', item)"
                    @update:selected="toggleSelection(item.id, $event)"
                />
            </v-col>
        </v-row>
    </div>
</template>

<script setup>
import MediaCard from './MediaCard.vue';

const props = defineProps({
    media: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['click', 'delete', 'update:selectedIds']);

const isSelected = (id) => {
    return props.selectedIds.includes(id);
};

const toggleSelection = (id, selected) => {
    let newSelection;
    if (selected) {
        newSelection = [...props.selectedIds, id];
    } else {
        newSelection = props.selectedIds.filter(i => i !== id);
    }
    emit('update:selectedIds', newSelection);
};
</script>
