import { describe, it, expect, vi, beforeEach } from 'vitest';
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

const render = (event, props = {}) => mount(CalendarEventHandler, {
    // Shallow: this is about the drawer's own structure, not its children
    shallow: true,
    props: { isDrawerOpen: true, editMode: false, event, saving: false, ...props },
    global: {
        plugins: [i18n, router],
        stubs: {
            // The real drawer is not rendered under shallow, but the box it
            // scrolls is the one the component reaches for by class.
            VNavigationDrawer: {
                template: '<aside><div class="v-navigation-drawer__content"><slot /></div></aside>',
            },
        },
    },
});

const anEvent = {
    id: 1,
    title: 'Test Event',
    start: '2099-01-01T00:00:00Z',
    extendedProps: {},
};

describe('event drawer scrolling', () => {
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

    /**
     * The drawer's own content box scrolls. An inner scrolling panel was
     * tried and broke scrolling outright: a JS scrollbar measures its
     * height once, while the drawer is closed and zero-tall, decides there
     * is nothing to scroll, and never reconsiders.
     */
    it('puts nothing between the drawer and its content that could scroll instead', () => {
        const w = render(anEvent);

        expect(w.find('.event-drawer-content').exists()).toBe(true);
        expect(w.find('.event-drawer-content').element.tagName).toBe('DIV');
        expect(w.find('.ps').exists()).toBe(false);
        expect(w.html()).not.toContain('perfect-scrollbar');
    });

    // Sticky only works while the header is inside the box that scrolls
    it('keeps the header a sibling of the content, not outside the scrolling box', () => {
        const w = render(anEvent);
        const header = w.find('.event-drawer-header');

        expect(header.exists()).toBe(true);
        expect(header.element.nextElementSibling)
            .toBe(w.find('.event-drawer-content').element);
    });

    // An immediate watcher calls getEvent during setup, so it has to be
    // hoisted — a const would be in its temporal dead zone
    it('mounts with an event already selected', () => {
        expect(() => render(anEvent)).not.toThrow();
        expect(render(anEvent).find('.event-drawer-header').exists()).toBe(true);
    });

    describe('opening at the top', () => {
        // The drawer is mounted once and only shown and hidden, so the box
        // it scrolls keeps the position the last event was left at.
        const scrollBox = w => w.find('.v-navigation-drawer__content').element;

        it('jumps back to the top when the drawer opens', async () => {
            const w = render(anEvent, { isDrawerOpen: false });

            scrollBox(w).scrollTop = 250;

            await w.setProps({ isDrawerOpen: true });
            await nextTick();

            expect(scrollBox(w).scrollTop).toBe(0);
        });

        it('jumps back to the top when another event is opened', async () => {
            const w = render(anEvent);

            scrollBox(w).scrollTop = 250;

            await w.setProps({ event: { ...anEvent, id: 2, title: 'Another' } });
            await nextTick();

            expect(scrollBox(w).scrollTop).toBe(0);
        });

        it('leaves the position alone while the drawer is shut', async () => {
            const w = render(anEvent, { isDrawerOpen: false });

            scrollBox(w).scrollTop = 250;

            await w.setProps({ event: { ...anEvent, id: 2 } });
            await nextTick();

            expect(scrollBox(w).scrollTop).toBe(250);
        });
    });
});
