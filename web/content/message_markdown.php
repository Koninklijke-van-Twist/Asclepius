<?php

/**
 * Safe Markdown for ticket message bodies.
 *
 * User/bot content is untrusted: text is HTML-escaped first, only a small
 * set of tags is generated, and link hrefs are protocol-restricted.
 * Inline attachments (`[[attachment:name]]`) and keyboard icons (`[Ctrl]`)
 * are handled outside Markdown so those UI mechanisms stay intact.
 */

function sanitizeMessageMarkdownHref(string $rawUrl): ?string
{
    $url = trim(html_entity_decode($rawUrl, ENT_QUOTES, 'UTF-8'));
    if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url) === 1) {
        return null;
    }

    if (preg_match('/^www\./i', $url) === 1) {
        $url = 'https://' . $url;
    }

    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1) {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return null;
        }

        return $url;
    }

    if (preg_match('/^(?:[.#\/?]|index\.php|admin\.php)/i', $url) !== 1) {
        return null;
    }

    return $url;
}

/**
 * @return array{text: string, codes: list<string>}
 */
function extractMessageInlineCodePlaceholders(string $text): array
{
    $codes = [];
    $replaced = preg_replace_callback(
        '/`([^`\n]+)`/',
        static function (array $match) use (&$codes): string {
            $codes[] = (string) $match[1];

            return "\x1AASCCODE" . (count($codes) - 1) . "\x1A";
        },
        $text
    );

    return [
        'text' => is_string($replaced) ? $replaced : $text,
        'codes' => $codes,
    ];
}

/**
 * @param list<string> $codes
 */
function restoreMessageInlineCodePlaceholders(string $html, array $codes): string
{
    foreach ($codes as $index => $code) {
        $html = str_replace(
            "\x1AASCCODE" . $index . "\x1A",
            '<code class="message-md-code">' . h($code) . '</code>',
            $html
        );
    }

    return $html;
}

/**
 * Bold/italic on already-escaped text. Generated links are placeholders at
 * this point, so `*` inside a URL can never become emphasis.
 */
function applyMessageInlineEmphasis(string $escapedText): string
{
    $escapedText = preg_replace('/\*\*(?!\s)([^*\n]+?)(?<!\s)\*\*/', '<strong>$1</strong>', $escapedText) ?? $escapedText;
    $escapedText = preg_replace('/(?<!\*)\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/', '<em>$1</em>', $escapedText) ?? $escapedText;

    return $escapedText;
}

/**
 * Splits trailing sentence punctuation off a bare URL candidate (GFM-style).
 * A closing parenthesis is only stripped when it is unbalanced, so
 * `(https://x.nl/a)` loses the `)` but `https://x.nl/Foo_(bar)` keeps it.
 *
 * @return array{0: string, 1: string} [url, suffix]
 */
function splitMessageUrlTrailingPunctuation(string $candidate): array
{
    $url = $candidate;
    while ($url !== '') {
        $last = substr($url, -1);
        if (strpbrk($last, '.,;:!?*_~\'"]') !== false) {
            $url = substr($url, 0, -1);
            continue;
        }
        if ($last === ')' && substr_count($url, ')') > substr_count($url, '(')) {
            $url = substr($url, 0, -1);
            continue;
        }
        break;
    }

    return [$url, (string) substr($candidate, strlen($url))];
}

function buildMessageAnchorHtml(string $href, string $labelHtml, bool $forEmail, bool $allowNewTab = true): string
{
    $safeHref = h($href);
    if ($forEmail || !$allowNewTab) {
        return '<a href="' . $safeHref . '">' . $labelHtml . '</a>';
    }

    $ticketId = extractAsclepiusTicketIdFromUrl($href);
    $target = $ticketId > 0 ? '' : ' target="_blank" rel="noopener noreferrer"';

    return '<a href="' . $safeHref . '"' . $target . '>' . $labelHtml . '</a>';
}

/**
 * Renders one line of inline message text to safe HTML.
 *
 * Tokenized so links are never matched inside generated HTML:
 *  1. inline code, markdown links, bare URLs, e-mail addresses and phone
 *     numbers are pulled out of the RAW text and replaced by placeholders
 *     (their HTML is built and escaped individually);
 *  2. the remaining text is HTML-escaped and gets bold/italic/shortcut markup;
 *  3. the placeholders are put back.
 */
