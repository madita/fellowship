import { sliceEvents, createPlugin } from '@fullcalendar/core';
import { i18n } from '@/plugins/vue-i18n.js';
import { eventBus } from '../common/eventBus.js';
import { formatDate } from '@/plugins/formatDate.js';
import { useUserStore } from '@/store/userStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import { formatEventLocationLabel } from '@/utils/eventLocation.js';
import { hasEventEnded } from '@/utils/eventTime.js';
import './custom-list-view.css';

const t = (key) => i18n.global.t(key);

// The member's clock preference, without seconds — a list is tighter than a grid
function userTimeFormat() {
    const timeFormat = useUserStore().user?.time_format
        || useSettingsStore().appSettings?.time_format
        || 'H:i:s';

    return timeFormat.replace(':s', '').replace(' s', '');
}

const pad = number => String(number).padStart(2, '0');

/**
 * The calendar date an instant falls on, as "2026-10-01".
 *
 * Read in whichever timezone the member's times are shown in, so the day
 * an event is filed under agrees with the clock printed beside it. Going
 * through toISOString() instead — as this view used to — files an evening
 * event under the next day for anyone east of UTC.
 */
function dayKey(instant) {
    return formatDate(instant, 'Y-m-d');
}

// Plain arithmetic on the key itself. No timezone is involved, so stepping
// over a daylight-saving change cannot repeat or skip a day.
function nextDayKey(key) {
    const [year, month, day] = key.split('-').map(Number);
    const next = new Date(Date.UTC(year, month - 1, day + 1));

    return `${next.getUTCFullYear()}-${pad(next.getUTCMonth() + 1)}-${pad(next.getUTCDate())}`;
}

/**
 * Every day an event covers.
 *
 * FullCalendar's slice end is exclusive — an all-day event on the 1st ends
 * at midnight on the 2nd — so a moment comes off before asking which day
 * it finishes on, or every event would claim a day it does not run on.
 */
function daysCovered(range) {
    const last = dayKey(new Date(range.end.getTime() - 1));
    const days = [dayKey(range.start)];

    // The cap is a guard: a range whose end precedes its start would
    // otherwise never reach the last day
    while (days[days.length - 1] !== last && days.length < 366) {
        days.push(nextDayKey(days[days.length - 1]));
    }

    return days;
}

function el(tag, className, text) {
    const node = document.createElement(tag);

    if (className) node.className = className;
    // textContent, so a title containing markup stays a title
    if (text !== undefined && text !== null) node.textContent = text;

    return node;
}

/**
 * How a day's slice of an event reads on the clock.
 *
 * A single-day event shows its own hours. A longer one shows when it opens
 * on the first day and when it closes on the last, with an arrow pointing
 * at the days between — which carry no times of their own.
 */
function timeLabel(def, position) {
    const origin = def.extendedProps?.originDate;

    if (def.allDay || !origin) return t('events.allDay');

    const format = userTimeFormat();
    const from = origin.start ? formatDate(origin.start, format) : '';
    const until = origin.end ? formatDate(origin.end, format) : '';

    if (position.isStart && position.isEnd) {
        return until && until !== from ? `${from} – ${until}` : from;
    }

    if (position.isStart) return `${from} →`;
    if (position.isEnd) return `→ ${until}`;

    return t('events.allDay');
}

// Today and tomorrow are worth naming; everything else is just its date.
function relativeDayName(key) {
    const today = dayKey(new Date());
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (key === today) return t('events.today');
    if (key === dayKey(tomorrow)) return t('events.tomorrow');

    return null;
}

function dayHeader(key) {
    const [year, month, day] = key.split('-').map(Number);
    // Built from the key and formatted without the timezone step, so the
    // header reads as the day it is keyed by rather than being shifted
    // across midnight a second time
    const date = new Date(year, month - 1, day);
    const show = format => formatDate(date, format, { useTimezone: false });

    const header = el('div', 'event-list__day-header');
    const heading = el('div', 'event-list__day-heading');

    heading.append(
        el('span', 'event-list__weekday', show('l')),
        el('span', 'event-list__date', show())
    );

    header.append(heading);

    const relative = relativeDayName(key);
    if (relative) header.append(el('span', 'event-list__badge', relative));

    return header;
}

/**
 * One event on one day, as a button.
 *
 * A button rather than a bare anchor so it can be reached and opened from
 * the keyboard; the old markup used an <a> with no href, which takes no
 * focus at all.
 */
function eventRow(entry, openEvent) {
    const { def, position } = entry;
    const colour = def.extendedProps?.colorName || def.extendedProps?.color;

    const item = el('li', 'event-list__item');
    const row = el('button', 'event-list__row');
    row.type = 'button';

    if (hasEventEnded({ start: def.extendedProps?.originDate?.start, end: def.extendedProps?.originDate?.end })) {
        row.classList.add('event-list__row--past');
    }

    const bar = el('span', 'event-list__bar');
    if (colour) bar.style.backgroundColor = colour;

    const body = el('span', 'event-list__body');
    body.append(el('span', 'event-list__title', def.title));

    // Where it is, and what kind of event it is — the two things worth
    // knowing before deciding to open it
    const meta = el('span', 'event-list__meta');
    const where = formatEventLocationLabel({ location: def.extendedProps?.location }, t);

    if (where && where !== t('events.noLocation')) {
        meta.append(el('span', 'event-list__where', where));
    }

    if (def.extendedProps?.type) {
        const type = el('span', 'event-list__type', def.extendedProps.type);
        if (colour) type.style.setProperty('--event-type-colour', colour);
        meta.append(type);
    }

    if (meta.childElementCount) body.append(meta);

    row.append(
        bar,
        el('span', 'event-list__time', timeLabel(def, position)),
        body
    );

    row.addEventListener('click', () => openEvent(def));
    item.append(row);

    return item;
}

function emptyState() {
    const empty = el('div', 'event-list__empty');

    empty.append(
        el('div', 'event-list__empty-icon', '📅'),
        el('p', 'event-list__empty-text', t('events.noEventsFound'))
    );

    return empty;
}

const CustomViewConfig = {
    classNames: ['custom-view'],
    duration: { month: 1 },
    type: 'list',

    content(props) {
        const segs = sliceEvents(props, true); // allDay=true

        // day key → the events running that day, in the order they start
        const byDay = new Map();

        segs.forEach(seg => {
            const days = daysCovered(seg.range);

            days.forEach((key, index) => {
                if (!byDay.has(key)) byDay.set(key, []);

                byDay.get(key).push({
                    def: seg.def,
                    start: seg.range.start,
                    position: { isStart: index === 0, isEnd: index === days.length - 1 },
                });
            });
        });

        const openEvent = (def) => {
            const event = { ...def };

            event.start = def.extendedProps?.originDate?.start;
            event.end = def.extendedProps?.originDate?.end;
            event.id = def.publicId;

            eventBus.emit('openSidebarWithEvent', event);
        };

        const list = el('div', 'event-list');

        if (!byDay.size) {
            list.append(emptyState());

            return { domNodes: [list] };
        }

        [...byDay.keys()].sort().forEach(key => {
            const section = el('section', 'event-list__day');
            const events = el('ul', 'event-list__events');

            byDay.get(key)
                .sort((a, b) => a.start - b.start)
                .forEach(entry => events.append(eventRow(entry, openEvent)));

            section.append(dayHeader(key), events);
            list.append(section);
        });

        return { domNodes: [list] };
    },
};

export default createPlugin({
    views: {
        custom: CustomViewConfig,
    },
});
