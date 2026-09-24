import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import en from '@/translations/en.js';
import EventShow from '@/pages/events/EventShow.vue';

vi.mock('axios');
vi.mock('@/composables/useDialog.js', () => ({
    useDialog: () => ({ requestError: vi.fn(() => Promise.resolve()), success: vi.fn(() => Promise.resolve()) }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/events', name: 'events', component: { template: '<div/>' } },
        { path: '/events/:id', name: 'event-show', component: EventShow },
        { path: '/events/:id/edit', name: 'event-edit', component: { template: '<div/>' } },
    ],
});

// The shape EventController@show actually returns, taken from the endpoint.
const eventPayload = (overrides = {}) => ({
    id: 1, user_id: 1, slug: 'test-event',
    startDate: '2026-09-24', endDate: '2026-09-26',
    startTime: null, endTime: null, allDay: null, event_type_id: 1,
    start: '2026-09-24T00:00:00Z', end: '2026-09-26T23:59:59Z',
    location: null, title: 'Test Event', description: '<p>Hello there</p>',
    ...overrides,
});

const render = async (event = eventPayload(), isGoing = null) => {
    axios.get.mockResolvedValue({ data: { event, isGoing, answers: {}, guests: [] } });

    router.push('/events/1');
    await router.isReady();

    const wrapper = mount(EventShow, {
        global: {
            plugins: [i18n, router],
            stubs: { 'page-header': { props: ['title', 'subtitle'], template: '<header><h1>{{ title }}</h1><p class="sub">{{ subtitle }}</p><slot name="actions" /></header>' } },
        },
    });
    await flushPromises();
    return wrapper;
};

describe('EventShow', () => {
    beforeEach(() => {
        const data = new Map();
        vi.stubGlobal('localStorage', {
            getItem: k => (data.has(k) ? data.get(k) : null),
            setItem: (k, v) => data.set(k, String(v)),
            removeItem: k => data.delete(k), clear: () => data.clear(),
        });
        setActivePinia(createPinia());
    });

    // A member who has not answered yet arrives with isGoing null, which
    // used to throw mid-render and leave nothing but the page header.
    it('renders the body for a member who has not answered yet', async () => {
        const w = await render();

        expect(w.text()).toContain('Hello there');
        expect(w.text()).toContain(en.events.areYouComing);
        expect(w.text()).toContain(en.events.location);
    });

    it('still renders once an answer has been given', async () => {
        const w = await render(eventPayload(), { type: 'going' });
        expect(w.text()).toContain('Hello there');
    });

    it('shows an all-day event as dates alone, with no 00:00 hanging off them', async () => {
        const w = await render();
        const subtitle = w.find('.sub').text();

        expect(subtitle).not.toContain('00:00');
        // Two days, so a range — the separator, not the dashes inside Y-m-d
        expect(subtitle).toContain(' - ');
    });

    it('names a single all-day date once', async () => {
        const w = await render(eventPayload({ endDate: '2026-09-24', end: '2026-09-24T23:59:59Z' }));

        expect(w.find('.sub').text()).not.toContain(' - ');
    });

    it('shows the clock for an event that has a start time', async () => {
        const w = await render(eventPayload({
            startTime: '18:30:00', endTime: '21:00:00',
            endDate: '2026-09-24',
            start: '2026-09-24T18:30:00Z', end: '2026-09-24T21:00:00Z',
        }));

        expect(w.find('.sub').text()).toMatch(/\d{1,2}:\d{2}/);
    });
});
