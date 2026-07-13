<template>
    <div class="d-flex flex-column" style="min-height: 100vh;">
        <!-- Navigation -->
        <v-navigation-drawer
            v-model="drawer"
            :temporary="$vuetify.display.mobile"
            :permanent="!$vuetify.display.mobile"
            class="elevation-1"
            :light="menuTheme === 'light'"
            :dark="menuTheme === 'dark'"
        >
            <a class="skip-nav-link" href="#main-content">
                {{ $t('layout.skipNavigation') }}
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
            <v-card class="flex-grow-1 d-flex"
                    :class="[isToolbarDetached ? 'pa-1 mt-3 mx-1' : 'pa-0 ma-0']"
                    :flat="!isToolbarDetached">
                <div class="d-flex flex-grow-1 align-center">

                    <!-- search input mobile -->
                    <v-text-field
                        v-if="showSearch"
                        append-icon="mdi-close"
                        :placeholder="$t('layout.search')"
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
                            class="mx-1 d-none d-md-block"
                            :placeholder="$t('menu.search')"
                            prepend-inner-icon="mdi-magnify"
                            hide-details
                            filled
                            rounded
                            dense
                        ></v-text-field>

                        <v-spacer class="d-block d-sm-none"></v-spacer>

                        <v-btn class="d-flex d-md-none" icon @click="showSearch = true">
                            <v-icon>mdi-magnify</v-icon>
                        </v-btn>

                        <mega-menu />
                        <toolbar-language v-if="languageChangeEnabled" class="d-none d-sm-block"/>

<!--                                                <toolbar-apps/>-->

                        <template v-if="authenticated">
                            <div class="mr-1">
                                <toolbar-notifications/>
                            </div>
                            <div v-if="sandboxEnabled" class="mr-1">
                                <sandbox-notifications/>
                            </div>
                            <div class="mr-1">
                                <conversations-notification/>
                            </div>
                            <v-btn icon variant="text" class="mx-1 d-none d-md-flex" @click="showUsersDrawer = !showUsersDrawer" :title="$t ? $t('toolbar.users') : 'Users'">
                                <v-icon>mdi-account-group</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" class="mx-1 d-none d-md-flex" @click="showSettingsDrawer = !showSettingsDrawer" :title="$t ? $t('toolbar.settings') : 'Settings'">
                                <v-icon>mdi-cog</v-icon>
                            </v-btn>
                            <toolbar-user/>
                        </template>
                        <template v-else>
                            <v-btn class="mx-1 d-none d-sm-flex" to="/auth/signin">
                                {{ $t('layout.signIn') }}
                            </v-btn>
                            <v-btn icon class="mx-1 d-flex d-sm-none" to="/auth/signin" :title="$t('layout.signIn')">
                                <v-icon>mdi-login</v-icon>
                            </v-btn>
                        </template>

                    </div>
                </div>
            </v-card>
        </v-app-bar>

        <v-main id="main-content" class="flex-grow-1">
            <!-- Maintenance Mode Banner for Admins -->
            <v-alert
                v-if="maintenanceMode"
                type="warning"
                variant="tonal"
                class="ma-0 rounded-0"
                density="comfortable"
            >
                <div class="d-flex align-center justify-space-between flex-wrap">
                    <div class="d-flex align-center">
                        <v-icon class="mr-2">mdi-wrench</v-icon>
                        <div>
                            <strong>{{ $t('layout.maintenanceModeActive') }}</strong>
                            <div class="text-caption">{{ $t('layout.nonAdminAccess') }}</div>
                        </div>
                    </div>
                    <v-btn
                        size="small"
                        color="warning"
                        variant="elevated"
                        :to="{ name: 'admin-settings' }"
                        class="mt-2 mt-sm-0"
                    >
                        <v-icon class="mr-1" size="small">mdi-cog</v-icon>
                        {{ $t('layout.manageSettings') }}
                    </v-btn>
                </div>
            </v-alert>

            <v-container class="pa-0" :fluid="!isContentBoxed">
                <v-layout style="min-height: 100vh;" :class="{'px-2 px-sm-4': !isContentBoxed}">
                    <slot></slot>
                </v-layout>
            </v-container>

            <v-navigation-drawer
                v-if="showUsersDrawer"
                v-model="showUsersDrawer"
                location="right"
                temporary
                :width="$vuetify.display.mobile ? '100%' : '320'"
                class="elevation-2"
            >
                <SidebarUsers @close="showUsersDrawer = false"/>
            </v-navigation-drawer>

            <v-navigation-drawer
                v-if="showSettingsDrawer"
                v-model="showSettingsDrawer"
                location="right"
                temporary
                :width="$vuetify.display.mobile ? '100%' : '360'"
                class="elevation-2"
            >
                <UserSettingsSidebar @close="showSettingsDrawer = false"/>
            </v-navigation-drawer>

