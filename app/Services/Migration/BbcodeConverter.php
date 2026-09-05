<?php

namespace App\Services\Migration;

/**
 * Converts phpBB post text into clean HTML.
 *
 * Handles both storage formats:
 *  - phpBB 3.2+ (s9e TextFormatter XML): <r>…</r> / <t>…</t> with
 *    <QUOTE author>, <URL url>, <B>, <LIST>… and <s>/<e> markers that
 *    carry the raw bbcode source.
 *  - classic bbcode with uid markers ([b:1a2b3c]…[/b:1a2b3c]) plus the
 *    <!-- s… --> smiley and <!-- m --> magic-url comments.
 */
class BbcodeConverter
{
    public function toHtml(?string $text): string
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return '';
        }

        $trimmed = ltrim($text);
        if (str_starts_with($trimmed, '<r>') || str_starts_with($trimmed, '<t>')) {
            return $this->fromTextFormatterXml($trimmed);
        }

        return $this->fromClassicBbcode($text);
    }

    private function fromTextFormatterXml(string $xml): string
    {
        // <s>/<e> hold the original bbcode source — drop them entirely.
        $xml = preg_replace('/<s>.*?<\/s>|<e>.*?<\/e>/s', '', $xml);

        // Smilies (<E>:)</E>) keep their text form.
        $xml = preg_replace('/<E>(.*?)<\/E>/s', '$1', $xml);

        $replacements = [
            '/<QUOTE author="([^"]*)">/s' => '<blockquote><p><strong>$1:</strong></p>',
            '/<QUOTE>/' => '<blockquote>',
            '/<\/QUOTE>/' => '</blockquote>',
            '/<URL url="([^"]*)">/s' => '<a href="$1">',
            '/<\/URL>/' => '</a>',
            '/<EMAIL email="([^"]*)">/s' => '<a href="mailto:$1">',
            '/<\/EMAIL>/' => '</a>',
            '/<IMG src="([^"]*)">.*?<\/IMG>/s' => '<img src="$1" alt="">',
            '/<CODE>/' => '<pre><code>',
            '/<\/CODE>/' => '</code></pre>',
            '/<LIST type="decimal">/' => '<ol>',
            '/<LIST[^>]*>/' => '<ul>',
            '/<\/LIST>/' => '</ul>',
            '/<LI>/' => '<li>',
            '/<\/LI>/' => '</li>',
            '/<B>/' => '<strong>',
            '/<\/B>/' => '</strong>',
            '/<I>/' => '<em>',
            '/<\/I>/' => '</em>',
            '/<U>/' => '<u>',
            '/<\/U>/' => '</u>',
            '/<S>/' => '<s>',
            '/<\/S>/' => '</s>',
            '/<SIZE[^>]*>/' => '',
            '/<\/SIZE>/' => '',
            '/<COLOR color="([^"]*)">/' => '<span style="color:$1">',
            '/<\/COLOR>/' => '</span>',
        ];
        $xml = preg_replace(array_keys($replacements), array_values($replacements), $xml);

        // Container / unknown formatter tags: keep only their content.
        $xml = preg_replace('/<\/?[rt]>/', '', $xml);
        $xml = preg_replace('/<\/?[A-Z][A-Z0-9_]*[^>]*>/', '', $xml);

        return $this->finalize($xml);
    }

    private function fromClassicBbcode(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        // Strip uid markers: [b:1a2b3c] → [b]. Greedy so tags whose
        // arguments contain colons ([url=http://…:uid]) keep the argument
        // and lose only the trailing uid.
        $text = preg_replace('/\[([^\]]+):[a-z0-9]{5,12}\]/i', '[$1]', $text);

        // Smiley images and magic-url wrappers stored as HTML comments.
        $text = preg_replace('/<!-- s(.*?) --><img[^>]*><!-- s\1 -->/s', '$1', $text);
        $text = preg_replace('/<!-- [mw] -->(.*?)<!-- [mw] -->/s', '$1', $text);
        $text = preg_replace('/<!-- e --><a href="mailto:([^"]*)"[^>]*>.*?<\/a><!-- e -->/s', '<a href="mailto:$1">$1</a>', $text);

        $replacements = [
            '/\[quote="?([^"\]]*)"?\](.*?)\[\/quote\]/s' => '<blockquote><p><strong>$1:</strong></p>$2</blockquote>',
            '/\[quote\](.*?)\[\/quote\]/s' => '<blockquote>$1</blockquote>',
            '/\[b\](.*?)\[\/b\]/s' => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/s' => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/s' => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/s' => '<s>$1</s>',
            '/\[url=([^\]]+)\](.*?)\[\/url\]/s' => '<a href="$1">$2</a>',
            '/\[url\](.*?)\[\/url\]/s' => '<a href="$1">$1</a>',
            '/\[img\](.*?)\[\/img\]/s' => '<img src="$1" alt="">',
            '/\[code\](.*?)\[\/code\]/s' => '<pre><code>$1</code></pre>',
            '/\[list=1\](.*?)\[\/list\]/s' => '<ol>$1</ol>',
            '/\[list[^\]]*\](.*?)\[\/list\]/s' => '<ul>$1</ul>',
            '/\[\*\](.*?)(?=\[\*\]|<\/[ou]l>|$)/s' => '<li>$1</li>',
            '/\[size=[^\]]*\](.*?)\[\/size\]/s' => '$1',
            '/\[color=([^\]]+)\](.*?)\[\/color\]/s' => '<span style="color:$1">$2</span>',
        ];

        do {
            $text = preg_replace(array_keys($replacements), array_values($replacements), $text, -1, $count);
        } while ($count > 0);

        return $this->finalize($text);
    }

    private function finalize(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", trim($html));

        // Newlines inside block elements are fine; bare ones become breaks.
        $html = preg_replace("/\n{2,}/", '</p><p>', $html);
        $html = nl2br($html, false);

        if ($html !== '' && !str_starts_with($html, '<p>') && !preg_match('/^<(blockquote|pre|ul|ol)/', $html)) {
            $html = '<p>' . $html . '</p>';
        }

        return $html;
    }
}