function renderMessageInlineHtml(string $text, bool $forEmail = false): string
{
    $inlineCode = extractMessageInlineCodePlaceholders($text);
    $work = $inlineCode['text'];
    $tokens = [];
    $store = static function (string $html) use (&$tokens): string {
        $key = "\x1AASCTOK" . count($tokens) . "\x1A";
        $tokens[$key] = $html;

        return $key;
    };

    // 1a. Markdown links [label](url). Only safe hrefs become links.
    $work = preg_replace_callback(
        '/\[([^\]\n]+)\]\(([^)\s\x1A]+)\)/',
        static function (array $match) use ($forEmail, $store): string {
            $href = sanitizeMessageMarkdownHref((string) $match[2]);
            if ($href === null) {
                return (string) $match[0];
            }

            $labelHtml = applyMessageInlineEmphasis(h((string) $match[1]));

            return $store(buildMessageAnchorHtml($href, $labelHtml, $forEmail));
        },
        $work
    ) ?? $work;

    // 1b. Bare http(s):// and www. URLs.
    $work = preg_replace_callback(
        '~(?<![\w.@/-])(?:https?://|www\.)[^\s<>\x1A]+~i',
        static function (array $match) use ($forEmail, $store): string {
            [$url, $suffix] = splitMessageUrlTrailingPunctuation((string) $match[0]);
            if (preg_match('~^(?:https?://|www\.)[^\s]+$~i', $url) !== 1 || preg_match('~^(?:https?://|www\.)$~i', $url) === 1) {
                return (string) $match[0];
            }

            $href = str_starts_with(strtolower($url), 'www.') ? 'https://' . $url : $url;
            $ticketId = extractAsclepiusTicketIdFromUrl($href);
            $labelHtml = $ticketId > 0 ? h(formatTicketRefLabel($ticketId)) : h($url);

            return $store(buildMessageAnchorHtml($href, $labelHtml, $forEmail)) . $suffix;
        },
        $work
    ) ?? $work;

    // 1c. E-mail addresses.
    $work = preg_replace_callback(
        '/(?<![\w.@\x1A])([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/i',
        static function (array $match) use ($store): string {
            $email = (string) $match[1];

            return $store('<a href="mailto:' . h($email) . '">' . h($email) . '</a>');
        },
        $work
    ) ?? $work;

    // 1d. Phone numbers.
    $work = preg_replace_callback(
        '/(?<![\w\x1A])(\+?[0-9][0-9 \t()\/.-]{6,}[0-9])/',
        static function (array $match) use ($store): string {
            $phoneText = trim((string) $match[1]);
            $phoneHref = preg_replace('/[^0-9+]/', '', $phoneText) ?? '';
            if ($phoneHref === '') {
                return (string) $match[0];
            }

            return $store('<a href="tel:' . h($phoneHref) . '">' . h($phoneText) . '</a>');
        },
        $work
    ) ?? $work;

    // 2. Escape and format the remaining text.
    $html = applyMessageInlineEmphasis(h($work));
    $html = renderShortcutMarkup($html, $forEmail);

    // 3. Restore links, then inline code (link labels may contain code).
    $html = $tokens !== [] ? strtr($html, $tokens) : $html;

    return restoreMessageInlineCodePlaceholders($html, $inlineCode['codes']);
}

function renderMessageMarkdownCodeBlock(string $language, string $code): string
{
    $language = strtolower(trim($language));
    $class = $language !== '' && preg_match('/^[a-z0-9_-]+$/', $language) === 1
        ? ' class="language-' . h($language) . '"'
        : '';

    return '<pre class="message-md-pre"><code' . $class . '>' . h($code) . '</code></pre>';
}

/**
 * Volledige afgesloten ```-hekken, in volgorde. Een hek zonder sluitregel telt niet:
 * die blijft gewone tekst, net als bij het renderen.
 *
 * @return list<string>
 */
function messageFencedCodeBlocks(string $text): array
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $normalized);
    $blocks = [];
    $count = count($lines);
    $index = 0;
    while ($index < $count) {
        $trimmed = trim((string) $lines[$index]);
        if (preg_match('/^```([a-zA-Z0-9_-]*)[ \t]*$/', $trimmed) === 1) {
            $close = null;
            for ($lookAhead = $index + 1; $lookAhead < $count; $lookAhead++) {
                if (preg_match('/^```[ \t]*$/', trim((string) $lines[$lookAhead])) === 1) {
                    $close = $lookAhead;
                    break;
                }
            }
            if ($close !== null) {
                $blocks[] = implode("\n", array_slice($lines, $index, $close - $index + 1));
                $index = $close + 1;
                continue;
            }
        }
        $index++;
    }

    return $blocks;
}

/**
 * Haalt afgesloten codehekken uit de tekst vóór de vertaler. De placeholder
 * blijft staan zodat het blok op dezelfde plek terugkomt. De code zelf gaat
 * niet naar de vertaaldienst.
 *
 * @return array{text: string, tokens: list<string>}
 */