<!--            <ConversationBox v-if="showChatBox" />-->
            <conversation-box-manager />

        </v-main>

        <v-footer app class="flex-shrink-0">
            <location-menu location="footer" variant="inline" />
            <v-spacer></v-spacer>
            <div class="overline">
                @fellowship
            </div>
        </v-footer>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useTheme, useDisplay } from 'vuetify'
import { useAuthStore } from "@/store/authStore.js";
import { useUserStore } from "@/store/userStore.js";
import { useSettingsStore } from "@/store/settingStore.js";
import { useMenuStore } from "@/store/menuStore.js";
// import { useAppStore } from '@/api/useApi.js'
import {useAppStore} from "@/store/app/index.js"
import { useMagicKeys, whenever } from '@vueuse/core'

import config from '../configs'

import MainMenu from '../components/navigation/MainMenu.vue'
import MegaMenu from '../components/navigation/MegaMenu.vue'
import LocationMenu from '../components/navigation/LocationMenu.vue'
import ToolbarUser from '../components/toolbar/ToolbarUser.vue'
import ToolbarApps from '../components/toolbar/ToolbarApps.vue'
import ToolbarLanguage from '../components/toolbar/ToolbarLanguage.vue'
import ToolbarNotifications from '../components/toolbar/ToolbarNotifications.vue'
import ConversationsNotification from '../components/conversation/ConversationsNotification.vue'
import SandboxNotifications from '../components/sandbox/SandboxNotifications.vue'
import ConversationBox from '../components/conversation/ConversationBox.vue'
import ConversationBoxManager from '../components/conversation/ConversationBoxManager.vue'
import SidebarUsers from '../components/conversation/SidebarUsers.vue'
import UserSettingsSidebar from '../components/settings/UserSettingsSidebar.vue'
import eventBus from '../components/common/eventBus.js'
import { useConversationStore } from '@/store/conversationStore.js'

