<template>
    <div class="mega-menu">
        <!-- Desktop: dropdown panel. The button lives in the activator slot so
             Vuetify handles open/close/anchoring natively. -->
        <v-menu
            v-if="mdAndUp"
            v-model="menuOpen"
            :close-on-content-click="false"
            location="bottom end"
            transition="slide-y-transition"
            min-width="280"
            max-width="min(960px, 96vw)"
        >
            <template #activator="{ props: activatorProps }">
                <v-btn
                    icon
                    variant="text"
                    size="small"
                    :aria-label="$t('megaMenu.openMenu')"
                    v-bind="activatorProps"
                >
                    <v-icon>{{ menuOpen ? 'mdi-close' : 'mdi-view-grid-outline' }}</v-icon>
                </v-btn>
            </template>

            <v-card rounded="0" class="pa-2 overflow-x-auto">
                <div v-if="isLoading" class="text-center py-6">
                    <v-progress-circular indeterminate size="24" width="2" class="mb-2" />
                    <div class="text-caption text-medium-emphasis">{{ $t('megaMenu.loading') }}</div>
                </div>

                <v-row v-else-if="columns.length" no-gutters class="flex-nowrap align-start">
                    <v-col
                        v-for="col in columns"
                        :key="col.key"
                        cols="auto"
                        class="mega-col px-1"
                    >
                        <!-- Column for an item with children: heading + child cards -->
                        <template v-if="col.group">
                            <component
                                :is="linkTag(col.group)"
                                v-bind="linkProps(col.group)"
                                class="mega-heading d-block px-3 pt-2 pb-1 text-decoration-none"
                                :class="{ 'mega-heading--link': hasLink(col.group) }"
                                @click="hasLink(col.group) && (menuOpen = false)"
                            >
                                <div class="d-flex align-center text-overline">
                                    <v-icon v-if="col.group.icon" :icon="col.group.icon" size="16" class="mr-2" />
                                    {{ col.group.label }}
                                </div>
                                <div v-if="descOf(col.group)" class="text-caption">
                                    {{ descOf(col.group) }}
                                </div>
                            </component>
                            <mega-menu-item
                                v-for="child in col.group.children"
                                :key="child.id"
                                :item="child"
                                @navigate="menuOpen = false"
                            />
                        </template>

                        <!-- Column of consecutive standalone items -->
                        <template v-else>
                            <mega-menu-item
                                v-for="item in col.items"
                                :key="item.id"
                                :item="item"
                                @navigate="menuOpen = false"
                            />
                        </template>
                    </v-col>
                </v-row>

                <div v-else class="text-center text-caption text-medium-emphasis py-4">
                    {{ $t('megaMenu.noItems') }}
                </div>
            </v-card>
        </v-menu>

        <!-- Mobile: trigger + navigation drawer -->
        <template v-else>
            <v-btn
                icon
                variant="text"
                size="small"
                :aria-label="$t('megaMenu.openMenu')"
                @click="menuOpen = !menuOpen"
            >
                <v-icon>{{ menuOpen ? 'mdi-close' : 'mdi-view-grid-outline' }}</v-icon>
            </v-btn>

            <v-navigation-drawer
                v-model="menuOpen"
                temporary
                location="left"
                :width="320"
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

                <div class="pa-2">
                    <div v-if="isLoading" class="d-flex align-center pa-3">
                        <v-progress-circular indeterminate size="20" width="2" class="mr-2" />
                        {{ $t('megaMenu.loading') }}
                    </div>

                    <template v-else-if="items.length">
                        <div v-for="group in items" :key="group.id" class="mb-2">
                            <template v-if="group.children && group.children.length">
                                <component
                                    :is="linkTag(group)"
                                    v-bind="linkProps(group)"
                                    class="mega-heading d-block px-3 pt-2 pb-1 text-decoration-none"
                                    :class="{ 'mega-heading--link': hasLink(group) }"
                                    @click="hasLink(group) && (menuOpen = false)"
                                >
                                    <div class="d-flex align-center text-overline">
                                        <v-icon v-if="group.icon" :icon="group.icon" size="16" class="mr-2" />
                                        {{ group.label }}
                                    </div>
                                    <div v-if="descOf(group)" class="text-caption">
                                        {{ descOf(group) }}
                                    </div>
                                </component>
                                <mega-menu-item
                                    v-for="child in group.children"
                                    :key="child.id"
                                    :item="child"
                                    @navigate="menuOpen = false"
                                />
                            </template>

                            <mega-menu-item
                                v-else
                                :item="group"
                                @navigate="menuOpen = false"
                            />
                        </div>
                    </template>

                    <div v-else class="text-caption text-medium-emphasis pa-3">
                        {{ $t('megaMenu.noItems') }}
                    </div>
                </div>
            </v-navigation-drawer>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useDisplay } from 'vuetify';
import { useMenuStore } from '@/store/menuStore.js';
import { useMenuLink } from '@/composables/useMenuLink.js';
import MegaMenuItem from '@/components/navigation/MegaMenuItem.vue';

const props = defineProps({
    location: { type: String, default: 'header' },
});

const route = useRoute();
const { mdAndUp } = useDisplay();
const menuStore = useMenuStore();
const { hasLink, linkTag, linkProps } = useMenuLink();

const menuOpen = ref(false);

// menuStore is keyed by location — wrap the per-location getters as computeds
// so the template stays readable.
const items = computed(() => menuStore.items(props.location));
const isLoading = computed(() => menuStore.isLoading(props.location));

// Build columns: an item with children is its own column (heading + cards);
// consecutive items without children are grouped together into one column.
const columns = computed(() => {
    const result = [];
    let bucket = null;

    const flush = () => {
        if (bucket && bucket.length) {
            result.push({ key: `i-${bucket[0].id}`, items: bucket });
        }
        bucket = null;
    };

    for (const item of items.value) {
        if (item.children && item.children.length) {
            flush();
            result.push({ key: `g-${item.id}`, group: item });
        } else {
            if (!bucket) bucket = [];
            bucket.push(item);
        }
    }
    flush();

    return result;
});

function descOf(item) {
    return item.metadata?.description || '';
}

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
    display: inline-flex;
    align-items: center;
}

/* Each column of the panel has a fixed width for a tidy grid. */
.mega-col {
    width: 260px;
}

/* Muted by default; label + description subtitle inherit this colour. */
.mega-heading {
    color: rgba(var(--v-theme-on-surface), 0.55);
}

.mega-heading--link {
    cursor: pointer;
}

.mega-heading--link:hover {
    color: rgb(var(--v-theme-primary));
}
</style>
