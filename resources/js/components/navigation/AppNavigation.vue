<template>
  <div>
    <!-- Desktop Header Navigation -->
    <v-app-bar
      v-if="!isMobile"
      app
      :elevation="scrolled ? 4 : 0"
      :color="scrolled || !transparent ? 'surface' : 'transparent'"
      flat
    >
      <v-container>
        <v-row align="center">
          <v-col cols="auto">
            <!-- Logo -->
            <router-link to="/" class="d-flex align-center text-decoration-none">
              <v-img
                v-if="settings.logo"
                :src="settings.logo"
                max-height="40"
                max-width="120"
                contain
              />
              <h2 v-else class="text-h5">{{ settings.site_name || 'Fellowship' }}</h2>
            </router-link>
          </v-col>

          <v-spacer />

          <!-- Navigation Items -->
          <v-col cols="auto">
            <nav class="d-flex align-center gap-2">
              <template v-for="item in filteredItems" :key="item.id">
                <!-- Dropdown Menu -->
                <v-menu v-if="item.children && item.children.length" offset-y>
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      :prepend-icon="item.icon"
                      variant="text"
                    >
                      {{ item.label }}
                      <v-icon right size="small">mdi-chevron-down</v-icon>
                    </v-btn>
                  </template>
                  <v-list>
                    <v-list-item
                      v-for="child in item.children"
                      :key="child.id"
                      :to="child.href"
                      :href="child.type === 'external' ? child.href : null"
                      :target="child.target"
                    >
                      <template #prepend v-if="child.icon">
                        <v-icon>{{ child.icon }}</v-icon>
                      </template>
                      <v-list-item-title>{{ child.label }}</v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-menu>

                <!-- Regular Link -->
                <v-btn
                  v-else
                  :to="item.type !== 'external' ? item.href : null"
                  :href="item.type === 'external' ? item.href : null"
                  :target="item.target"
                  :prepend-icon="item.icon"
                  variant="text"
                >
                  {{ item.label }}
                </v-btn>
              </template>

              <!-- Auth Buttons -->
              <v-divider vertical class="mx-2" v-if="!$store.state.auth.user" />
              <v-btn v-if="!$store.state.auth.user" to="/login" variant="outlined">
                Login
              </v-btn>
              <v-btn v-if="!$store.state.auth.user" to="/register" color="primary">
                Register
              </v-btn>

              <!-- User Menu -->
              <v-menu v-if="$store.state.auth.user" offset-y>
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    variant="text"
                  >
                    <v-avatar size="32">
                      <v-icon>mdi-account-circle</v-icon>
                    </v-avatar>
                  </v-btn>
                </template>
                <v-list>
                  <v-list-item :to="`/user/${$store.state.auth.user.username}`">
                    <template #prepend>
                      <v-icon>mdi-account</v-icon>
                    </template>
                    <v-list-item-title>Profile</v-list-item-title>
                  </v-list-item>
                  <v-list-item to="/dashboard">
                    <template #prepend>
                      <v-icon>mdi-view-dashboard</v-icon>
                    </template>
                    <v-list-item-title>Dashboard</v-list-item-title>
                  </v-list-item>
                  <v-list-item v-if="$store.getters['auth/isAdmin']" to="/admin/settings">
                    <template #prepend>
                      <v-icon>mdi-cog</v-icon>
                    </template>
                    <v-list-item-title>Settings</v-list-item-title>
                  </v-list-item>
                  <v-divider />
                  <v-list-item @click="logout">
                    <template #prepend>
                      <v-icon>mdi-logout</v-icon>
                    </template>
                    <v-list-item-title>Logout</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </nav>
          </v-col>
        </v-row>
      </v-container>
    </v-app-bar>

    <!-- Mobile Navigation -->
    <v-app-bar v-if="isMobile" app color="surface">
      <v-app-bar-nav-icon @click="drawer = !drawer" />
      <v-toolbar-title>
        <router-link to="/" class="text-decoration-none text-inherit">
          {{ settings.site_name || 'Fellowship' }}
        </router-link>
      </v-toolbar-title>
      <v-spacer />
      <v-btn v-if="$store.state.auth.user" icon @click="userMenu = !userMenu">
        <v-icon>mdi-account-circle</v-icon>
      </v-btn>
    </v-app-bar>

    <!-- Mobile Drawer -->
    <v-navigation-drawer v-model="drawer" temporary app>
      <v-list>
        <!-- Logo/Title -->
        <v-list-item>
          <router-link to="/" @click="drawer = false">
            <v-img
              v-if="settings.logo"
              :src="settings.logo"
              max-height="40"
              contain
            />
            <h3 v-else>{{ settings.site_name || 'Fellowship' }}</h3>
          </router-link>
        </v-list-item>

        <v-divider />

        <!-- Menu Items -->
        <template v-for="item in filteredItems" :key="item.id">
          <!-- Parent with children -->
          <v-list-group v-if="item.children && item.children.length" :value="item.label">
            <template #activator="{ props }">
              <v-list-item v-bind="props">
                <template #prepend v-if="item.icon">
                  <v-icon>{{ item.icon }}</v-icon>
                </template>
                <v-list-item-title>{{ item.label }}</v-list-item-title>
              </v-list-item>
            </template>

            <v-list-item
              v-for="child in item.children"
              :key="child.id"
              :to="child.type !== 'external' ? child.href : null"
              :href="child.type === 'external' ? child.href : null"
              :target="child.target"
              @click="drawer = false"
            >
              <template #prepend v-if="child.icon">
                <v-icon>{{ child.icon }}</v-icon>
              </template>
              <v-list-item-title>{{ child.label }}</v-list-item-title>
            </v-list-item>
          </v-list-group>

          <!-- Single item -->
          <v-list-item
            v-else
            :to="item.type !== 'external' ? item.href : null"
            :href="item.type === 'external' ? item.href : null"
            :target="item.target"
            @click="drawer = false"
          >
            <template #prepend v-if="item.icon">
              <v-icon>{{ item.icon }}</v-icon>
            </template>
            <v-list-item-title>{{ item.label }}</v-list-item-title>
          </v-list-item>
        </template>

        <v-divider />

        <!-- Auth Actions -->
        <v-list-item v-if="!$store.state.auth.user" to="/login" @click="drawer = false">
          <template #prepend>
            <v-icon>mdi-login</v-icon>
          </template>
          <v-list-item-title>Login</v-list-item-title>
        </v-list-item>
        <v-list-item v-if="!$store.state.auth.user" to="/register" @click="drawer = false">
          <template #prepend>
            <v-icon>mdi-account-plus</v-icon>
          </template>
          <v-list-item-title>Register</v-list-item-title>
        </v-list-item>

        <!-- User Menu -->
        <template v-if="$store.state.auth.user">
          <v-list-item :to="`/user/${$store.state.auth.user.username}`" @click="drawer = false">
            <template #prepend>
              <v-icon>mdi-account</v-icon>
            </template>
            <v-list-item-title>Profile</v-list-item-title>
          </v-list-item>
          <v-list-item to="/dashboard" @click="drawer = false">
            <template #prepend>
              <v-icon>mdi-view-dashboard</v-icon>
            </template>
            <v-list-item-title>Dashboard</v-list-item-title>
          </v-list-item>
          <v-list-item v-if="$store.getters['auth/isAdmin']" to="/admin/settings" @click="drawer = false">
            <template #prepend>
              <v-icon>mdi-cog</v-icon>
            </template>
            <v-list-item-title>Settings</v-list-item-title>
          </v-list-item>
          <v-divider />
          <v-list-item @click="logout">
            <template #prepend>
              <v-icon>mdi-logout</v-icon>
            </template>
            <v-list-item-title>Logout</v-list-item-title>
          </v-list-item>
        </template>
      </v-list>
    </v-navigation-drawer>
  </div>
