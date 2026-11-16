<template>
    <!-- Desktop Header -->
    <v-app-bar
        v-if="!mobile"
        :elevation="scrolled ? 4 : 0"
        :color="scrolled || !transparent ? 'surface' : 'transparent'"
        flat
        density="comfortable"
    >
        <router-link to="/" class="d-inline-flex align-center text-decoration-none mx-4">
            <v-img
                v-if="logoSrc"
                :src="logoSrc"
                max-height="36"
                max-width="160"
                contain
            />
            <span v-else class="text-h6 font-weight-medium text-on-surface">
                {{ appName }}
            </span>
        </router-link>

        <v-spacer />

        <nav class="d-flex align-center ga-1">
            <template v-for="item in items" :key="item.id">
                <!-- Dropdown -->
                <v-menu
                    v-if="item.children && item.children.length"
                    offset-y
                    transition="slide-y-transition"
                >
                    <template v-slot:activator="{ props: activatorProps }">
                        <v-btn v-bind="activatorProps" variant="text">
                            <v-icon v-if="item.icon" start size="small">{{ item.icon }}</v-icon>
                            {{ item.label }}
                            <v-icon end size="small">mdi-chevron-down</v-icon>
                        </v-btn>
                    </template>

                    <v-list density="compact">
                        <v-list-item
                            v-for="child in item.children"
                            :key="child.id"
                            :to="child.type !== 'external' ? child.href : undefined"
                            :href="child.type === 'external' ? child.href : undefined"
                            :target="child.target"
                            :active="isActive(child.href)"
                        >
                            <template v-slot:prepend>
                                <v-icon v-if="isActive(child.href)">mdi-check</v-icon>
                                <v-icon v-else-if="child.icon">{{ child.icon }}</v-icon>
                                <div v-else style="width: 24px;"></div>
                            </template>
                            <v-list-item-title>{{ child.label }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>

                <!-- Flat link -->
                <v-btn
                    v-else
                    :to="item.type !== 'external' ? item.href : undefined"
                    :href="item.type === 'external' ? item.href : undefined"
                    :target="item.target"
                    :active="isActive(item.href)"
                    variant="text"
                >
                    <v-icon v-if="item.icon" start size="small">{{ item.icon }}</v-icon>
                    {{ item.label }}
                </v-btn>
            </template>
        </nav>

        <!-- Guest actions -->
        <template v-if="!authenticated">
            <v-divider vertical class="mx-3" />
            <v-btn to="/auth/signin" variant="text">{{ $t('auth.signin') }}</v-btn>
            <v-btn to="/auth/signup" color="primary" variant="tonal" class="mx-2">
                {{ $t('auth.signup') }}
            </v-btn>
        </template>

        <!-- User menu -->
        <v-menu
            v-else
            offset-y
            left
            transition="slide-y-transition"
        >
            <template v-slot:activator="{ props: activatorProps }">
                <v-btn v-bind="activatorProps" icon class="mx-2">
                    <user-avatar :user="user" />
                </v-btn>
            </template>

            <v-list density="compact">
                <v-list-item :to="`/user/${user?.username}`">
                    <template v-slot:prepend><v-icon>mdi-account</v-icon></template>
                    <v-list-item-title>{{ $t('menu.profile') }}</v-list-item-title>
                </v-list-item>
                <v-list-item to="/dashboard">
                    <template v-slot:prepend><v-icon>mdi-view-dashboard</v-icon></template>
                    <v-list-item-title>{{ $t('menu.dashboard') }}</v-list-item-title>
                </v-list-item>
                <v-list-item v-if="isAdmin" to="/admin/settings">
                    <template v-slot:prepend><v-icon>mdi-cog</v-icon></template>
                    <v-list-item-title>{{ $t('menu.settings') }}</v-list-item-title>
                </v-list-item>

                <v-divider class="my-1" />

                <v-list-item @click="signOut">
                    <template v-slot:prepend><v-icon>mdi-logout</v-icon></template>
                    <v-list-item-title>{{ $t('menu.logout') }}</v-list-item-title>
                </v-list-item>
            </v-list>
        </v-menu>
    </v-app-bar>

    <!-- Mobile Header -->
    <v-app-bar v-else color="surface" flat density="comfortable">
        <v-app-bar-nav-icon @click="drawer = !drawer" />
        <v-app-bar-title>
            <router-link to="/" class="text-decoration-none text-on-surface">
                {{ appName }}
            </router-link>
        </v-app-bar-title>
    </v-app-bar>

    <!-- Mobile Drawer -->
    <v-navigation-drawer v-if="mobile" v-model="drawer" temporary>
        <v-list density="compact">
            <v-list-item>
                <router-link
                    to="/"
                    class="d-inline-flex align-center text-decoration-none text-on-surface"
                    @click="drawer = false"
                >
                    <v-img
                        v-if="logoSrc"
                        :src="logoSrc"
                        max-height="36"
                        max-width="160"
                        contain
                    />
                    <span v-else class="text-h6 font-weight-medium">{{ appName }}</span>
                </router-link>
            </v-list-item>

            <v-divider class="my-1" />

            <template v-for="item in items" :key="item.id">
                <v-list-group
                    v-if="item.children && item.children.length"
                    :value="item.label"
                >
                    <template v-slot:activator="{ props: activatorProps }">
                        <v-list-item v-bind="activatorProps">
                            <template v-slot:prepend>
                                <v-icon v-if="item.icon">{{ item.icon }}</v-icon>
                                <div v-else style="width: 24px;"></div>
                            </template>
                            <v-list-item-title>{{ item.label }}</v-list-item-title>
                        </v-list-item>
                    </template>

                    <v-list-item
                        v-for="child in item.children"
                        :key="child.id"
                        :to="child.type !== 'external' ? child.href : undefined"
                        :href="child.type === 'external' ? child.href : undefined"
                        :target="child.target"
                        :active="isActive(child.href)"
                        @click="drawer = false"
                    >
                        <template v-slot:prepend>
                            <v-icon v-if="isActive(child.href)">mdi-check</v-icon>
                            <v-icon v-else-if="child.icon">{{ child.icon }}</v-icon>
                            <div v-else style="width: 24px;"></div>
                        </template>
                        <v-list-item-title>{{ child.label }}</v-list-item-title>
                    </v-list-item>
                </v-list-group>

                <v-list-item
                    v-else
                    :to="item.type !== 'external' ? item.href : undefined"
                    :href="item.type === 'external' ? item.href : undefined"
                    :target="item.target"
                    :active="isActive(item.href)"
                    @click="drawer = false"
                >
                    <template v-slot:prepend>
                        <v-icon v-if="isActive(item.href)">mdi-check</v-icon>
                        <v-icon v-else-if="item.icon">{{ item.icon }}</v-icon>
                        <div v-else style="width: 24px;"></div>
                    </template>
                    <v-list-item-title>{{ item.label }}</v-list-item-title>
                </v-list-item>
            </template>

            <v-divider class="my-1" />

            <template v-if="!authenticated">
                <v-list-item to="/auth/signin" @click="drawer = false">
                    <template v-slot:prepend><v-icon>mdi-login</v-icon></template>
                    <v-list-item-title>{{ $t('auth.signin') }}</v-list-item-title>
                </v-list-item>
                <v-list-item to="/auth/signup" @click="drawer = false">
                    <template v-slot:prepend><v-icon>mdi-account-plus</v-icon></template>
                    <v-list-item-title>{{ $t('auth.signup') }}</v-list-item-title>
                </v-list-item>
            </template>

            <template v-else>
                <v-list-item :to="`/user/${user?.username}`" @click="drawer = false">
                    <template v-slot:prepend><v-icon>mdi-account</v-icon></template>
                    <v-list-item-title>{{ $t('menu.profile') }}</v-list-item-title>
                </v-list-item>
                <v-list-item to="/dashboard" @click="drawer = false">
                    <template v-slot:prepend><v-icon>mdi-view-dashboard</v-icon></template>
                    <v-list-item-title>{{ $t('menu.dashboard') }}</v-list-item-title>
                </v-list-item>
                <v-list-item v-if="isAdmin" to="/admin/settings" @click="drawer = false">
                    <template v-slot:prepend><v-icon>mdi-cog</v-icon></template>
                    <v-list-item-title>{{ $t('menu.settings') }}</v-list-item-title>
                </v-list-item>

                <v-divider class="my-1" />

                <v-list-item @click="signOut">
                    <template v-slot:prepend><v-icon>mdi-logout</v-icon></template>
                    <v-list-item-title>{{ $t('menu.logout') }}</v-list-item-title>
                </v-list-item>
            </template>
        </v-list>
    </v-navigation-drawer>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { useDisplay } from 'vuetify';
import { useAuthStore } from '@/store/authStore.js';
import { useUserStore } from '@/store/userStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import { useMenuStore } from '@/store/menuStore.js';
import UserAvatar from '@/components/common/UserAvatar.vue';

const props = defineProps({
    location: { type: String, default: 'header' },
    transparent: { type: Boolean, default: false },
});

const { mobile } = useDisplay();
const route = useRoute();

const authStore = useAuthStore();
const userStore = useUserStore();
const settingsStore = useSettingsStore();
const menuStore = useMenuStore();

const drawer = ref(false);
const scrolled = ref(false);

const items = computed(() => menuStore.items(props.location));
const authenticated = computed(() => authStore.isLoggedIn);
const user = computed(() => userStore.user);
const isAdmin = computed(() => userStore.hasRole?.('admin') ?? false);
const appName = computed(() => settingsStore.appName);
const logoSrc = computed(() => {
    if (settingsStore.appLogo) return settingsStore.appLogo;
    const raw = settingsStore.logoLight;
    return raw ? `/storage/${raw}` : null;
});

const isActive = (href) => !!href && route.path === href;

const handleScroll = () => {
    scrolled.value = window.scrollY > 50;
};

const signOut = async () => {
    drawer.value = false;
    await authStore.logout();
};

onMounted(() => {
    menuStore.fetchMenu(props.location);
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>
