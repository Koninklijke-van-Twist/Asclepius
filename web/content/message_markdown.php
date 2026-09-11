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
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
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

function applyMessageInlineMarkdown(string $escapedText, bool $forEmail = false): string
{
    $escapedText = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)\s]+)\)/',
        static function (array $match) use ($forEmail): string {
            $href = sanitizeMessageMarkdownHref((string) $match[2]);
            if ($href === null) {
                return (string) $match[0];
            }

            $label = (string) $match[1];
            $safeHref = h($href);
            if ($forEmail) {
                return '<a href="' . $safeHref . '">' . $label . '</a>';
            }

            $ticketId = extractAsclepiusTicketIdFromUrl($href);
            $target = $ticketId > 0 ? '' : ' target="_blank" rel="noopener noreferrer"';

            return '<a href="' . $safeHref . '"' . $target . '>' . $label . '</a>';
        },
        $escapedText
    ) ?? $escapedText;

    $escapedText = preg_replace('/\*\*(?!\s)([^*\n]+?)(?<!\s)\*\*/', '<strong>$1</strong>', $escapedText) ?? $escapedText;
    $escapedText = preg_replace('/(?<!\*)\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/', '<em>$1</em>', $escapedText) ?? $escapedText;

    return $escapedText;
}

function mapMessageHtmlTextSegments(string $html, callable $mapper): string
{
    return preg_replace_callback(
        '/(<[^>]+>)|([^<]+)/',
        static function (array $match) use ($mapper): string {
            if (($match[1] ?? '') !== '') {
                return (string) $match[1];
            }

            return (string) $mapper((string) ($match[2] ?? ''));
        },
        $html
    ) ?? $html;
}

function renderMessageMarkdownCodeBlock(string $language, string $code): string
{
    $language = strtolower(trim($language));
    $class = $language !== '' && preg_match('/^[a-z0-9_-]+$/', $language) === 1
        ? ' class="language-' . h($language) . '"'
        : '';

    return '<pre class="message-md-pre"><code' . $class . '>' . h($code) . '</code></pre>';
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

function isMessageMarkdownCheckboxLine(string $line): bool
{
    return preg_match('/^(\s*)\[( |x|X)\](?:\s+(.*))?$/', $line) === 1;
}

function isMessageMarkdownBlockStart(string $line): bool
{
    $trimmed = trim($line);

    return isMessageMarkdownFenceLine($trimmed)
        || isMessageMarkdownAttachmentLine($trimmed)
        || isMessageMarkdownHeadingLine($trimmed)
        || isMessageMarkdownQuoteLine($trimmed)
        || isMessageMarkdownUnorderedLine($trimmed)
        || isMessageMarkdownOrderedLine($trimmed)
        || isMessageMarkdownCheckboxLine($line);
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
        if (str_starts_with($trimmed, 'Status gewijzigd naar ')) {
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
