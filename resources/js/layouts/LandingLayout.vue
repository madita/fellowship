<template>
    <div>
        <v-app-bar app flat height="80">
            <a class="skip-nav-link" href="#main-content">
                skip navigation
            </a>
            <v-container class="py-0 px-0 px-sm-2 fill-height d-flex align-center">
                <router-link to="/dashboard" class="d-flex align-center text-decoration-none mr-2">
                    <img :src="appLogo" :alt="`Logo of ${appName}`" height="70"/>
                </router-link>

                <v-spacer></v-spacer>


<!--                <div class="d-none d-md-block">-->
                <div class="d-flex align-center">
                    <v-btn class="mx-1" @click="$helpers.scrollTo('#feature1');">
                        Feature 1
                    </v-btn>
                    <toolbar-language v-if="languageChangeEnabled" class="mx-1"/>
                    <v-btn
                        icon
                        variant="text"
                        class="mx-1"
                        @click="toggleTheme"
                        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                    >
                        <v-icon>{{ isDark ? 'mdi-weather-sunny' : 'mdi-weather-night' }}</v-icon>
                    </v-btn>
                    <template v-if="!authenticated">
                        <v-btn class="mx-1" to="/auth/signin">
                            Sign In
                        </v-btn>

                        <v-btn variant="outlined" size="large" to="/auth/signup">
                            Sign Up
                        </v-btn>
                    </template>

                    <template v-else>

                            <div :class="[$vuetify.rtl ? 'ml-1' : 'mr-1']">
                                <toolbar-notifications/>
                            </div>
                            <div :class="[$vuetify.rtl ? 'ml-1' : 'mr-1']">
                                <conversations-notification/>
                            </div>
                            <v-btn icon variant="text" class="mx-1" @click="showUsersDrawer = !showUsersDrawer" :title="$t ? $t('toolbar.users') : 'Users'">
                                <v-icon>mdi-account-group</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" class="mx-1" @click="showSettingsDrawer = !showSettingsDrawer" :title="$t ? $t('toolbar.settings') : 'Settings'">
                                <v-icon>mdi-cog</v-icon>
                            </v-btn>
                            <toolbar-user/>

                    </template>

                </div>



            </v-container>
        </v-app-bar>

        <v-main id="main-content">
            <!-- Maintenance Mode Banner for Admins -->
            <v-alert
                v-if="maintenanceMode && authenticated && isAdmin"
                type="warning"
                variant="tonal"
                class="ma-0 rounded-0"
                density="comfortable"
            >
                <div class="d-flex align-center justify-space-between flex-wrap">
                    <div class="d-flex align-center">
                        <v-icon class="mr-2">mdi-wrench</v-icon>
                        <div>
                            <strong>Maintenance Mode Active</strong>
                            <div class="text-caption">Non-admin users cannot access the site.</div>
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
                        Manage Settings
                    </v-btn>
                </div>
            </v-alert>

            <router-view :key="$route.fullPath"></router-view>
            <v-container></v-container>
            <!-- Custom Footer (if enabled) -->
            <div v-if="customFooterEnabled && customFooterHtml" v-html="processedFooterHtml"></div>

            <!-- Default Footer -->
            <v-footer v-else color="transparent">
                <v-container class="py-5">
                    <v-row>
                        <v-col cols="12" md="4">
                            <div class="text-h6 text-lg-h5 font-weight-bold">Navigation</div>
                            <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"/>
                            <div class="d-flex flex-wrap">
                                <div v-for="(link, i) in links" :key="i" class="w-half body-1 mb-1">
                                    <router-link
                                        v-if="link.to"
                                        class="text-decoration-none text-primary"
                                        :to="link.to"
                                    >{{ link.label }}
                                    </router-link>
                                    <a
                                        v-else
                                        class="text-decoration-none text-primary"
                                        :href="link.href"
                                        :target="link.target || 'blank'"
                                    >{{ link.label }}</a>
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-h6 text-lg-h5 font-weight-bold">Contact Information</div>
                            <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"/>
                            <div v-if="contactAddress" class="d-flex mb-2 font-weight-bold">
                                <v-icon color="primary lighten-1" class="mr-2">mdi-map-marker-outline</v-icon>
                                {{ contactAddress }}
                            </div>
                            <div v-if="contactPhone" class="d-flex mb-2">
                                <v-icon color="primary lighten-1" class="mr-2">mdi-phone-outline</v-icon>
                                <a :href="`tel:${contactPhone}`" class="text-decoration-none text-primary">{{ contactPhone }}</a>
                            </div>
                            <div v-if="contactEmail" class="d-flex mb-2">
                                <v-icon color="primary lighten-1" class="mr-2">mdi-email-outline</v-icon>
                                <a :href="`mailto:${contactEmail}`" class="text-decoration-none text-primary">{{ contactEmail }}</a>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-h6 text-lg-h5 font-weight-bold">Newsletter</div>
                            <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"/>
                            <div class="d-flex flex-column flex-lg-row w-full">
                                <v-text-field
                                    variant="outlined"
                                    label="Your email"
                                    dense
                                    height="44"
                                    class="mr-lg-2"
                                ></v-text-field>
                                <v-btn size="large" color="primary">Subscribe</v-btn>
                            </div>
                            <div v-if="socialTwitter || socialFacebook || socialInstagram" class="text-center text-md-right mt-4 mt-lg-2">
                                Connect
                                <v-btn v-if="socialTwitter" :href="socialTwitter" target="_blank" fab small color="primary" class="ml-2">
                                    <v-icon>mdi-twitter</v-icon>
                                </v-btn>
                                <v-btn v-if="socialFacebook" :href="socialFacebook" target="_blank" fab small color="primary" class="ml-2">
                                    <v-icon>mdi-facebook</v-icon>
                                </v-btn>
                                <v-btn v-if="socialInstagram" :href="socialInstagram" target="_blank" fab small color="primary" class="ml-2">
                                    <v-icon>mdi-instagram</v-icon>
                                </v-btn>
                            </div>
                        </v-col>
                    </v-row>
                    <v-divider class="my-3"></v-divider>
                    <div class="text-center caption">
                        {{ appCopyright }}
                    </div>
                </v-container>
            </v-footer>

            <v-navigation-drawer
                v-model="showUsersDrawer"
                location="right"
                temporary
                width="320"
                class="elevation-2"
            >
                <SidebarUsers/>
            </v-navigation-drawer>

            <v-navigation-drawer
                v-model="showSettingsDrawer"
                location="right"
                temporary
                width="360"
                class="elevation-2"
            >
                <UserSettingsSidebar/>
            </v-navigation-drawer>

            <conversation-box-manager />
        </v-main>
    </div>
