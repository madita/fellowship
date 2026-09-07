<?php

namespace Tests\Unit;

use App\Services\Migration\BbcodeConverter;
use PHPUnit\Framework\TestCase;

class BbcodeConverterTest extends TestCase
{
    private BbcodeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new BbcodeConverter();
    }

    public function test_textformatter_xml_format(): void
    {
        $xml = '<r><QUOTE author="Laiza"><s>[quote="Laiza"]</s>Original text<e>[/quote]</e></QUOTE>'
            . 'My <B><s>[b]</s>bold<e>[/b]</e></B> reply with <URL url="https://example.org"><s>[url]</s>a link<e>[/url]</e></URL> <E>:)</E></r>';

        $html = $this->converter->toHtml($xml);

        $this->assertStringContainsString('<blockquote><p><strong>Laiza:</strong></p>Original text</blockquote>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<a href="https://example.org">a link</a>', $html);
        $this->assertStringContainsString(':)', $html);
        $this->assertStringNotContainsString('[quote', $html);
        $this->assertStringNotContainsString('<QUOTE', $html);
        $this->assertStringNotContainsString('<r>', $html);
    }

    public function test_classic_bbcode_with_uid_markers(): void
    {
        $text = '[quote=&quot;Vimes&quot;:1a2b3c4d]Watch out[/quote:1a2b3c4d] '
            . '[b:1a2b3c4d]Yes[/b:1a2b3c4d] see [url=http://example.org:1a2b3c4d]here[/url:1a2b3c4d]';

        $html = $this->converter->toHtml($text);

        $this->assertStringContainsString('<blockquote><p><strong>Vimes:</strong></p>Watch out</blockquote>', $html);
        $this->assertStringContainsString('<strong>Yes</strong>', $html);
        $this->assertStringContainsString('<a href="http://example.org">here</a>', $html);
        $this->assertStringNotContainsString('1a2b3c4d', $html);
    }

    public function test_smilies_and_magic_urls(): void
    {
        $text = 'Hi <!-- s:) --><img src="{SMILIES_PATH}/icon_e_smile.gif" alt=":)" title="Smile" /><!-- s:) --> '
            . 'visit <!-- m --><a class="postlink" href="http://example.org">http://example.org</a><!-- m -->';

        $html = $this->converter->toHtml($text);

        $this->assertStringContainsString('Hi :)', $html);
        $this->assertStringContainsString('href="http://example.org"', $html);
        $this->assertStringNotContainsString('<!--', $html);
        $this->assertStringNotContainsString('SMILIES_PATH', $html);
    }

    public function test_plain_text_gets_paragraphs(): void
    {
        $html = $this->converter->toHtml("First line\nsecond line\n\nNew paragraph");

        $this->assertStringContainsString('<br', $html);
        $this->assertStringContainsString('</p><p>', $html);
    }

    public function test_empty_input(): void
    {
        $this->assertSame('', $this->converter->toHtml(null));
        $this->assertSame('', $this->converter->toHtml('  '));
    }
}
