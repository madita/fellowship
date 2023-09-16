<template>
    <v-menu offset-y left transition="slide-y-transition">
        <template v-slot:activator="{ on }">
            <v-btn icon class="elevation-2">
                <v-badge
                    color="success"
                    offset-x="10"
                    offset-y="10"
                >
                    <user-avatar :user="user"></user-avatar>
                </v-badge>
            </v-btn>
        </template>

        <!-- user menu list -->
        <v-list dense>
            <v-list-item
                :icon="item.icon"
                v-for="(item, index) in menu"
                :key="index"
                :to="item.link"
                :exact="item.exact"
                :disabled="item.disabled"
                link
            >
<!--                <v-list-item-icon>-->
<!--                    <v-icon small :class="{ 'grey&#45;&#45;text': item.disabled }">{{ item.icon }}</v-icon>-->
<!--                </v-list-item-icon>-->

                    <v-list-item-title>{{ item.key ? $t(item.key) : item.text }}</v-list-item-title>

            </v-list-item>

            <v-divider class="my-1"></v-divider>

            <v-list-item @click.prevent="signOut" icon="mdi-logout-variant">
<!--                <v-list-item-icon>-->
<!--                    <v-icon small>mdi-logout-variant</v-icon>-->
<!--                </v-list-item-icon>-->

                    <v-list-item-title>{{ $t('menu.logout') }}</v-list-item-title>

            </v-list-item>
        </v-list>
    </v-menu>
</template>

<script>
import config from '../../configs'
import UserAvatar from "../common/UserAvatar.vue";
import {mapActions, mapGetters} from "vuex";
/*
|---------------------------------------------------------------------
| Toolbar User Component
|---------------------------------------------------------------------
|
| Quickmenu for user menu shortcuts on the toolbar
|
*/
export default {
    components: {
        UserAvatar
    },
    data() {
        return {
            menu: config.toolbar.user
        }
    },
    methods: {
        ...mapActions({
            signOutAction: 'auth/signOut'
        }),

        async signOut() {
            await this.signOutAction()

            this.$router.replace({name: 'home'})
        }
    },
    computed: {
        ...mapGetters({
            authenticated: 'auth/authenticated',
            user: 'auth/user',
        })
    }
}
</script>
