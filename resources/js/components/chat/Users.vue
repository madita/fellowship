<template>
    <v-sheet>
        <div class="px-6 pt-3">
<!--            <v-text-field-->
<!--                variant="outlined"-->
<!--                v-model="searchValue"-->
<!--                append-inner-icon="mdi-magnify"-->
<!--                placeholder="Search Contact"-->
<!--                hide-details-->
<!--                density="compact"-->
<!--            ></v-text-field>-->
            {{ $t('chat.online', { count: users.length }) }}
        </div>
    </v-sheet>
    <perfect-scrollbar class="lgScroll">
        <v-list>
            <!---Single Item-->
            <v-list-item
                :value="user.id"
                color="primary"
                class="text-no-wrap chatItem"
                v-for="user in users" :key="user.id"
                lines="two"
            >
                <!---Avatar-->
                <template v-slot:prepend>
                    <user-avatar :user="user"></user-avatar>
<!--                    <v-badge-->
<!--                        class="badg-dot"-->
<!--                        dot-->
<!--                        :color="-->
<!--                            chat.status === 'away'-->
<!--                                ? 'warning'-->
<!--                                : chat.status === 'busy'-->
<!--                                ? 'error'-->
<!--                                : chat.status === 'online'-->
<!--                                ? 'success'-->
<!--                                : 'containerBg'-->
<!--                        "-->
<!--                    >-->
<!--                    </v-badge>-->
                </template>
                <!---Name-->
                <v-list-item-title class="text-subtitle-1 textPrimary w-100 font-weight-medium">{{ user.username }}</v-list-item-title>
                <!---Subtitle-->

                <!---Last seen--->
                <template v-slot:append>
                    <div class="d-flex flex-column text-right w-25">
                        <small class="textPrimary text-subtitle-2">
                            last active time
                        </small>
                    </div>
                </template>
            </v-list-item>
        </v-list>
    </perfect-scrollbar>


<!--    <v-card>-->
<!--        <v-card-title> {{ $t('chat.online', { count: users.length }) }}</v-card-title>-->
<!--        <v-card-text>-->
<!--            <v-list dense>-->
<!--                <v-list-item v-for="user in users" :key="user.id">-->
<!--                        <v-list-item-title>{{ user.username }}</v-list-item-title>-->
<!--                </v-list-item>-->
<!--            </v-list>-->
<!--        </v-card-text>-->
<!--    </v-card>-->
</template>

<script>
import {ref, onMounted, computed} from 'vue';
import { useUserStore } from "@/store/userStore.js";
// import useEventBus from '@/bus.js';
import {useOnlineUsersStore} from "@/store/onlineUsersStore.js";
import UserAvatar from "@/components/common/UserAvatar.vue";

export default {
    components: {UserAvatar},
    setup() {
        const usersDrawer = ref(true);
        const searchValue = ref('');
        // const users = ref([]);

        const userStore = useUserStore();
        const onlineUsers = useOnlineUsersStore();
        const user = userStore.user;

        const users = computed(() => {
            if (onlineUsers.users === []) {
                return [];
            }
            return onlineUsers.users;
        });

        // const { on } = useEventBus();


        onMounted(() => {




            // on('chatUsers.here', (usersData) => {
            //     console.log('test',usersData);
            //     users.value = usersData;
            // });
            //
            // on('chatUsers.joined', (user) => {
            //     users.value.unshift(user);
            // });
            //
            // on('chatUsers.left', (user) => {
            //     users.value = users.value.filter((u) => u.id !== user.id);
            // });
        });

        return {
            usersDrawer,
            users,
            user
        };
    }
}
</script>

<style lang="scss">
.users {
    background-color: #fff;
    border: 1px solid #d3e0e9;
    border-radius: 3px;

    &__header {
        padding: 15px;
        font-weight: 800;
        margin: 0;
    }

    &__user {
        padding: 0 15px;

        &:last-child {
            padding-bottom: 15px;
        }
    }
}
</style>
