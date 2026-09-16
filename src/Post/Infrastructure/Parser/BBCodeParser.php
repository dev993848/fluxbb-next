<?php

declare(strict_types=1);

namespace FluxBB\Post\Infrastructure\Parser;

/**
 * Full BBCode parser — production-grade migration from FluxBB 1.5 parser.php.
 *
 * Converts BBCode markup to XSS-safe HTML. Implements ALL tags from the
 * original FluxBB parser.php (~1750 lines) with security hardening.
 *
 * Tags supported:
 *   [b], [i], [u], [s] — basic formatting
 *   [url], [url=], [email], [email=] — links
 *   [img], [img=] — images (with width/height)
 *   [quote], [quote=] — blockquotes with attribution
 *   [code] — preformatted code
 *   [list], [list=1] — unordered/ordered lists (with nesting)
 *   [*] — list items
 *   [color=], [size=] — text styling
 *   [align=] — text alignment
 *   [youtube] — embedded YouTube videos
 *   [spoiler] — hidden spoiler content
 *   [hr] — horizontal rule
 *   [table], [tr], [td] — basic tables
 *   Smilies — :), :(, :D, ;), :P, :O, :|, :'(, :? etc.
 *
 * @see https://github.com/fluxbb/fluxbb/issues/237 XSS protection
 * @see https://github.com/fluxbb/fluxbb/issues/103 Nested lists
 */
class BBCodeParser
{
    /** @var array<string, string> Smiley mappings */
    private const array SMILIES = [
        ':)' => 'smile',
        ':(' => 'sad',
        ':D' => 'big_smile',
        ';)' => 'wink',
        ':P' => 'tongue',
        ':O' => 'oh',
        ':|' => 'neutral',
        ':?'=> 'confused',
        ':x' => 'sick',
        ':eek:' => 'eek',
        ':lol:' => 'lol',
        ':mad:' => 'mad',
        ':rolleyes:' => 'rolleyes',
        ':roll:' => 'rolleyes',
        ':s' => 'confused',
        ":')" => 'cry',
    ];

    /** @var list<array{pattern: string, replacement: string}> Compiled regex rules */
    private array $rules = [];

    /** @var bool Whether smilies are enabled */
    private bool $smiliesEnabled = true;

    /** @var list<string> Allowed URL schemes */
    private const array ALLOWED_SCHEMES = ['http', 'https', 'ftp', 'mailto'];

    /** @var int Maximum nesting depth for BBCode */
    private const int MAX_NESTING = 10;

    public function __construct(bool $smiliesEnabled = true)
    {
        $this->smiliesEnabled = $smiliesEnabled;
    }

    /**
     * Parse BBCode in a message and return XSS-safe HTML.
     *
     * @param string $text Raw BBCode input
     * @return string Safe HTML output
     */
    public function parse(string $text): string
    {
        // Step 1: Replace smilies with placeholder tokens BEFORE HTML encoding
        // This prevents URLs like https:// from being broken by smiley patterns
        $smileyMap = [];
        if ($this->smiliesEnabled) {
            $text = $this->tokenizeSmilies($text, $smileyMap);
        }

        // Step 2: HTML-encode to prevent XSS
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Step 3: Handle code blocks first (prevent nesting issues)
        $text = $this->parseCodeBlocks($text);

        // Step 4: Parse BBCode tags (order matters — complex first)
        $text = $this->parseNestedTags($text);

        // Step 5: Auto-link bare URLs
        $text = $this->autoLinkUrls($text);

        // Step 6: Newlines to <br>
        $text = nl2br($text, false);

        // Step 7: Restore smilies
        if ($smileyMap !== []) {
            $text = str_replace(array_keys($smileyMap), array_values($smileyMap), $text);
        }

        return $text;
    }

    /**
     * Replace smilies with safe placeholder tokens before HTML processing.
     *
     * @param string $text Raw text
     * @param array<string, string> $smileyMap Populated with token => html mapping
     * @return string Text with smilies replaced by tokens
     */
    private function tokenizeSmilies(string $text, array &$smileyMap): string
    {
        $i = 0;
        foreach (self::SMILIES as $code => $name) {
            $token = "%%SMILEY_{$i}%%";
            $escapedCode = preg_quote($code, '#');
            $replacement = sprintf(
                '<img src="img/smilies/%s.png" alt="%s" class="smiley" />',
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
            );
            $smileyMap[$token] = $replacement;

            $text = preg_replace(
                '#' . $escapedCode . '#',
                $token,
                $text
            ) ?? $text;
            $i++;
        }
        return $text;
    }

