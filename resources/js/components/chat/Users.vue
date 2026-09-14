<template>
    <v-sheet>
        <div class="px-4 py-3 text-subtitle-1 font-weight-medium">
            {{ $t('chat.online', { count: users.length }) }}
        </div>
        <v-divider />
    </v-sheet>
    <perfect-scrollbar class="lgScroll">
        <empty-state
            v-if="!users.length"
            compact
            icon="mdi-account-off-outline"
            :title="$t('chat.noUsersOnline')"
        />
        <v-list v-else>
            <!---Single Item-->
            <v-list-item
                :value="user.id"
                color="primary"
                class="text-no-wrap chatItem"
                v-for="user in users" :key="user.id"
            >
                <!---Avatar-->
                <template v-slot:prepend>
                    <user-avatar :user="user"></user-avatar>
                </template>
                <!---Name-->
                <v-list-item-title class="text-subtitle-1 w-100 font-weight-medium">{{ user.username }}</v-list-item-title>
            </v-list-item>
        </v-list>
    </perfect-scrollbar>
</template>

<script>
import {ref, onMounted, computed} from 'vue';
import { useUserStore } from "@/store/userStore.js";

import {useOnlineUsersStore} from "@/store/onlineUsersStore.js";
import UserAvatar from "@/components/common/UserAvatar.vue";
import EmptyState from "@/components/common/EmptyState.vue";

export default {
    components: {UserAvatar, EmptyState},
    setup() {
        const usersDrawer = ref(true);
        const searchValue = ref('');

        const userStore = useUserStore();
        const onlineUsers = useOnlineUsersStore();
        const user = userStore.user;

        const users = computed(() => {
            return onlineUsers.users;
        });

        return {
            usersDrawer,
            users,
            user
        };
    }
}
</script>
