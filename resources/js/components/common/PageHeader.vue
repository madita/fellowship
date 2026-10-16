<template>
    <header class="page-header" :class="{ 'page-header--band': band }">
        <v-container :fluid="fluid" class="py-0">
            <div class="d-flex flex-wrap align-center ga-4">
                <v-btn
                    v-if="backTo"
                    icon="mdi-arrow-left"
                    variant="text"
                    :to="backTo"
                    :aria-label="$t('common.back')"
                    :title="$t('common.back')"
                />
                <v-icon v-if="icon" size="40" color="primary" class="page-header__icon">{{ icon }}</v-icon>
                <div class="flex-grow-1 page-header__text">
                    <div v-if="$slots.breadcrumbs" class="page-header__crumbs">
                        <slot name="breadcrumbs" />
                    </div>
                    <breadcrumbs v-else-if="trail.length" :items="trail" class="page-header__crumbs" />
                    <h1 class="page-header__title text-h4 font-weight-bold text-gradient">{{ title }}</h1>
                    <p v-if="subtitle" class="text-subtitle-1 text-medium-emphasis mb-0 mt-1">{{ subtitle }}</p>
                </div>
                <div v-if="$slots.actions" class="d-flex align-center flex-wrap ga-2 page-header__actions">
                    <slot name="actions" />
                </div>
            </div>
            <div v-if="$slots.default" class="mt-4">
                <slot />
            </div>
        </v-container>
    </header>
</template>

<script>
/**
 * The page title block used on every top-level page: tinted band,
 * gradient title, subtitle, optional back button and action buttons.
 *
 *   <page-header :title="$t('forum.title')" :subtitle="$t('forum.subtitle')" icon="mdi-forum">
 *       <template #actions><v-btn color="primary" variant="elevated">…</v-btn></template>
 *       <v-text-field … />   <!-- optional extra row, e.g. search -->
 *   </page-header>
 *
 * Every header carries a trail above the title. A page with somewhere to
 * sit says where — :breadcrumbs="[{ title: forum.name, to: … }]" — and a
 * page that is simply itself needs to pass nothing: the trail is then the
 * site root and this title. See Breadcrumbs for what an item may be, for
 * the root step, and for the setting that turns the whole thing off.
 * :breadcrumbs="false" drops it from one header.
 *
 * The default slot renders *below* the title, which is why passing a
 * v-breadcrumbs there never read as a breadcrumb.
 *
 * Page content follows in its own <v-container> (fluid for full-width
 * admin tools) so it lines up with the header.
 */
import Breadcrumbs from '@/components/common/Breadcrumbs.vue';

export default {
    name: 'PageHeader',
    components: { Breadcrumbs },
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: '' },
        icon: { type: String, default: '' },
        // Route for the back button (string or router location)
        backTo: { type: [String, Object], default: null },
        // The trail above the title; strings or { title, to, href,
        // disabled, icon }, or false for no trail at all
        breadcrumbs: { type: [Array, Boolean], default: () => [] },
        // Tinted gradient band behind the header (off for embedded/compact use)
        band: { type: Boolean, default: true },
        // Full-width by default; pass :fluid="false" only for a boxed content container
        fluid: { type: Boolean, default: true },
    },
    computed: {
        /**
         * What the trail is: what the page passed, or — for a page that
         * passed nothing — the title it is already showing, so that every
         * header has one without every page spelling it out.
         */
        trail() {
            if (this.breadcrumbs === false) {
                return [];
            }

            const items = (Array.isArray(this.breadcrumbs) ? this.breadcrumbs : []).filter(Boolean);

            return items.length ? items : [this.title];
        },
    },
};
</script>

<style scoped>
.page-header {
    padding: 24px 0;
    margin-bottom: 24px;
}

.page-header--band {
    padding: 32px 0;
    background: linear-gradient(
        135deg,
        rgba(var(--v-theme-primary), 0.1) 0%,
        rgba(var(--v-theme-secondary), 0.1) 100%
    );
    backdrop-filter: blur(10px);
}

.v-theme--dark .page-header--band {
    background: linear-gradient(
        135deg,
        rgba(var(--v-theme-primary), 0.18) 0%,
        rgba(var(--v-theme-secondary), 0.12) 100%
    );
}

.page-header__title {
    line-height: 1.2;
}

.page-header__text {
    min-width: 0;
}

.page-header__crumbs {
    margin-bottom: 2px;
}

@media (max-width: 599px) {
    .page-header,
    .page-header--band {
        padding: 20px 0;
        margin-bottom: 16px;
    }

    .page-header__icon {
        display: none;
    }

    .page-header__actions {
        width: 100%;
    }
}
</style>
