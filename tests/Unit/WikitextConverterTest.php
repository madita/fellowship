<?php

namespace Tests\Unit;

use App\Services\Migration\WikitextConverter;
use PHPUnit\Framework\TestCase;

class WikitextConverterTest extends TestCase
{
    public function test_mediawiki_tables_become_html_tables(): void
    {
        $wikitext = <<<'WIKI'
Vor der Tabelle.
{| class="prettytable sortable"
|-class="dog"
!Amtzeit von||bis || class="unsortable"|Abteilungsleiter || class="unsortable"|Stellvertreter
|-
|20.5.2001 ||31.10.2001 ||150780-M-080500 ||Hauptgefreiter Wiewunderland Jim
|}
Nach der Tabelle.
WIKI;

        $html = (new WikitextConverter())->toHtml($wikitext);

        $this->assertStringContainsString('<table class="prettytable sortable">', $html);
        $this->assertStringContainsString('<tr class="dog">', $html);
        $this->assertStringContainsString('<th>Amtzeit von</th>', $html);
        $this->assertStringContainsString('<th>bis</th>', $html);
        $this->assertStringContainsString('<th class="unsortable">Abteilungsleiter</th>', $html);
        $this->assertStringContainsString('<th class="unsortable">Stellvertreter</th>', $html);
        $this->assertStringContainsString('<td>20.5.2001</td>', $html);
        $this->assertStringContainsString('<td>Hauptgefreiter Wiewunderland Jim</td>', $html);
        $this->assertStringContainsString('</table>', $html);

        // No raw table markup left, surrounding text still wrapped normally.
        $this->assertStringNotContainsString('{|', $html);
        $this->assertStringNotContainsString('|}', $html);
        $this->assertStringContainsString('<p>Vor der Tabelle.</p>', $html);
        $this->assertStringContainsString('<p>Nach der Tabelle.</p>', $html);
    }

    public function test_table_cells_keep_converted_inline_markup_and_caption(): void
    {
        $wikitext = <<<'WIKI'
{| class="prettytable"
|+ Übersicht
|-
| '''fett''' || [[Nachtwache|die Wache]]
|}
WIKI;

        $html = (new WikitextConverter())->toHtml($wikitext);

        $this->assertStringContainsString('<caption>Übersicht</caption>', $html);
        $this->assertStringContainsString('<td><strong class="highlight">fett</strong></td>', $html);
        $this->assertStringContainsString('<td><a href="/wiki/nachtwache">die Wache</a></td>', $html);
    }

    public function test_event_handler_attributes_are_stripped(): void
    {
        $wikitext = <<<'WIKI'
{| class="x" onclick="evil()"
|-
| style="color:red" onmouseover="evil()"|Zelle
|}
WIKI;

        $html = (new WikitextConverter())->toHtml($wikitext);

        $this->assertStringContainsString('<table class="x">', $html);
        $this->assertStringContainsString('<td style="color:red">Zelle</td>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
    }
}