</template>

<script>
import { useTheme } from 'vuetify'
import config from '../configs'
import logoimg from '@/assets/images/logo.png';
import {useAuthStore} from "@/store/authStore.js";
import {useUserStore} from "@/store/userStore.js";
import {useSettingsStore} from "@/store/settingStore.js";
import ToolbarUser from '../components/toolbar/ToolbarUser.vue'
import ToolbarApps from '../components/toolbar/ToolbarApps.vue'
import ToolbarLanguage from '../components/toolbar/ToolbarLanguage.vue'
import ToolbarNotifications from '../components/toolbar/ToolbarNotifications.vue'
import ConversationsNotification from '../components/conversation/ConversationsNotification.vue'
import ConversationBoxManager from '../components/conversation/ConversationBoxManager.vue'
import SidebarUsers from '../components/conversation/SidebarUsers.vue'
import UserSettingsSidebar from '../components/settings/UserSettingsSidebar.vue'


// import {mapActions, mapGetters} from 'vuex'

export default {
    components: {
        ToolbarUser,
        ToolbarLanguage,
        ToolbarNotifications,
        ConversationsNotification,
        ConversationBoxManager,
        SidebarUsers,
        UserSettingsSidebar
    },
    setup() {
        const theme = useTheme()
        return {
            theme
        }
    },
    data() {
        return {
            logoimg,
            config,
            showUsersDrawer: false,
            showSettingsDrawer: false,
            links: [{
                label: 'Overview',
                to: '#'
            }, {
                label: 'Features',
                to: '#'
            }, {
                label: 'Documentation',
                to: '#'
            }, {
                label: 'News',
                to: '#'
            }, {
                label: 'FAQ',
                to: '#'
            }, {
                label: 'About us',
                to: '#'
            }, {
                label: 'Carrers',
                to: '#'
            }, {
                label: 'Press',
                to: '#'
            }]
        }
    },
    methods: {
        async signOut() {
            const auth = useAuthStore()
            await auth.logout()
        }
    },

    computed: {
        authenticated() {
            const authStore = useAuthStore();
            // console.log('landingauthstore',authStore)
            return authStore.isLoggedIn ;
        },
        user() {
            const userStore = useUserStore();
            return userStore.user;
        },
        appLogo() {
            const settingsStore = useSettingsStore();
            const logoLight = settingsStore.logoLight;
            const logoDark = settingsStore.logoDark;

            // Switch logo based on current theme
            if (this.isDark) {
                // Dark theme: prefer dark logo, fallback to light logo
                if (logoDark) {
                    return `/storage/${logoDark}`;
                } else if (logoLight) {
                    return `/storage/${logoLight}`;
                }
            } else {
                // Light theme: prefer light logo, fallback to dark logo
                if (logoLight) {
                    return `/storage/${logoLight}`;
                } else if (logoDark) {
                    return `/storage/${logoDark}`;
                }
            }

            // Fallback to default logo
            return this.logoimg;
        },
        appName() {
            const settingsStore = useSettingsStore();
            return settingsStore.appName;
        },
        appCopyright() {
            const settingsStore = useSettingsStore();
            return settingsStore.appCopyright;
        },
        languageChangeEnabled() {
            const settingsStore = useSettingsStore();
            return settingsStore.languageChangeEnabled;
        },
        contactAddress() {
            const settingsStore = useSettingsStore();
            return settingsStore.contactAddress;
        },
        contactPhone() {
            const settingsStore = useSettingsStore();
            return settingsStore.contactPhone;
        },
        contactEmail() {
            const settingsStore = useSettingsStore();
            return settingsStore.contactEmail;
        },
        socialTwitter() {
            const settingsStore = useSettingsStore();
            return settingsStore.socialTwitter;
        },
        socialFacebook() {
            const settingsStore = useSettingsStore();
            return settingsStore.socialFacebook;
        },
        socialInstagram() {
            const settingsStore = useSettingsStore();
            return settingsStore.socialInstagram;
        },
        customFooterEnabled() {
            const settingsStore = useSettingsStore();
            return settingsStore.customFooterEnabled;
        },
        customFooterHtml() {
            const settingsStore = useSettingsStore();
            return settingsStore.customFooterHtml;
        },
        isDark() {
            return this.theme.global.name.value === 'dark';
        },
        maintenanceMode() {
            const settingsStore = useSettingsStore();
            return settingsStore.maintenanceMode;
        },
        isAdmin() {
            const userStore = useUserStore();
            const user = userStore.user;
            return user && (
                user.isAdmin === true ||
                user.roles?.some(role => role.name === 'admin')
            );
        },
        processedFooterHtml() {
            if (!this.customFooterHtml) return '';

            // Replace template variables with actual values
            let html = this.customFooterHtml;

            const replacements = {
                '{{appName}}': this.appName || '',
                '{{appCopyright}}': this.appCopyright || '',
                '{{contactEmail}}': this.contactEmail || '',
                '{{contactPhone}}': this.contactPhone || '',
                '{{contactAddress}}': this.contactAddress || '',
                '{{socialTwitter}}': this.socialTwitter || '',
                '{{socialFacebook}}': this.socialFacebook || '',
                '{{socialInstagram}}': this.socialInstagram || '',
            };

            // Replace all variables in the HTML
            Object.keys(replacements).forEach(key => {
                const regex = new RegExp(key.replace(/[{}]/g, '\\$&'), 'g');
                html = html.replace(regex, replacements[key]);
            });

            // Handle v-if conditions by removing elements with empty values
            // This is a simple implementation - more complex logic may be needed
            html = html.replace(/v-if="([^"]*?)"/g, (match, condition) => {
                // Check if the condition references an empty value
                const isEmpty = Object.keys(replacements).some(key => {
                    return condition.includes(key) && !replacements[key];
                });
                return isEmpty ? 'style="display: none;"' : '';
            });

            return html;
        },
    },

    methods: {
        applyThemeSettings() {
            const settingsStore = useSettingsStore()
            const themeMode = settingsStore.themeMode

            if (themeMode === 'light') {
                this.theme.global.name.value = 'light'
            } else if (themeMode === 'dark') {
                this.theme.global.name.value = 'dark'
            } else if (themeMode === 'system') {
                // Detect system preference
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                this.theme.global.name.value = prefersDark ? 'dark' : 'light'
            }
        },
        toggleTheme() {
            this.theme.global.name.value = this.theme.global.name.value === 'dark' ? 'light' : 'dark';
        }
    },
    async mounted() {
        // console.log('test',this.authenticated)
        const settingsStore = useSettingsStore();
        if (!settingsStore.settingsLoaded) {
            await settingsStore.fetchAppSettings();
        }

        // Apply theme settings after settings are loaded
        this.applyThemeSettings()
    }
}
</script>

<style scoped>
/*skip to main content*/
.skip-nav-link {
    transform: translateY(-200%);
    transition: transform 325ms ease-in;
}

.skip-nav-link:focus {
    transform: translateY(-60%);
}

/* Ensure main content area has minimum viewport height */
:deep(.v-main) {
    min-height: 100vh;
}
</style>
