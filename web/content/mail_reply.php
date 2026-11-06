<?php

/**
 * Antwoord per e-mail op een ticketmail: configuratie, Message-ID's, matching en
 * het schoonmaken van de mailtekst. Geen database- of HTTP-afhankelijkheden,
 * zodat het los te testen is. Het ingest-endpoint staat in api.php.
 */

const MAIL_REPLY_MESSAGE_ID_PATTERN = '/^asclepius-t(\d+)-[0-9a-f]+@/i';

/**
 * Uit auth.php: $mailReplyIngest = ['enabled' => true, 'mailbox' => 'ict@kvt.nl', 'ticket_ids' => [123]].
 * Zonder 'ticket_ids' (of leeg) geldt de vlag voor alle tickets.
 *
 * @return array{enabled: bool, mailbox: string, ticket_ids: list<int>}
 */
function mailReplyIngestConfig(): array
{
    global $mailReplyIngest;

    $config = is_array($mailReplyIngest ?? null) ? $mailReplyIngest : [];
    $ticketIds = array_values(array_filter(
        array_map('intval', is_array($config['ticket_ids'] ?? null) ? $config['ticket_ids'] : []),
        static fn(int $id): bool => $id > 0
    ));

    return [
        'enabled' => ($config['enabled'] ?? false) === true,
        'mailbox' => strtolower(trim((string) ($config['mailbox'] ?? 'ict@kvt.nl'))),
        'ticket_ids' => $ticketIds,
    ];
}

function mailReplyIngestEnabledForTicket(int $ticketId): bool
{
    $config = mailReplyIngestConfig();
    if (!$config['enabled'] || $ticketId <= 0) {
        return false;
    }

    return $config['ticket_ids'] === [] || in_array($ticketId, $config['ticket_ids'], true);
}

function normalizeMailMessageId(string $value): string
{
    return strtolower(trim($value, " \t\r\n<>"));
}

/**
 * @param string|list<string> $value In-Reply-To of References, als tekst of lijst.
 * @return list<string>
 */
