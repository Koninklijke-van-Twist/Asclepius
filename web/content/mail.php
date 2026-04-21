<?php

function encodeMailHeader(string $value): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    return $value;
}

function formatMailAddress(string $name, string $email): string
{
    if ($name === '') {
        return $email;
    }

    return encodeMailHeader($name) . ' <' . $email . '>';
}

function smtpExpect($socket, array $expectedCodes): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    $statusCode = (int) substr($response, 0, 3);
    if (!in_array($statusCode, $expectedCodes, true)) {
        throw new RuntimeException('SMTP-fout: ' . trim($response));
    }

    return $response;
}

function smtpCommand($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCodes);
}

/**
 * Splitst het pakket dat buildNotificationBody teruggeeft in plain-text en HTML.
 * Als het geen HTML-mailpakket is, wordt $plain gevuld met de ruwe tekst en $html met null.
 */
function splitMailBody(string $message): array
{
    if (str_starts_with($message, "ASCLEPIUS_HTML_MAIL\x00")) {
        $parts = explode("\x00", $message, 3);
        return ['plain' => $parts[1] ?? '', 'html' => $parts[2] ?? null];
    }
    return ['plain' => $message, 'html' => null];
}

/**
 * Bouwt een multipart/alternative MIME-body (plain + html) of een eenvoudige text/plain body.
 * Geeft [headers_string, body_string] terug.
 */
function buildMimeParts(string $fromEmail, string $fromName, array $recipients, string $subject, string $plain, ?string $html): array
{
    $baseHeaders = [
        'From: ' . formatMailAddress($fromName, $fromEmail),
        'To: ' . implode(', ', $recipients),
        'Subject: ' . encodeMailHeader($subject),
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . uniqid('ticket-', true) . '@kvt.nl>',
        'MIME-Version: 1.0',
    ];

    if ($html === null) {
        $baseHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
        $baseHeaders[] = 'Content-Transfer-Encoding: 8bit';
        return [implode("\r\n", $baseHeaders), $plain];
    }

    $boundary = 'asc_' . bin2hex(random_bytes(12));
    $baseHeaders[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . str_replace(["\r\n", "\r"], "\n", $plain) . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . str_replace(["\r\n", "\r"], "\n", $html) . "\r\n"
        . '--' . $boundary . '--';

    return [implode("\r\n", $baseHeaders), $body];
}

function sendViaSmtp(array $smtp, string $fromEmail, string $fromName, array $recipients, string $subject, string $plain, ?string $html = null): bool
{
    $host = trim((string) ($smtp['host'] ?? ''));
    $port = (int) ($smtp['port'] ?? 25);

    if ($host === '' || $port <= 0) {
        return false;
    }

    $timeout = max(5, (int) ($smtp['timeout'] ?? 20));
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $socket = @stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errorNumber,
        $errorString,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if ($socket === false) {
        return false;
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtpExpect($socket, [220]);
        smtpCommand($socket, 'EHLO asclepius.kvt.nl', [250]);

        if (($smtp['encryption'] ?? '') === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS kon niet worden gestart.');
            }
            smtpCommand($socket, 'EHLO asclepius.kvt.nl', [250]);
        }

        $username = trim((string) ($smtp['username'] ?? ''));
        $password = (string) ($smtp['password'] ?? '');
        if ($username !== '') {
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode($username), [334]);
            smtpCommand($socket, base64_encode($password), [235]);
        }

        smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        foreach ($recipients as $recipient) {
            smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        }

        smtpCommand($socket, 'DATA', [354]);

        [$headersString, $bodyString] = buildMimeParts($fromEmail, $fromName, $recipients, $subject, $plain, $html);
        $raw = $headersString . "\r\n\r\n" . str_replace("\n.", "\n..", $bodyString) . "\r\n.\r\n";
        fwrite($socket, $raw);
        smtpExpect($socket, [250]);
        smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    } catch (Throwable $exception) {
        fclose($socket);
        error_log($exception->getMessage());
        return false;
    }
}

