<template>
    <v-list density="compact" class="user-mention-list py-1" elevation="4" rounded="lg">
        <template v-if="items.length">
            <v-list-item
                v-for="(item, index) in items"
                :key="item.id"
                :active="index === selectedIndex"
                color="primary"
                @click="selectItem(index)"
                @mousemove="selectedIndex = index"
            >
                <template #prepend>
                    <user-avatar :user="item" size="28" class="mr-2" />
                </template>
                <v-list-item-title class="text-body-2">{{ item.name || item.username }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">@{{ item.username }}</v-list-item-subtitle>
            </v-list-item>
        </template>
        <v-list-item v-else>
            <v-list-item-title class="text-body-2 text-medium-emphasis">{{ $t('editor.mentionNoResults') }}</v-list-item-title>
        </v-list-item>
    </v-list>
</template>

<script>
import UserAvatar from '@/components/common/UserAvatar.vue';

/**
 * Popup list of members while typing "@…" in the SimpleEditor.
 * Arrow keys move, Enter/Tab pick, the suggestion plugin handles Escape.
 */
export default {
    name: 'UserMentionList',
    components: { UserAvatar },
    props: {
        items: { type: Array, required: true },
        command: { type: Function, required: true },
    },
    data() {
        return { selectedIndex: 0 };
    },
    watch: {
        items() {
            this.selectedIndex = 0;
        },
    },
    methods: {
        onKeyDown({ event }) {
            if (event.key === 'ArrowUp') {
                this.selectedIndex = (this.selectedIndex + this.items.length - 1) % this.items.length;
                return true;
            }
            if (event.key === 'ArrowDown') {
                this.selectedIndex = (this.selectedIndex + 1) % this.items.length;
                return true;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                this.selectItem(this.selectedIndex);
                return true;
            }
            return false;
        },
        selectItem(index) {
            const item = this.items[index];
            if (!item) return;
            // The Mention extension inserts a node with these attrs
            this.command({ id: item.id, label: item.username });
        },
    },
};
</script>

<style scoped>
.user-mention-list {
    min-width: 220px;
    max-width: 320px;
    max-height: 280px;
    overflow-y: auto;
}
</style>