function maskMessageFencedCodeBlocks(string $text): array
{
    $blocks = messageFencedCodeBlocks($text);
    if ($blocks === []) {
        return ['text' => $text, 'tokens' => []];
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $tokens = [];
    foreach ($blocks as $block) {
        $placeholder = '⟦cb' . count($tokens) . '⟧';
        $position = strpos($normalized, $block);
        if ($position === false) {
            continue;
        }
        $tokens[] = $block;
        $normalized = substr_replace($normalized, $placeholder, $position, strlen($block));
    }

    return [
        'text' => $normalized,
        'tokens' => $tokens,
    ];
}

/**
 * Zet placeholders terug. Tekst die in het codeblok zelf staat, wordt niet
 * nog een keer als placeholder gelezen.
 *
 * @param list<string> $tokens
 */
function unmaskMessageFencedCodeBlocks(string $text, array $tokens): string
{
    if ($tokens === []) {
        return $text;
    }

    $parts = preg_split('/(⟦cb\d+⟧)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return $text;
    }

    $out = '';
    foreach ($parts as $part) {
        if (preg_match('/^⟦cb(\d+)⟧$/u', $part, $match) === 1) {
            $index = (int) $match[1];
            $out .= array_key_exists($index, $tokens) ? (string) $tokens[$index] : $part;
            continue;
        }
        $out .= $part;
    }

    return $out;
}

/**
 * Zet codehekken uit de brontekst terug als een vertaling ze heeft vertaald
 * of heeft laten vallen. Ontbrekende blokken komen achteraan, in de
 * oorspronkelijke volgorde.
 */
function restoreMessageFencedCodeBlocks(string $displayText, string $rawText): string
{
    $rawBlocks = messageFencedCodeBlocks($rawText);
    if ($rawBlocks === []) {
        return $displayText;
    }

    $display = str_replace(["\r\n", "\r"], "\n", $displayText);
    $displayBlocks = messageFencedCodeBlocks($display);
    if ($displayBlocks === $rawBlocks) {
        return $displayText;
    }

    $offset = 0;
    $pairCount = min(count($displayBlocks), count($rawBlocks));
    for ($index = 0; $index < $pairCount; $index++) {
        $position = strpos($display, $displayBlocks[$index], $offset);
        if ($position === false) {
            break;
        }
        $display = substr_replace($display, $rawBlocks[$index], $position, strlen($displayBlocks[$index]));
        $offset = $position + strlen($rawBlocks[$index]);
    }

    // Zelfde hek telt per keer. str_contains zou het tweede blok als aanwezig
    // zien zodra het eerste er nog staat, ook als de vertaler die placeholder
    // heeft laten vallen.
    $present = [];
    foreach (messageFencedCodeBlocks($display) as $block) {
        $present[$block] = ($present[$block] ?? 0) + 1;
    }
    foreach ($rawBlocks as $block) {
        if (($present[$block] ?? 0) > 0) {
            $present[$block]--;
            continue;
        }
        $display = rtrim($display, "\n");
        $display = ($display !== '' ? $display . "\n\n" : '') . $block;
    }

    return $display;
}

function isMessageMarkdownHeadingLine(string $trimmed): bool
{
    return preg_match('/^#{1,6}\s+\S/', $trimmed) === 1;
}

function isMessageMarkdownQuoteLine(string $trimmed): bool
{
    return str_starts_with($trimmed, '>');
}

function isMessageMarkdownUnorderedLine(string $trimmed): bool
{
    return preg_match('/^[-*+]\s+/', $trimmed) === 1;
}

function isMessageMarkdownOrderedLine(string $trimmed): bool
{
    return preg_match('/^\d+\.\s+/', $trimmed) === 1;
}

function isMessageMarkdownFenceLine(string $trimmed): bool
{
    return preg_match('/^```([a-zA-Z0-9_-]*)[ \t]*$/', $trimmed) === 1;
}

function isMessageMarkdownAttachmentLine(string $trimmed): bool
{
    return preg_match('/^\[\[attachment:(.+)\]\]$/', $trimmed) === 1;
}

/**
 * @return list<string> bestandsnamen van [[attachment:naam]] op een eigen regel
 */
function messageAttachmentMarkerNames(string $messageText): array
{
    $names = [];
    $normalized = str_replace(["\r\n", "\r"], "\n", $messageText);
    foreach (explode("\n", $normalized) as $line) {
        if (preg_match('/^\[\[attachment:(.+)\]\]$/', trim($line), $match) !== 1) {
            continue;
        }
        $name = trim((string) ($match[1] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return $names;
}

function messageAttachmentMarker(string $filename): string
{
    return '[[attachment:' . str_replace(['[', ']'], '', $filename) . ']]';
}

/**
 * Haalt [[attachment:…]] uit de tekst vóór de vertaler. De placeholder blijft
 * staan zodat de markering op dezelfde plek terugkomt.
 *
 * @return array{text: string, tokens: list<string>}
 */
function maskMessageAttachmentMarkers(string $text): array
{
    $tokens = [];
    $masked = preg_replace_callback(
        '/\[\[attachment:[^\]\r\n]+\]\]/',
        static function (array $match) use (&$tokens): string {
            $placeholder = '⟦att' . count($tokens) . '⟧';
            $tokens[] = (string) $match[0];

            return $placeholder;
        },
        $text
    );

    return [
        'text' => is_string($masked) ? $masked : $text,
        'tokens' => $tokens,
    ];
}

/**
 * @param list<string> $tokens
 */
function unmaskMessageAttachmentMarkers(string $text, array $tokens): string
{
    foreach ($tokens as $index => $token) {
        $text = str_replace('⟦att' . $index . '⟧', (string) $token, $text);
    }

    return $text;
}

/**
 * Haalt [[attachment:…]] weg van regels die nog andere tekst bevatten.
 * Alleen een markering op een eigen regel wordt als afbeelding getoond.
 */
function removeInlineAttachmentMarkers(string $text): string
{
    if (!str_contains($text, '[[attachment:')) {
        return $text;
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $normalized);
    $rewritten = [];
    $changed = false;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || preg_match('/^\[\[attachment:[^\]\n]+\]\]$/', $trimmed) === 1 || !str_contains($line, '[[attachment:')) {
            $rewritten[] = $line;
            continue;
        }

        $without = preg_replace('/\[\[attachment:[^\]\n]+\]\]/', '', $line);
        if (!is_string($without) || $without === $line) {
            $rewritten[] = $line;
            continue;
        }

        $collapsed = preg_replace('/[ \t]{2,}/', ' ', $without);
        $rewritten[] = trim(is_string($collapsed) ? $collapsed : $without);
        $changed = true;
    }

    if (!$changed) {
        return $text;
    }

    return implode("\n", $rewritten);
}

/**
 * Zet bijlagemarkeringen uit de originele tekst terug als een vertaling ze
 * heeft laten vallen of op dezelfde regel als andere tekst heeft gezet.
 * Alleen een markering op een eigen regel telt als aanwezig. Markeringen vóór
 * de eerste tekst komen vooraan, de rest achteraan, in de oorspronkelijke volgorde.
 */
function restoreMissingAttachmentMarkers(string $displayText, string $rawText): string
{
    if ($rawText === '' || $displayText === $rawText) {
        return $displayText;
    }

    $displayText = removeInlineAttachmentMarkers($displayText);

    $present = [];
    foreach (messageAttachmentMarkerNames($displayText) as $name) {
        $present[$name] = true;
    }

    $prefix = [];
    $suffix = [];
    $seenProse = false;
    $normalized = str_replace(["\r\n", "\r"], "\n", $rawText);
    foreach (explode("\n", $normalized) as $line) {
        if (preg_match('/^\[\[attachment:(.+)\]\]$/', trim($line), $match) === 1) {
            $name = trim((string) ($match[1] ?? ''));
            if ($name === '' || isset($present[$name])) {
                if ($name !== '') {
                    $present[$name] = true;
                }
                continue;
            }
            $present[$name] = true;
            $marker = messageAttachmentMarker($name);
            if ($seenProse) {
                $suffix[] = $marker;
            } else {
                $prefix[] = $marker;
            }
            continue;
        }
        if (trim($line) !== '') {
            $seenProse = true;
        }
    }

    if ($prefix === [] && $suffix === []) {
        return $displayText;
    }

    $display = str_replace(["\r\n", "\r"], "\n", $displayText);
    if ($prefix !== []) {
        $display = implode("\n", $prefix) . ($display !== '' ? "\n\n" . ltrim($display, "\n") : '');
    }
    if ($suffix !== []) {
        $display = rtrim($display, "\n");
        $display = ($display !== '' ? $display . "\n\n" : '') . implode("\n\n", $suffix);
    }

    return $display;
}

function isMessageMarkdownCheckboxLine(string $line): bool
{
    return preg_match('/^(\s*)\[( |x|X)\](?:\s+(.*))?$/', $line) === 1;
}

/**
 * @return list<string>
 */
function splitMessageMarkdownTableCells(string $trimmed): array
{
    $line = preg_replace('/^\s*\|/', '', $trimmed) ?? $trimmed;
    $line = preg_replace('/\|\s*$/', '', $line) ?? $line;
    $parts = preg_split('/(?<!\\\\)\|/', $line);
    if (!is_array($parts)) {
        return [];
    }

    return array_map(
        static fn(string $cell): string => trim(str_replace('\\|', '|', $cell)),
        $parts
    );
}

function isMessageMarkdownTableSeparatorLine(string $trimmed): bool
{
    if (!str_contains($trimmed, '|') || !str_contains($trimmed, '-')) {
        return false;
    }

    $cells = splitMessageMarkdownTableCells($trimmed);
    if ($cells === []) {
        return false;
    }

    foreach ($cells as $cell) {
        if (preg_match('/^:?-{3,}:?$/', $cell) !== 1) {
            return false;
        }
    }

    return true;
}

function isMessageMarkdownTableRowLine(string $trimmed): bool
{
    return $trimmed !== '' && str_contains($trimmed, '|');
}

/**
 * @return list<string>
 */
function parseMessageMarkdownTableAlignments(string $trimmed): array
{
    $alignments = [];
    foreach (splitMessageMarkdownTableCells($trimmed) as $cell) {
        $left = str_starts_with($cell, ':');
        $right = str_ends_with($cell, ':');
        if ($left && $right) {
            $alignments[] = 'center';
        } elseif ($right) {
            $alignments[] = 'right';
        } else {
            $alignments[] = 'left';
        }
    }

    return $alignments;
}

function isMessageMarkdownTableBodyStopLine(string $line): bool
{
    $trimmed = trim($line);
    if ($trimmed === '' || !isMessageMarkdownTableRowLine($trimmed)) {
        return true;
    }

    return isMessageMarkdownFenceLine($trimmed)
        || isMessageMarkdownAttachmentLine($trimmed)
        || isMessageMarkdownHeadingLine($trimmed)
        || isMessageMarkdownQuoteLine($trimmed)
        || isMessageMarkdownUnorderedLine($trimmed)
        || isMessageMarkdownOrderedLine($trimmed)
        || isMessageMarkdownCheckboxLine($line);
}

function isMessageMarkdownBlockStart(string $line, string $nextLine = ''): bool
{
    $trimmed = trim($line);

    return isMessageMarkdownFenceLine($trimmed)
        || isMessageMarkdownAttachmentLine($trimmed)
        || isMessageMarkdownHeadingLine($trimmed)
        || isMessageMarkdownQuoteLine($trimmed)
        || isMessageMarkdownUnorderedLine($trimmed)
        || isMessageMarkdownOrderedLine($trimmed)
        || isMessageMarkdownCheckboxLine($line)
        || (
            isMessageMarkdownTableRowLine($trimmed)
            && isMessageMarkdownTableSeparatorLine(trim($nextLine))
        );
}

function renderMessageMarkdownHeadingHtml(string $trimmed, bool $forEmail): string
{
    preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $match);
    $level = max(1, min(6, strlen((string) ($match[1] ?? '#'))));
    $tagLevel = min(6, $level + 2);
    $content = makeTextInteractive(trim((string) ($match[2] ?? '')), $forEmail);

    return '<h' . $tagLevel . ' class="message-md-heading message-md-heading-' . $level . '">'
        . $content
        . '</h' . $tagLevel . '>';
}

function renderMessageMarkdownCheckboxHtml(string $line, int $messageId, int $lineIndex, bool $forEmail): string
{
    if (preg_match('/^(\s*)\[( |x|X)\](?:\s+(.*))?$/', $line, $checkboxMatch) !== 1) {
        return makeTextInteractive($line, $forEmail);
    }

    $isChecked = strtolower((string) $checkboxMatch[2]) === 'x';
    $checkboxText = (string) ($checkboxMatch[3] ?? '');
    $checkboxLabel = $checkboxText !== '' ? makeTextInteractive($checkboxText, $forEmail) : '&nbsp;';

    if ($forEmail) {
        $marker = $isChecked ? '☑' : '☐';

        return '<span class="message-checkbox-line">' . $marker . ' ' . $checkboxLabel . '</span>';
    }

    return '<label class="message-checkbox-line">'
        . '<input type="checkbox" data-role="message-checkbox" data-message-id="' . (int) $messageId . '" data-line-index="' . (int) $lineIndex . '"'
        . ($isChecked ? ' checked' : '')
        . ($messageId > 0 ? '' : ' disabled')
        . '>'
        . '<span>' . $checkboxLabel . '</span>'
        . '</label>';
}

function renderMessageMarkdownAttachmentHtml(string $trimmed, array $attachments, bool $forEmail): string
{
    preg_match('/^\[\[attachment:(.+)\]\]$/', $trimmed, $attachmentMatch);
    $attachmentName = trim((string) ($attachmentMatch[1] ?? ''));
    $attachment = $attachmentName !== '' ? findAttachmentByOriginalName($attachments, $attachmentName) : null;

    if ($forEmail) {
        return '<p style="margin:8px 0;"><em>📎 '
            . htmlspecialchars($attachmentName !== '' ? $attachmentName : $trimmed, ENT_QUOTES, 'UTF-8')
            . '</em></p>';
    }

    if ($attachment !== null) {
        return renderMessageInlineAttachmentHtml($attachment);
    }

    return '<em>' . h($attachmentName !== '' ? $attachmentName : $trimmed) . '</em>';
}

function renderMessageMarkdownListItemHtml(string $itemText, int $messageId, int $lineIndex, bool $forEmail): string
{
    if (isMessageMarkdownCheckboxLine($itemText)) {
        return renderMessageMarkdownCheckboxHtml($itemText, $messageId, $lineIndex, $forEmail);
    }

    return makeTextInteractive($itemText, $forEmail);
}

function renderMessageMarkdownTableCellHtml(string $cellText, string $tag, string $alignment, bool $forEmail): string
{
    $alignClass = $alignment === 'center' || $alignment === 'right'
        ? ' message-md-cell-' . $alignment
        : '';
    $content = trim($cellText) === '' ? '&nbsp;' : makeTextInteractive($cellText, $forEmail);

    return '<' . $tag . ' class="message-md-cell' . $alignClass . '">' . $content . '</' . $tag . '>';
}

/**
 * @param list<string> $lines
 * @return array{html: string, next_index: int}|null
 */
function tryRenderMessageMarkdownTable(array $lines, int $index, bool $forEmail): ?array
{
    $headerLine = trim((string) ($lines[$index] ?? ''));
    $separatorLine = trim((string) ($lines[$index + 1] ?? ''));
    if (!isMessageMarkdownTableRowLine($headerLine) || !isMessageMarkdownTableSeparatorLine($separatorLine)) {
        return null;
    }

    $headerCells = splitMessageMarkdownTableCells($headerLine);
    if ($headerCells === []) {
        return null;
    }

    $columnCount = count($headerCells);
    $alignments = parseMessageMarkdownTableAlignments($separatorLine);
    $headerHtml = '';
    for ($column = 0; $column < $columnCount; $column++) {
        $alignment = $alignments[$column] ?? 'left';
        $headerHtml .= renderMessageMarkdownTableCellHtml((string) ($headerCells[$column] ?? ''), 'th', $alignment, $forEmail);
    }

    $bodyHtml = '';
    $rowIndex = $index + 2;
    $lineCount = count($lines);
    while ($rowIndex < $lineCount && !isMessageMarkdownTableBodyStopLine((string) $lines[$rowIndex])) {
        $rowCells = splitMessageMarkdownTableCells(trim((string) $lines[$rowIndex]));
        $bodyHtml .= '<tr>';
        for ($column = 0; $column < $columnCount; $column++) {
            $alignment = $alignments[$column] ?? 'left';
            $bodyHtml .= renderMessageMarkdownTableCellHtml((string) ($rowCells[$column] ?? ''), 'td', $alignment, $forEmail);
        }
        $bodyHtml .= '</tr>';
        $rowIndex++;
    }

    $html = '<div class="message-md-table-wrap"><table class="message-md-table"><thead><tr>'
        . $headerHtml
        . '</tr></thead>';
    if ($bodyHtml !== '') {
        $html .= '<tbody>' . $bodyHtml . '</tbody>';
    }
    $html .= '</table></div>';

    return [
        'html' => $html,
        'next_index' => $rowIndex,
    ];
}

function renderTicketMessageMarkdown(?string $messageText, int $messageId = 0, array $attachments = [], bool $forEmail = false): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $messageText));
    if ($normalized === '') {
        return '';
    }

    $lines = explode("\n", $normalized);
    $lineCount = count($lines);
    $parts = [];
    $pending = [];
    $index = 0;

    $flushPending = static function () use (&$pending, &$parts): void {
        if ($pending === []) {
            return;
        }

        $parts[] = implode('<br>', $pending);
        $pending = [];
    };

    while ($index < $lineCount) {
        $line = (string) $lines[$index];
        $trimmed = trim($line);

        if (isMessageMarkdownFenceLine($trimmed) && preg_match('/^```([a-zA-Z0-9_-]*)[ \t]*$/', $trimmed, $fenceMatch) === 1) {
            $closeIndex = null;
            for ($lookAhead = $index + 1; $lookAhead < $lineCount; $lookAhead++) {
                if (preg_match('/^```[ \t]*$/', trim((string) $lines[$lookAhead])) === 1) {
                    $closeIndex = $lookAhead;
                    break;
                }
            }

            if ($closeIndex !== null) {
                $flushPending();
                $codeLines = array_slice($lines, $index + 1, $closeIndex - $index - 1);
                $parts[] = renderMessageMarkdownCodeBlock((string) $fenceMatch[1], implode("\n", $codeLines));
                $index = $closeIndex + 1;
                continue;
            }
        }

        if (isMessageMarkdownAttachmentLine($trimmed)) {
            $flushPending();
            $parts[] = renderMessageMarkdownAttachmentHtml($trimmed, $attachments, $forEmail);
            $index++;
            continue;
        }

        if (isMessageMarkdownHeadingLine($trimmed)) {
            $flushPending();
            $parts[] = renderMessageMarkdownHeadingHtml($trimmed, $forEmail);
            $index++;
            continue;
        }

        $table = tryRenderMessageMarkdownTable($lines, $index, $forEmail);
        if ($table !== null) {
            $flushPending();
            $parts[] = $table['html'];
            $index = $table['next_index'];
            continue;
        }

        if (isMessageMarkdownQuoteLine($trimmed)) {
            $flushPending();
            $quoteLines = [];
            while ($index < $lineCount) {
                $quoteTrimmed = trim((string) $lines[$index]);
                if (!isMessageMarkdownQuoteLine($quoteTrimmed)) {
                    break;
                }

                $quoteBody = preg_replace('/^>\s?/', '', $quoteTrimmed) ?? $quoteTrimmed;
                $quoteLines[] = $quoteBody === '' ? '' : makeTextInteractive($quoteBody, $forEmail);
                $index++;
            }

            $parts[] = '<blockquote class="message-md-quote">' . implode('<br>', $quoteLines) . '</blockquote>';
            continue;
        }

        if (isMessageMarkdownUnorderedLine($trimmed)) {
            $flushPending();
            $items = [];
            while ($index < $lineCount) {
                $listTrimmed = trim((string) $lines[$index]);
                if (!isMessageMarkdownUnorderedLine($listTrimmed)) {
                    break;
                }

                $itemText = (string) preg_replace('/^[-*+]\s+/', '', $listTrimmed);
                $items[] = '<li>' . renderMessageMarkdownListItemHtml($itemText, $messageId, $index, $forEmail) . '</li>';
                $index++;
            }

            $parts[] = '<ul class="message-md-list">' . implode('', $items) . '</ul>';
            continue;
        }

        if (isMessageMarkdownOrderedLine($trimmed)) {
            $flushPending();
            $items = [];
            while ($index < $lineCount) {
                $listTrimmed = trim((string) $lines[$index]);
                if (!isMessageMarkdownOrderedLine($listTrimmed)) {
                    break;
                }

                $itemText = (string) preg_replace('/^\d+\.\s+/', '', $listTrimmed);
                $items[] = '<li>' . renderMessageMarkdownListItemHtml($itemText, $messageId, $index, $forEmail) . '</li>';
                $index++;
            }

            $parts[] = '<ol class="message-md-list message-md-list-ordered">' . implode('', $items) . '</ol>';
            continue;
        }

        if (isMessageMarkdownCheckboxLine($line)) {
            $flushPending();
            $parts[] = renderMessageMarkdownCheckboxHtml($line, $messageId, $index, $forEmail);
            $index++;
            continue;
        }

        if ($trimmed === '') {
            $pending[] = '';
            $index++;
            continue;
        }

        $interactiveLine = makeTextInteractive($line, $forEmail);
        if (ticketStatusNoteFromLine($trimmed) !== null) {
            $pending[] = '<small>' . $interactiveLine . '</small>';
            $index++;
            continue;
        }

        $pending[] = $interactiveLine;
        $index++;
    }

    $flushPending();

    return implode('', $parts);
}