function parseMailMessageIdList(string|array $value): array
{
    $text = is_array($value) ? implode(' ', array_map('strval', $value)) : $value;
    preg_match_all('/<([^<>\s]+)>/', $text, $matches);
    $ids = $matches[1] !== [] ? $matches[1] : preg_split('/[\s,]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

    return array_values(array_unique(array_filter(array_map('normalizeMailMessageId', $ids ?: []))));
}

function generateTicketMailMessageId(int $ticketId): string
{
    return 'asclepius-t' . $ticketId . '-' . bin2hex(random_bytes(8)) . '@kvt.nl';
}

/** Ticketnummer uit een Message-ID die Asclepius zelf heeft uitgestuurd. */
function ticketIdFromAsclepiusMessageId(string $messageId): int
{
    return preg_match(MAIL_REPLY_MESSAGE_ID_PATTERN, normalizeMailMessageId($messageId), $m) === 1 ? (int) $m[1] : 0;
}

/** Ticketnummer uit het onderwerp: "ticket #123" (alle talen) of "#123". */
function ticketIdFromMailSubject(string $subject): int
{
    return preg_match('/(?:ticket\s*)?#\s*(\d{1,9})\b/i', $subject, $m) === 1 ? (int) $m[1] : 0;
}

/**
 * Headers hebben voorrang op het onderwerp, omdat een onderwerp door de gebruiker is aan te passen.
 *
 * @param list<string> $referencedIds Genormaliseerde In-Reply-To + References.
 * @param callable(list<string>): int $lookupStoredIds Zoekt Message-ID's die Asclepius uitstuurde op in de database.
 * @return array{ticket_id: int, via: string}
 */
function matchMailToTicketId(array $referencedIds, string $subject, callable $lookupStoredIds): array
{
    $stored = $referencedIds === [] ? 0 : $lookupStoredIds($referencedIds);
    if ($stored > 0) {
        return ['ticket_id' => $stored, 'via' => 'header'];
    }

    foreach (array_reverse($referencedIds) as $id) {
        $embedded = ticketIdFromAsclepiusMessageId($id);
        if ($embedded > 0) {
            return ['ticket_id' => $embedded, 'via' => 'header'];
        }
    }

    $fromSubject = ticketIdFromMailSubject($subject);

    return ['ticket_id' => $fromSubject, 'via' => $fromSubject > 0 ? 'subject' : 'none'];
}

/**
 * @param array<string, mixed>|list<array{name?: string, value?: string}> $headers Map of Graph-lijst (internetMessageHeaders).
 * @return array<string, string> Kleine letters als sleutel.
 */
function normalizeMailHeaders(array $headers): array
{
    $normalized = [];
    foreach ($headers as $key => $value) {
        if (is_array($value) && isset($value['name'])) {
            $key = $value['name'];
            $value = $value['value'] ?? '';
        }
        if (is_string($key) && (is_string($value) || is_numeric($value))) {
            $normalized[strtolower(trim($key))] = trim((string) $value);
        }
    }

    return $normalized;
}

/**
 * Reden om de mail te negeren (auto-antwoord, NDR, lus), of null als het een gewoon antwoord lijkt.
 *
 * @param array<string, string> $headers Uit normalizeMailHeaders().
 * @param list<string> $ownAddresses Adressen van Asclepius zelf en de gedeelde mailbox.
 */
function mailReplyIgnoreReason(string $fromEmail, string $subject, string $messageId, array $headers, array $ownAddresses): ?string
{
    $from = strtolower(trim($fromEmail));
    $localPart = (string) strstr($from, '@', true);

    if (in_array($from, array_map('strtolower', $ownAddresses), true)
        || ticketIdFromAsclepiusMessageId($messageId) > 0
    ) {
        return 'loop';
    }

    if (preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)$/', $localPart) === 1) {
        return 'automatic_sender';
    }

    $autoSubmitted = strtolower($headers['auto-submitted'] ?? 'no');
    $precedence = strtolower($headers['precedence'] ?? '');
    if ($autoSubmitted !== 'no'
        || in_array($precedence, ['bulk', 'junk', 'auto_reply', 'list'], true)
        || isset($headers['x-autoreply']) || isset($headers['x-autorespond'])
        || str_contains(strtolower($headers['content-type'] ?? ''), 'multipart/report')
    ) {
        return 'auto_reply';
    }

    $autoSubject = '/^\s*(automatic reply|automatisch antwoord|autoreply|auto-reply|out of office|afwezig|abwesenheit|automatische antwort|undeliverable|onbestelbaar|niet bezorgd|delivery status notification|mail delivery failed|zustellung fehlgeschlagen|nicht zustellbar|r[ée]ponse automatique)/i';
    if (preg_match($autoSubject, $subject) === 1) {
        return 'auto_reply';
    }

    return null;
}

/**
 * Het kale HTML-deel van een antwoord: alles vanaf het eerste citaat- of handtekeningblok vervalt.
 * Markers: Outlook (desktop, web, mobiel), Gmail, Apple Mail, Thunderbird, Yahoo.
 */
function htmlMailReplyPart(string $html): string
{
    $marker = '/<(?:div|span|table|p|blockquote)\b[^>]*(?:'
        . '\bid\s*=\s*["\']?(?:appendonsend|divRplyFwdMsg|Signature|AppleMailSignature|ms-outlook-mobile-signature)\b'
        . '|\bclass\s*=\s*["\']?[^"\'>]*\b(?:gmail_quote|gmail_attr|gmail_signature|OutlookMessageHeader|moz-cite-prefix|moz-signature|yahoo_quoted)\b'
        . '|border-top\s*:\s*solid\s*#(?:E1E1E1|B5C4DF)'
        . '|data-smartmail\s*=\s*["\']?gmail_signature'
        . ')|<blockquote\b/i';

    if (preg_match($marker, $html, $match, PREG_OFFSET_CAPTURE) === 1) {
        return substr($html, 0, (int) $match[0][1]);
    }

    return $html;
}

function htmlMailToText(string $html): string
{
    $html = preg_replace('~<(head|style|script)\b.*?</\1>|<!--.*?-->~is', '', $html) ?? '';
    $html = preg_replace_callback(
        '~<a\b[^>]*\bhref\s*=\s*["\'](https?://[^"\']+)["\'][^>]*>(.*?)</a>~is',
        static function (array $m): string {
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $label === '' || $label === $url ? $url : '[' . $label . '](' . $url . ')';
        },
        $html
    ) ?? '';
    $html = preg_replace('~<li\b[^>]*>~i', "\n- ", $html) ?? '';
    $html = preg_replace('~</p>~i', "\n\n", $html) ?? '';
    $html = preg_replace('~<br\s*/?>|</(?:div|tr|li|h[1-6]|table|ul|ol)>~i', "\n", $html) ?? '';
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return str_replace("\u{00A0}", ' ', $text);
}

