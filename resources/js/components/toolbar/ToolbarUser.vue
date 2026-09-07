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
                :to="item.link || undefined"
                :exact="item.exact"
                :disabled="item.disabled"
                @click="item.action ? handleAction(item.action) : null"
                :link="!!item.link || !!item.action"
            >
                <template v-slot:prepend v-if="item.icon">
                    <v-icon>{{ item.icon }}</v-icon>
                </template>
                <v-list-item-title>{{ item.key ? $t(item.key) : item.text }}</v-list-item-title>
            </v-list-item>

            <!-- Administration — clearly separated from the personal links -->
            <template v-if="isAdmin">
                <v-divider class="my-1"></v-divider>
                <v-list-subheader class="text-uppercase text-caption font-weight-bold">
                    {{ $t('menu.admin') }}
                </v-list-subheader>
                <v-list-item to="/admin/settings" link>
                    <template v-slot:prepend>
                        <v-icon color="primary">mdi-shield-crown-outline</v-icon>
                    </template>
                    <v-list-item-title>{{ $t('menu.adminSettings') }}</v-list-item-title>
                </v-list-item>
            </template>

            <v-divider class="my-1"></v-divider>

            <v-list-item @click.prevent="signOut">
                <template v-slot:prepend>
                    <v-icon>mdi-logout</v-icon>
                </template>
                <v-list-item-title>{{ $t('menu.logout') }}</v-list-item-title>
            </v-list-item>
        </v-list>
    </v-menu>
</template>

<script>
import { computed, onMounted } from 'vue';
import config from '../../configs'
import UserAvatar from "../common/UserAvatar.vue";
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import {useRouter} from "vue-router";
import eventBus from '../common/eventBus.js'
import permission from '../../helpers/permission.js'

export default {
    components: {
        UserAvatar
    },
    setup() {
        const authStore = useAuthStore();
        const userStore = useUserStore();
        const router = useRouter()

        // Same gate the sidebar uses: items tagged with a feature disappear
        // when that feature is deactivated.
        const menu = computed(() => config.toolbar.user.filter(item => permission.applyPermissions(item)));
        const isAdmin = computed(() => userStore.hasRole('admin'));

        const signOut = async () => {
            await authStore.logout();
            await authStore.resetStore();
            await userStore.resetStore();
            router.replace({name: 'home'});
        }

        const handleAction = (action) => {
            if (action === 'settings') {
                eventBus.emit('toolbar.settings.open')
            }
        }

        onMounted(() => {

            //console.log(userStore.user);
        });

        return {
            menu,
            isAdmin,
            signOut,
            handleAction,
            user: userStore.user,
            authenticated: authStore.isLoggedIn
        };
    }
}
</script>