/**
 * Systeemregel "Status gewijzigd naar …" in elke ondersteunde taal.
 * De opmaak (<small>) hangt aan deze herkenning, niet aan de Nederlandse tekst.
 *
 * @return array{status: string, known: bool, label: string}|null
 */
function ticketStatusNoteFromLine(string $line): ?array
{
    $trimmed = trim($line);
    if ($trimmed === '') {
        return null;
    }

    $lower = mb_strtolower($trimmed, 'UTF-8');
    foreach (ticketStatusNotePrefixes() as $prefix) {
        if (!str_starts_with($lower, $prefix)) {
            continue;
        }

        $label = trim(mb_substr($trimmed, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'), " .\t");
        $canonical = ticketStatusLabelMap()[mb_strtolower($label)] ?? null;

        return [
            'status' => $canonical ?? $label,
            'known' => $canonical !== null,
            'label' => $label,
        ];
    }

    return null;
}

/** Eerste bekende status in een bericht, voor de statushistorie. */
function ticketStatusFromMessageText(string $messageText): ?string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", trim($messageText));
    if ($normalized === '') {
        return null;
    }

    foreach (explode("\n", $normalized) as $line) {
        $note = ticketStatusNoteFromLine($line);
        if ($note !== null && $note['known']) {
            return $note['status'];
        }
    }

    return null;
}

/**
 * @return list<array{status: string, known: bool, label: string}>
 */
function ticketStatusNotesInText(string $text): array
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $notes = [];
    foreach (explode("\n", $normalized) as $line) {
        $note = ticketStatusNoteFromLine($line);
        if ($note !== null) {
            $notes[] = $note;
        }
    }

    return $notes;
}

