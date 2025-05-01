<template>
    <div>
        <v-list expand density="compact">
            <!-- menu level 1 -->
            <template v-for="(level1Item, level1Index) in menu" :key="level1Index">
                <nav-menu-item
                    :menu-item="level1Item"
                >
                    <template v-if="level1Item.items && level1Item.items.length">
                        <!-- menu level 2 -->
                        <template v-for="(level2Item, level2Index) in level1Item.items" :key="level2Index">
                            <nav-menu-item
                                :menu-item="level2Item"
                                subgroup
                                small
                            >
                                <template v-if="level2Item.items && level2Item.items.length">
                                    <!-- menu level 3 -->
                                    <nav-menu-item
                                        v-for="(level3Item, level3Index) in level2Item.items"
                                        :key="level3Index"
                                        :menu-item="level3Item"
                                        small
                                    />
                                </template>
                            </nav-menu-item>
                        </template>
                    </template>
                </nav-menu-item>
            </template>
        </v-list>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import NavMenuItem from './NavMenuItem.vue'

const props = defineProps({
    menu: {
        type: Array,
        default: () => []
    }
})

// Track opened menu groups
const openGroups = ref([])
</script>
