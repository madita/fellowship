<template>
    <!-- Inline variant: horizontal row of links (footer use). -->
    <div v-if="variant === 'inline'" class="location-menu-inline d-flex flex-wrap align-center ga-2">
        <template v-if="hasItems">
            <v-btn
                v-for="item in items"
                :key="item.id"
                :to="item.type !== 'external' ? item.href : undefined"
                :href="item.type === 'external' ? item.href : undefined"
                :target="item.type === 'external' ? '_blank' : undefined"
                :prepend-icon="item.icon || undefined"
                variant="text"
                size="small"
                density="comfortable"
            >
                {{ item.label }}
            </v-btn>
        </template>
        <span v-else-if="!isLoading" class="text-caption text-medium-emphasis">
            {{ $t('megaMenu.noItems') }}
        </span>
    </div>

    <!-- List variant: vertical v-list (drawer / sidebar use). -->
    <v-list v-else density="compact" nav>
        <template v-if="isLoading">
            <v-list-item>
                <v-progress-circular indeterminate size="18" width="2" class="mr-2" />
                {{ $t('megaMenu.loading') }}
            </v-list-item>
        </template>

        <template v-else-if="hasItems">
            <template v-for="item in items" :key="item.id">
                <v-list-group v-if="item.children && item.children.length > 0">
                    <template #activator="{ props: activatorProps }">
                        <v-list-item v-bind="activatorProps">
                            <template #prepend>
                                <v-icon v-if="item.icon" :icon="item.icon" size="small" />
                            </template>
                            <v-list-item-title>{{ item.label }}</v-list-item-title>
                        </v-list-item>
                    </template>
                    <v-list-item
                        v-for="child in item.children"
                        :key="child.id"
                        :to="child.type !== 'external' ? child.href : undefined"
                        :href="child.type === 'external' ? child.href : undefined"
                        :target="child.type === 'external' ? '_blank' : undefined"
                        @click="$emit('navigate')"
                    >
                        <template #prepend>
                            <v-icon v-if="child.icon" :icon="child.icon" size="small" />
                        </template>
                        <v-list-item-title>{{ child.label }}</v-list-item-title>
                        <template #append>
                            <v-icon v-if="child.type === 'external'" size="x-small">mdi-open-in-new</v-icon>
                        </template>
                    </v-list-item>
                </v-list-group>

                <v-list-item
                    v-else
                    :to="item.type !== 'external' ? item.href : undefined"
                    :href="item.type === 'external' ? item.href : undefined"
                    :target="item.type === 'external' ? '_blank' : undefined"
                    @click="$emit('navigate')"
                >
                    <template #prepend>
                        <v-icon v-if="item.icon" :icon="item.icon" size="small" />
                    </template>
                    <v-list-item-title>{{ item.label }}</v-list-item-title>
                    <template #append>
                        <v-icon v-if="item.type === 'external'" size="x-small">mdi-open-in-new</v-icon>
                    </template>
                </v-list-item>
            </template>
        </template>

        <template v-else>
            <v-list-item>
                <v-list-item-title class="text-caption text-medium-emphasis">
                    {{ $t('megaMenu.noItems') }}
                </v-list-item-title>
            </v-list-item>
        </template>
    </v-list>
</template>

<script setup>
import { computed, watch, onMounted } from 'vue';
import { useMenuStore } from '@/store/menuStore.js';

const props = defineProps({
    location: { type: String, required: true },
    variant: {
        type: String,
        default: 'list',
        validator: (v) => ['list', 'inline'].includes(v),
    },
});
defineEmits(['navigate']);

const menuStore = useMenuStore();

const items = computed(() => menuStore.items(props.location));
const isLoading = computed(() => menuStore.isLoading(props.location));
const hasItems = computed(() => items.value.length > 0);

watch(() => props.location, (loc) => {
    menuStore.fetchMenu(loc);
});

onMounted(() => {
    menuStore.fetchMenu(props.location);
});
</script>
