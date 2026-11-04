// Reading the link report.
//
// The backend hands over three things: counts per scope and verdict, the
// hosts behind them, and as many rows as are worth sending. Turning that
// into what the screen shows — how far the check has got, which numbers
// belong in which column, what a spreadsheet of it looks like — is kept
// here so it can be tested without mounting the page.

// Where a link points. Ordered the way the report reads: the old site
// first, because those are the ones this site can actually do something
// about, then the wider web, then its own pages.
export const SCOPES = ['legacy', 'external', 'internal'];

// Verdicts, worst first.
export const STATUSES = ['broken', 'unsure', 'ok', 'pending', 'skipped'];

// Only links that leave the site are asked about by default: a path on
// this site is answered by this site, and a report full of its own pages
// says nothing about the web the content came from.
export const DEFAULT_SCOPES = ['external', 'legacy'];

// A verdict worth a colour gets one; "not asked yet" is not a verdict
// and stays the colour of everything else on the page.
export const STATUS_COLOURS = {
    broken: 'error',
    unsure: 'warning',
    ok: 'success',
    pending: null,
    skipped: null,
};

export const SCOPE_ICONS = {
    internal: 'mdi-home-outline',
    legacy: 'mdi-history',
    external: 'mdi-open-in-new',
};

const count = (value) => Number(value) || 0;

// How many links in one scope, or how many of them got one verdict.
export const scopeCount = (summary, scope, status = null) => {
    const row = summary?.scopes?.[scope];
    if (!row) return 0;

    return status === null ? count(row.links) : count(row.statuses?.[status]);
};

export const statusCount = (summary, status) => count(summary?.statuses?.[status]);

// The columns of one line of the summary table.
export const scopeRow = (summary, scope) => ({
    scope,
    links: scopeCount(summary, scope),
    uses: count(summary?.scopes?.[scope]?.uses),
    ...Object.fromEntries(STATUSES.map(status => [status, scopeCount(summary, scope, status)])),
});

// Scopes that have anything in them. A report with no links to the old
// site should not show an empty row headed "the old site".
export const presentScopes = (summary) => SCOPES.filter(scope => scopeCount(summary, scope) > 0);

// What is still waiting for an answer in the scopes being checked —
// which is what the button counts down.
export const waitingIn = (summary, scopes = DEFAULT_SCOPES) =>
    (scopes || []).reduce((total, scope) => total + scopeCount(summary, scope, 'pending'), 0);

// How far a run has got, as a percentage, given what it started with.
// Without a starting figure there is nothing to measure against, and a
// bar that invents one is worse than no bar.
export const checkProgress = (summary, scopes, started) => {
    if (!started) return null;

    const left = waitingIn(summary, scopes);
    const done = Math.max(0, started - left);

    return Math.min(100, Math.round((done / started) * 1000) / 10);
};

// Hosts worth naming: the ones with something broken behind them. One
// dead server usually accounts for a whole column of the report.
export const worstHosts = (hosts, limit = 6) =>
    (hosts || [])
        .filter(host => count(host.broken) > 0)
        .slice(0, limit);

const csvCell = (value) => {
    const text = value === null || value === undefined ? '' : String(value);

    return /[",\n;]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
};

// The report as a spreadsheet, one line per link, with the pages it sits
// on in the last column — the form somebody fixing them works from.
export const toCsv = (links, labels = {}) => {
    const header = [
        labels.url || 'URL',
        labels.host || 'Host',
        labels.scope || 'Points at',
        labels.status || 'Status',
        labels.http || 'HTTP',
        labels.uses || 'Used',
        labels.reason || 'Reason',
        labels.where || 'Found on',
    ];

    const rows = (links || []).map(link => [
        link.url,
        link.host,
        link.scope,
        link.status,
        link.http_status || '',
        link.uses,
        link.redirect_url ? `→ ${link.redirect_url}` : (link.reason || ''),
        (link.occurrences || []).map(place => place.title || place.url || place.place).join(' | '),
    ].map(csvCell).join(','));

    return [header.map(csvCell).join(','), ...rows].join('\r\n');
};
