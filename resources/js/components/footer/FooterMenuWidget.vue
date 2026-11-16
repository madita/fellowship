<template>
    <div>
        <footer-widget-heading :title="config.title" />

        <div v-if="isLoading" class="text-body-2 text-medium-emphasis">
            {{ $t('megaMenu.loading') }}
        </div>

        <!-- Button style -->
        <div v-else-if="hasItems && isButton" class="d-flex flex-wrap ga-2">
            <v-btn
                v-for="item in flatItems"
                :key="item.id"
                :to="!isExternal(item) ? item.href : undefined"
                :href="isExternal(item) ? item.href : undefined"
                :target="isExternal(item) ? (item.target || '_blank') : undefined"
                :rel="isExternal(item) ? 'noopener noreferrer' : undefined"
                :prepend-icon="item.icon || undefined"
                variant="tonal"
                color="primary"
                size="small"
            >
                {{ item.label }}
            </v-btn>
        </div>

        <!-- Text style (simple / bold) -->
        <div v-else-if="hasItems" :class="isInline ? 'd-flex flex-wrap align-center ga-3' : 'd-flex flex-column'">
            <template v-for="item in items" :key="item.id">
                <div class="mb-2" :class="sizeClass">
                    <component
                        :is="isExternal(item) ? 'a' : 'router-link'"
                        v-bind="linkProps(item)"
                        class="footer-link text-decoration-none text-primary"
                        :class="{ 'font-weight-bold': isBold }"
                    >
                        <v-icon v-if="item.icon" :icon="item.icon" size="x-small" class="mr-1" />
                        {{ item.label }}
                    </component>
                </div>

                <!-- Child items (list layout only) -->
                <template v-if="!isInline && item.children && item.children.length">
                    <div
                        v-for="child in item.children"
                        :key="child.id"
                        class="mb-2 ml-4"
                        :class="sizeClass"
                    >
                        <component
                            :is="isExternal(child) ? 'a' : 'router-link'"
                            v-bind="linkProps(child)"
                            class="footer-link text-decoration-none text-primary"
                            :class="{ 'font-weight-bold': isBold }"
                        >
                            <v-icon v-if="child.icon" :icon="child.icon" size="x-small" class="mr-1" />
                            {{ child.label }}
                        </component>
                    </div>
                </template>
            </template>
        </div>

        <div v-else class="text-caption text-medium-emphasis">
            {{ $t('megaMenu.noItems') }}
        </div>
    </div>
</template>

<script setup>
import { computed, watch, onMounted } from 'vue';
import { useMenuStore } from '@/store/menuStore.js';
import { useMenuLink } from '@/composables/useMenuLink.js';
import FooterWidgetHeading from './FooterWidgetHeading.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const menuStore = useMenuStore();
const { isExternal, linkProps } = useMenuLink();

// New widgets target a specific menu by slug; older widgets fall back to a
// location so existing footers keep rendering.
const slug = computed(() => props.config.menu_slug || '');
const location = computed(() => (props.config.menu_slug ? '' : props.config.location || 'footer'));

// Store key: slug lookups are namespaced to avoid colliding with locations.
const menuKey = computed(() => (slug.value ? `slug:${slug.value}` : location.value));

const items = computed(() => menuStore.items(menuKey.value));
const isLoading = computed(() => menuStore.isLoading(menuKey.value));
const hasItems = computed(() => items.value.length > 0);

const isInline = computed(() => props.config.layout === 'inline');
const isBold = computed(() => props.config.style === 'bold');
const isButton = computed(() => props.config.style === 'button');
const sizeClass = computed(() =>
    isBold.value ? 'text-body-1 text-sm-h6' : 'text-body-2 text-sm-body-1'
);

// Button style renders a flat group, so pull child items up alongside parents.
const flatItems = computed(() =>
    items.value.flatMap((item) => [item, ...(item.children || [])])
);

function load() {
    if (slug.value) {
        menuStore.fetchMenuBySlug(slug.value);
    } else if (location.value) {
        menuStore.fetchMenu(location.value);
    }
}

watch(() => [slug.value, location.value], load);
onMounted(load);
</script>

<style scoped>
.footer-link {
    transition: opacity 0.15s ease;
}

.footer-link:hover {
    text-decoration: underline !important;
    text-underline-offset: 3px;
}
</style>
