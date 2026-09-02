<?php

namespace App\Services\Migration;

use App\Models\Tag\Term;
use Illuminate\Support\Str;

/**
 * Converts MediaWiki wikitext into the HTML this app's wiki pages use, and
 * extracts the categories declared in the text ([[Category:…]] and the
 * German [[Kategorie:…]]).
 *
 * This is the generalised version of the transformation the legacy
 * MigrateWikiPagesJob performed inline: no hardcoded archive paths, both
 * English and German namespaces, and image references are stripped instead
 * of copied from a local folder (media files are not part of a DB import).
 */
class WikitextConverter
{
    private const CATEGORY_NAMESPACES = '(?:Category|Kategorie)';

    /**
     * @return array{html: string, categories: string[]}
     */
    public function convert(string $wikitext): array
    {
        return [
            'categories' => $this->extractCategories($wikitext),
            'html' => $this->toHtml($wikitext),
        ];
    }

    /**
     * @return string[]
     */
    public function extractCategories(string $content): array
    {
        preg_match_all('/\[\[' . self::CATEGORY_NAMESPACES . ':([^\]|]+)(?:\|[^\]]*)?\]\]/u', $content, $matches);

        $categories = [];
        foreach ($matches[1] as $category) {
            $category = trim(str_replace('_', ' ', strip_tags($category)));
            if ($category !== '') {
                $categories[] = $category;
            }
        }

        return array_values(array_unique($categories));
    }

    public function toHtml(string $content): string
    {
        $content = str_replace(["\r\n", "\r", '‎'], ["\n", "\n", ''], $content);

        // Headers (deepest first so ===…=== is not eaten by ==…==).
        $content = preg_replace('/=====(.*?)=====/', '<h5 id="$1">$1</h5>', $content);
        $content = preg_replace('/====(.*?)====/', '<h4 id="$1">$1</h4>', $content);
        $content = preg_replace('/===(.*?)===/', '<h3 id="$1">$1</h3>', $content);
        $content = preg_replace('/==(.*?)==/', '<h2 id="$1">$1</h2>', $content);

        // Date templates ({{dts|d|m|y}}).
        $content = preg_replace('/{{dts\|(\d+)\|(\d+)\|(\d+)}}/', '$1.$2.$3', $content);

        $content = $this->processLists($content);

        // Text formatting.
        $content = preg_replace("/'''(.*?)'''/", '<strong class="highlight">$1</strong>', $content);
        $content = preg_replace("/''(.*?)''/", '<em class="highlight">$1</em>', $content);

        $content = $this->processCategoryLinks($content);

        // Category declarations and interwiki language links carry no content.
        $content = preg_replace('/\[\[' . self::CATEGORY_NAMESPACES . ':[^\]]*\]\]/u', '', $content);
        $content = preg_replace('/\[\[[a-z]{2,3}:[^\]]*\]\]/', '', $content);

        // Image references: the files live outside the database — strip them.
        $content = preg_replace('/\[\[(?:File|Image|Bild|Datei):[^\]]*\]\]/u', '', $content);

        $content = $this->processInternalLinks($content);

        // External links: [https://… label]
        $content = preg_replace(
            '/\[(https?:\/\/[^\s\]]+)\s+([^\]]+)\]/',
            '<a href="$1">$2</a>',
            $content
        );

        // Remaining templates carry MediaWiki-specific behaviour we can't map.
        do {
            $content = preg_replace('/{{[^{}]*}}/s', '', $content, -1, $replaced);
        } while ($replaced > 0);

        // Anything still wrapped in [[…]] (unknown namespaces): keep the label.
        $content = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]|]*)\]\]/', '$1', $content);

        return $this->wrapInParagraphs($content);
    }

    private function processLists(string $content): string
    {
        preg_match_all("/(^[ \t]*\*.*\n?)+/m", $content, $matches);

        foreach (array_unique($matches[0]) as $block) {
            $list = '<ul>';
            $sublist = false;

            foreach (preg_split("/\n/", $block) as $line) {
                $line = trim($line);
                if (Str::startsWith($line, '**')) {
                    if (!$sublist) {
                        $sublist = true;
                        $list .= '<ul>';
                    }
                    $list .= '<li>' . trim(substr($line, 2)) . '</li>';
                } elseif (Str::startsWith($line, '*')) {
                    if ($sublist) {
                        $sublist = false;
                        $list .= '</ul>';
                    }
                    $list .= '<li>' . trim(substr($line, 1)) . '</li>';
                }
            }

            if ($sublist) {
                $list .= '</ul>';
            }
            $list .= '</ul>';

            $content = Str::replace($block, $list . "\n", $content);
        }

        return $content;
    }

    /**
     * [[:Category:Name|Label]] links become term (tag) links; the term is
     * created when it doesn't exist yet.
     */
    private function processCategoryLinks(string $content): string
    {
        preg_match_all('/\[\[:' . self::CATEGORY_NAMESPACES . ':([^\]|]+)(?:\|([^\]]*))?\]\]/u', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = trim(str_replace('_', ' ', $match[1]));
            $term = Term::where('slug', Str::slug($name))->first() ?? Term::firstOrCreateByTitle($name);

            $label = isset($match[2]) && trim($match[2]) !== '' ? trim($match[2]) : $term->title;
            $alternative = isset($match[2]) && trim($match[2]) !== '' ? trim($match[2]) : null;

            $replace = "<a style=\"font-weight:600\" data-term-id=\"{$term->id}\" data-tag=\"{$term->title}\""
                . " data-linked-resource-type=\"terms\" data-alternative=\"{$alternative}\""
                . " href=\"/wiki/category/{$term->slug}\" contenteditable=\"false\">#{$label}</a>";

            $content = Str::replace($match[0], $replace, $content);
        }

        return $content;
    }

    /**
     * Plain internal links ([[Page]] / [[Page|Label]]) point at the slug the
     * imported page will get, so links resolve once both pages are imported.
     */
    private function processInternalLinks(string $content): string
    {
        preg_match_all('/\[\[([^\]|:]+)(?:\|([^\]]*))?\]\]/u', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $target = trim(str_replace('_', ' ', $match[1]));
            $label = isset($match[2]) && trim($match[2]) !== '' ? trim($match[2]) : $target;
            $slug = Str::slug($target);

            $content = Str::replace($match[0], "<a href=\"/wiki/{$slug}\">{$label}</a>", $content);
        }

        return $content;
    }

    private function wrapInParagraphs(string $content): string
    {
        $lines = [];
        foreach (preg_split("/\n/", $content) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $lines[] = Str::startsWith($trimmed, '<') ? $trimmed : "<p>{$trimmed}</p>";
        }

        return implode("\n", $lines);
    }
}
