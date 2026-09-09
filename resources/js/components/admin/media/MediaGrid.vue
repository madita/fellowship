<template>
    <div>
        <loading-state v-if="loading" />

        <empty-state
            v-else-if="!media.length"
            icon="mdi-image-off"
            :title="$t('mediaCenter.noMediaFound')"
            :text="$t('mediaCenter.adjustFiltersHint')"
        />

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
import EmptyState from '../../common/EmptyState.vue';
import LoadingState from '../../common/LoadingState.vue';

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
