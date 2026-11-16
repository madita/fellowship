<template>
    <nav
        v-if="enabled && trail.length"
        class="breadcrumbs"
        :aria-label="$t('components.breadcrumb.label')"
    >
        <v-breadcrumbs :items="trail" :density="density" class="pa-0 text-caption">
            <template #divider>
                <v-icon size="small">mdi-chevron-right</v-icon>
            </template>

            <!-- Taken over so a crumb can carry an icon; Vuetify would
                 otherwise render the bare title -->
            <template #title="{ index }">
                <v-icon
                    v-if="iconAt(index)"
                    :icon="iconAt(index)"
                    size="x-small"
                    class="mr-1"
                />
                <span class="breadcrumbs__title">{{ titleAt(index) }}</span>
            </template>
        </v-breadcrumbs>
    </nav>
</template>

<script>
/**
 * The trail above a page title: where this page sits in the site.
 *
 * Five pages built their own v-breadcrumbs before this — two of them
 * passing it to PageHeader's default slot, which renders *below* the
 * title — each repeating the chevron divider and the compact caption
 * styling, and none of them telling a screen reader what the list was.
 * Usually it belongs to the page title, so PageHeader takes the same
 * items and puts them where they go:
 *
 *   <page-header :title="thread.title" :breadcrumbs="crumbs" />
 *
 * An item is what Vuetify already accepts — a string, or
 * { title, to, href, disabled } — plus an optional `icon`. The last one
 * is the page one is on, so Vuetify renders it unlinked by itself; pass
 * `disabled: false` on it to override that. A falsy entry is dropped,
 * so a conditional step can be written inline:
 *
 *   :breadcrumbs="[forum, category && { title: category.name, to: category.url }, thread.title]"
 *
 * Every trail starts at the same place, so no page passes that step: the
 * site root is prepended here, and which page that is comes from
 * Admin → Settings → Navigation → Breadcrumbs. A member starts at their
 * dashboard and a guest at the landing page, because the dashboard is
 * behind the login. The same screen turns trails off site-wide, which is
 * why this component — rather than each caller — asks.
 */
import { useSettingsStore } from '@/store/settingStore.js';
import { useAuthStore } from '@/store/authStore.js';

export default {
    name: 'Breadcrumbs',
    props: {
        // Strings or { title, to, href, disabled, icon }; falsy entries drop out
        items: { type: Array, default: () => [] },
        // Start the trail at the site root. Off for a trail that is not a
        // path through the site, e.g. a file manager's own path bar.
        root: { type: Boolean, default: true },
        density: { type: String, default: 'compact' },
    },
    setup() {
        return {
            settingsStore: useSettingsStore(),
            authStore: useAuthStore(),
        };
    },
    computed: {
        // Trails can be turned off for the whole site
        enabled() {
            return this.settingsStore.breadcrumbsEnabled;
        },

        /**
         * Where the trail starts. A guest is sent to the landing page even
         * where the setting says otherwise — the dashboard would bounce
         * them to the login.
         */
        rootItem() {
            const mode = this.settingsStore.breadcrumbsRoot;

            if ( ! this.root || mode === 'none') {
                return null;
            }

            if (mode === 'auto' && this.authStore.isAuthenticated) {
                return {
                    title: this.$t('components.breadcrumb.dashboard'),
                    to: { name: 'dashboard' },
                    icon: 'mdi-view-dashboard-outline',
                };
            }

            return {
                title: this.$t('components.breadcrumb.home'),
                to: '/',
                icon: 'mdi-home-outline',
            };
        },

        /**
         * The items as given, tidied up: strings widened, gaps dropped,
         * the root step put in front.
         */
        crumbs() {
            const items = this.items
                .filter(Boolean)
                .map(item => (typeof item === 'string' ? { title: item } : { ...item }));

            if (this.rootItem && items.length) {
                items.unshift(this.rootItem);
            }

            return items;
        },

        /**
         * What v-breadcrumbs is handed: its own keys and nothing else, or
         * an icon would come out as an attribute on the list item.
         */
        trail() {
            return this.crumbs.map(({ icon, ...item }) => item);
        },
    },
    methods: {
        iconAt(index) {
            return this.crumbs[index]?.icon;
        },
        titleAt(index) {
            return this.crumbs[index]?.title;
        },
    },
};
</script>

<style scoped>
/* A long trail wraps rather than pushing the page sideways */
.breadcrumbs :deep(.v-breadcrumbs) {
    flex-wrap: wrap;
}

/* And a long title is cut rather than taking the whole line */
.breadcrumbs__title {
    display: inline-block;
    max-width: 32ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: bottom;
}

@media (max-width: 599px) {
    .breadcrumbs__title {
        max-width: 16ch;
    }
}
</style>
