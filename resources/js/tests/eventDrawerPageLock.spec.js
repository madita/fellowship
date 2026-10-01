import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import en from '@/translations/en.js';
import CalendarEventHandler from '@/components/event/CalendarEventHandler.vue';

vi.mock('axios');

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });
const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/', component: { template: '<div/>' } }],
});

// No id: this version calls getEvent from an immediate watcher, and the
// drawer is never mounted with an event already chosen in the app either.
const anEvent = { title: 'Test Event', start: '2099-01-01T00:00:00Z', extendedProps: {} };

const render = (props = {}) => mount(CalendarEventHandler, {
    shallow: true,
    props: { isDrawerOpen: false, editMode: false, event: anEvent, saving: false, ...props },
    global: { plugins: [i18n, router] },
});

const locked = () => document.body.classList.contains('event-drawer-open');

describe('the page behind the event drawer', () => {
    beforeEach(() => {
        const store = new Map();
        vi.stubGlobal('localStorage', {
            getItem: k => store.get(k) ?? null,
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: k => store.delete(k),
            clear: () => store.clear(),
        });
        setActivePinia(createPinia());
    });

    afterEach(() => {
        document.body.classList.remove('event-drawer-open');
        document.body.style.removeProperty('--event-drawer-scroll-gap');
    });

    it('scrolls freely while the drawer is shut', () => {
        render();

        expect(locked()).toBe(false);
    });

    /**
     * Two scrollbars down the right-hand side, with the wheel moving
     * whichever the pointer is over, is the thing being avoided here.
     */
    it('is held still while the drawer is open', async () => {
        const w = render();

        await w.setProps({ isDrawerOpen: true });

        expect(locked()).toBe(true);
    });

    it('is released again when the drawer closes', async () => {
        const w = render({ isDrawerOpen: true });

        await w.setProps({ isDrawerOpen: true });
        expect(locked()).toBe(true);

        await w.setProps({ isDrawerOpen: false });
        expect(locked()).toBe(false);
    });

    // Following the expand button to the event's own page unmounts the
    // drawer; a page that stayed locked after that would be unusable
    it('is released if the drawer is torn down while open', async () => {
        const w = render();

        await w.setProps({ isDrawerOpen: true });
        expect(locked()).toBe(true);

        w.unmount();

        expect(locked()).toBe(false);
    });

    it('stands in for the width the scrollbar was taking, then gives it back', async () => {
        const w = render();

        await w.setProps({ isDrawerOpen: true });
        expect(document.body.style.getPropertyValue('--event-drawer-scroll-gap')).toMatch(/^\d+px$/);

        await w.setProps({ isDrawerOpen: false });
        expect(document.body.style.getPropertyValue('--event-drawer-scroll-gap')).toBe('');
    });

    it('locks straight away when mounted with the drawer already open', () => {
        render({ isDrawerOpen: true });

        expect(locked()).toBe(true);
    });

    /**
     * With the page held still, the panel is the only thing left that can
     * scroll — so it has to be something that actually does. A JS
     * scrollbar was sitting here before, and those set
     * overflow: hidden !important on the container and measure the
     * geometry as they mount, with the drawer still shut.
     */
    it('leaves the scrolling to a plain element, not a JS scrollbar', () => {
        const w = render({ isDrawerOpen: true });
        const panel = w.find('.event-drawer-content');

        expect(panel.exists()).toBe(true);
        expect(panel.element.tagName).toBe('DIV');
        expect(panel.classes()).not.toContain('ps');
        expect(w.html()).not.toContain('perfect-scrollbar');
    });
});

/**
 * The drawer is positioned against the page, not the screen, so it always
 * sits at the very top of the document. Open an event after scrolling down
 * and it opens above the viewport, out of sight.
 */
describe('the page position when the event drawer opens', () => {
    let scrollTo;

    beforeEach(() => {
        const store = new Map();
        vi.stubGlobal('localStorage', {
            getItem: k => store.get(k) ?? null,
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: k => store.delete(k),
            clear: () => store.clear(),
        });
        setActivePinia(createPinia());

        // jsdom has no layout, so scrolling is stubbed and asserted on
        scrollTo = vi.fn();
        vi.stubGlobal('scrollTo', scrollTo);
        vi.stubGlobal('scrollY', 640);
    });

    afterEach(() => {
        document.body.classList.remove('event-drawer-open');
        vi.unstubAllGlobals();
    });

    it('brings the page to the top so the drawer is on screen', async () => {
        const w = render();

        await w.setProps({ isDrawerOpen: true });

        expect(scrollTo).toHaveBeenCalledWith(0, 0);
    });

    // A page that cannot scroll cannot be scrolled to the top either
    it('moves the page before locking it, not after', async () => {
        const w = render();
        const lockedWhenScrolled = [];

        scrollTo.mockImplementation(() => {
            lockedWhenScrolled.push(document.body.classList.contains('event-drawer-open'));
        });

        await w.setProps({ isDrawerOpen: true });

        expect(lockedWhenScrolled).toEqual([false]);
    });

    it('puts the page back where it was when the drawer closes', async () => {
        const w = render();

        await w.setProps({ isDrawerOpen: true });
        scrollTo.mockClear();

        await w.setProps({ isDrawerOpen: false });

        expect(scrollTo).toHaveBeenCalledWith(0, 640);
    });

    it('releases the page before putting it back', async () => {
        const w = render();

        await w.setProps({ isDrawerOpen: true });
        scrollTo.mockClear();

        const lockedWhenRestored = [];
        scrollTo.mockImplementation(() => {
            lockedWhenRestored.push(document.body.classList.contains('event-drawer-open'));
        });

        await w.setProps({ isDrawerOpen: false });

        expect(lockedWhenRestored).toEqual([false]);
    });
});

describe('opening the event drawer at the top', () => {
    beforeEach(() => {
        const store = new Map();
        vi.stubGlobal('localStorage', {
            getItem: k => store.get(k) ?? null,
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: k => store.delete(k),
            clear: () => store.clear(),
        });
        setActivePinia(createPinia());
    });

    afterEach(() => {
        document.body.classList.remove('event-drawer-open');
    });

    // The panel is the element that scrolls — resetting Vuetify's box
    // around it, which does not, is what failed before
    const panel = w => w.find('.event-drawer-content').element;

    it('jumps back to the top when the drawer opens', async () => {
        const w = render();

        panel(w).scrollTop = 250;

        await w.setProps({ isDrawerOpen: true });
        await nextTick();

        expect(panel(w).scrollTop).toBe(0);
    });

    it('jumps back to the top when another event is opened into it', async () => {
        const w = render({ isDrawerOpen: true });

        panel(w).scrollTop = 250;

        await w.setProps({ event: { ...anEvent, title: 'Another' } });
        await nextTick();

        expect(panel(w).scrollTop).toBe(0);
    });

    it('leaves the position alone while the drawer is shut', async () => {
        const w = render();

        panel(w).scrollTop = 250;

        await w.setProps({ event: { ...anEvent, title: 'Another' } });
        await nextTick();

        expect(panel(w).scrollTop).toBe(250);
    });
});
