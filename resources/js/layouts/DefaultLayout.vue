<template>
    <div>
        <!-- Navigation -->
        <v-navigation-drawer
            v-model="drawer"
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
                            :placeholder="$t('menu.search')"
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

                                                <toolbar-language/>

<!--                                                <toolbar-apps/>-->

                        <template v-if="authenticated">
                            <div :class="[$vuetify.rtl ? 'ml-1' : 'mr-1']">
                                <toolbar-notifications/>
                            </div>

                            <toolbar-user/>
                        </template>
                        <template v-else>
                            <v-btn class="mx-1" to="/auth/signin">
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
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from "@/store/authStore.js";
import { useUserStore } from "@/store/userStore.js";
// import { useAppStore } from '@/api/useApi.js'
import {useAppStore} from "@/store/app/index.js"
import { useMagicKeys, whenever } from '@vueuse/core'

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
    setup() {
        const drawer = ref(null)
        const showSearch = ref(false)
        const navigation = ref(config.navigation)

        const appStore = useAppStore()
        const authStore = useAuthStore()
        const userStore = useUserStore()

        const product = computed(() => appStore.product)
        const isContentBoxed = computed(() => appStore.isContentBoxed)
        const menuTheme = computed(() => appStore.menuTheme)
        const toolbarTheme = computed(() => appStore.toolbarTheme)
        const isToolbarDetached = computed(() => appStore.isToolbarDetached)
        const authenticated = computed(() => authStore.isLoggedIn)
        const user = computed(() => userStore.user)

        const keys = useMagicKeys()

        function routeHome() {
            this.$router.replace({name: 'home'})
        }

        async function signOut() {
            await authStore.signOut()
        }

        onMounted(() => {
            whenever(keys['Ctrl+/'], () => {
                console.log('Shift+Space have been pressed')
            })
        })

        return {
            drawer,
            showSearch,
            navigation,
            product,
            isContentBoxed,
            menuTheme,
            toolbarTheme,
            isToolbarDetached,
            authenticated,
            user,
            signOut,
            routeHome
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
