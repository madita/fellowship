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
            max-width="min(960px, calc(100vw - 32px))"
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

            <v-card rounded="lg" class="mega-panel pa-2">
                <div v-if="isLoading" class="text-center py-6">
                    <v-progress-circular indeterminate size="24" width="2" class="mb-2" />
                    <div class="text-caption text-medium-emphasis">{{ $t('megaMenu.loading') }}</div>
                </div>

                <div v-else-if="columns.length" class="mega-grid">
                    <div
                        v-for="col in columns"
                        :key="col.key"
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
                    </div>
                </div>

                <div v-else class="text-center text-caption text-medium-emphasis py-4">
                    {{ $t('megaMenu.noItems') }}
                </div>
            </v-card>
        </v-menu>

        <!-- Mobile: trigger + navigation drawer.

             The drawer is teleported out of this component, because this
             component renders inside the app bar and the bar is a Vuetify
             layout item: it carries an inline transform, which makes it
             the containing block for the fixed-position drawer nested
             inside it, and `.v-toolbar__content` clips what overflows.
             The drawer was being cut down to the height of the bar — its
             title row and the close button were all of the menu a phone
             ever saw. -->
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

            <teleport :to="drawerHost">
                <v-navigation-drawer
                    v-model="menuOpen"
                    temporary
                    location="right"
                    :width="drawerWidth"
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
            </teleport>
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
const { mdAndUp, width } = useDisplay();
const menuStore = useMenuStore();
const { hasLink, linkTag, linkProps } = useMenuLink();

const menuOpen = ref(false);

// 320 is the width this drawer wants, but a phone narrower than that gets
// a drawer that covers the page edge to edge, with the content it is
// covering nowhere in sight. Leave a strip of it showing to tap back to.
const drawerWidth = computed(() => Math.min(320, Math.round(width.value * 0.9)));

// Where that drawer goes: the layout root, the nearest ancestor of the app
// bar that is not transformed, so the drawer is laid out against the window
// and its scrim covers the page however far down it is scrolled. Resolved
// after mount, because that is when the element is there to find; `body`
// stands in until then, and wherever there is no Vuetify app around this
// component at all.
const drawerHost = ref('body');

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
    drawerHost.value = document.querySelector('.v-application__wrap') ?? 'body';
    menuStore.fetchMenu(props.location);
});
</script>

<style scoped>
.mega-menu {
    display: inline-flex;
    align-items: center;
}

/**
 * The panel wraps.
 *
 * It used to be one nowrap row of 260px columns inside a card capped at
 * 960px, which is three and a half columns: a menu with four groups put
 * the rest behind a horizontal scrollbar, and nothing in the panel said
 * there was more to the right. Columns now run on to a second row.
 *
 * They ask for 230 and take what is going: four to a row on a full-width
 * panel, which is about the 260 they used to be fixed at. The bounds are
 * the lower so a column still fits an icon tile beside a line of
 * description, the upper so a lone column on the last row does not
 * stretch the width of the panel.
 */
.mega-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 4px 0;
}

.mega-col {
    flex: 1 1 230px;
    min-width: 210px;
    max-width: 320px;
}

/* And it stays on screen vertically too: a group with a long list of
   children scrolls inside the panel rather than running off the bottom,
   where a dropdown has nothing to scroll with. */
.mega-panel {
    max-height: min(70vh, 620px);
    overflow-y: auto;
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
