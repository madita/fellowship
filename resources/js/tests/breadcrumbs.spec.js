import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import en from '@/translations/en.js';
import { useSettingsStore } from '@/store/settingStore.js';
import { useAuthStore } from '@/store/authStore.js';
import Breadcrumbs from '@/components/common/Breadcrumbs.vue';
import PageHeader from '@/components/common/PageHeader.vue';

/**
 * The trail above a page title.
 *
 * Five pages rolled their own v-breadcrumbs before this, two of them into
 * PageHeader's default slot — which renders below the title, so what was
 * meant as a breadcrumb read as a caption under the heading. What is worth
 * pinning down is the item shapes callers already pass (Vuetify's own, so
 * the migrated pages did not have to change their data), that the header
 * puts the trail above the title, and the two things the admin decides:
 * whether trails show at all and where they start.
 */
const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

// v-breadcrumbs is stubbed down to what matters here: the items it is
// handed, and the title slot the component overrides to place icons
const stubs = {
    'v-breadcrumbs': {
        props: ['items', 'density'],
        template: `<ul class="crumbs" :data-density="density">
            <li v-for="(item, index) in items" :key="index" :data-keys="Object.keys(item).join(',')"
                :data-to="typeof item.to === 'object' ? item.to.name : (item.to || '')" :data-disabled="String(item.disabled)">
                <slot name="title" :item="item" :index="index" />
            </li>
        </ul>`,
    },
    'v-icon': { props: ['icon'], template: '<i :data-icon="icon"><slot /></i>' },
    'v-container': { template: '<div><slot /></div>' },
    'v-btn': { template: '<button><slot /></button>' },
};

const render = (props = {}) => mount(Breadcrumbs, { props, global: { plugins: [i18n], stubs } });

const titles = (w) => w.findAll('li').map(li => li.text());
// The trail without its root step, which every case but the root ones
// takes for granted
const steps = (w) => titles(w).slice(1);

/**
 * A signed-out visitor, unless a test says otherwise. The auth store
 * reads its state from localStorage — and Node's own global one shadows
 * jsdom's, so it is stubbed the way the store specs do it.
 */
const freshStores = () => {
    const data = new Map();
    vi.stubGlobal('localStorage', {
        getItem: key => (data.has(key) ? data.get(key) : null),
        setItem: (key, value) => data.set(key, String(value)),
        removeItem: key => data.delete(key),
        clear: () => data.clear(),
    });
    setActivePinia(createPinia());
    useAuthStore().isLoggedIn = false;
};

