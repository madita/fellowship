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
                            <sandbox-notifications v-if="sandboxEnabled"/>
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

            <!-- Default Footer with Widgets -->
            <v-footer v-else color="transparent">
                <v-container class="py-3 py-sm-5 px-3 px-sm-4">
                    <!-- Section-based footer -->
                    <div v-if="footerSections.length > 0">
                        <v-row v-for="section in footerSections" :key="section.id" class="mb-4">
                            <v-col
                                v-for="columnNumber in getColumnCount(section.layout)"
                                :key="columnNumber"
                                cols="12"
                                :md="getColumnWidth(section.layout, columnNumber)"
                                class="mb-4 mb-md-0"
                            >
                                <div
                                    v-for="widget in getWidgetsInColumn(section, columnNumber)"
                                    :key="widget.id"
                                    class="mb-3"
                                >
                                    <footer-widget-renderer :widget="widget" />
                                </div>
                            </v-col>
                        </v-row>
                    </div>

                    <!-- Flat widgets (no sections) -->
                    <v-row v-else-if="footerWidgets.length > 0">
                        <v-col
                            v-for="widget in footerWidgets"
                            :key="widget.id"
                            cols="12"
                            :md="getColumnSize(footerWidgets.length)"
                            class="mb-4 mb-md-0"
                        >
                            <footer-widget-renderer :widget="widget" />
                        </v-col>
                    </v-row>

                    <!-- No widgets -->
                    <v-row v-else>
                        <v-col cols="12" class="text-center text-caption text-medium-emphasis">
                            No footer widgets configured. Go to Settings → Footer to add widgets.
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
import {useFooterStore} from "@/store/footerStore.js";
import ToolbarUser from '../components/toolbar/ToolbarUser.vue'
import ToolbarApps from '../components/toolbar/ToolbarApps.vue'
import ToolbarLanguage from '../components/toolbar/ToolbarLanguage.vue'
import ToolbarNotifications from '../components/toolbar/ToolbarNotifications.vue'
import ConversationsNotification from '../components/conversation/ConversationsNotification.vue'
import SandboxNotifications from '../components/sandbox/SandboxNotifications.vue'
import ConversationBoxManager from '../components/conversation/ConversationBoxManager.vue'
import SidebarUsers from '../components/conversation/SidebarUsers.vue'
import UserSettingsSidebar from '../components/settings/UserSettingsSidebar.vue'
import FooterWidgetRenderer from '../components/footer/FooterWidgetRenderer.vue'
import eventBus from '../components/common/eventBus.js'


// import {mapActions, mapGetters} from 'vuex'

export default {
    components: {
        ToolbarUser,
        ToolbarLanguage,
        ToolbarNotifications,
        ConversationsNotification,
        SandboxNotifications,
        ConversationBoxManager,
        SidebarUsers,
        UserSettingsSidebar,
        FooterWidgetRenderer
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
        sandboxEnabled() {
            const settingsStore = useSettingsStore();
            return settingsStore.sandboxEnabled;
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

            // Handle data-if-auth - show only to authenticated users
            html = html.replace(/data-if-auth/g, () => {
                return this.authenticated ? '' : 'style="display: none;"';
            });

            // Handle data-if-guest - show only to non-authenticated users
            html = html.replace(/data-if-guest/g, () => {
                return !this.authenticated ? '' : 'style="display: none;"';
            });

            return html;
        },
        menuItems() {
            const homepageStore = useHomepageStore();
            return homepageStore.activeMenuItems;
        },
        footerWidgets() {
            try {
                const footerStore = useFooterStore();
                return footerStore.activeWidgets || [];
            } catch (error) {
                console.error('Error getting footer widgets:', error);
                return [];
            }
        },
        footerSections() {
            try {
                const footerStore = useFooterStore();
                return footerStore.activeSections || [];
            } catch (error) {
                console.error('Error getting footer sections:', error);
                return [];
            }
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
        getColumnSize(count) {
            // Calculate responsive column size based on widget count
            if (count === 1) return 12;
            if (count === 2) return 6;
            if (count === 3) return 4;
            if (count >= 4) return 3;
            return 4; // default
        },
        getColumnCount(layout) {
            // Return number of columns for this layout
            const columnMap = {
                '1-col': 1,
                '2-col': 2,
                '3-col': 3,
                '4-col': 4,
            };
            return columnMap[layout] || 1;
        },
        getColumnWidth(layout, columnNumber) {
            // Get column width based on section layout
            const layoutMap = {
                '1-col': [12],
                '2-col': [6, 6],
                '3-col': [4, 4, 4],
                '4-col': [3, 3, 3, 3],
            };

            const widths = layoutMap[layout] || [12];
            return widths[columnNumber - 1] || 12;
        },
        getWidgetsInColumn(section, columnNumber) {
            // Get all widgets that belong to this column, sorted by order
            if (!section.widgets || !Array.isArray(section.widgets)) {
                return [];
            }

            return section.widgets
                .filter(widget => {
                    // Convert both to numbers for comparison
                    return Number(widget.column) === Number(columnNumber);
                })
                .sort((a, b) => (a.order || 0) - (b.order || 0));
        },
        getSectionColumnWidth(layout, widgetCount, widgetIndex) {
            // Legacy function - kept for compatibility
            // Get column width based on section layout
            const layoutMap = {
                '1-col': [12],
                '2-col': [6, 6],
                '3-col': [4, 4, 4],
                '4-col': [3, 3, 3, 3],
            };

            const widths = layoutMap[layout] || [12];

            // If more widgets than columns, distribute evenly
            if (widgetIndex >= widths.length) {
                return Math.floor(12 / widgetCount);
            }

            return widths[widgetIndex];
        },
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

        // Fetch footer widgets and sections (public API)
        const footerStore = useFooterStore();
        if (!footerStore.sectionsLoaded) {
            try {
                await footerStore.fetchPublicSections();
            } catch (error) {
                console.error('Failed to load footer sections:', error);
            }
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
