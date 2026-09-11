/**
 * Pure helpers for the admin data table (components/common/DataTable/DataTable.vue):
 * header normalisation, per-type cell descriptions and the URL query state.
 * No Vue, no stores — everything here is unit-tested in tests/dataTableCells.spec.js.
 */

export const EMPTY_CELL = '—';
export const LONGTEXT_LENGTH = 80;
export const DEFAULT_PER_PAGE_OPTIONS = [10, 25, 50, 100];
export const CELL_TYPES = ['id', 'text', 'longtext', 'boolean', 'number', 'date', 'datetime', 'json', 'image'];

const isPlainObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value) && !(value instanceof Date);

/** 'created_at' → 'Created at', 'user_id' → 'User ID', 'isActive' → 'Is active'. */
export function humanizeKey(key) {
    if (key === null || key === undefined) return '';
    const words = String(key)
        .replace(/([a-z\d])([A-Z])/g, '$1 $2')
        .replace(/[_.\-\s]+/g, ' ')
        .trim()
        .toLowerCase()
        .replace(/\b(id|url|ip|api)\b/g, (m) => m.toUpperCase());
    return words.charAt(0).toUpperCase() + words.slice(1);
}

/** null, undefined, blank strings, empty arrays and empty objects. `false` and `0` are values. */
export function isEmptyValue(value) {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    if (isPlainObject(value)) return Object.keys(value).length === 0;
    return false;
}