describe('Breadcrumbs', () => {
    beforeEach(freshStores);
    afterEach(() => vi.unstubAllGlobals());

    it('takes the item shape the pages already passed', () => {
        const w = render({
            items: [
                { title: 'Settings', to: { name: 'admin-settings' }, disabled: false },
                { title: 'Appearance', disabled: true },
            ],
        });

        expect(steps(w)).toEqual(['Settings', 'Appearance']);
    });

    it('widens a bare string into an item', () => {
        expect(steps(render({ items: ['Forum', 'General'] }))).toEqual(['Forum', 'General']);
    });

    /**
     * So a step that may not apply can be written inline, rather than the
     * caller building the array up imperatively.
     */
    it('drops the gaps, so a conditional step can be written inline', () => {
        const category = null;
        const w = render({ items: ['Forum', category && { title: 'Category' }, 'A thread'] });

        expect(steps(w)).toEqual(['Forum', 'A thread']);
    });

    /**
     * Vuetify spreads each item onto its list item, so an icon left in
     * there would come out as an attribute on the <li>.
     */
    it('keeps a crumb icon out of what v-breadcrumbs is handed', () => {
        const w = render({ items: [{ title: 'Tickets', to: '/account/tickets', icon: 'mdi-ticket-outline' }] });

        const row = w.findAll('li')[1];
        expect(row.attributes('data-keys')).toBe('title,to');
        expect(row.find('i').attributes('data-icon')).toBe('mdi-ticket-outline');
    });

    it('tells a screen reader what the list is', () => {
        expect(render({ items: ['Wiki'] }).find('nav').attributes('aria-label')).toBe(en.components.breadcrumb.label);
    });

    it('renders nothing at all when there is no trail', () => {
        expect(render({ items: [] }).find('nav').exists()).toBe(false);
        expect(render({ items: [null, false] }).find('nav').exists()).toBe(false);
    });

    /**
     * Where a trail starts is one setting for the whole site, so no page
     * passes that step and no two pages can disagree about it.
     */
    describe('the first step', () => {
        it('sends a guest to the landing page', () => {
            const w = render({ items: ['Wiki'] });

            const root = w.findAll('li')[0];
            expect(root.text()).toBe(en.components.breadcrumb.home);
            expect(root.attributes('data-to')).toBe('/');
            expect(root.find('i').attributes('data-icon')).toBe('mdi-home-outline');
        });

        it('sends a member to their dashboard', () => {
            useAuthStore().isLoggedIn = true;

            const root = render({ items: ['Wiki'] }).findAll('li')[0];
            expect(root.text()).toBe(en.components.breadcrumb.dashboard);
            expect(root.attributes('data-to')).toBe('dashboard');
        });

        /**
         * The dashboard is behind the login, so a guest gets the landing
         * page whatever the setting says.
         */
        it('can be the landing page for members too', () => {
            useSettingsStore().appSettings.breadcrumbs_root = 'home';
            useAuthStore().isLoggedIn = true;

            expect(titles(render({ items: ['Wiki'] }))).toEqual([en.components.breadcrumb.home, 'Wiki']);
        });

        it('can be left off', () => {
            useSettingsStore().appSettings.breadcrumbs_root = 'none';

            expect(titles(render({ items: ['Wiki'] }))).toEqual(['Wiki']);
        });

        /**
         * A path bar that is not a path through the site — the media
         * centre's folders — opts out in the markup rather than by setting.
         */
        it('is left off a trail that says it is not one', () => {
            expect(titles(render({ items: ['Wiki'], root: false }))).toEqual(['Wiki']);
        });

        it('is not added to an empty trail', () => {
            expect(render({ items: [] }).find('nav').exists()).toBe(false);
        });
    });

    it('shows nothing anywhere once trails are turned off', () => {
        useSettingsStore().appSettings.breadcrumbs_enabled = false;

        expect(render({ items: ['Forum', 'General'] }).find('nav').exists()).toBe(false);
    });

    /**
     * A setting comes back from the API as whatever was stored, and the
     * switch is stored as '1' or '0' — read naively, the string '0' is
     * true and turning trails off would do nothing.
     */
    describe('reading the settings', () => {
        it('takes the switch as a string as well as a boolean', () => {
            const store = useSettingsStore();

            store.appSettings.breadcrumbs_enabled = '0';
            expect(store.breadcrumbsEnabled).toBe(false);

            store.appSettings.breadcrumbs_enabled = 'false';
            expect(store.breadcrumbsEnabled).toBe(false);

            store.appSettings.breadcrumbs_enabled = '1';
            expect(store.breadcrumbsEnabled).toBe(true);
        });

        it('leaves trails on for an install that has never set them', () => {
            const store = useSettingsStore();

            store.appSettings.breadcrumbs_enabled = undefined;
            expect(store.breadcrumbsEnabled).toBe(true);

            store.appSettings.breadcrumbs_root = undefined;
            expect(store.breadcrumbsRoot).toBe('auto');
        });

        it('falls back rather than trusting a value it does not know', () => {
            const store = useSettingsStore();

            store.appSettings.breadcrumbs_root = 'somewhere-else';
            expect(store.breadcrumbsRoot).toBe('auto');
        });
    });
});

describe('PageHeader with a trail', () => {
    beforeEach(freshStores);
    afterEach(() => vi.unstubAllGlobals());

    const header = (props = {}, slots = {}) => mount(PageHeader, {
        props: { title: 'Council of Elrond', ...props },
        slots,
        global: { plugins: [i18n], stubs },
    });

    it('puts the trail above the title, not under it', () => {
        const w = header({ breadcrumbs: ['Forum', 'General'] });

        const text = w.find('.page-header__text').html();
        expect(text.indexOf('page-header__crumbs')).toBeLessThan(text.indexOf('<h1'));
        expect(w.find('.crumbs').exists()).toBe(true);
    });

    /**
     * The point of the default: a page with nowhere in particular to sit
     * still gets a trail, so a breadcrumb is not something each of the
     * sixty headers has to remember.
     */
    it('falls back to the root and its own title', () => {
        expect(titles(header())).toEqual([en.components.breadcrumb.home, 'Council of Elrond']);
    });

    it('lets one header drop the trail', () => {
        expect(header({ breadcrumbs: false }).find('.crumbs').exists()).toBe(false);
    });

    it('still lets a page render its own trail', () => {
        const w = header({}, { breadcrumbs: '<span class="mine">A path bar</span>' });

        expect(w.find('.mine').exists()).toBe(true);
        expect(w.find('.crumbs').exists()).toBe(false);
    });
});
