/**
 * Basic HTML sanitizer to prevent XSS attacks.
 * Removes script tags, event handlers, and dangerous protocols.
 * 
 * For more robust sanitization, consider using DOMPurify:
 * npm install dompurify
 * import DOMPurify from 'dompurify';
 * export const sanitizeHtml = (html) => DOMPurify.sanitize(html);
 */

const DANGEROUS_TAGS = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button'];
const DANGEROUS_ATTRS = ['onclick', 'onerror', 'onload', 'onmouseover', 'onfocus', 'onblur'];

/**
 * Sanitize HTML string by removing dangerous elements and attributes.
 * @param {string} html - The HTML string to sanitize
 * @returns {string} - Sanitized HTML string
 */
export function sanitizeHtml(html) {
    if (!html || typeof html !== 'string') {
        return '';
    }

    // Create a temporary element to parse the HTML
    const temp = document.createElement('div');
    temp.innerHTML = html;

    // Remove dangerous tags
    DANGEROUS_TAGS.forEach(tag => {
        const elements = temp.querySelectorAll(tag);
        elements.forEach(el => el.remove());
    });

    // Remove dangerous attributes from all elements
    const allElements = temp.querySelectorAll('*');
    allElements.forEach(el => {
        // Remove event handlers
        DANGEROUS_ATTRS.forEach(attr => {
            el.removeAttribute(attr);
        });

        // Remove any attribute starting with 'on'
        Array.from(el.attributes).forEach(attr => {
            if (attr.name.toLowerCase().startsWith('on')) {
                el.removeAttribute(attr.name);
            }
        });

        // Remove javascript: and data: URLs from href/src
        ['href', 'src', 'action'].forEach(attr => {
            const value = el.getAttribute(attr);
            if (value) {
                const lowerValue = value.toLowerCase().trim();
                if (lowerValue.startsWith('javascript:') || lowerValue.startsWith('data:text/html')) {
                    el.removeAttribute(attr);
                }
            }
        });
    });

    return temp.innerHTML;
}

export default sanitizeHtml;
