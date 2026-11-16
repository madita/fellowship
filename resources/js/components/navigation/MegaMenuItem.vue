<template>
    <component
        :is="linkTag"
        v-bind="linkProps"
        class="mega-item"
        :class="{ 'mega-item--link': hasLink }"
        @click="hasLink && $emit('navigate')"
    >
        <div class="mega-item__icon">
            <v-icon :icon="item.icon || 'mdi-arrow-right-thin'" size="20" />
        </div>
        <div class="mega-item__body">
            <div class="mega-item__title">
                {{ item.label }}
                <v-icon v-if="isExternal" size="14" class="ml-1 text-medium-emphasis">mdi-open-in-new</v-icon>
            </div>
            <div v-if="description" class="mega-item__desc">
                {{ description }}
            </div>
        </div>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { useMenuLink } from '@/composables/useMenuLink.js';

const props = defineProps({
    item: { type: Object, required: true },
});
defineEmits(['navigate']);

const link = useMenuLink();

const isExternal = computed(() => link.isExternal(props.item));
const hasLink = computed(() => link.hasLink(props.item));
const linkTag = computed(() => link.linkTag(props.item));
const linkProps = computed(() => link.linkProps(props.item));

const description = computed(() => props.item.metadata?.description || '');
</script>

<style scoped>
/* Second-level destination card: icon tile + title + description */
.mega-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 4px;
    text-decoration: none;
    color: inherit;
    transition: background-color 0.15s ease;
}

.mega-item--link {
    cursor: pointer;
}

.mega-item--link:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.06);
}

.mega-item__icon {
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    color: rgb(var(--v-theme-primary));
    background-color: rgb(var(--v-theme-surface));
}

.mega-item__body {
    min-width: 0;
}

.mega-item__title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.35;
    color: rgba(var(--v-theme-on-surface), 0.92);
}

.mega-item__desc {
    margin-top: 2px;
    font-size: 0.8125rem;
    line-height: 1.35;
    color: rgba(var(--v-theme-on-surface), 0.6);
}
</style>
