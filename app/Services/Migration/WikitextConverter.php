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
 * MigrateWikiPagesJob (since removed) performed inline: no hardcoded archive paths, both
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

        // Tables last: inline markup inside the cells is already converted.
        $content = $this->processTables($content);

        return $this->wrapInParagraphs($content);
    }

    /**
     * Convert MediaWiki table markup into HTML tables:
     *   {| class="prettytable"     table start (attributes)
     *   |+ caption                 caption
     *   |- class="row"             row start (attributes)
     *   ! a !! b || class="c"|d    header cells ("attrs|content" per cell)
     *   | a || b                   data cells
     *   |}                         table end
     * Nested tables are appended into the parent's current cell.
     */
    private function processTables(string $content): string
    {
        if (!str_contains($content, '{|')) {
            return $content;
        }

        $out = [];
        $stack = [];

        foreach (explode("\n", $content) as $line) {
            $trim = trim($line);

            if (str_starts_with($trim, '{|')) {
                $stack[] = [
                    'attrs' => $this->sanitizeAttributes(substr($trim, 2)),
                    'caption' => null,
                    'rows' => [],
                    'rowAttrs' => '',
                    'cells' => [],
                ];
                continue;
            }

            if (!$stack) {
                $out[] = $line;
                continue;
            }

            $table = &$stack[count($stack) - 1];

            if (str_starts_with($trim, '|}')) {
                $html = $this->renderTable($table);
                unset($table);
                array_pop($stack);

                if ($stack) {
                    // nested table becomes part of the parent's current cell
                    $parent = &$stack[count($stack) - 1];
                    if (!$parent['cells']) {
                        $parent['cells'][] = ['td', '', ''];
                    }
                    $parent['cells'][count($parent['cells']) - 1][2] .= $html;
                    unset($parent);
                } else {
                    $out[] = $html;
                }
                continue;
            }

            if (str_starts_with($trim, '|+')) {
                $table['caption'] = trim(substr($trim, 2));
            } elseif (str_starts_with($trim, '|-')) {
                $this->flushRow($table);
                $table['rowAttrs'] = $this->sanitizeAttributes(ltrim(substr($trim, 2), '-'));
            } elseif (str_starts_with($trim, '!')) {
                foreach (preg_split('/!!|\|\|/', substr($trim, 1)) as $cell) {
                    $table['cells'][] = $this->makeCell('th', $cell);
                }
            } elseif (str_starts_with($trim, '|')) {
                foreach (explode('||', substr($trim, 1)) as $cell) {
                    $table['cells'][] = $this->makeCell('td', $cell);
                }
            } elseif ($trim !== '') {
                // continuation of the previous cell's content
                if (!$table['cells']) {
                    $table['cells'][] = ['td', '', ''];
                }
                $table['cells'][count($table['cells']) - 1][2] .= '<br>' . $trim;
            }

            unset($table);
        }

        // Unterminated tables: render what we have.
        while ($stack) {
            $table = array_pop($stack);
            $out[] = $this->renderTable($table);
        }

        return implode("\n", $out);
    }

    /**
     * @return array{0:string,1:string,2:string} [tag, attribute string, content]
     */
    private function makeCell(string $tag, string $cell): array
    {
        $attrs = '';
        $content = trim($cell);

        // "class="unsortable"|Content" — text before the first pipe is
        // attributes when it actually looks like attributes.
        $pipe = strpos($content, '|');
        if ($pipe !== false) {
            $candidate = substr($content, 0, $pipe);
            if (preg_match('/^\s*(?:[\w-]+\s*=\s*("[^"]*"|\'[^\']*\'|[\w-]+)\s*)+$/', $candidate)) {
                $attrs = $this->sanitizeAttributes($candidate);
                $content = trim(substr($content, $pipe + 1));
            }
        }

        return [$tag, $attrs, $content];
    }

    /**
     * Keep only harmless presentation attributes (drops on* handlers etc.).
     */
    private function sanitizeAttributes(string $raw): string
    {
        $allowed = ['class', 'style', 'id', 'colspan', 'rowspan', 'align', 'valign', 'width', 'scope'];

        preg_match_all('/([\w-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|(\S+))/', $raw, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            if (in_array($name, $allowed, true)) {
                $value = $match[3] !== '' ? $match[3] : ($match[4] ?? '') . ($match[5] ?? '');
                $attrs[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
            }
        }

        return $attrs ? ' ' . implode(' ', $attrs) : '';
    }

    /**
     * @param array{attrs:string,caption:?string,rows:array,rowAttrs:string,cells:array} $table
     */
    private function renderTable(array $table): string
    {
        $this->flushRow($table);

        $html = '<table' . $table['attrs'] . '>';
        if ($table['caption'] !== null && $table['caption'] !== '') {
            $html .= '<caption>' . $table['caption'] . '</caption>';
        }

        foreach ($table['rows'] as $row) {
            $html .= '<tr' . $row['attrs'] . '>';
            foreach ($row['cells'] as [$tag, $attrs, $content]) {
                $html .= "<{$tag}{$attrs}>{$content}</{$tag}>";
            }
            $html .= '</tr>';
        }

        return $html . '</table>';
    }

    private function flushRow(array &$table): void
    {
        if ($table['cells']) {
            $table['rows'][] = ['attrs' => $table['rowAttrs'], 'cells' => $table['cells']];
        }
        $table['cells'] = [];
        $table['rowAttrs'] = '';
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