</template>

<script>
import axios from 'axios';
import { useDisplay } from 'vuetify';

export default {
  name: 'AppNavigation',
  props: {
    location: {
      type: String,
      default: 'header',
    },
    transparent: {
      type: Boolean,
      default: false,
    },
  },
  setup() {
    const { mobile } = useDisplay();
    return { isMobile: mobile };
  },
  data() {
    return {
      items: [],
      drawer: false,
      userMenu: false,
      scrolled: false,
      settings: {},
    };
  },
  computed: {
    filteredItems() {
      // Items are already filtered server-side by auth/permissions
      return this.items;
    },
  },
  mounted() {
    this.fetchMenu();
    this.fetchSettings();
    window.addEventListener('scroll', this.handleScroll);
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    async fetchMenu() {
      try {
        const { data } = await axios.get(`/api/menus/location/${this.location}`);
        this.items = data.items || [];
      } catch (error) {
        console.error('Error fetching menu:', error);
      }
    },
    async fetchSettings() {
      try {
        const { data } = await axios.get('/api/settings/public');
        this.settings = data;
      } catch (error) {
        console.error('Error fetching settings:', error);
      }
    },
    handleScroll() {
      this.scrolled = window.scrollY > 50;
    },
    async logout() {
      await this.$store.dispatch('auth/logout');
      this.drawer = false;
      this.userMenu = false;
      this.$router.push('/');
    },
  },
};
</script>

<style scoped>
.gap-2 {
  gap: 0.5rem;
}

.text-inherit {
  color: inherit;
}
</style>