export default {
    components: {
        MainMenu,
        MegaMenu,
        LocationMenu,
        ToolbarUser,
        ToolbarApps,
        ToolbarLanguage,
        ToolbarNotifications,
        ConversationBox,
        ConversationBoxManager,
        SidebarUsers,
        UserSettingsSidebar,
        ConversationsNotification,
        SandboxNotifications,
    },
    setup() {
        const drawer = ref(true)
        const showSearch = ref(false)
        const navigation = ref(config.navigation)
        const showChatBox = ref(false)
        const showUsersDrawer = ref(false)
        const showSettingsDrawer = ref(false)

        const theme = useTheme()
        const display = useDisplay()
        const appStore = useAppStore()
        const authStore = useAuthStore()
        const userStore = useUserStore()
        const conversationStore = useConversationStore()
        const settingsStore = useSettingsStore()

        const product = computed(() => ({
            name: settingsStore.appName || appStore.product.name,
            version: appStore.product.version
        }))
        const isContentBoxed = computed(() => appStore.isContentBoxed)
        const menuTheme = computed(() => appStore.menuTheme)
        const toolbarTheme = computed(() => appStore.toolbarTheme)
        const isToolbarDetached = computed(() => appStore.isToolbarDetached)
        const authenticated = computed(() => authStore.isLoggedIn)
        const user = computed(() => userStore.user)
        const languageChangeEnabled = computed(() => settingsStore.languageChangeEnabled)
        const maintenanceMode = computed(() => settingsStore.maintenanceMode)
        const sandboxEnabled = computed(() => settingsStore.sandboxEnabled)

        const keys = useMagicKeys()

        function routeHome() {
            this.$router.replace({name: 'home'})
        }

        async function signOut() {
            await authStore.signOut()
        }

        let onChatClose = null
        let onConversationNew = null
        let onSettingsOpen = null
        let presenceChannel = null
        let userPrivateChannelName = null
        onMounted(async () => {
            // Fetch app settings if not already loaded
            if (!settingsStore.settingsLoaded) {
                await settingsStore.fetchAppSettings();
            }

            // Apply theme settings after settings are loaded
            applyThemeSettings()

            whenever(keys['Ctrl+/'], () => {
                console.log('Shift+Space have been pressed')
            })
            // Hide chat box when child component emits close via event bus
            onChatClose = () => { showChatBox.value = false }
            eventBus.on('chat.close', onChatClose)

            // Open chat box when a conversation is initiated from SidebarUsers or elsewhere
            onConversationNew = () => { showChatBox.value = true }
            eventBus.on('conversation.new', onConversationNew)

            // Open settings drawer when clicked from toolbar user menu
            onSettingsOpen = () => { showSettingsDrawer.value = true }
            eventBus.on('toolbar.settings.open', onSettingsOpen)

            // Initialize presence for SidebarUsers if Echo is available
            try {
                if (window.Echo) {
                    presenceChannel = window.Echo.join('chat')
                        .here((users) => {
                            eventBus.emit('users.here', Array.isArray(users) ? users : [])
                        })
                        .joining((user) => {
                            eventBus.emit('users.joined', user)
                            try {
                                const currentUserId = window?.App?.user?.id
                                if (!currentUserId || user?.id === currentUserId) return
                                // Open chat box and seed the conversation with the newly online user
                                showChatBox.value = true
                                eventBus.emit('conversation.new', user)
                            } catch (e) { /* no-op */ }
                        })
                        .leaving((user) => {
                            eventBus.emit('users.left', user)
                        })
                }
            } catch (e) {
                console.warn('Failed to init presence channel for users sidebar:', e)
            }

            // Listen on private user channel to open chat in real time when a conversation is created for this user
            try {
                if (window.Echo && window.App?.user?.id) {
                    const uid = window.App.user.id
                    userPrivateChannelName = `user.${uid}`
                    window.Echo
                        .private(userPrivateChannelName)
                        .listen('Conversations\\ConversationCreated', (e) => {
                            try {
                                const uuid = e?.data?.uuid
                                    ?? e?.conversation?.uuid
                                    ?? e?.conversation_uuid
                                    ?? e?.data?.conversation?.uuid
                                const sender = e?.data?.user?.data
                                    ?? e?.user
                                    ?? e?.sender
                                    ?? null
                                // Open the floating chatbox
                                showChatBox.value = true
                                // Seed the conversation recipient so the header shows correctly
                                if (sender) {
                                    eventBus.emit('conversation.new', sender)
                                }
                                // Proactively load the conversation so messages appear immediately
                                if (uuid) {
                                    conversationStore.fetchConversation(uuid)
                                }
                            } catch (err) {
                                console.warn('Failed to process ConversationCreated event:', err)
                            }
                        })
                }
            } catch (e) {
                console.warn('Failed to subscribe to private user channel:', e)
            }
        })
        onUnmounted(() => {
            if (onChatClose) {
                eventBus.off('chat.close', onChatClose)
            }
            if (onConversationNew) {
                eventBus.off('conversation.new', onConversationNew)
            }
            if (onSettingsOpen) {
                eventBus.off('toolbar.settings.open', onSettingsOpen)
            }
            try {
                if (presenceChannel && window.Echo) {
                    // Leave presence channel
                    window.Echo.leave('chat')
                }
                if (userPrivateChannelName && window.Echo) {
                    window.Echo.leave(userPrivateChannelName)
                }
            } catch (e) {
                // no-op
            }
        })

        const applyThemeSettings = () => {
            const themeMode = settingsStore.themeMode

            if (themeMode === 'light') {
                theme.global.name.value = 'light'
            } else if (themeMode === 'dark') {
                theme.global.name.value = 'dark'
            } else if (themeMode === 'system') {
                // Detect system preference
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                theme.global.name.value = prefersDark ? 'dark' : 'light'
            }
        }

        return {
            drawer,
            showSearch,
            navigation,
            showChatBox,
            showUsersDrawer,
            showSettingsDrawer,
            product,
            isContentBoxed,
            menuTheme,
            toolbarTheme,
            isToolbarDetached,
            authenticated,
            user,
            languageChangeEnabled,
            maintenanceMode,
            sandboxEnabled,
            signOut,
            routeHome,
            applyThemeSettings
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
