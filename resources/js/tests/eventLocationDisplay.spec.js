import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { createPinia, setActivePinia } from 'pinia';
import en from '@/translations/en.js';
import EventLocationDisplay from '@/components/event/EventLocationDisplay.vue';

vi.mock('axios');

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const stubs = {
    'v-icon': { template: '<i :data-icon="$slots.default?.()?.[0]?.children" />' },
    'router-link': { props: ['to'], template: '<a class="internal" :data-to="JSON.stringify(to)"><slot /></a>' },
};

const render = location => mount(EventLocationDisplay, {
    props: { location },
    global: { plugins: [i18n], stubs },
});

describe('EventLocationDisplay', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('says so when the event has no location', () => {
        expect(render(null).text()).toContain(en.events.noLocationSpecified);
    });

    it('reads a legacy plain-string location as free text', () => {
        const w = render('Community hall');
        expect(w.text()).toContain('Community hall');
        expect(w.find('a').exists()).toBe(false);
    });

    it('links a physical address out to the map, in a new tab', () => {
        const w = render({ type: 'real', address: 'Marktplatz 1, Köln' });
        const link = w.find('a');

        expect(link.attributes('href')).toContain('openstreetmap.org');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener noreferrer');
    });

    it('opens an IRC channel the member is in inside the client, not the browser', () => {
        const w = render({
            type: 'virtual',
            virtualMode: 'irc',
            irc_channel: '#rivendell',
            irc_channel_id: 7,
        });

        expect(w.find('a.internal').attributes('data-to')).toContain('/irc');
        expect(w.text()).toContain('#rivendell');
    });

    it('shows a channel nobody has joined as a name rather than a dead link', () => {
        const w = render({ type: 'virtual', virtualMode: 'irc', irc_channel: '#moria', irc_channel_id: null });

        expect(w.text()).toContain('#moria');
        expect(w.find('a').exists()).toBe(false);
    });

    it('never renders a stored javascript: URL as a link', () => {
        const w = render({ type: 'virtual', virtualMode: 'url', url: 'javascript:alert(1)' });

        expect(w.find('a').exists()).toBe(false);
        expect(w.text()).toContain(en.events.noLocationSpecified);
    });
});