function ticketMessageIsStatusNoteOnly(string $text): bool
{
    $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($normalized === '') {
        return false;
    }

    $sawNote = false;
    foreach (explode("\n", $normalized) as $line) {
        if (trim($line) === '') {
            continue;
        }
        if (ticketStatusNoteFromLine($line) === null) {
            return false;
        }
        $sawNote = true;
    }

    return $sawNote;
}

function ticketTextWithoutStatusNotes(string $text): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $kept = [];
    foreach (explode("\n", $normalized) as $line) {
        if (ticketStatusNoteFromLine($line) !== null) {
            continue;
        }
        $kept[] = $line;
    }

    return rtrim(implode("\n", $kept));
}

/**
 * @param array{status: string, known: bool, label: string} $note
 */
function ticketRenderStatusNote(array $note, string $lang): string
{
    $lang = strtolower(trim($lang));
    if (!defined('SUPPORTED_LANGUAGES') || !is_array(SUPPORTED_LANGUAGES) || !array_key_exists($lang, SUPPORTED_LANGUAGES)) {
        $lang = 'nl';
    }

    $label = $note['known'] && function_exists('translateStatus')
        ? translateStatus((string) $note['status'], $lang)
        : (string) $note['label'];
    if (function_exists('__mail')) {
        return __mail('flash.status_changed_to', $lang, $label);
    }

    return 'Status gewijzigd naar ' . $label . '.';
}