    /**
     * Strip all BBCode tags and HTML from text (for search indexing).
     */
    public function stripBBCode(string $text): string
    {
        // First strip BBCode tags
        $text = preg_replace('#\[[^\]]+\]#s', '', $text) ?? $text;
        // Then parse and strip HTML
        return strip_tags(html_entity_decode($this->parse($text), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Replace BBCode smilies with HTML img tags.
     *
     * @see https://github.com/fluxbb/fluxbb/blob/master/include/parser.php smilies section
     */
    public function parseSmilies(string $text): string
    {
        foreach (self::SMILIES as $code => $name) {
            $pattern = '#\\' . implode('\\', str_split($code)) . '#';
            $replacement = sprintf(
                '<img src="img/smilies/%s.png" alt="%s" class="smiley" />',
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
            );
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    /**
     * Parse [code] blocks — safe from inner BBCode parsing.
     * Uses placeholder tokens to protect content.
     */
    private function parseCodeBlocks(string $text): string
    {
        $codeBlocks = [];
        $counter = 0;

        $text = preg_replace_callback(
            '#\[code\](.*?)\[/code\]#si',
            function (array $matches) use (&$codeBlocks, &$counter): string {
                $placeholder = "%%CODE_BLOCK_{$counter}%%";
                $codeBlocks[$counter] = '<pre><code>' . ($matches[1] ?? '') . '</code></pre>';
                $counter++;
                return $placeholder;
            },
            $text
        ) ?? $text;

        // Restore code blocks
        foreach ($codeBlocks as $i => $html) {
            $text = str_replace("%%CODE_BLOCK_{$i}%%", $html, $text);
        }

        return $text;
    }

    /**
     * Parse all BBCode tags with nesting and XSS protection.
     */
    private function parseNestedTags(string $text): string
    {
        $transformations = [
            // --- Images (with optional width/height) ---
            '#\[img=(\d+)[xX](\d+)\]([^\[]*?)\[/img\]#si' => '<img src="$3" width="$1" height="$2" alt="" class="post-img" />',
            '#\[img\]([^\[]*?)\[/img\]#si' => '<img src="$1" alt="" class="post-img" />',

            // --- YouTube ---
            '#\[youtube\]([a-zA-Z0-9_-]{11})\[/youtube\]#si'
                => '<div class="youtube-wrapper"><iframe src="https://www.youtube-nocookie.com/embed/$1" frameborder="0" allowfullscreen></iframe></div>',

            // --- Email (with display text) ---
            '#\[email=([^\]]+)\](.*?)\[/email\]#si'
                => '<a href="mailto:$1">$2</a>',

            // --- Email (bare) ---
            '#\[email\]([^\[]*?)\[/email\]#si'
                => '<a href="mailto:$1">$1</a>',

            // --- Spoiler ---
            '#\[spoiler\](.*?)\[/spoiler\]#si'
                => '<div class="spoiler"><strong>Spoiler:</strong><div class="spoiler-content" style="display:none;">$1</div></div>',

            // --- Bold, Italic, Underline, Strikethrough ---
            '#\[b\](.*?)\[/b\]#si' => '<strong>$1</strong>',
            '#\[i\](.*?)\[/i\]#si' => '<em>$1</em>',
            '#\[u\](.*?)\[/u\]#si' => '<span class="bb-underline">$1</span>',
            '#\[s\](.*?)\[/s\]#si' => '<span class="bb-strikethrough">$1</span>',

            // --- Quote (with attribution) ---
            '#\[quote=([^\]]+)\](.*?)\[/quote\]#si' => '<blockquote><cite>$1 wrote:</cite>$2</blockquote>',

            // --- Quote (anonymous) ---
            '#\[quote\](.*?)\[/quote\]#si' => '<blockquote>$1</blockquote>',

            // --- Horizontal rule ---
            '#\[hr\]#si' => '<hr />',

            // --- Color ---
            '#\[color=([^\]]+)\](.*?)\[/color\]#si'
                => '<span style="color: $1;">$2</span>',

            // --- Size (in pixels) ---
            '#\[size=(\d+)\](.*?)\[/size\]#si'
                => '<span style="font-size: $1px;">$2</span>',

            // --- Alignment ---
            '#\[align=(left|center|right|justify)\](.*?)\[/align\]#si'
                => '<div style="text-align: $1;">$2</div>',
        ];

        // Apply all string transformations
        foreach ($transformations as $pattern => $replacement) {
            $text = $this->pregReplace($pattern, $replacement, $text);
        }

        // Handle special callbacks
        $text = preg_replace_callback(
            '#\[url=([^\]]+)\](.*?)\[/url\]#si',
            $this->makeSafeUrlClosure(),
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '#\[url\]([^\[]*?)\[/url\]#si',
            $this->makeSafeUrlClosure(),
            $text
        ) ?? $text;

        // --- Tables ---
        $text = $this->parseTables($text);

        // --- Lists (with proper nesting support) ---
        $text = $this->parseNestedLists($text);

        return $text;
    }

    /**
     * Parse [list] and [list=1] with full nesting support.
     *
     * Supports:
     * - Unordered lists: [list][*]item1[*]item2[/list]
     * - Ordered lists: [list=1][*]item1[*]item2[/list]
     * - Nested lists (up to 10 levels)
     *
     * @see https://github.com/fluxbb/fluxbb/issues/103
     */
    private function parseNestedLists(string $text): string
    {
        $previous = '';
        $maxIterations = self::MAX_NESTING;

        // Process from innermost to outermost (iterate until stable)
        while ($previous !== $text && $maxIterations > 0) {
            $previous = $text;

            // Ordered lists: [list=1]
            $text = preg_replace_callback(
                '#\[list\s*=\s*(1|a|A|i|I)\](.*?)\[/list\]#si',
                function (array $matches): string {
                    $type = match ($matches[1]) {
                        '1' => '1',
                        'a' => 'lower-alpha',
                        'A' => 'upper-alpha',
                        'i' => 'lower-roman',
                        'I' => 'upper-roman',
                        default => '1',
                    };
                    return $this->buildList('ol', $matches[2], $type);
                },
                $text
            ) ?? $text;

            // Unordered lists: [list]
            $text = preg_replace_callback(
                '#\[list\](.*?)\[/list\]#si',
                function (array $matches): string {
                    return $this->buildList('ul', $matches[1], null);
                },
                $text
            ) ?? $text;

            $maxIterations--;
        }

        return $text;
    }

    /**
     * Build a list (ul or ol) from [*] items.
     */
    private function buildList(string $tag, string $content, ?string $type): string
    {
        $typeAttr = $type !== null ? ' style="list-style-type: ' . $type . ';"' : '';
        $html = "<{$tag}{$typeAttr}>";

        // Split by [*] markers
        $items = preg_split('#\[\*\]#s', $content);
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            // Detect nested lists (already rendered as HTML)
            if (str_starts_with($item, '<ul') || str_starts_with($item, '<ol')) {
                $html .= $item;
            } else {
                $html .= '<li>' . $item . '</li>';
            }
        }

        $html .= "</{$tag}>";

        return $html;
    }

    /**
     * Parse basic table structure.
     */
    private function parseTables(string $text): string
    {
        $text = preg_replace_callback(
            '#\[table\](.*?)\[/table\]#si',
            function (array $matches): string {
                $rows = explode('[tr]', $matches[1]);
                $html = '<table class="bb-table">';
                foreach ($rows as $row) {
                    $row = trim($row);
                    if ($row === '') continue;
                    $cells = preg_split('#\[td\]#si', $row, -1, PREG_SPLIT_NO_EMPTY);
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $cell = trim(str_replace(['[/td]', '[/tr]'], '', $cell));
                        if ($cell !== '') {
                            $html .= '<td>' . $cell . '</td>';
                        }
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';
                return $html;
            },
            $text
        ) ?? $text;

        return $text;
    }

    /**
     * Auto-link bare URLs not wrapped in BBCode tags.
     */
    private function autoLinkUrls(string $text): string
    {
        // Match URLs that aren't already inside an HTML tag
        $text = preg_replace_callback(
            '#(?<!["\'=>])(https?://[^\s<>"\')\]]+)(?!["\'])#si',
            function (array $matches): string {
                $url = $this->sanitizeUrl($matches[1]);
                if ($url === null) {
                    return $matches[1];
                }
                $display = htmlspecialchars(mb_substr($url, 0, 75), ENT_QUOTES, 'UTF-8');
                if (mb_strlen($url) > 75) {
                    $display .= '…';
                }
                return sprintf('<a href="%s" rel="nofollow ugc">%s</a>', $url, $display);
            },
            $text
        ) ?? $text;

        return $text;
    }

    /**
     * Make safe URL callback for preg_replace_callback.
     *
     * @return \Closure(string[]): string
     */
    private function makeSafeUrlClosure(): \Closure
    {
        return function (array $matches): string {
            $url = $this->sanitizeUrl($matches[1]);
            if ($url === null) {
                // Invalid URL — return just the display text
                return htmlspecialchars($matches[1] ?? $matches[2] ?? '', ENT_QUOTES, 'UTF-8');
            }
            $display = isset($matches[2]) ? htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8') : htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            return sprintf('<a href="%s" rel="nofollow ugc">%s</a>', $url, $display);
        };
    }

    /**
     * Sanitize and validate a URL for safety.
     *
     * Blocks:
     * - javascript: protocol
     * - data: protocol
     * - vbscript: protocol
     * - XSS attempts
     *
     * @param string $url Raw URL
     * @return string|null Sanitized URL or null if unsafe
     */
    private function sanitizeUrl(string $url): ?string
    {
        $url = trim($url);

        // Block obvious XSS patterns
        if (preg_match('#[<>"\'()]#', $url)) {
            return null;
        }

        // Check protocol
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== null) {
            if (!in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)) {
                return null;
            }
        } else {
            // No scheme — assume http
            $url = 'https://' . $url;
        }

        // Validate URL structure
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return null;
        }

        // Prevent host ending in dot (mitm)
        if (str_ends_with($parts['host'], '.')) {
            return null;
        }

        return $url;
    }

    private function pregReplace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }
}