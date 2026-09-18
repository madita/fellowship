import { describe, it, expect } from 'vitest';
import { renderRichText, hasRichText, toEditorHtml } from '@/utils/richText.js';

describe('rich text', () => {
    it('renders plain text as escaped paragraphs with line breaks', () => {
        expect(renderRichText('Tom & Jerry <3\nthere\n\nBye')).toBe('<p>Tom &amp; Jerry &lt;3<br>there</p><p>Bye</p>');
    });

    it('keeps editor HTML and mentions but drops scripts', () => {
        const html = renderRichText('<p><span class="mention" data-username="alice">@alice</span></p><script>alert(1)</script>');
        expect(html).toContain('data-username="alice"');
        expect(html).not.toContain('script');
    });

    it('treats empty editor markup as no text', () => {
        expect(hasRichText('<p></p>')).toBe(false);
        expect(hasRichText('<p>&nbsp;</p>')).toBe(false);
        expect(hasRichText('<p>x</p>')).toBe(true);
    });

    it('turns plain text into editor HTML', () => {
        expect(toEditorHtml('a\nb')).toBe('<p>a<br>b</p>');
        expect(toEditorHtml('<p>a</p>')).toBe('<p>a</p>');
    });
});
