import axios from 'axios';

/**
 * Shared bits of the "related content" feature (wiki pages, pages, posts,
 * events and albums linked to each other): the kinds, their icon/colour,
 * a cached request for /api/relateable/kinds and template helpers.
 */

/** Linkable content kinds, in display order. */
export const RELATED_KINDS = ['wiki', 'page', 'post', 'event', 'collection'];

const KIND_META = {
    wiki: { icon: 'mdi-book-open-page-variant-outline', color: 'primary' },
    page: { icon: 'mdi-file-document-outline', color: 'info' },
    post: { icon: 'mdi-post-outline', color: 'secondary' },
    event: { icon: 'mdi-calendar-star', color: 'warning' },
    collection: { icon: 'mdi-image-album', color: 'success' },
};
const FALLBACK_META = { icon: 'mdi-link-variant', color: 'secondary' };

export function kindMeta(kind) {
    return KIND_META[kind] || FALLBACK_META;
}

export function kindOrder(kind) {
    const index = RELATED_KINDS.indexOf(kind);
    return index === -1 ? RELATED_KINDS.length : index;
}

/** Stable identity of a linkable item: "<model class>:<id>". */
export function itemKey(item) {
    return item ? `${item.type}:${item.id}` : '';
}

export function isExternalUrl(url) {
    return typeof url === 'string' && /^https?:\/\//i.test(url);
}

let kindsRequest = null;

/** Loads the kinds once per page load; a failed request is retried on the next call. */
export function fetchRelatedKinds() {
    if (!kindsRequest) {
        kindsRequest = axios.get('/api/relateable/kinds')
            .then(({ data }) => data?.data || [])
            .catch((error) => {
                kindsRequest = null;
                throw error;
            });
    }
    return kindsRequest;
}

/** Options API helpers for templates that render linkable items. */
export const relatedKindMixin = {
    methods: {
        kindLabel(kind, fallback = '') {
            return RELATED_KINDS.includes(kind) ? this.$t(`relatedContent.kinds.${kind}`) : (fallback || kind || '');
        },
        kindLabelPlural(kind) {
            return RELATED_KINDS.includes(kind) ? this.$t(`relatedContent.kindsPlural.${kind}`) : (kind || '');
        },
        kindIcon(kind) {
            return kindMeta(kind).icon;
        },
        kindColor(kind) {
            return kindMeta(kind).color;
        },
        itemKey,
        internalLink(url) {
            return url && !isExternalUrl(url) ? url : undefined;
        },
        externalLink(url) {
            return isExternalUrl(url) ? url : undefined;
        },
    },
};
