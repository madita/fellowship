<template>
    <div>
        <!-- Notification Button -->
        <v-btn icon class="rounded-pill" id="page-header-notifications" @click="toggleDropdown">
            <v-icon>mdi-email</v-icon>
            <v-badge
                v-if="conversations.length > 0"
                color="primary"
                overlap
                dot
                content="{{ conversations.length }}"
            ></v-badge>
        </v-btn>

        <!-- Dropdown Menu -->
        <v-menu v-model="dropdownOpen" offset-y>
            <template #activator="{ attrs }">
                <v-btn v-bind="attrs"></v-btn>
            </template>

            <v-card>
                <v-card-title class="text-center">
                    Private Messages
                </v-card-title>

                <v-divider></v-divider>

                <!-- Empty State -->
                <v-card-text v-if="conversations.length === 0" class="text-center">
                    <h6 class="text-muted">You don't have any notifications</h6>
                    <v-icon size="24">mdi-bell-outline</v-icon>
                </v-card-text>

                <!-- Conversations List -->
                <v-list v-else>
                    <v-subheader>You have {{ conversations.length }} new conversations</v-subheader>

                    <v-divider></v-divider>

                    <template v-for="(conversation, index) in conversations" :key="conversation.id">
                        <v-list-item :href="`${conversationUrl}/${conversation.id}`">
                            <v-list-item-avatar>
                                <v-icon>mdi-bell</v-icon>
                            </v-list-item-avatar>
                            <v-list-item-title>
                                {{ conversation.body }}
                            </v-list-item-title>
                            <v-list-item-subtitle>
                                {{ conversation.created_at }}
                            </v-list-item-subtitle>
                            <v-list-item-action>
                                <v-btn small icon>
                                    <v-icon color="info">mdi-eye</v-icon>
                                </v-btn>
                            </v-list-item-action>
                        </v-list-item>
                        <v-divider v-if="index < conversations.length - 1"></v-divider>
                    </template>
                </v-list>

                <!-- Footer -->
                <v-card-actions>
                    <v-btn text color="primary" v-if="conversations.length > 0">
                        Mark all as read
                    </v-btn>
                    <v-btn text :href="conversationUrl">
                        View All
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-menu>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useConversationStore } from '@/store/conversationStore'; // Pinia store

export default {
    setup() {
        const store = useConversationStore(); // Pinia store instance
        const dropdownOpen = ref(false);
        const conversationUrl = '/conversations'; // Replace with your actual route URL.

        // Computed property for conversations
        const conversations = computed(() => store.conversations);

        // Fetch conversations on mount
        onMounted(() => {
            store.fetchConversations(1);
        });

        // Methods
        const toggleDropdown = () => {
            dropdownOpen.value = !dropdownOpen.value;
        };

        return {
            conversations,
            conversationUrl,
            dropdownOpen,
            toggleDropdown,
        };
    },
};
</script>

<style scoped>
.badge-circle.badge-md {
    width: 1rem;
    height: 1rem;
    position: relative;
    top: -12px;
    left: -12px;
}
</style>
