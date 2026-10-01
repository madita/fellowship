import { describe, it, expect } from 'vitest';
import {
    blankLocation,
    normalizeLocation,
    isSafeWebUrl,
    buildViewLocation,
    formatEventLocationLabel,
    mergeTopLevelIntoExtendedProps,
    mapSearchUrl,
} from '@/utils/eventLocation.js';

// i18n stand-in: returns the key so assertions can match on it.
const t = (key) => key;

describe('normalizeLocation', () => {
    it('returns the blank shape for null/undefined', () => {
        expect(normalizeLocation(null)).toEqual(blankLocation());
        expect(normalizeLocation(undefined)).toEqual(blankLocation());
    });

    it('treats a legacy plain string as a custom location', () => {
        const loc = normalizeLocation('Community hall');
        expect(loc.type).toBe('custom');
        expect(loc.text).toBe('Community hall');
    });

    it('treats an empty/whitespace string as no location', () => {
        expect(normalizeLocation('   ').type).toBeNull();
    });

    it('merges a structured object over the blank shape', () => {
        const loc = normalizeLocation({ type: 'real', address: 'Main St 1' });
        expect(loc.type).toBe('real');
        expect(loc.address).toBe('Main St 1');
        // Untouched keys keep their defaults so the edit form can bind.
        expect(loc.url).toBe('');
        expect(loc.irc_channel_id).toBeNull();
    });

    it('defaults virtualMode to irc when missing', () => {
        expect(normalizeLocation({ type: 'virtual' }).virtualMode).toBe('irc');
        expect(normalizeLocation({ type: 'virtual', virtualMode: 'url' }).virtualMode).toBe('url');
    });
});

describe('isSafeWebUrl', () => {
    it('accepts http(s) URLs only', () => {
        expect(isSafeWebUrl('https://example.com')).toBe(true);
        expect(isSafeWebUrl('http://example.com')).toBe(true);
        expect(isSafeWebUrl('HTTPS://EXAMPLE.COM')).toBe(true);
    });

    it('rejects other schemes and scheme-less strings', () => {
        expect(isSafeWebUrl('javascript:alert(1)')).toBe(false);
        expect(isSafeWebUrl('data:text/html,<script>')).toBe(false);
        expect(isSafeWebUrl('example.com/meet')).toBe(false);
        expect(isSafeWebUrl('')).toBe(false);
    });
});

describe('buildViewLocation', () => {
    it('returns null without a location or type', () => {
        expect(buildViewLocation(null, t)).toBeNull();
        expect(buildViewLocation(blankLocation(), t)).toBeNull();
    });

    it('builds a Google Maps link for a real address', () => {
        const view = buildViewLocation({ type: 'real', address: 'Main St 1, Berlin' }, t, 'google');
        expect(view.label).toBe('Main St 1, Berlin');
        expect(view.external).toBe(true);
        expect(view.href).toBe(
            `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent('Main St 1, Berlin')}`
        );
    });

    it('prefers coordinates for the maps query and labels them when no address', () => {
        const view = buildViewLocation({ type: 'real', address: '', lat: 52.5, lng: 13.4 }, t, 'google');
        expect(view.label).toBe('52.5, 13.4');
        expect(view.href).toContain(encodeURIComponent('52.5,13.4'));
    });

    it('returns null for a real location with neither address nor coordinates', () => {
        expect(buildViewLocation({ type: 'real', address: '  ', lat: null, lng: null }, t)).toBeNull();
    });

    it('links an IRC channel into the internal client with the channel query', () => {
        const view = buildViewLocation(
            { type: 'virtual', virtualMode: 'irc', irc_channel_id: 5, irc_channel: '#general' },
            t
        );
        expect(view.label).toBe('#general');
        expect(view.to).toEqual({ path: '/irc', query: { channel: 5 } });
        expect(view.href).toBeUndefined();
    });

    it('falls back to a translated label when the channel name is unknown', () => {
        const view = buildViewLocation({ type: 'virtual', virtualMode: 'irc', irc_channel_id: 5 }, t);
        expect(view.label).toBe('events.locationIrcChannel');
    });

    it('returns null for an IRC location without a channel id', () => {
        expect(buildViewLocation({ type: 'virtual', virtualMode: 'irc', irc_channel_id: null }, t)).toBeNull();
    });

    it('links a plain web URL', () => {
        const view = buildViewLocation({ type: 'virtual', virtualMode: 'url', url: 'https://meet.example.com/x' }, t);
        expect(view.href).toBe('https://meet.example.com/x');
        expect(view.external).toBe(true);
    });

    it('never links javascript:/scheme-less URLs', () => {
        expect(buildViewLocation({ type: 'virtual', virtualMode: 'url', url: 'javascript:alert(document.cookie)' }, t)).toBeNull();
        expect(buildViewLocation({ type: 'virtual', virtualMode: 'url', url: 'example.com' }, t)).toBeNull();
    });

    it('renders custom text without a link', () => {
        const view = buildViewLocation({ type: 'custom', text: 'At the lake' }, t);
        expect(view.label).toBe('At the lake');
        expect(view.href).toBeUndefined();
        expect(view.to).toBeUndefined();
    });

    it('returns null for empty custom text', () => {
        expect(buildViewLocation({ type: 'custom', text: '   ' }, t)).toBeNull();
    });
});

