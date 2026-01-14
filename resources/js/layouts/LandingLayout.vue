<template>
    <div>
        <v-app-bar app flat :height="$vuetify.display.mobile ? 56 : 80">
            <a class="skip-nav-link" href="#main-content">
                skip navigation
            </a>
            <v-container class="py-0 px-2 px-sm-3 fill-height d-flex align-center">
                <router-link to="/dashboard" class="text-decoration-none logo-container">
                    <div class="d-flex flex-column align-center">
                        <img :src="appLogo" :alt="`Logo of ${appName}`" class="logo-responsive"/>
                        <div v-if="siteTagline" class="tagline-text">{{ siteTagline }}</div>
                    </div>
                </router-link>

                <v-spacer></v-spacer>

                <div class="toolbar-actions d-flex align-center">
                    <!-- Dynamic homepage menu -->
                    <template v-if="menuItems && menuItems.length > 0">
                        <v-btn
                            v-for="item in menuItems"
                            :key="item.id"
                            class="d-none d-md-flex"
                            size="small"
                            @click="scrollToSection(item.anchor_target)"
                        >
                            {{ item.label }}
                        </v-btn>
                    </template>
<!--                    <v-btn icon size="small" class="d-flex d-md-none" @click="showMobileMenu = true">-->
<!--                        <v-icon>mdi-menu</v-icon>-->
<!--                    </v-btn>-->
                    <!-- Language Switcher -->
                    <toolbar-language v-if="languageChangeEnabled" class="d-none d-sm-flex"/>
                    <v-btn
                        icon
                        variant="text"
                        size="small"
                        @click="toggleTheme"
                        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                    >
                        <v-icon>{{ isDark ? 'mdi-weather-sunny' : 'mdi-weather-night' }}</v-icon>
                    </v-btn>
                    <template v-if="!authenticated">
                        <v-btn class="d-none d-sm-flex" size="small" to="/auth/signin">
                            Sign In
                        </v-btn>
                        <v-btn icon size="small" class="d-flex d-sm-none" to="/auth/signin" :title="$t ? $t('auth.signin') : 'Sign In'">
                            <v-icon>mdi-login</v-icon>
                        </v-btn>

                        <v-btn variant="outlined" class="d-none d-sm-flex" to="/auth/signup">
                            Sign Up
                        </v-btn>
                        <v-btn icon size="small" variant="outlined" class="d-flex d-sm-none" to="/auth/signup" :title="$t ? $t('auth.signup') : 'Sign Up'">
                            <v-icon>mdi-account-plus</v-icon>
                        </v-btn>
                    </template>

                    <template v-else>
                            <toolbar-notifications/>
                            <conversations-notification/>
                            <v-btn icon variant="text" size="small" class="d-none d-md-flex" @click="showUsersDrawer = !showUsersDrawer" :title="$t ? $t('toolbar.users') : 'Users'">
                                <v-icon>mdi-account-group</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="small" class="d-none d-md-flex" @click="showSettingsDrawer = !showSettingsDrawer" :title="$t ? $t('toolbar.settings') : 'Settings'">
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
                class="ma-0 rounded-0 px-3 px-sm-4"
                density="comfortable"
            >
                <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                    <div class="d-flex align-center">
                        <v-icon class="mr-2" size="small">mdi-wrench</v-icon>
                        <div>
                            <strong class="text-body-2 text-sm-body-1">Maintenance Mode Active</strong>
                            <div class="text-caption d-none d-sm-block">Non-admin users cannot access the site.</div>
                        </div>
                    </div>
                    <v-btn
                        size="small"
                        color="warning"
                        variant="elevated"
                        :to="{ name: 'admin-settings' }"
                        class="flex-shrink-0"
                    >
                        <v-icon class="mr-1" size="small">mdi-cog</v-icon>
                        <span class="d-none d-sm-inline">Manage </span>Settings
                    </v-btn>
                </div>
            </v-alert>

            <router-view :key="$route.fullPath"></router-view>
            <v-container></v-container>
            <!-- Custom Footer (if enabled) -->
            <div v-if="customFooterEnabled && customFooterHtml" v-html="processedFooterHtml"></div>

            <!-- Default Footer -->
            <v-footer v-else color="transparent">
                <v-container class="py-3 py-sm-5 px-3 px-sm-4">
                    <v-row>
                        <v-col v-if="showNavigationSection" cols="12" md="4" class="mb-4 mb-md-0">
                            <div class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold">Navigation</div>
                            <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>
                            <div class="d-flex flex-column">
                                <div v-for="(link, i) in links" :key="i" class="text-body-2 text-sm-body-1 mb-2">
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
                                        :target="link.target || '_blank'"
                                    >{{ link.label }}</a>
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4" class="mb-4 mb-md-0">
                            <div class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold">Contact Information</div>
                            <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>
                            <div v-if="contactAddress" class="d-flex align-start mb-2 font-weight-bold text-body-2 text-sm-body-1">
                                <v-icon color="primary lighten-1" class="mr-2 flex-shrink-0" size="small">mdi-map-marker-outline</v-icon>
                                <span>{{ contactAddress }}</span>
                            </div>
                            <div v-if="contactPhone" class="d-flex align-center mb-2 text-body-2 text-sm-body-1">
                                <v-icon color="primary lighten-1" class="mr-2 flex-shrink-0" size="small">mdi-phone-outline</v-icon>
                                <a :href="`tel:${contactPhone}`" class="text-decoration-none text-primary">{{ contactPhone }}</a>
                            </div>
                            <div v-if="contactEmail" class="d-flex align-center mb-2 text-body-2 text-sm-body-1">
                                <v-icon color="primary lighten-1" class="mr-2 flex-shrink-0" size="small">mdi-email-outline</v-icon>
                                <a :href="`mailto:${contactEmail}`" class="text-decoration-none text-primary text-truncate">{{ contactEmail }}</a>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4" class="mb-4 mb-md-0">
                            <div class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold">Newsletter</div>
                            <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>
                            <div class="d-flex flex-column flex-sm-row w-full">
                                <v-text-field
                                    variant="outlined"
                                    label="Your email"
                                    density="compact"
                                    class="mr-sm-2 mb-2 mb-sm-0"
                                ></v-text-field>
                                <v-btn color="primary" class="flex-shrink-0">Subscribe</v-btn>
                            </div>
                            <div v-if="socialTwitter || socialFacebook || socialInstagram" class="text-center text-md-right mt-4 mt-lg-2 text-body-2">
                                <span class="mr-2">Connect</span>
                                <v-btn v-if="socialTwitter" :href="socialTwitter" target="_blank" icon size="small" color="primary" class="ml-1">
                                    <v-icon size="small">mdi-twitter</v-icon>
                                </v-btn>
                                <v-btn v-if="socialFacebook" :href="socialFacebook" target="_blank" icon size="small" color="primary" class="ml-1">
                                    <v-icon size="small">mdi-facebook</v-icon>
                                </v-btn>
                                <v-btn v-if="socialInstagram" :href="socialInstagram" target="_blank" icon size="small" color="primary" class="ml-1">
                                    <v-icon size="small">mdi-instagram</v-icon>
                                </v-btn>
                            </div>
                        </v-col>
                    </v-row>
                    <v-divider class="my-2 my-sm-3"></v-divider>
                    <div class="text-center text-caption text-sm-body-2">
                        {{ appCopyright }}
                    </div>
                </v-container>
            </v-footer>

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
import {useHomepageStore} from "@/store/homepageStore.js";
import ToolbarUser from '../components/toolbar/ToolbarUser.vue'
import ToolbarApps from '../components/toolbar/ToolbarApps.vue'
import ToolbarLanguage from '../components/toolbar/ToolbarLanguage.vue'
import ToolbarNotifications from '../components/toolbar/ToolbarNotifications.vue'
import ConversationsNotification from '../components/conversation/ConversationsNotification.vue'
import ConversationBoxManager from '../components/conversation/ConversationBoxManager.vue'
import SidebarUsers from '../components/conversation/SidebarUsers.vue'
import UserSettingsSidebar from '../components/settings/UserSettingsSidebar.vue'
import eventBus from '../components/common/eventBus.js'


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
            showSettingsDrawer: false
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
        siteTagline() {
            const settingsStore = useSettingsStore();
            return settingsStore.siteTagline;
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
                'appName': this.appName || '',
                'appCopyright': this.appCopyright || '',
                'contactEmail': this.contactEmail || '',
                'contactPhone': this.contactPhone || '',
                'contactAddress': this.contactAddress || '',
                'socialTwitter': this.socialTwitter || '',
                'socialFacebook': this.socialFacebook || '',
                'socialInstagram': this.socialInstagram || '',
            };

            // Replace all {{variable}} patterns
            Object.keys(replacements).forEach(key => {
                const pattern = `{{${key}}}`;
                const regex = new RegExp(pattern.replace(/[{}]/g, '\\$&'), 'g');
                html = html.replace(regex, replacements[key]);
            });

            // Handle data-if attributes - hide elements if the referenced value is empty
            html = html.replace(/data-if="([^"]+)"/g, (match, varName) => {
                const isEmpty = !replacements[varName] || replacements[varName].trim() === '';
                return isEmpty ? 'style="display: none;"' : '';
            });

            return html;
        },
        menuItems() {
            const homepageStore = useHomepageStore();
            return homepageStore.activeMenuItems;
        },
        links() {
            const settingsStore = useSettingsStore();
            const quicklinks = settingsStore.appSettings.footer_quicklinks;

            try {
                if (quicklinks) {
                    const parsed = typeof quicklinks === 'string' ? JSON.parse(quicklinks) : quicklinks;
                    if (Array.isArray(parsed)) {
                        // Filter links based on authentication status and authOnly property
                        const filteredLinks = parsed.filter(link => {
                            // If link requires auth and user is not authenticated, hide it
                            if (link.authOnly && !this.authenticated) {
                                return false;
                            }
                            return true;
                        });

                        return filteredLinks.map(link => ({
                            label: link.label,
                            to: link.external ? undefined : link.url,
                            href: link.external ? link.url : undefined,
                            target: link.external ? '_blank' : undefined
                        }));
                    }
                    return [];
                }
            } catch (e) {
                console.error('Failed to parse footer quicklinks:', e);
            }

            // Return default links if no custom quicklinks are set
            return [
                { label: 'Home', to: '/' },
                { label: 'Features', to: '/features' },
                { label: 'About', to: '/about' },
                { label: 'Contact', to: '/contact' }
            ];
        },
        showNavigationSection() {
            // Show navigation section if there are links to display
            return this.links && this.links.length > 0;
        },
    },

    methods: {
        async signOut() {
            const auth = useAuthStore()
            await auth.logout()
        },
        scrollToSection(anchorId) {
            // Ensure anchor ID has # prefix
            const targetId = anchorId.startsWith('#') ? anchorId : `#${anchorId}`;
            const element = document.querySelector(targetId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                // Fallback to using the helper if available
                if (this.$helpers && this.$helpers.scrollTo) {
                    this.$helpers.scrollTo(targetId);
                }
            }
        },
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
        },
        openSettingsDrawer() {
            this.showSettingsDrawer = true
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

        // Fetch homepage menu items
        const homepageStore = useHomepageStore();
        try {
            await homepageStore.fetchPublicMenu();
        } catch (error) {
            console.error('Failed to load homepage menu:', error);
        }

        // Listen for settings drawer open event from toolbar user menu
        eventBus.on('toolbar.settings.open', this.openSettingsDrawer)
    },
    beforeUnmount() {
        eventBus.off('toolbar.settings.open', this.openSettingsDrawer)
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

/* Logo container and responsive logo */
.logo-container {
    flex-shrink: 0;
    height: 100%;
    display: flex;
    align-items: center;
}

.logo-responsive {
    height: 70px;
    max-height: 100%;
    width: auto;
    max-width: 180px;
    object-fit: contain;
}

.tagline-text {
    font-size: 0.75rem;
    font-weight: 500;
    color: rgba(var(--v-theme-on-surface), 0.7);
    margin-top: 2px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

/* Toolbar actions */
.toolbar-actions {
    flex-wrap: nowrap;
    gap: 2px;
}

/* Mobile specific styles */
@media (max-width: 600px) {
    .logo-responsive {
        height: 48px;
        max-width: 100px;
    }

    .tagline-text {
        font-size: 0.625rem;
        max-width: 100px;
        margin-top: 1px;
    }

    :deep(.v-app-bar) {
        overflow-x: hidden;
    }

    :deep(.v-toolbar__content) {
        padding-left: 4px !important;
        padding-right: 4px !important;
    }

    .toolbar-actions {
        gap: 0;
    }

    .toolbar-actions :deep(.v-btn) {
        min-width: 36px !important;
        padding: 0 4px !important;
    }

    .toolbar-actions :deep(.v-btn:not(.v-btn--icon)) {
        padding: 0 8px !important;
    }
}

/* Tablet specific styles */
@media (min-width: 600px) and (max-width: 960px) {
    .logo-responsive {
        height: 60px;
        max-width: 150px;
    }

    .tagline-text {
        font-size: 0.7rem;
        max-width: 150px;
    }

    .toolbar-actions {
        gap: 4px;
    }
}

/* Responsive footer text */
@media (max-width: 960px) {
    .w-half {
        width: 100% !important;
    }
}

/* Additional mobile footer styles */
@media (max-width: 600px) {
    :deep(.v-footer) .text-truncate {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
</style>
