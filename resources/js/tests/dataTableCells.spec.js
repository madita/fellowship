import { describe, it, expect } from 'vitest';
import {
    EMPTY_CELL,
    humanizeKey,
    isEmptyValue,
    stripHtml,
    truncate,
    countEntries,
    toBoolean,
    inferType,
    resolveType,
    describeCell,
    normalizeHeaders,
    fieldLabel,
    parseTableQuery,
    buildTableQuery,
    sameQuery,
} from '@/utils/dataTableCells.js';

describe('dataTableCells: text helpers', () => {
    it('humanizes column keys', () => {
        expect(humanizeKey('created_at')).toBe('Created at');
        expect(humanizeKey('user_id')).toBe('User ID');
        expect(humanizeKey('isActive')).toBe('Is active');
        expect(humanizeKey('event-type')).toBe('Event type');
        expect(humanizeKey(null)).toBe('');
    });

    it('treats null, blank strings and empty collections as empty but keeps false and 0', () => {
        [null, undefined, '', '   ', [], {}].forEach((v) => expect(isEmptyValue(v)).toBe(true));
        [false, 0, 'a', [1], { a: 1 }].forEach((v) => expect(isEmptyValue(v)).toBe(false));
    });

    it('strips HTML, drops scripts and decodes entities once', () => {
        expect(stripHtml('<p>Hello <strong>world</strong></p><p>again</p>')).toBe('Hello world again');
        expect(stripHtml('a<br>b<script>alert(1)</script>')).toBe('a b');
        expect(stripHtml('Tom &amp; Jerry &lt;3 &amp;lt;')).toBe('Tom & Jerry <3 &lt;');
        expect(stripHtml(null)).toBe('');
    });

    it('truncates with an ellipsis only when needed', () => {
        expect(truncate('short', 10)).toBe('short');
        const out = truncate('x'.repeat(100), 80);
        expect(out).toHaveLength(80);
        expect(out.endsWith('…')).toBe(true);
    });

    it('counts entries of arrays, objects and JSON strings', () => {
        expect(countEntries([1, 2, 3])).toBe(3);
        expect(countEntries({ a: 1, b: 2 })).toBe(2);
        expect(countEntries('["a","b"]')).toBe(2);
        expect(countEntries('not json')).toBeNull();
        expect(countEntries('{broken')).toBeNull();
        expect(countEntries(5)).toBeNull();
    });

    it('reads booleans from common representations', () => {
        expect(toBoolean(true)).toBe(true);
        expect(toBoolean(0)).toBe(false);
        expect(toBoolean('1')).toBe(true);
        expect(toBoolean('false')).toBe(false);
        expect(toBoolean('maybe')).toBeNull();
        expect(toBoolean(2)).toBeNull();
    });
});

describe('dataTableCells: types', () => {
    it('infers a type when the header has none', () => {
        expect(inferType('id', 5)).toBe('id');
        expect(inferType('active', true)).toBe('boolean');
        expect(inferType('options', { a: 1 })).toBe('json');
        expect(inferType('count', 12)).toBe('number');
        expect(inferType('created_at', '2025-03-01T10:00:00Z')).toBe('datetime');
        expect(inferType('start_date', '2025-03-01')).toBe('date');
        expect(inferType('avatar', 'https://cdn.example.com/a.png')).toBe('image');
        expect(inferType('content', '<p>Hi</p>')).toBe('longtext');
        expect(inferType('name', 'Frodo')).toBe('text');
    });

    it('prefers the header type over inference', () => {
        expect(resolveType({ key: 'flag', type: 'boolean' }, 1)).toBe('boolean');
        expect(resolveType({ key: 'flag', type: 'nonsense' }, 1)).toBe('number');
    });
});

