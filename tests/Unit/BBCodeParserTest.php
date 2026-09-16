<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Post\Infrastructure\Parser\BBCodeParser;
use PHPUnit\Framework\TestCase;

/**
 * Full BBCode parser test suite.
 *
 * Tests all implemented tags with XSS, nesting, and edge cases.
 */
class BBCodeParserTest extends TestCase
{
    private BBCodeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BBCodeParser(smiliesEnabled: true);
    }

    public function testBold(): void
    {
        $this->assertStringContainsString('<strong>text</strong>', $this->parser->parse('[b]text[/b]'));
    }

    public function testItalic(): void
    {
        $this->assertStringContainsString('<em>text</em>', $this->parser->parse('[i]text[/i]'));
    }

    public function testUnderline(): void
    {
        $this->assertStringContainsString('bb-underline', $this->parser->parse('[u]text[/u]'));
    }

    public function testStrikethrough(): void
    {
        $this->assertStringContainsString('bb-strikethrough', $this->parser->parse('[s]text[/s]'));
    }

    public function testUrl(): void
    {
        $result = $this->parser->parse('[url]https://example.com[/url]');
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('rel="nofollow ugc"', $result);
    }

    public function testUrlWithTitle(): void
    {
        $result = $this->parser->parse('[url=https://example.com]Example[/url]');
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('>Example<', $result);
    }

    public function testEmail(): void
    {
        $result = $this->parser->parse('[email]user@example.com[/email]');
        $this->assertStringContainsString('href="mailto:user@example.com"', $result);
    }

    public function testImage(): void
    {
        $result = $this->parser->parse('[img]https://example.com/img.png[/img]');
        $this->assertStringContainsString('src="https://example.com/img.png"', $result);
    }

    public function testImageWithSize(): void
    {
        $result = $this->parser->parse('[img=640x480]https://example.com/img.png[/img]');
        $this->assertStringContainsString('width="640" height="480"', $result);
    }

    public function testQuote(): void
    {
        $result = $this->parser->parse('[quote]cited text[/quote]');
        $this->assertStringContainsString('<blockquote>cited text</blockquote>', $result);
    }

    public function testQuoteWithAttribution(): void
    {
        $result = $this->parser->parse('[quote=Alice]cited text[/quote]');
        $this->assertStringContainsString('<cite>Alice wrote:</cite>', $result);
        $this->assertStringContainsString('cited text', $result);
    }

    public function testCode(): void
    {
        $result = $this->parser->parse('[code]<?php echo "hello"; ?>[/code]');
        $this->assertStringContainsString('<pre><code>', $result);
        $this->assertStringContainsString('echo', $result);
    }

    public function testHorizontalRule(): void
    {
        $this->assertStringContainsString('<hr />', $this->parser->parse('[hr]'));
    }

    public function testColor(): void
    {
        $result = $this->parser->parse('[color=red]text[/color]');
        $this->assertStringContainsString('color: red', $result);
    }

    public function testSize(): void
    {
        $result = $this->parser->parse('[size=18]text[/size]');
        $this->assertStringContainsString('font-size: 18px', $result);
    }

    public function testAlignment(): void
    {
        $result = $this->parser->parse('[align=center]centered text[/align]');
        $this->assertStringContainsString('text-align: center', $result);
    }

    public function testSpoiler(): void
    {
        $result = $this->parser->parse('[spoiler]hidden content[/spoiler]');
        $this->assertStringContainsString('class="spoiler"', $result);
        $this->assertStringContainsString('hidden content', $result);
    }

    public function testYouTube(): void
    {
        $result = $this->parser->parse('[youtube]dQw4w9WgXcQ[/youtube]');
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $result);
        $this->assertStringContainsString('youtube-wrapper', $result);
    }

    public function testUnorderedList(): void
    {
        $result = $this->parser->parse("[list][*]Item 1[*]Item 2[/list]");
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Item 1</li>', $result);
        $this->assertStringContainsString('<li>Item 2</li>', $result);
    }

    public function testOrderedList(): void
    {
        $result = $this->parser->parse("[list=1][*]First[*]Second[/list]");
        $this->assertStringContainsString('<ol', $result);
        $this->assertStringContainsString('<li>First</li>', $result);
    }

    public function testNestedLists(): void
    {
        $result = $this->parser->parse("[list][*]Top[*]Sub list[list][*]Nested 1[*]Nested 2[/list][*]Bottom[/list]");
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('Nested 1', $result);
        $this->assertStringContainsString('Bottom', $result);
    }

    public function testTable(): void
    {
        $result = $this->parser->parse("[table][tr][td]Cell 1[/td][td]Cell 2[/td][/tr][/table]");
        $this->assertStringContainsString('bb-table', $result);
        $this->assertStringContainsString('Cell 1', $result);
        $this->assertStringContainsString('Cell 2', $result);
    }

    public function testSmilies(): void
    {
        $result = $this->parser->parse('Hello :) world');
        $this->assertStringContainsString('class="smiley"', $result);
        $this->assertStringContainsString('smile', $result);
    }

    public function testXssViaUrl(): void
    {
        $result = $this->parser->parse('[url]javascript:alert(1)[/url]');
        // The URL should NOT generate a clickable link
        $this->assertStringNotContainsString('<a href=', $result, 'XSS URL should not produce a link tag');
        // The javascript scheme is blocked, URL shown as plain text (safe)
        $this->assertStringNotContainsString('href="javascript', $result, 'No href with javascript:');
    }

    public function testXssViaTagInjection(): void
    {
        $result = $this->parser->parse('[b]<script>alert(1)</script>[/b]');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testStripsBBCode(): void
    {
        $plain = $this->parser->stripBBCode('[b]bold[/b] and [i]italic[/i]');
        $this->assertStringContainsString('bold', $plain);
        $this->assertStringContainsString('italic', $plain);
        $this->assertStringNotContainsString('<strong>', $plain);
    }

    public function testAutoLinkBareUrl(): void
    {
        $result = $this->parser->parse('Visit https://example.com for info');
        $this->assertStringContainsString('href="https://example.com"', $result);
    }

    public function testUrlWithSmileyInPathDoesNotBreak(): void
    {
        $result = $this->parser->parse('Check https://example.com/page :)');
        $this->assertStringContainsString('href="https://example.com/page"', $result);
        $this->assertStringContainsString('class="smiley"', $result);
    }

    public function testMultipleTags(): void
    {
        $result = $this->parser->parse('[b][i]bold italic[/i][/b]');
        $this->assertStringContainsString('<strong><em>bold italic</em></strong>', $result);
    }

    public function testComplexMarkup(): void
    {
        $input = "[quote=Admin][b]Important:[/b]\n[url=https://example.com/tos]Terms[/url][/quote]";
        $result = $this->parser->parse($input);
        $this->assertStringContainsString('<blockquote>', $result);
        $this->assertStringContainsString('<strong>Important:</strong>', $result);
        $this->assertStringContainsString('href="https://example.com/tos"', $result);
    }
}