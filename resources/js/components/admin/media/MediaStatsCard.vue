<template>
    <v-card class="mb-4">
        <v-card-text>
            <v-row>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-center">
                        <div class="text-h4 font-weight-bold">{{ stats.total_count || 0 }}</div>
                        <div class="text-caption text-grey">{{ $t('mediaCenter.totalFiles') }}</div>
                    </div>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-center">
                        <div class="text-h4 font-weight-bold">{{ stats.size_formatted || '0 B' }}</div>
                        <div class="text-caption text-grey">{{ $t('mediaCenter.totalStorage') }}</div>
                    </div>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-center">
                        <div class="text-h4 font-weight-bold">{{ imageCount }}</div>
                        <div class="text-caption text-grey">{{ $t('mediaCenter.images') }}</div>
                    </div>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-center">
                        <div class="text-h4 font-weight-bold">{{ contextCount }}</div>
                        <div class="text-caption text-grey">{{ $t('mediaCenter.contexts') }}</div>
                    </div>
                </v-col>
            </v-row>

            <v-expand-transition>
                <div v-if="expanded">
                    <v-divider class="my-4" />
                    <v-row>
                        <v-col cols="12" md="4">
                            <div class="text-subtitle-2 mb-2">{{ $t('mediaCenter.byContext') }}</div>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="context in stats.by_context"
                                    :key="context.model_type"
                                    class="px-0"
                                >
                                    <v-list-item-title class="text-body-2">
                                        {{ context.label }}
                                    </v-list-item-title>
                                    <template #append>
                                        <span class="text-caption text-grey">
                                            {{ context.count }} ({{ context.size_formatted }})
                                        </span>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-subtitle-2 mb-2">{{ $t('mediaCenter.byFileType') }}</div>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="type in stats.by_type"
                                    :key="type.type"
                                    class="px-0"
                                >
                                    <v-list-item-title class="text-body-2">
                                        {{ type.type }}
                                    </v-list-item-title>
                                    <template #append>
                                        <span class="text-caption text-grey">
                                            {{ type.count }} ({{ type.size_formatted }})
                                        </span>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-subtitle-2 mb-2">{{ $t('mediaCenter.byCollection') }}</div>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="col in stats.by_collection"
                                    :key="col.collection"
                                    class="px-0"
                                >
                                    <v-list-item-title class="text-body-2">
                                        {{ col.collection }}
                                    </v-list-item-title>
                                    <template #append>
                                        <span class="text-caption text-grey">
                                            {{ col.count }} ({{ col.size_formatted }})
                                        </span>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-col>
                    </v-row>
                </div>
            </v-expand-transition>
        </v-card-text>
        <v-card-actions class="pt-0">
            <v-spacer />
            <v-btn
                variant="text"
                size="small"
                @click="expanded = !expanded"
            >
                {{ expanded ? $t('common.showLess') : $t('common.showDetails') }}
                <v-icon :icon="expanded ? 'mdi-chevron-up' : 'mdi-chevron-down'" end />
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_count: 0,
            total_size: 0,
            size_formatted: '0 B',
            by_context: [],
            by_type: [],
            by_collection: [],
        }),
    },
});

const expanded = ref(false);

const imageCount = computed(() => {
    const imageType = props.stats.by_type?.find(t => t.type === 'Images');
    return imageType?.count || 0;
});

const contextCount = computed(() => {
    return props.stats.by_context?.length || 0;
});
</script>
