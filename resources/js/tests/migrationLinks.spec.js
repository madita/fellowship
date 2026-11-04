import { describe, it, expect } from 'vitest';
import {
    DEFAULT_SCOPES,
    STATUS_COLOURS,
    checkProgress,
    presentScopes,
    scopeCount,
    scopeRow,
    statusCount,
    toCsv,
    waitingIn,
    worstHosts,
} from '@/utils/migrationLinks.js';

const summary = {
    links: 9,
    uses: 20,
    scopes: {
        external: { links: 5, uses: 12, statuses: { broken: 2, ok: 1, pending: 2 } },
        legacy: { links: 3, uses: 6, statuses: { ok: 3 } },
        internal: { links: 1, uses: 2, statuses: { pending: 1 } },
    },
    statuses: { broken: 2, ok: 4, pending: 3 },
    pending: 2,
};

describe('reading the counts', () => {
    it('counts the links in a scope, and the verdicts within it', () => {
        expect(scopeCount(summary, 'external')).toBe(5);
        expect(scopeCount(summary, 'external', 'broken')).toBe(2);
        expect(scopeCount(summary, 'legacy', 'broken')).toBe(0);
        expect(statusCount(summary, 'ok')).toBe(4);
    });

    it('answers zero rather than breaking on a report that was never run', () => {
        expect(scopeCount({}, 'external')).toBe(0);
        expect(scopeCount(undefined, 'external', 'broken')).toBe(0);
        expect(statusCount(null, 'ok')).toBe(0);
        expect(waitingIn(undefined)).toBe(0);
        expect(presentScopes(undefined)).toEqual([]);
    });

    it('builds a summary line with every verdict filled in', () => {
        const row = scopeRow(summary, 'external');

        expect(row).toMatchObject({ scope: 'external', links: 5, uses: 12, broken: 2, ok: 1, pending: 2 });
        // A verdict nothing landed in is still a column
        expect(row.unsure).toBe(0);
        expect(row.skipped).toBe(0);
    });

    it('leaves out a scope the content never points at', () => {
        expect(presentScopes(summary)).toEqual(['legacy', 'external', 'internal']);
        expect(presentScopes({ scopes: { external: { links: 2 } } })).toEqual(['external']);
    });
});

describe('what is left to ask', () => {
    it('adds up only the scopes being checked', () => {
        // Links to this site are not asked about unless that is turned on
        expect(waitingIn(summary, DEFAULT_SCOPES)).toBe(2);
        expect(waitingIn(summary, ['external', 'legacy', 'internal'])).toBe(3);
    });

    it('measures progress against what the run started with', () => {
        expect(checkProgress(summary, DEFAULT_SCOPES, 10)).toBe(80);
        expect(checkProgress({ ...summary, scopes: {} }, DEFAULT_SCOPES, 10)).toBe(100);
    });

    it('shows no progress bar when there is nothing to measure against', () => {
        // Inventing a figure would be worse than admitting we have none
        expect(checkProgress(summary, DEFAULT_SCOPES, 0)).toBe(null);
        expect(checkProgress(summary, DEFAULT_SCOPES, undefined)).toBe(null);
    });
});

describe('the hosts behind the broken links', () => {
    it('names only the ones with something broken, worst first', () => {
        const hosts = [
            { host: 'dead.example', links: 9, broken: 9 },
            { host: 'alive.example', links: 4, broken: 0 },
            { host: 'half.example', links: 6, broken: 2 },
        ];

        expect(worstHosts(hosts).map(host => host.host)).toEqual(['dead.example', 'half.example']);
        expect(worstHosts(hosts, 1)).toHaveLength(1);
        expect(worstHosts(undefined)).toEqual([]);
    });
});

describe('the spreadsheet', () => {
    const links = [
        {
            url: 'https://gone.example/page',
            host: 'gone.example',
            scope: 'external',
            status: 'broken',
            http_status: 404,
            uses: 3,
            reason: null,
            redirect_url: null,
            occurrences: [{ title: 'Hanau, June 2004' }, { title: 'Reports' }],
        },
        {
            url: 'https://moved.example/a,b',
            host: 'moved.example',
            scope: 'legacy',
            status: 'ok',
            http_status: 200,
            uses: 1,
            reason: null,
            redirect_url: 'https://moved.example/b',
            occurrences: [],
        },
    ];

    it('writes a header and one line per link', () => {
        const rows = toCsv(links).split('\r\n');

        expect(rows).toHaveLength(3);
        expect(rows[0]).toContain('URL');
        expect(rows[1]).toContain('https://gone.example/page');
        expect(rows[1]).toContain('404');
    });

    it('puts the pages a link sits on in the last column', () => {
        expect(toCsv(links)).toContain('"Hanau, June 2004 | Reports"');
    });

    it('says where a link landed instead of leaving the column empty', () => {
        expect(toCsv(links)).toContain('→ https://moved.example/b');
    });

    it('quotes anything a spreadsheet would read as another column', () => {
        // A comma in an address must not become a column break
        expect(toCsv(links)).toContain('"https://moved.example/a,b"');
    });

    it('takes the column headings in the reader\'s language', () => {
        expect(toCsv([], { url: 'Adresse', host: 'Host' })).toContain('Adresse,Host');
    });

    it('writes a header and nothing else for an empty report', () => {
        expect(toCsv([]).split('\r\n')).toHaveLength(1);
        expect(toCsv(undefined).split('\r\n')).toHaveLength(1);
    });
});

describe('the colours', () => {
    it('gives a colour to a verdict and none to the absence of one', () => {
        expect(STATUS_COLOURS.broken).toBe('error');
        expect(STATUS_COLOURS.unsure).toBe('warning');
        expect(STATUS_COLOURS.ok).toBe('success');
        // "Not asked yet" is not a verdict, so it is not coloured like one
        expect(STATUS_COLOURS.pending).toBe(null);
    });
});
