<template>
    <div
        v-shortkey="['ctrl', '/']"
        class="d-flex flex-grow-1"
        @shortkey="onKeyup"
    >
        <!-- Navigation -->
        <v-navigation-drawer
            v-model="drawer"
            app
            floating
            class="elevation-1"
            :right="$vuetify.rtl"
            :light="menuTheme === 'light'"
            :dark="menuTheme === 'dark'"
        >
        <a class="skip-nav-link" href="#main-content">
                skip navigation
        </a>
            <!-- Navigation menu info -->
            <template v-slot:prepend>
                <div class="pa-2">
                    <div @click="routeHome" class="cursor-pointer title font-weight-bold text-uppercase text-primary">{{ product.name }}</div>
                    <div class="overline grey--text">{{ product.version }}</div>
                </div>
            </template>
            <!-- Navigation menu -->
            <main-menu :menu="navigation.menu"/>

            <!-- Navigation menu footer -->
            <template v-slot:append>
                <!-- Footer navigation links -->
                <div class="pa-1 text-center">
                    <v-btn
                        v-for="(item, index) in navigation.footer"
                        :key="index"
                        :href="item.href"
                        :target="item.target"
                        small
                        text
                    >
                        {{ item.key ? $t(item.key) : item.text }}
                    </v-btn>
                </div>

            </template>
        </v-navigation-drawer>
        <!-- Toolbar -->
        <v-app-bar
            app
            :color="isToolbarDetached ? 'surface' : undefined"
            :flat="isToolbarDetached"
            :light="toolbarTheme === 'light'"
            :dark="toolbarTheme === 'dark'"
        >
            <v-card class="flex-grow-1 d-flex" :class="[isToolbarDetached ? 'pa-1 mt-3 mx-1' : 'pa-0 ma-0']"
                    :flat="!isToolbarDetached">
                <div class="d-flex flex-grow-1 align-center">

                    <!-- search input mobile -->
                    <v-text-field
                        v-if="showSearch"
                        append-icon="mdi-close"
                        placeholder="Search"
                        prepend-inner-icon="mdi-magnify"
                        hide-details
                        solo
                        flat
                        autofocus
                        @click:append="showSearch = false"
                    ></v-text-field>

                    <div v-else class="d-flex flex-grow-1 align-center">
                        <v-app-bar-nav-icon @click.stop="drawer = !drawer"></v-app-bar-nav-icon>

                        <v-spacer class="d-none d-lg-block"></v-spacer>

                        <!-- search input desktop -->
                        <v-text-field
                            ref="search"
                            class="mx-1 hidden-xs-only"
                            placeholder="Search/Suche"
                            prepend-inner-icon="mdi-magnify"
                            hide-details
                            filled
                            rounded
                            dense
                        ></v-text-field>

                        <v-spacer class="d-block d-sm-none"></v-spacer>

                        <v-btn class="d-block d-sm-none" icon @click="showSearch = true">
                            <v-icon>mdi-magnify</v-icon>
                        </v-btn>

<!--                        <toolbar-language/>-->

<!--                        <toolbar-apps/>-->

                        <template v-if="authenticated">
                            <div :class="[$vuetify.rtl ? 'ml-1' : 'mr-1']">
                                <toolbar-notifications/>
                            </div>

                            <toolbar-user/>
                        </template>
                        <template v-else>
                            <v-btn text class="mx-1" to="/auth/signin">
                                Sign In
                            </v-btn>
                        </template>

                    </div>
                </div>
            </v-card>
        </v-app-bar>

        <v-main id="main-content">
            <v-container class="fill-height pa-0" :fluid="!isContentBoxed">
                <v-layout>
                    <slot></slot>
                </v-layout>
            </v-container>

            <v-footer inset>
                <v-spacer></v-spacer>
                <div class="overline">
                    @fellowship
                </div>
            </v-footer>
        </v-main>
    </div>
</template>

<script>
import {mapActions, mapGetters, mapState} from 'vuex'

// navigation menu configurations
import config from '../configs'

import MainMenu from '../components/navigation/MainMenu.vue'
import ToolbarUser from '../components/toolbar/ToolbarUser.vue'
import ToolbarApps from '../components/toolbar/ToolbarApps.vue'
import ToolbarLanguage from '../components/toolbar/ToolbarLanguage.vue'
import ToolbarNotifications from '../components/toolbar/ToolbarNotifications.vue'

export default {
    components: {
        MainMenu,
        ToolbarUser,
        ToolbarApps,
        ToolbarLanguage,
        ToolbarNotifications
    },
    data() {
        return {
            drawer: null,
            showSearch: false,

            navigation: config.navigation
        }
    },
    computed: {
        ...mapState('app', ['product', 'isContentBoxed', 'menuTheme', 'toolbarTheme', 'isToolbarDetached']),
        ...mapGetters({
            authenticated: 'auth/authenticated',
            user: 'auth/user',
        })
    },
    methods: {
        ...mapActions({
            signOutAction: 'auth/signOut'
        }),

        async signOut() {
            await this.signOutAction()
        },
        routeHome() {
            this.$router.replace({name: 'home'})
        },
        onKeyup(e) {
            this.$refs.search.focus()
        }
    }
}
</script>

<style>
/*skip to main content*/
.skip-nav-link {
    transform: translateY(-350%);
    transition: transform 325ms ease-in;
}

.skip-nav-link:focus {
    transform: translateY(-230%);
}
</style>

