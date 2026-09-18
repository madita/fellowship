import { sanitizeHtml } from '@/utils/sanitize.js';

/**
 * Text written with the simple editor is HTML; older and generated texts
 * (legacy claims, approval tickets) are plain text with line breaks.
 * Both render safely with `class="rich-content"` and v-html.
 */

const escapeHtml = (text) => text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

export const isHtml = (value) => /<\/?[a-z][\s\S]*>/i.test(value || '');

export function renderRichText(value) {
    if (!value) return '';
    if (!isHtml(value)) {
        return value.split(/\n{2,}/).map(block => `<p>${escapeHtml(block).replace(/\n/g, '<br>')}</p>`).join('');
    }
    return sanitizeHtml(value);
}

// Whether editor HTML holds any text (an empty paragraph does not count)
export function hasRichText(value) {
    return (value || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;
}

// Editor HTML for text that may still be plain
export function toEditorHtml(value) {
    return isHtml(value) ? value : renderRichText(value);
}