/**
 * @param list<array{status: string, known: bool, label: string}> $notes
 */
function ticketAppendStatusNotes(string $body, array $notes, string $lang): string
{
    if ($notes === []) {
        return $body;
    }

    $lines = [];
    foreach ($notes as $note) {
        $lines[] = ticketRenderStatusNote($note, $lang);
    }
    $noteBlock = implode("\n", $lines);
    if (trim($body) === '') {
        return $noteBlock;
    }

    return rtrim($body) . "\n\n" . $noteBlock;
}

/** Zet statusregels om naar de i18n-tekst in $lang en haalt ze uit de te vertalen body. */
function ticketTranslationDisplayText(string $sourceText, string $translatedText, string $lang): string
{
    $notes = ticketStatusNotesInText($sourceText);
    if ($notes === []) {
        return $translatedText;
    }

    return ticketAppendStatusNotes(ticketTextWithoutStatusNotes($translatedText), $notes, $lang);
}

/** @return list<string> */
function ticketStatusNotePrefixes(): array
{
    return [
        'status gewijzigd naar ',
        'status changed to ',
        'status geändert auf ',
        'statut modifié en ',
    ];
}

/** @return array<string, string> */
function ticketStatusLabelMap(): array
{
    return [
        'ingediend' => 'ingediend',
        'submitted' => 'ingediend',
        'eingereicht' => 'ingediend',
        'soumis' => 'ingediend',
        'in behandeling' => 'in behandeling',
        'in progress' => 'in behandeling',
        'in bearbeitung' => 'in behandeling',
        'en cours' => 'in behandeling',
        'afwachtende op gebruiker' => 'afwachtende op gebruiker',
        'awaiting user' => 'afwachtende op gebruiker',
        'wartet auf benutzer' => 'afwachtende op gebruiker',
        "en attente de l'utilisateur" => 'afwachtende op gebruiker',
        'afwachtende op bestelling' => 'afwachtende op bestelling',
        'awaiting order' => 'afwachtende op bestelling',
        'wartet auf bestellung' => 'afwachtende op bestelling',
        'en attente de commande' => 'afwachtende op bestelling',
        'afwachtende op derde partij' => 'afwachtende op derde partij',
        'awaiting third party' => 'afwachtende op derde partij',
        'wartet auf dritte partei' => 'afwachtende op derde partij',
        'en attente de tiers' => 'afwachtende op derde partij',
        'afgehandeld' => 'afgehandeld',
        'resolved' => 'afgehandeld',
        'erledigt' => 'afgehandeld',
        'résolu' => 'afgehandeld',
        'resolu' => 'afgehandeld',
    ];
}
