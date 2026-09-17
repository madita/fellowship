/**
 * The ticket list keeps its filters in the address. The ticket page reuses
 * the last list address: for its "back" link and for the ticket drawer,
 * which shows the same filtered tickets.
 */

export const TICKET_FILTER_KEYS = ['status', 'type', 'assigned_to', 'priority', 'search', 'mine', 'created_by', 'due'];

const storageKey = (listRouteName) => `tickets.listQuery.${listRouteName}`;

export const defaultTicketFilters = () => ({
    status: 'open', type: null, assigned_to: null, priority: null, search: '', mine: null, created_by: null, due: null,
});

const queryValue = (query, key) => (typeof query[key] === 'string' && query[key] !== '' ? query[key] : null);

export function ticketFiltersFromQuery(query) {
    return {
        ...defaultTicketFilters(),
        ...Object.fromEntries(TICKET_FILTER_KEYS.map(key => [key, queryValue(query, key)])),
        // No status in the address means the default (open); an empty one means all
        status: query.status === undefined ? 'open' : queryValue(query, 'status'),
        search: queryValue(query, 'search') || '',
    };
}

// API parameters for GET /api/tickets
export function ticketFilterParams(filters) {
    return Object.fromEntries(TICKET_FILTER_KEYS.filter(key => filters[key]).map(key => [key, filters[key]]));
}

export function rememberTicketListQuery(listRouteName, query) {
    try {
        sessionStorage.setItem(storageKey(listRouteName), JSON.stringify(query));
    } catch {
        // Storage unavailable: the ticket page falls back to the plain list
    }
}

export function rememberedTicketListQuery(listRouteName) {
    try {
        return JSON.parse(sessionStorage.getItem(storageKey(listRouteName))) || {};
    } catch {
        return {};
    }
}