function sendTicketEmail(array $recipients, string $subject, string $message, ?string $excludeEmail = null): void
{
    global $mailSettings;

    $normalizedRecipients = [];
    foreach ($recipients as $recipient) {
        $email = strtolower(trim((string) $recipient));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if ($excludeEmail !== null && $email === strtolower(trim($excludeEmail))) {
            continue;
        }
        $normalizedRecipients[$email] = $email;
    }

    if ($normalizedRecipients === []) {
        return;
    }

    $fromEmail = (string) ($mailSettings['from_email'] ?? 'kvtbot@kvt.nl');
    $fromName = (string) ($mailSettings['from_name'] ?? 'KVT Bot');
    $prefix = trim((string) ($mailSettings['subject_prefix'] ?? 'ICT Tickets'));
    $fullSubject = $prefix !== '' ? $prefix . ' - ' . $subject : $subject;
    $smtp = (array) ($mailSettings['smtp'] ?? []);

    ['plain' => $plain, 'html' => $html] = splitMailBody($message);

    if ($smtp !== [] && sendViaSmtp($smtp, $fromEmail, $fromName, array_values($normalizedRecipients), $fullSubject, $plain, $html)) {
        return;
    }

    // Fallback: mail() — multipart als HTML beschikbaar
    [$headersString, $bodyString] = buildMimeParts($fromEmail, $fromName, array_values($normalizedRecipients), $fullSubject, $plain, $html);

    @mail(
        implode(', ', array_values($normalizedRecipients)),
        encodeMailHeader($fullSubject),
        $bodyString,
        $headersString
    );
}

function routeNotificationRecipients(?TicketStore $store, array $ictUsers, array $recipients, ?string $ticketCategory = null): array
{
    $resolvedRecipients = [];
    $notes = [];
    $ictLookup = array_fill_keys(array_map('strtolower', $ictUsers), true);
    $availabilityByUser = $store instanceof TicketStore ? $store->getIctUserAvailability() : [];

    foreach ($recipients as $recipient) {
        $email = strtolower(trim((string) $recipient));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $resolvedRecipients[$email] = $email;

        if (!isset($ictLookup[$email]) || ($availabilityByUser[$email] ?? true)) {
            continue;
        }

        $forwardedTo = $store->pickAvailableIctUser($ticketCategory, $email);
        if ($forwardedTo !== null && $forwardedTo !== $email) {
            $resolvedRecipients[$forwardedTo] = $forwardedTo;
            $notes[] = 'Let op: ' . $email . ' staat momenteel als afwezig gemarkeerd. Deze melding is daarom ook doorgestuurd naar ' . $forwardedTo . '.';
        }
    }

    return [
        'recipients' => array_values($resolvedRecipients),
        'note' => implode(PHP_EOL, array_unique($notes)),
    ];
}

function sendTicketNotification(?TicketStore $store, array $ictUsers, array $recipients, string $subject, string $message, ?string $excludeEmail = null, ?string $ticketCategory = null): void
{
    $routing = routeNotificationRecipients($store, $ictUsers, $recipients, $ticketCategory);
    $note = ($routing['note'] ?? '');

    if ($note === '') {
        sendTicketEmail($routing['recipients'] ?? $recipients, $subject, $message, $excludeEmail);
        return;
    }

    ['plain' => $plain, 'html' => $html] = splitMailBody($message);
    $plain .= PHP_EOL . PHP_EOL . $note;
    if ($html !== null) {
        $noteHtml = '<p style="margin-top:16px;font-size:12px;color:#5b6b82;border-top:1px solid #d8e0eb;padding-top:12px;">'
            . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</p>';
        $html = str_replace('</body>', $noteHtml . '</body>', $html);
    }

    // Herverpak als HTML-mailpakket
    $packed = $html !== null
        ? "ASCLEPIUS_HTML_MAIL\x00" . $plain . "\x00" . $html
        : $plain;

    sendTicketEmail($routing['recipients'] ?? $recipients, $subject, $packed, $excludeEmail);
}
