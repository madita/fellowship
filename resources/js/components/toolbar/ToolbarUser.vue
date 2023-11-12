<template>
    <v-menu offset-y left transition="slide-y-transition">
        <template v-slot:activator="{ props }">
            <v-btn icon class="elevation-2" v-bind="props">
                <v-badge color="success" offset-x="5" offset-y="5">
                    <user-avatar :user="user"></user-avatar>
                </v-badge>
            </v-btn>
        </template>

        <!-- user menu list -->
        <v-list>
            <v-list-item
                v-for="(item, index) in menu"
                :key="index"
                :to="item.link"
                :exact="item.exact"
                :disabled="item.disabled"
                link
            >
                <v-list-item-title>{{ item.key ? $t(item.key) : item.text }}</v-list-item-title>
            </v-list-item>

            <v-divider class="my-1"></v-divider>

            <v-list-item @click.prevent="signOut">
                <v-list-item-title>{{ $t('menu.logout') }}</v-list-item-title>
            </v-list-item>
        </v-list>
    </v-menu>
</template>

<script>
import { ref, onMounted } from 'vue';
import config from '../../configs'
import UserAvatar from "../common/UserAvatar.vue";
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import {useRouter} from "vue-router";

export default {
    components: {
        UserAvatar
    },
    setup() {
        const menu = ref(config.toolbar.user);
        const authStore = useAuthStore();
        const userStore = useUserStore();
        const router = useRouter()

        const signOut = async () => {
            await authStore.resetStore();
            router.replace({name: 'home'});
        }

        onMounted(() => {
            console.log(userStore.user);
        });

        return {
            menu,
            signOut,
            user: userStore.user,
            authenticated: authStore.isLoggedIn
        };
    }
}
</script>