describe('dataTableCells: describeCell', () => {
    it('renders empty values as a muted dash for every type', () => {
        expect(describeCell({ key: 'x', type: 'boolean' }, null)).toEqual({ kind: 'empty', text: EMPTY_CELL });
        expect(describeCell({ key: 'x', type: 'json' }, [])).toEqual({ kind: 'empty', text: EMPTY_CELL });
        expect(describeCell({ key: 'x', type: 'longtext' }, '<p> </p>')).toEqual({ kind: 'empty', text: EMPTY_CELL });
    });

    it('describes booleans, including 0/1', () => {
        expect(describeCell({ key: 'active', type: 'boolean' }, 1)).toEqual({ kind: 'boolean', value: true });
        expect(describeCell({ key: 'active', type: 'boolean' }, false)).toEqual({ kind: 'boolean', value: false });
        expect(describeCell({ key: 'active', type: 'boolean' }, 'odd').kind).toBe('text');
    });

    it('keeps date values raw for the template to format, falls back to text when unparsable', () => {
        expect(describeCell({ key: 'd', type: 'date' }, '2025-01-02')).toEqual({ kind: 'date', value: '2025-01-02' });
        expect(describeCell({ key: 'd', type: 'datetime' }, '2025-01-02 10:00:00').kind).toBe('datetime');
        expect(describeCell({ key: 'd', type: 'datetime' }, 'soon')).toEqual({ kind: 'text', text: 'soon' });
    });

    it('counts json entries and keeps the JSON as title', () => {
        const cell = describeCell({ key: 'meta', type: 'json' }, { a: 1, b: 2 });
        expect(cell.kind).toBe('json');
        expect(cell.count).toBe(2);
        expect(cell.title).toBe('{"a":1,"b":2}');
        expect(describeCell({ key: 'meta', type: 'json' }, 'plain').kind).toBe('longtext');
    });

    it('strips and truncates long text with the full text as title', () => {
        const html = `<p>${'word '.repeat(40)}</p>`;
        const cell = describeCell({ key: 'content', type: 'longtext' }, html);
        expect(cell.kind).toBe('longtext');
        expect(cell.text.length).toBeLessThanOrEqual(80);
        expect(cell.text).not.toContain('<');
        expect(cell.title).toBe('word '.repeat(40).trim());
    });

    it('describes numbers, ids, images and plain text', () => {
        expect(describeCell({ key: 'n', type: 'number' }, 0)).toEqual({ kind: 'number', text: '0' });
        expect(describeCell({ key: 'id', type: 'id' }, 7)).toEqual({ kind: 'id', text: '7' });
        expect(describeCell({ key: 'avatar', type: 'image' }, '/img/a.png')).toEqual({ kind: 'image', src: '/img/a.png' });
        expect(describeCell({ key: 'name', type: 'text' }, 'Sam')).toEqual({ kind: 'text', text: 'Sam' });
        const long = describeCell({ key: 'name', type: 'text' }, 'y'.repeat(120));
        expect(long.text).toHaveLength(80);
        expect(long.title).toHaveLength(120);
    });
});

describe('dataTableCells: headers and labels', () => {
    it('normalizes the new header shape and keeps one trailing actions column', () => {
        const headers = normalizeHeaders([
            { key: 'id', title: 'ID', sortable: true, type: 'id' },
            { key: 'count', title: 'Count', sortable: true, type: 'number' },
            { key: 'actions', title: 'Actions', sortable: false, align: 'end' },
        ], { actionsTitle: 'Aktionen' });
        expect(headers.map((h) => h.key)).toEqual(['id', 'count', 'actions']);
        expect(headers[1].align).toBe('end');
        expect(headers[2]).toMatchObject({ title: 'Aktionen', sortable: false, align: 'end' });
    });

    it('accepts legacy { text, value } headers and humanizes raw keys', () => {
        const headers = normalizeHeaders([
            { text: 'created_at', value: 'created_at', sortable: false },
            { text: 'Display name', value: 'name' },
        ], { actionsTitle: 'Actions' });
        expect(headers[0]).toMatchObject({ key: 'created_at', title: 'Created at', sortable: false, align: 'start', type: null });
        expect(headers[1]).toMatchObject({ key: 'name', title: 'Display name', sortable: true });
        expect(headers[2].key).toBe('actions');
    });

    it('builds field labels from column_map, header titles, then the key', () => {
        const headers = [{ key: 'title', title: 'Headline' }];
        expect(fieldLabel('name', { columnMap: { name: 'Full name' } })).toBe('Full name');
        expect(fieldLabel('title', { headers })).toBe('Headline');
        expect(fieldLabel('event_type_id', {})).toBe('Event type ID');
    });
});

describe('dataTableCells: URL query state', () => {
    it('parses valid values and drops invalid ones', () => {
        expect(parseTableQuery({ page: '3', per_page: '50', sort_by: 'name', sort_dir: 'desc', search: ' frodo ' }))
            .toEqual({ page: 3, perPage: 50, sortBy: 'name', sortDir: 'desc', search: 'frodo' });
        expect(parseTableQuery({ page: '-1', per_page: '7', sort_by: 'x;drop', sort_dir: 'sideways' }))
            .toEqual({ page: 1, perPage: 10, sortBy: null, sortDir: 'asc', search: '' });
    });

    it('builds a clean query that keeps unrelated keys', () => {
        const base = { tab: 'users', page: '9' };
        expect(buildTableQuery(base, { page: 1, perPage: 10, sortBy: null, sortDir: 'asc', search: '' }))
            .toEqual({ tab: 'users' });
        expect(buildTableQuery(base, { page: 2, perPage: 25, sortBy: 'name', sortDir: 'desc', search: 'sam' }))
            .toEqual({ tab: 'users', page: '2', per_page: '25', sort_by: 'name', sort_dir: 'desc', search: 'sam' });
    });

    it('compares queries by value', () => {
        expect(sameQuery({ page: '2' }, { page: 2 })).toBe(true);
        expect(sameQuery({ page: '2' }, { page: '2', search: 'a' })).toBe(false);
        expect(sameQuery({}, {})).toBe(true);
    });
});
