// Shared location logic for the event feature.
//
// The API returns an event's location as a structured object
// ({ type: 'real'|'virtual'|'custom', ... }), as a legacy plain string
// (free-text custom location) or as null. These helpers normalise that
// value for the edit form and reduce it to labels/links for display, so
// CalendarEventHandler and EventPage share a single shape.

export const blankLocation = () => ({
    type: null,
    address: '',
    lat: null,
    lng: null,
    virtualMode: 'irc',
    irc_channel_id: null,
    url: '',
    text: '',
    irc_channel: null,
});

// Normalise any location value (null, legacy string, structured object)
// to the full object shape the edit form binds to.
export const normalizeLocation = (loc) => {
    const base = blankLocation();
    if (typeof loc === 'string') {
        return loc.trim() === '' ? base : { ...base, type: 'custom', text: loc };
    }
    if (loc && typeof loc === 'object') {
        return { ...base, ...loc, virtualMode: loc.virtualMode ?? 'irc' };
    }
    return base;
};

// Only plain web URLs may become clickable hrefs — a stored javascript:
// or data: URL must never be rendered as a link for other viewers.
export const isSafeWebUrl = (url) => /^https?:\/\//i.test(url);

// How a location renders in view mode: an icon, a label and (optionally)
// a link — a Google Maps search for physical addresses, the internal IRC
// client for a channel, or the raw URL for an online link.
// `t` is the i18n translate function. Returns null when there is nothing
// to show.
export const buildViewLocation = (loc, t) => {
    if (!loc || !loc.type) return null;

    if (loc.type === 'real') {
        const address = (loc.address || '').trim();
        const hasCoords = loc.lat != null && loc.lng != null && loc.lat !== '' && loc.lng !== '';
        if (!address && !hasCoords) return null;
        const query = hasCoords ? `${loc.lat},${loc.lng}` : address;
        return {
            icon: 'mdi-map-marker',
            label: address || `${loc.lat}, ${loc.lng}`,
            href: `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`,
            external: true,
        };
    }

    if (loc.type === 'virtual') {
        if (loc.virtualMode === 'irc') {
            if (!loc.irc_channel_id) return null;
            const name = loc.irc_channel ? String(loc.irc_channel).replace(/^#/, '') : null;
            return {
                icon: 'mdi-pound',
                label: name ? `#${name}` : t('events.locationIrcChannel'),
                to: { path: '/irc', query: { channel: loc.irc_channel_id } },
            };
        }
        const url = (loc.url || '').trim();
        if (!isSafeWebUrl(url)) return null;
        return { icon: 'mdi-link-variant', label: url, href: url, external: true };
    }

    const text = (loc.text || '').trim();
    return text ? { icon: 'mdi-map-marker-outline', label: text } : null;
};

// Reduce a location to a plain-text label for list subtitles.
export const formatEventLocationLabel = (event, t) => {
    const loc = event.location ?? event.extendedProps?.location;
    if (typeof loc === 'string') return loc.trim() || t('events.noLocation');
    if (!loc || !loc.type) return t('events.noLocation');
    switch (loc.type) {
        case 'real': {
            const hasCoords = loc.lat != null && loc.lng != null && loc.lat !== '' && loc.lng !== '';
            return loc.address || (hasCoords ? `${loc.lat}, ${loc.lng}` : t('events.noLocation'));
        }
        case 'virtual':
            if (loc.virtualMode === 'irc') {
                return loc.irc_channel
                    ? `#${String(loc.irc_channel).replace(/^#/, '')}`
                    : t('events.locationIrcChannel');
            }
            return loc.url || t('events.noLocation');
        default:
            return loc.text || t('events.noLocation');
    }
};

// Raw API items (list/upcoming views) carry these fields at top level;
// only FullCalendar transposes them into extendedProps. Merging them in
// gives the drawer a single shape to read — and stops a save of a
// list-opened event from wiping its stored location.
const TOP_LEVEL_EVENT_KEYS = [
    'location',
    'event_type_id',
    'description',
    'type',
    'user_id',
    'event_profile_id',
];

export const mergeTopLevelIntoExtendedProps = (event) => {
    const ep = event.extendedProps = event.extendedProps || {};
    for (const key of TOP_LEVEL_EVENT_KEYS) {
        if (ep[key] === undefined && event[key] !== undefined) {
            ep[key] = event[key];
        }
    }
    ep.location = normalizeLocation(ep.location);
    return event;
};
