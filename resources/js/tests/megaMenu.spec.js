import { describe, it, expect, beforeEach, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import en from '@/translations/en.js';
import { useMenuStore } from '@/store/menuStore.js';
import MegaMenu from '@/components/navigation/MegaMenu.vue';

/**
 * The mega menu at a given screen width.
 *
 * It used to lay the panel out as one nowrap row of 260px columns inside a
 * card capped at 960 — three and a half columns, so a menu with four
 * groups put the rest behind a horizontal scrollbar with nothing in the
 * panel to say there was more to the right. And the drawer it falls back
 * to on a phone came in from the left at a flat 320 wide, over the top of
 * the layout's own left-hand navigation drawer and, on a 320px screen,
 * over the whole page.
 */

// The viewport these tests pretend to be on. useDisplay comes from the
// Vuetify plugin, which is not installed here; the component reads only
// the md breakpoint and the width off it.
const viewport = vi.hoisted(() => ({ width: 1440 }));

vi.mock('vuetify', async () => {
    const { computed } = await import('vue');

    return {
        useDisplay: () => ({
            width: computed(() => viewport.width),
            mdAndUp: computed(() => viewport.width >= 960),
        }),
    };
});

vi.mock('vue-router', () => ({
    useRoute: () => ({ fullPath: '/' }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const stubs = {
    // Rendered open, so the panel is there to assert on
    'v-menu': {
        props: ['modelValue', 'minWidth', 'maxWidth', 'location'],
        template: `<div class="menu" :data-max-width="maxWidth">
            <slot name="activator" :props="{}" />
            <slot />
        </div>`,
    },
    'v-navigation-drawer': {
        props: ['modelValue', 'location', 'width', 'temporary'],
        template: '<aside class="drawer" :data-location="location" :data-width="width"><slot /></aside>',
    },
    'v-card': { template: '<div class="card" v-bind="$attrs"><slot /></div>' },
    'v-btn': { template: '<button v-bind="$attrs"><slot /></button>' },
    'v-icon': { props: ['icon'], template: '<i :data-icon="icon"><slot /></i>' },
    'v-divider': { template: '<hr />' },
    'v-progress-circular': { template: '<span class="loading" />' },
    'router-link': { props: ['to'], template: '<a :href="String(to)"><slot /></a>' },
};

/**
 * A header menu: two groups with children, then two items without, which
 * the panel is supposed to collect into a single column rather than give
 * a column each.
 */
const menu = [
    {
        id: 1,
        label: 'Community',
        href: '#',
        icon: 'mdi-account-group',
        children: [
            { id: 11, label: 'Forum', href: '/forum' },
            { id: 12, label: 'Gallery', href: '/gallery' },
        ],
    },
    {
        id: 2,
        label: 'Knowledge',
        href: '/wiki',
        children: [{ id: 21, label: 'Wiki', href: '/wiki' }],
    },
    { id: 3, label: 'Events', href: '/events' },
    { id: 4, label: 'Timeline', href: '/timeline' },
];

const render = (items = menu) => {
    const store = useMenuStore();
    store.byLocation.header = { items, meta: null, isLoaded: true, isLoading: false, error: null };

    return mount(MegaMenu, { global: { plugins: [i18n], stubs } });
};

describe('MegaMenu', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        viewport.width = 1440;
        // The drawer teleports into the body; clear what the last mount left
        document.body.innerHTML = '';
    });

    describe('the panel, on a screen wide enough for one', () => {
        it('gives a group its own column and collects the loose items into one', () => {
            const w = render();

            // Community, Knowledge, then Events + Timeline together
            expect(w.findAll('.mega-col')).toHaveLength(3);
        });

        it('wraps its columns rather than scrolling sideways', () => {
            const w = render();

            // The panel's own class carries the height cap and the columns
            // sit in a wrapping row; neither is a horizontal scroller
            expect(w.find('.mega-panel').exists()).toBe(true);
            expect(w.find('.mega-grid').exists()).toBe(true);
            expect(w.find('.overflow-x-auto').exists()).toBe(false);
        });

        it('is never wider than the window it opens in', () => {
            const w = render();

            expect(w.find('.menu').attributes('data-max-width')).toBe('min(960px, calc(100vw - 32px))');
        });

        it('reaches every destination in the menu', () => {
            const w = render();

            const links = w.findAll('.mega-item').map(el => el.text());
            expect(links).toEqual(['Forum', 'Gallery', 'Wiki', 'Events', 'Timeline']);
        });

        it('says so when the menu is empty', () => {
            const w = render([]);

            expect(w.find('.mega-grid').exists()).toBe(false);
            expect(w.text()).toContain(en.megaMenu.noItems);
        });
    });

    describe('the drawer, on a screen that is not', () => {
        beforeEach(() => {
            viewport.width = 390;
        });

        // The drawer is teleported out of the component, so it is in the
        // document to query rather than in the wrapper to find.
        const drawer = () => document.body.querySelector('.drawer');
        const labels = root => [...root.querySelectorAll('.mega-item')]
            .map(el => el.textContent.trim());

        it('takes over from the panel', () => {
            const w = render();

            expect(drawer()).not.toBeNull();
            expect(w.find('.mega-panel').exists()).toBe(false);
        });

        /**
         * Rendered where it is written, it is a fixed-position drawer
         * inside the app bar — and the bar is a Vuetify layout item, so it
         * carries a transform and becomes the drawer's containing block,
         * with `.v-toolbar__content` clipping whatever overflows it. The
         * drawer came out the height of the bar: its title row and the
         * close button, and none of the menu.
         */
        it('leaves the app bar it is rendered from', () => {
            const w = render();

            expect(w.find('.drawer').exists()).toBe(false);
            expect(drawer().parentElement).toBe(document.body);
        });

        /**
         * The left is the layout's own navigation drawer, and this one's
         * button is at the right-hand end of the bar.
         */
        /**
         * Where it ends up in an app that has a layout root: inside it,
         * rather than in the body, so the scrim it puts over the page
         * covers the page rather than the first screen of it.
         */
        it('moves into the layout root once the app has one', async () => {
            const wrap = document.createElement('div');
            wrap.className = 'v-application__wrap';
            document.body.appendChild(wrap);

            render();
            await nextTick();

            expect(wrap.querySelector('.drawer')).not.toBeNull();
        });
        it('comes in from the right', () => {
            render();

            expect(drawer().getAttribute('data-location')).toBe('right');
        });

        it('leaves a strip of the page showing on a narrow phone', () => {
            viewport.width = 320;
            render();

            // 320 * 0.9, so there is still something to tap to get back
            expect(drawer().getAttribute('data-width')).toBe('288');
        });

        it('asks for no more than 320 once there is room for it', () => {
            viewport.width = 420;
            render();

            expect(drawer().getAttribute('data-width')).toBe('320');
        });

        it('lists the groups and their children', () => {
            render();

            expect(labels(drawer())).toEqual(['Forum', 'Gallery', 'Wiki', 'Events', 'Timeline']);
        });
    });
});