const ENTITIES = { '&nbsp;': ' ', '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'", '&apos;': "'" };

/** Plain text of an HTML fragment, whitespace collapsed. */
export function stripHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/<(script|style)\b[^>]*>[\s\S]*?<\/\1>/gi, ' ')
        .replace(/<br\s*\/?>|<\/(p|div|li|h[1-6]|tr|td|blockquote)>/gi, ' ')
        .replace(/<[^>]*>/g, '')
        .replace(/&(nbsp|amp|lt|gt|quot|apos|#39);/g, (m) => ENTITIES[m])
        .replace(/&#(\d+);/g, (_, code) => String.fromCharCode(Number(code)))
        .replace(/\s+/g, ' ')
        .trim();
}

export function truncate(text, max = LONGTEXT_LENGTH) {
    const str = text === null || text === undefined ? '' : String(text);
    if (str.length <= max) return str;
    return `${str.slice(0, Math.max(0, max - 1)).trimEnd()}…`;
}

/** Entry count of an array/object (or its JSON string), null when it is not a collection. */
export function countEntries(value) {
    if (Array.isArray(value)) return value.length;
    if (isPlainObject(value)) return Object.keys(value).length;
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!/^[[{]/.test(trimmed)) return null;
        try {
            const parsed = JSON.parse(trimmed);
            return Array.isArray(parsed) || isPlainObject(parsed) ? countEntries(parsed) : null;
        } catch {
            return null;
        }
    }
    return null;
}

/** true/false for booleans, 1/0 and their string forms, null for anything else. */
export function toBoolean(value) {
    if (typeof value === 'boolean') return value;
    if (value === 1 || value === 0) return value === 1;
    if (typeof value === 'string') {
        const v = value.trim().toLowerCase();
        if (['1', 'true', 'yes', 'on'].includes(v)) return true;
        if (['0', 'false', 'no', 'off'].includes(v)) return false;
    }
    return null;
}

const looksLikeDate = (value) => value instanceof Date
    ? !Number.isNaN(value.getTime())
    : typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value.trim());

const IMAGE_KEY = /(avatar|image|img|photo|picture|thumbnail|thumb|logo|cover|banner)/i;
const IMAGE_SRC = /^(data:image\/|https?:\/\/.+\.(png|jpe?g|gif|webp|svg|avif)(\?.*)?$|\/.+\.(png|jpe?g|gif|webp|svg|avif)(\?.*)?$)/i;

/** Best guess for tables whose headers carry no `type` (legacy responses). */
export function inferType(key, value) {
    const k = String(key || '');
    if (k === 'id') return 'id';
    if (typeof value === 'boolean') return 'boolean';
    if (Array.isArray(value) || isPlainObject(value)) return 'json';
    if (typeof value === 'number') return 'number';
    if (typeof value === 'string') {
        if (/(_at|_on)$/.test(k) && looksLikeDate(value)) return 'datetime';
        if (/(^|_)date$/.test(k) && looksLikeDate(value)) return /\d{2}:\d{2}/.test(value) ? 'datetime' : 'date';
        if (IMAGE_KEY.test(k) && IMAGE_SRC.test(value.trim())) return 'image';
        if (/<[a-z][^>]*>/i.test(value) || value.length > LONGTEXT_LENGTH) return 'longtext';
    }
    return 'text';
}

export function resolveType(header, value) {
    const type = header && header.type;
    return CELL_TYPES.includes(type) ? type : inferType(header && header.key, value);
}

/**
 * What a cell should render. `kind` is one of CELL_TYPES or 'empty'; the template
 * picks the markup, formatting that needs the app (dates, i18n) stays there.
 */
export function describeCell(header, value, { maxLength = LONGTEXT_LENGTH } = {}) {
    if (isEmptyValue(value)) return { kind: 'empty', text: EMPTY_CELL };
    const type = resolveType(header, value);

    switch (type) {
        case 'boolean': {
            const bool = toBoolean(value);
            if (bool === null) return { kind: 'text', text: String(value) };
            return { kind: 'boolean', value: bool };
        }
        case 'date':
        case 'datetime':
            if (!looksLikeDate(value)) return { kind: 'text', text: String(value) };
            return { kind: type, value };
        case 'number':
            return { kind: 'number', text: String(value) };
        case 'json': {
            const count = countEntries(value);
            const full = typeof value === 'string' ? value : JSON.stringify(value);
            if (count === null) {
                return { kind: 'longtext', text: truncate(full, maxLength), title: full };
            }
            return { kind: 'json', count, title: truncate(full, 500) };
        }
        case 'longtext': {
            const plain = stripHtml(value);
            if (!plain) return { kind: 'empty', text: EMPTY_CELL };
            return { kind: 'longtext', text: truncate(plain, maxLength), title: plain };
        }
        case 'image':
            return { kind: 'image', src: String(value) };
        case 'id':
            return { kind: 'id', text: String(value) };
        default: {
            const text = typeof value === 'object' ? JSON.stringify(value) : String(value);
            return text.length > maxLength
                ? { kind: 'text', text: truncate(text, maxLength), title: text }
                : { kind: 'text', text };
        }
    }
}

/**
 * Response headers → v-data-table headers. Accepts the new shape
 * ({ key, title, sortable, type, align }) and the legacy one ({ text, value }).
 * Always ends with a single, non-sortable, end-aligned 'actions' column.
 */
export function normalizeHeaders(rawHeaders, { actionsTitle = '', columnMap = {} } = {}) {
    const list = Array.isArray(rawHeaders) ? rawHeaders : [];
    const headers = [];
    let actions = null;

    list.forEach((raw) => {
        if (!raw) return;
        const key = raw.key ?? raw.value;
        if (key === undefined || key === null || key === '') return;
        if (key === 'actions') {
            actions = { key: 'actions', title: actionsTitle || raw.title || raw.text || '', sortable: false, align: 'end', type: null };
            return;
        }
        let title = raw.title ?? raw.text ?? '';
        if (!title || title === key) title = (columnMap && columnMap[key]) || humanizeKey(key);
        const type = CELL_TYPES.includes(raw.type) ? raw.type : null;
        headers.push({
            key,
            title,
            sortable: raw.sortable !== false,
            align: raw.align || (type === 'number' ? 'end' : 'start'),
            type,
        });
    });

    headers.push(actions || { key: 'actions', title: actionsTitle, sortable: false, align: 'end', type: null });
    return headers;
}

/** Label for a record field (quick edit): column_map, then header title, then humanised key. */
export function fieldLabel(column, { columnMap = {}, headers = [] } = {}) {
    if (columnMap && columnMap[column] && columnMap[column] !== column) return columnMap[column];
    const header = (headers || []).find((h) => h && h.key === column);
    if (header && header.title && header.title !== column) return header.title;
    return humanizeKey(column);
}

/* ------------------------------------------------------------------ */
/* URL query state                                                     */
/* ------------------------------------------------------------------ */

const firstValue = (v) => (Array.isArray(v) ? v[0] : v);

/** Route query → table state, invalid values dropped. */
export function parseTableQuery(query = {}, { perPageOptions = DEFAULT_PER_PAGE_OPTIONS, defaultPerPage = 10 } = {}) {
    const page = parseInt(firstValue(query.page), 10);
    const perPage = parseInt(firstValue(query.per_page), 10);
    const sortBy = firstValue(query.sort_by);
    const sortDir = firstValue(query.sort_dir);
    const search = firstValue(query.search);

    return {
        page: Number.isInteger(page) && page > 0 ? page : 1,
        perPage: perPageOptions.includes(perPage) ? perPage : defaultPerPage,
        sortBy: typeof sortBy === 'string' && /^[\w.-]+$/.test(sortBy) ? sortBy : null,
        sortDir: sortDir === 'desc' ? 'desc' : 'asc',
        search: typeof search === 'string' ? search.trim() : '',
    };
}

/**
 * Table state → route query. Keeps unrelated keys of `baseQuery`, omits defaults
 * (page 1, default page size, no sort, empty search) so clean URLs stay clean.
 */
export function buildTableQuery(baseQuery, { page, perPage, sortBy, sortDir, search }, { defaultPerPage = 10 } = {}) {
    const query = { ...(baseQuery || {}) };
    ['page', 'per_page', 'sort_by', 'sort_dir', 'search'].forEach((k) => delete query[k]);
    if (page && page > 1) query.page = String(page);
    if (perPage && perPage !== defaultPerPage) query.per_page = String(perPage);
    if (sortBy) {
        query.sort_by = sortBy;
        query.sort_dir = sortDir === 'desc' ? 'desc' : 'asc';
    }
    if (search) query.search = search;
    return query;
}

/** Shallow equality of two route query objects (values compared as strings). */
export function sameQuery(a = {}, b = {}) {
    const ka = Object.keys(a);
    const kb = Object.keys(b);
    if (ka.length !== kb.length) return false;
    return ka.every((k) => String(firstValue(a[k])) === String(firstValue(b[k])) && Array.isArray(a[k]) === Array.isArray(b[k]));
}