/**
 * Knipt citaten en handtekening weg uit tekst (Nederlands, Engels, Duits, Frans).
 * Nooit perfect: bij twijfel blijft tekst staan, de originele mail staat nog in de mailbox.
 */
function stripQuotedMailText(string $text): string
{
    $lines = preg_split('/\R/', str_replace("\u{00A0}", ' ', $text)) ?: [];
    $count = count($lines);
    $fromWord = '(?:from|van|von|de|da)';
    $headerWord = '(?:sent|verzonden|gesendet|date|datum|envoy[ée]|to|aan|an|[àa]|subject|onderwerp|betreff|objet|cc)';
    $originalMarker = '/^\s*[-_=*\s]*(?:original message|oorspronkelijk bericht|oorspronkelijke bericht|origineel bericht|urspr[üu]ngliche nachricht|message d\'origine|forwarded message|doorgestuurd bericht|weitergeleitete nachricht)[-_=*\s]*$/iu';
    $wroteLine = '/^\s*(?:on|op|am|le|el)\s.{3,300}?\s(?:wrote\s*:|(?:schreef|schrieb|a [ée]crit|escribi[óo])\b.{0,200}?:)\s*$/iu';
    $signatureStart = '/^(?:--|__|—)\s*$/u';
    $mobileFooter = '/^\s*(?:sent from my |sent from outlook|sent from mail for|get outlook for|verzonden vanaf mijn |verzonden vanuit |verstuurd vanaf mijn |verstuurd vanuit |von meinem .* gesendet|gesendet von |envoy[ée] de mon )/iu';
    $closing = '/^\s*(?:met (?:vriendelijke )?groet(?:en)?|vriendelijke groet(?:en)?|hartelijke groet(?:en)?|groet(?:en)?|mvg|best regards|kind regards|warm regards|regards|mit freundlichen gr(?:ü|ue)(?:ß|ss)en|freundliche gr(?:ü|ue)(?:ß|ss)e|mfg|cordialement)\s*[,.!]?\s*$/iu';

    $cut = $count;
    for ($i = 0; $i < $count; $i++) {
        $line = rtrim($lines[$i]);
        $joined = $line . ' ' . trim($lines[$i + 1] ?? '');
        $nextLines = array_slice($lines, $i + 1, 6);
        $isHeaderBlock = preg_match('/^\s*\**' . $fromWord . '\**\s*:\s*\**\s*\S/iu', $line) === 1
            && count(array_filter($nextLines, static fn(string $next): bool => preg_match('/^\s*\**' . $headerWord . '\**\s*:/iu', $next) === 1)) >= 1;

        if (preg_match($originalMarker, $line) === 1
            || preg_match($wroteLine, $line) === 1
            || preg_match($wroteLine, $joined) === 1
            || preg_match($signatureStart, $line) === 1
            || preg_match($mobileFooter, $line) === 1
            || $isHeaderBlock
            || preg_match('/^\s*>/', $line) === 1
        ) {
            $cut = $i;
            break;
        }

        $remaining = array_filter(array_slice($lines, $i + 1), static fn(string $next): bool => trim($next) !== '');
        if (preg_match($closing, $line) === 1 && count($remaining) <= 10 && trim(implode('', array_slice($lines, 0, $i))) !== '') {
            $cut = $i;
            break;
        }
    }

    $kept = array_slice($lines, 0, $cut);
    while ($kept !== [] && preg_match('/^[\s_\-=*]*$/', (string) end($kept)) === 1) {
        array_pop($kept);
    }

    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", array_map('rtrim', $kept))) ?? '');
}

/** Alleen het nieuwe antwoord uit een mail; HTML heeft voorrang omdat het structuurmarkers heeft. */
function extractMailReplyText(string $bodyText, string $bodyHtml = ''): string
{
    $text = trim($bodyHtml) !== '' ? htmlMailToText(htmlMailReplyPart($bodyHtml)) : $bodyText;

    return stripQuotedMailText($text);
}