describe('formatEventLocationLabel', () => {
    it('never JSON-stringifies the location object', () => {
        const label = formatEventLocationLabel({ location: { type: 'custom', text: 'Town square' } }, t);
        expect(label).toBe('Town square');
        expect(label).not.toContain('{');
    });

    it('reads extendedProps.location as fallback', () => {
        const label = formatEventLocationLabel(
            { extendedProps: { location: { type: 'real', address: 'Main St 1' } } },
            t
        );
        expect(label).toBe('Main St 1');
    });

    it('supports legacy string locations', () => {
        expect(formatEventLocationLabel({ location: 'Old style place' }, t)).toBe('Old style place');
    });

    it('falls back to the no-location label', () => {
        expect(formatEventLocationLabel({}, t)).toBe('events.noLocation');
        expect(formatEventLocationLabel({ location: null }, t)).toBe('events.noLocation');
        expect(formatEventLocationLabel({ location: '  ' }, t)).toBe('events.noLocation');
    });

    it('labels coordinates, IRC channels and URLs', () => {
        expect(formatEventLocationLabel({ location: { type: 'real', lat: 1, lng: 2 } }, t)).toBe('1, 2');
        expect(formatEventLocationLabel(
            { location: { type: 'virtual', virtualMode: 'irc', irc_channel: '#dev' } }, t
        )).toBe('#dev');
        expect(formatEventLocationLabel(
            { location: { type: 'virtual', virtualMode: 'url', url: 'https://x.test' } }, t
        )).toBe('https://x.test');
    });
});

describe('mergeTopLevelIntoExtendedProps', () => {
    it('copies raw API item fields into extendedProps (list-opened events)', () => {
        const event = mergeTopLevelIntoExtendedProps({
            id: 1,
            title: 'Meetup',
            location: { type: 'custom', text: 'Town square' },
            event_type_id: 3,
            description: 'Bring snacks',
            type: 'Treffen',
            user_id: 9,
            event_profile_id: 2,
        });
        expect(event.extendedProps.location.text).toBe('Town square');
        expect(event.extendedProps.event_type_id).toBe(3);
        expect(event.extendedProps.description).toBe('Bring snacks');
        expect(event.extendedProps.type).toBe('Treffen');
        expect(event.extendedProps.user_id).toBe(9);
        expect(event.extendedProps.event_profile_id).toBe(2);
    });

    it('does not overwrite values already in extendedProps (calendar-opened events)', () => {
        const event = mergeTopLevelIntoExtendedProps({
            id: 1,
            event_type_id: 99, // stale top-level copy
            extendedProps: {
                event_type_id: 3,
                location: { type: 'real', address: 'Main St 1' },
            },
        });
        expect(event.extendedProps.event_type_id).toBe(3);
        expect(event.extendedProps.location.address).toBe('Main St 1');
    });

    it('always leaves a fully-shaped location for the edit form', () => {
        const event = mergeTopLevelIntoExtendedProps({ id: 1, title: 'Bare' });
        expect(event.extendedProps.location).toEqual(blankLocation());
    });

    it('normalises legacy string locations while merging', () => {
        const event = mergeTopLevelIntoExtendedProps({ id: 1, location: 'Old hall' });
        expect(event.extendedProps.location.type).toBe('custom');
        expect(event.extendedProps.location.text).toBe('Old hall');
    });
});

describe('map links follow the configured provider', () => {
    const point = { type: 'real', address: 'Community hall', lat: 51.5, lng: -0.12 };

    it('sends a picked point to OpenStreetMap by default', () => {
        const view = buildViewLocation(point, t);

        expect(view.href).toContain('openstreetmap.org');
        expect(view.href).toContain('mlat=51.5');
        expect(view.label).toBe('Community hall');
    });

    it('sends the same point to Google when that is the provider', () => {
        const view = buildViewLocation(point, t, 'google');

        expect(view.href).toContain('google.com/maps');
        expect(view.href).toContain('51.5%2C-0.12');
    });

    it('searches the address when no point was picked', () => {
        const address = { type: 'real', address: 'Community hall' };

        expect(mapSearchUrl(address, 'osm')).toContain('search?query=Community%20hall');
        expect(mapSearchUrl(address, 'google')).toContain('query=Community%20hall');
    });

    it('has nowhere to send an empty location', () => {
        expect(mapSearchUrl({ type: 'real', address: '' })).toBeNull();
    });
});

describe('an IRC channel as a location', () => {
    it('links to the window of a channel the member is in', () => {
        const view = buildViewLocation(
            { type: 'virtual', virtualMode: 'irc', irc_channel_id: 7, irc_channel: '#shire' },
            t
        );

        expect(view.label).toBe('#shire');
        expect(view.to).toEqual({ path: '/irc', query: { channel: 7 } });
    });

    /**
     * An event is often held in a channel nobody has joined yet, so the
     * name reads on its own rather than linking nowhere.
     */
    it('names a channel that has no window yet, without a link', () => {
        const view = buildViewLocation(
            { type: 'virtual', virtualMode: 'irc', irc_channel: '#moot' },
            t
        );

        expect(view.label).toBe('#moot');
        expect(view.to).toBeUndefined();
        expect(view.href).toBeUndefined();
    });

    it('shows nothing when there is neither a channel nor a name', () => {
        expect(buildViewLocation({ type: 'virtual', virtualMode: 'irc' }, t)).toBeNull();
    });

    it('labels a list row with the channel name', () => {
        const label = formatEventLocationLabel(
            { location: { type: 'virtual', virtualMode: 'irc', irc_channel: '#moot' } },
            t
        );

        expect(label).toBe('#moot');
    });
});
