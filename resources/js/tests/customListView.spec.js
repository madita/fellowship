import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

// Standing in at the module boundary: sliceEvents is fed fixtures, and
// createPlugin hands the view config straight back so it can be driven.
const sliceEvents = vi.fn();

vi.mock('@fullcalendar/core', () => ({
    sliceEvents: (...args) => sliceEvents(...args),
    createPlugin: config => config,
}));

const { default: plugin } = await import('@/components/event/custom-list-view.js');
const { eventBus } = await import('@/components/common/eventBus.js');

const view = plugin.views.custom;

// A day's worth of milliseconds, for building exclusive slice ends
const DAY = 24 * 60 * 60 * 1000;

const seg = ({
    title = 'Council of Elrond',
    allDay = false,
    start = '2099-03-04T18:30:00Z',
    end = '2099-03-04T21:00:00Z',
    rangeStart = new Date('2099-03-04T00:00:00Z'),
    rangeEnd = new Date('2099-03-05T00:00:00Z'),
    colorName = '#071CB4',
    type = 'Treffen',
    location = null,
    publicId = '7',
} = {}) => ({
    def: {
        defId: publicId,
        publicId,
        title,
        allDay,
        extendedProps: { originDate: { start, end }, colorName, type, location },
    },
    range: { start: rangeStart, end: rangeEnd },
});

const render = (segs) => {
    sliceEvents.mockReturnValue(segs);

    const { domNodes } = view.content({});

    return domNodes[0];
};

describe('the calendar list view', () => {
    beforeEach(() => {
        // Node's own global localStorage shadows jsdom's; the user store
        // reads it as it is created
        const store = new Map();
        vi.stubGlobal('localStorage', {
            getItem: k => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: k => store.delete(k),
            clear: () => store.clear(),
        });

        setActivePinia(createPinia());
        sliceEvents.mockReset();
    });

    it('says so when there is nothing on', () => {
        const list = render([]);

        expect(list.querySelector('.event-list__empty')).not.toBeNull();
        expect(list.querySelectorAll('.event-list__item')).toHaveLength(0);
    });

    it('groups events under a header for the day they fall on', () => {
        const list = render([seg()]);

        expect(list.querySelectorAll('.event-list__day')).toHaveLength(1);
        expect(list.querySelector('.event-list__weekday').textContent).toBeTruthy();
        expect(list.querySelectorAll('.event-list__item')).toHaveLength(1);
        expect(list.querySelector('.event-list__title').textContent).toBe('Council of Elrond');
    });

    /**
     * Titles are written by members. The view used to build its markup as a
     * string, so a title carrying tags became part of the document.
     */
    it('renders a title containing markup as text', () => {
        const list = render([seg({ title: '<img src=x onerror=alert(1)>Boom' })]);
        const title = list.querySelector('.event-list__title');

        expect(title.textContent).toBe('<img src=x onerror=alert(1)>Boom');
        expect(title.querySelector('img')).toBeNull();
        expect(list.querySelector('img')).toBeNull();
    });

    // The colour was being written as a Vue binding inside an HTML string,
    // so it never reached the element and every dot came out grey
    it('paints the bar with the event type colour', () => {
        const list = render([seg({ colorName: '#071CB4' })]);

        expect(list.querySelector('.event-list__bar').style.backgroundColor)
            .toBe('rgb(7, 28, 180)');
    });

    it('leaves the bar at its default when the type has no colour', () => {
        const list = render([seg({ colorName: null })]);

        expect(list.querySelector('.event-list__bar').style.backgroundColor).toBe('');
    });

    it('marks an all-day event as such instead of showing a clock', () => {
        const list = render([seg({ allDay: true })]);

        expect(list.querySelector('.event-list__time').textContent).toBe('All Day');
    });

    it('shows the hours of a timed event as a range', () => {
        const list = render([seg()]);

        expect(list.querySelector('.event-list__time').textContent).toMatch(/\d{1,2}:\d{2}\s–\s\d{1,2}:\d{2}/);
    });

    /**
     * A slice end is exclusive: an event through the 4th and 5th ends at
     * midnight on the 6th. Counting that as a third day — which the old
     * loop did — listed the event on a day it does not run.
     */
    it('lists a multi-day event on each day it actually runs', () => {
        const list = render([seg({
            rangeStart: new Date('2099-03-04T00:00:00Z'),
            rangeEnd: new Date('2099-03-06T00:00:00Z'),
        })]);

        expect(list.querySelectorAll('.event-list__day')).toHaveLength(2);
        expect(list.querySelectorAll('.event-list__item')).toHaveLength(2);
    });

    it('points the times of a multi-day event at the days between', () => {
        const list = render([seg({
            rangeStart: new Date('2099-03-04T00:00:00Z'),
            rangeEnd: new Date('2099-03-07T00:00:00Z'),
        })]);
        const times = [...list.querySelectorAll('.event-list__time')].map(node => node.textContent);

        expect(times).toHaveLength(3);
        expect(times[0]).toMatch(/→$/);
        expect(times[1]).toBe('All Day');
        expect(times[2]).toMatch(/^→/);
    });

    // Grouping through toISOString() files a late event under the next day
    // for anyone whose timezone runs ahead of UTC
    it('files an event under the day it falls on in the shown timezone', () => {
        const lateEvening = new Date('2099-03-04T23:30:00Z');
        const list = render([seg({
            rangeStart: lateEvening,
            rangeEnd: new Date(lateEvening.getTime() + DAY),
        })]);

        const shown = list.querySelector('.event-list__date').textContent;

        expect(shown).toContain('04');
    });

    it('shows where the event is when it has somewhere', () => {
        const list = render([seg({
            location: { type: 'virtual', virtualMode: 'irc', irc_channel: '#rivendell' },
        })]);

        expect(list.querySelector('.event-list__where').textContent).toBe('#rivendell');
    });

    it('leaves out the location line when there is none', () => {
        const list = render([seg({ location: null })]);

        expect(list.querySelector('.event-list__where')).toBeNull();
    });

    it('dims an event that has already happened', () => {
        const past = render([seg({ start: '2020-01-01T10:00:00Z', end: '2020-01-01T11:00:00Z' })]);
        const ahead = render([seg()]);

        expect(past.querySelector('.event-list__row').classList).toContain('event-list__row--past');
        expect(ahead.querySelector('.event-list__row').classList).not.toContain('event-list__row--past');
    });

    /**
     * The rows were anchors with no href, which take no keyboard focus — so
     * the list could only be used with a mouse.
     */
    it('makes each row a button, so it can be reached from the keyboard', () => {
        const list = render([seg()]);
        const row = list.querySelector('.event-list__row');

        expect(row.tagName).toBe('BUTTON');
        expect(row.type).toBe('button');
    });

    it('opens the event when its row is used', () => {
        const emit = vi.spyOn(eventBus, 'emit');
        const list = render([seg({ publicId: '42' })]);

        list.querySelector('.event-list__row').click();

        expect(emit).toHaveBeenCalledWith('openSidebarWithEvent', expect.objectContaining({
            id: '42',
            title: 'Council of Elrond',
            start: '2099-03-04T18:30:00Z',
            end: '2099-03-04T21:00:00Z',
        }));

        emit.mockRestore();
    });
});
