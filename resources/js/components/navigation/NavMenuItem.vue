<template>
    <div class="nav-menu-item">
        <!-- Handle items with links but no children -->
        <v-list-item
            v-if="menuItem.link && !menuItem.items && $helpers.applyPermissions(menuItem)"
            :to="menuItem.link"
            :href="menuItem.href"
            :target="menuItem.target"
            :exact="menuItem.exact"
            :disabled="menuItem.disabled"
            :prepend-icon="menuItem.icon"
            selected-class="text-primary"
            :title="menuItem.text"
            :value="menuItem.key"
        ></v-list-item>

        <!-- Handle items that are just headers (no link) -->
        <v-list-subheader v-else-if="menuItem.text && !menuItem.items && !menuItem.link">
            {{ menuItem.text }}
        </v-list-subheader>

        <!-- Handle items with children -->
        <v-list-group
            v-else-if="menuItem.items && menuItem.items.length && $helpers.applyPermissions(menuItem)"
            :value="menuItem.key"
        >
            <template v-slot:activator="{ props }">
                <v-list-item
                    v-bind="props"
                    :prepend-icon="menuItem.icon"
                    :title="menuItem.text"
                    :value="menuItem.key"
                ></v-list-item>
            </template>

            <!-- Render child items through slot -->
            <slot></slot>
        </v-list-group>

        <!-- Empty item case -->
        <span v-else></span>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
    menuItem: {
        type: Object,
        required: true
    },
    subgroup: {
        type: Boolean,
        default: false
    },
    small: {
        type: Boolean,
        default: false
    }
})

const route = useRoute()

const isActive = computed(() => {
    if (!props.menuItem.link || !route) return false

    if (props.menuItem.regex) {
        const regex = new RegExp(props.menuItem.regex)
        return regex.test(route.path)
    }

    return route.path === props.menuItem.link
})
</script>


