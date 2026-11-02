/**
 * The pages a tour can be attached to, read from the router itself.
 *
 * Asking an admin to type a path means asking them to know it; the router
 * already holds every page the app has, so the editor offers that list and
 * keeps the free-text field for the cases it cannot express.
 *
 * A route with parameters becomes a pattern: `/wiki/:slug` is offered as
 * `/wiki/*`, because help written for a wiki page is written for all of
 * them, not for one.
 */

/** Routes nobody would write help for. */
const SKIP_NAMES = ['error', 'access-denied', 'page'];

/**
 * @param {Array} routes - what `router.getRoutes()` returns
 * @returns {Array<{name: string, path: string, pattern: string, label: string}>}
 */
export function helpRouteOptions(routes = []) {
    const seen = new Set();

    return routes
        .filter(route => route.name && !SKIP_NAMES.includes(route.name))
        // The catch-all would offer a pattern matching the entire site
        .filter(route => !String(route.path).includes(':pathMatch'))
        .map(route => ({
            name: String(route.name),
            path: route.path,
            pattern: patternFor(route.path),
            label: labelFor(route.name),
        }))
        .filter(option => {
            // Several routes can collapse to the same pattern — every wiki
            // page, say — and the list only needs it once
            if (seen.has(option.pattern)) return false;

            seen.add(option.pattern);

            return true;
        })
        .sort((a, b) => a.pattern.localeCompare(b.pattern));
}

/**
 * The pattern that matches a route's pages. Everything from the first
 * parameter onwards collapses into a single `*`, so `/forum/:id/:slug?`
 * is `/forum/*` rather than `/forum/*\/*` — which would miss the page
 * where the optional half is absent.
 */
export function patternFor(path) {
    const parts = String(path).split('/');
    const first = parts.findIndex(part => part.startsWith(':'));

    if (first === -1) return normalise(path);

    return normalise([...parts.slice(0, first), '*'].join('/'));
}

/** 'admin-settings-category' reads as 'Admin settings category'. */
export function labelFor(name) {
    const words = String(name).replace(/[-_]+/g, ' ').trim();

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function normalise(path) {
    const trimmed = String(path).replace(/\/+$/, '');

    return trimmed === '' ? '/' : trimmed;
}
