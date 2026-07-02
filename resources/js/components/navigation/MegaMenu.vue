<template>
    <div class="mega-menu">
        <!-- Trigger icon -->
        <v-btn
            icon
            variant="text"
            size="small"
            :aria-label="$t('megaMenu.openMenu')"
            @click="menuOpen = !menuOpen"
        >
            <v-icon>{{ menuOpen ? 'mdi-close' : 'mdi-view-grid-outline' }}</v-icon>
        </v-btn>

        <!-- Desktop: dropdown panel -->
        <v-menu
            v-if="mdAndUp"
            v-model="menuOpen"
            :close-on-content-click="false"
            location="bottom end"
            transition="slide-y-transition"
            min-width="320"
            max-width="600"
        >
            <template #activator="{ props }">
                <!-- hidden activator - we control open state manually via the icon above -->
                <span ref="menuActivator" v-bind="props" class="mega-menu-activator" />
            </template>

            <v-card class="mega-menu-panel">
                <template v-if="isLoading">
                    <v-card-text class="text-center py-6">
                        <v-progress-circular indeterminate size="24" width="2" class="mb-2" />
                        <div class="text-caption text-medium-emphasis">{{ $t('megaMenu.loading') }}</div>
                    </v-card-text>
                </template>

                <template v-else-if="hasItems">
                    <v-list density="compact" nav>
                        <template v-for="item in items" :key="item.id">
                            <!-- Items with children: expandable group -->
                            <v-list-group v-if="item.children && item.children.length > 0">
                                <template #activator="{ props }">
                                    <v-list-item v-bind="props">
                                        <template #prepend>
                                            <v-icon v-if="item.icon" :icon="item.icon" size="small" />
                                        </template>
                                        <v-list-item-title class="font-weight-medium">{{ item.label }}</v-list-item-title>
                                    </v-list-item>
                                </template>
                                <v-list-item
                                    v-for="child in item.children"
                                    :key="child.id"
                                    :to="child.type !== 'external' ? child.href : undefined"
                                    :href="child.type === 'external' ? child.href : undefined"
                                    :target="child.type === 'external' ? '_blank' : undefined"
                                    @click="menuOpen = false"
                                >
                                    <template #prepend>
                                        <v-icon v-if="child.icon" :icon="child.icon" size="small" />
                                    </template>
                                    <v-list-item-title>{{ child.label }}</v-list-item-title>
                                    <v-list-item-subtitle
                                        v-if="child.metadata && child.metadata.description"
                                        class="text-caption"
                                    >
                                        {{ child.metadata.description }}
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <v-icon v-if="child.type === 'external'" size="x-small">mdi-open-in-new</v-icon>
                                    </template>
                                </v-list-item>
                            </v-list-group>

                            <!-- Items without children -->
                            <v-list-item
                                v-else
                                :to="item.type !== 'external' ? item.href : undefined"
                                :href="item.type === 'external' ? item.href : undefined"
                                :target="item.type === 'external' ? '_blank' : undefined"
                                @click="menuOpen = false"
                            >
                                <template #prepend>
                                    <v-icon v-if="item.icon" :icon="item.icon" size="small" />
                                </template>
                                <v-list-item-title class="font-weight-medium">{{ item.label }}</v-list-item-title>
                                <template #append>
                                    <v-icon v-if="item.type === 'external'" size="x-small">mdi-open-in-new</v-icon>
                                </template>
                            </v-list-item>
                        </template>
                    </v-list>
                </template>

                <template v-else>
                    <v-card-text class="text-center text-caption text-medium-emphasis py-4">
                        {{ $t('megaMenu.noItems') }}
                    </v-card-text>
                </template>
            </v-card>
        </v-menu>

        <!-- Mobile: navigation drawer -->
        <v-navigation-drawer
            v-else
            v-model="menuOpen"
            temporary
            location="left"
            :width="300"
        >
            <div class="d-flex align-center justify-space-between pa-3">
                <span class="text-subtitle-1 font-weight-bold">{{ $t('megaMenu.menuTitle') }}</span>
                <v-btn
                    icon
                    variant="text"
                    size="small"
                    :aria-label="$t('megaMenu.closeMenu')"
                    @click="menuOpen = false"
                >
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </div>
            <v-divider />
            <v-list density="compact" nav>
                <template v-if="isLoading">
                    <v-list-item>
                        <v-progress-circular indeterminate size="20" width="2" class="mr-2" />
                        {{ $t('megaMenu.loading') }}
                    </v-list-item>
                </template>
                <template v-else-if="hasItems">
                    <template v-for="item in items" :key="item.id">
                        <!-- Items with children: expandable group -->
                        <v-list-group v-if="item.children && item.children.length > 0">
                            <template #activator="{ props }">
                                <v-list-item v-bind="props">
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
                                @click="menuOpen = false"
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

                        <!-- Items without children -->
                        <v-list-item
                            v-else
                            :to="item.type !== 'external' ? item.href : undefined"
                            :href="item.type === 'external' ? item.href : undefined"
                            :target="item.type === 'external' ? '_blank' : undefined"
                            @click="menuOpen = false"
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
        </v-navigation-drawer>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useDisplay } from 'vuetify';
import { useMenuStore } from '@/store/menuStore.js';

const props = defineProps({
    location: { type: String, default: 'header' },
});

const route = useRoute();
const { mdAndUp } = useDisplay();
const menuStore = useMenuStore();

const menuOpen = ref(false);

// menuStore is keyed by location — wrap the per-location getters as computeds
// so the template stays readable.
const items = computed(() => menuStore.items(props.location));
const isLoading = computed(() => menuStore.isLoading(props.location));
const hasItems = computed(() => items.value.length > 0);

watch(() => route.fullPath, () => {
    menuOpen.value = false;
});

watch(() => props.location, (loc) => {
    menuStore.fetchMenu(loc);
});

onMounted(() => {
    menuStore.fetchMenu(props.location);
});
</script>

<style scoped>
.mega-menu {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.mega-menu-activator {
    position: absolute;
    top: 100%;
    right: 0;
    width: 1px;
    height: 1px;
    pointer-events: none;
}
</style>
