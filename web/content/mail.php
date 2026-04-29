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
    $ictLookup = array_fill_keys(extractIctUserEmails($ictUsers), true);
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

function normalizeNotificationRecipients(array $recipients, ?string $excludeEmail = null): array
{
    $normalized = [];
    $exclude = $excludeEmail !== null ? strtolower(trim($excludeEmail)) : null;

    foreach ($recipients as $recipient) {
        $email = strtolower(trim((string) $recipient));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if ($exclude !== null && $email === $exclude) {
            continue;
        }
        $normalized[$email] = $email;
    }

    return array_values($normalized);
}

function extractDesktopNotificationBody(string $message): string
{
    ['plain' => $plain] = splitMailBody($message);
    $lines = preg_split('/\R/', $plain) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }
        return function_exists('mb_substr') ? mb_substr($trimmed, 0, 220) : substr($trimmed, 0, 220);
    }

    return '';
}

function base64UrlDecode(string $value): string
{
    $value = strtr(trim($value), '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($value, true);
    return $decoded === false ? '' : $decoded;
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function buildEcPublicKeyPemFromRaw(string $rawPublicKey): ?string
{
    if (strlen($rawPublicKey) !== 65 || $rawPublicKey[0] !== "\x04") {
        return null;
    }

    $derPrefix = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200');
    if ($derPrefix === false) {
        return null;
    }

    $der = $derPrefix . $rawPublicKey;
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function hkdfExtract(string $salt, string $ikm): string
{
    return hash_hmac('sha256', $ikm, $salt, true);
}

function hkdfExpand(string $prk, string $info, int $length): string
{
    $result = '';
    $block = '';
    $counter = 1;

    while (strlen($result) < $length) {
        $block = hash_hmac('sha256', $block . $info . chr($counter), $prk, true);
        $result .= $block;
        $counter++;
    }

    return substr($result, 0, $length);
}

function createVapidJwt(string $endpoint, string $subject, string $privatePem): ?array
{
    $privatePem = str_replace('\\n', "\n", $privatePem);
    $parsedEndpoint = parse_url($endpoint);
    $scheme = $parsedEndpoint['scheme'] ?? '';
    $host = $parsedEndpoint['host'] ?? '';
    if ($scheme === '' || $host === '') {
        return null;
    }

    $audience = $scheme . '://' . $host;
    $now = time();
    $claims = [
        'aud' => $audience,
        'exp' => $now + 12 * 60 * 60,
        'sub' => $subject,
    ];

    $header = ['alg' => 'ES256', 'typ' => 'JWT'];
    $encodedHeader = base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES) ?: '{}');
    $encodedClaims = base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES) ?: '{}');
    $payload = $encodedHeader . '.' . $encodedClaims;

    $privateKey = openssl_pkey_get_private($privatePem);
    if ($privateKey === false) {
        return null;
    }

    $signatureDer = '';
    if (!openssl_sign($payload, $signatureDer, $privateKey, OPENSSL_ALGO_SHA256)) {
        return null;
    }

    $signatureRaw = convertEcdsaDerToRaw($signatureDer, 64);
    if ($signatureRaw === null) {
        return null;
    }

    return [
        'jwt' => $payload . '.' . base64UrlEncode($signatureRaw),
        'audience' => $audience,
    ];
}

function convertEcdsaDerToRaw(string $der, int $partLength): ?string
{
    $offset = 0;
    if (!isset($der[$offset]) || ord($der[$offset]) !== 0x30) {
        return null;
    }
    $offset++;
    if (!isset($der[$offset])) {
        return null;
    }

    $seqLen = ord($der[$offset]);
    $offset++;
    if (($seqLen & 0x80) !== 0) {
        $lenBytes = $seqLen & 0x7f;
        if ($lenBytes <= 0 || !isset($der[$offset + $lenBytes - 1])) {
            return null;
        }
        $offset += $lenBytes;
    }

    if (!isset($der[$offset]) || ord($der[$offset]) !== 0x02) {
        return null;
    }
    $offset++;
    $rLen = ord($der[$offset] ?? "\x00");
    $offset++;
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;

    if (!isset($der[$offset]) || ord($der[$offset]) !== 0x02) {
        return null;
    }
    $offset++;
    $sLen = ord($der[$offset] ?? "\x00");
    $offset++;
    $s = substr($der, $offset, $sLen);

    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, $partLength, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, $partLength, "\x00", STR_PAD_LEFT);

    if (strlen($r) !== $partLength || strlen($s) !== $partLength) {
        return null;
    }

    return $r . $s;
}

function encryptWebPushPayload(string $jsonPayload, string $userPublicKeyRaw, string $authSecretRaw): ?array
{
    $userPublicKeyPem = buildEcPublicKeyPemFromRaw($userPublicKeyRaw);
    if ($userPublicKeyPem === null) {
        return null;
    }

    $serverKeyResource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($serverKeyResource === false) {
        return null;
    }

    $serverKeyDetails = openssl_pkey_get_details($serverKeyResource);
    $serverX = $serverKeyDetails['ec']['x'] ?? null;
    $serverY = $serverKeyDetails['ec']['y'] ?? null;
    if (!is_string($serverX) || !is_string($serverY)) {
        return null;
    }

    $serverPublicKeyRaw = "\x04" . $serverX . $serverY;
    $userPublicResource = openssl_pkey_get_public($userPublicKeyPem);
    if ($userPublicResource === false) {
        return null;
    }

    $sharedSecret = openssl_pkey_derive($userPublicResource, $serverKeyResource, 32);
    if (!is_string($sharedSecret) || strlen($sharedSecret) !== 32) {
        return null;
    }

    $keyInfo = "WebPush: info\0" . $userPublicKeyRaw . $serverPublicKeyRaw;
    $ikm = hkdfExpand(hkdfExtract($authSecretRaw, $sharedSecret), $keyInfo, 32);

    $salt = random_bytes(16);
    $prk = hkdfExtract($salt, $ikm);
    $contentEncryptionKey = hkdfExpand($prk, "Content-Encoding: aes128gcm\0", 16);
    $nonce = hkdfExpand($prk, "Content-Encoding: nonce\0", 12);

    $plaintext = $jsonPayload . "\x02";
    $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false || !is_string($tag)) {
        return null;
    }

    $recordSize = 4096;
    $binaryBody = $salt
        . pack('N', $recordSize)
        . chr(strlen($serverPublicKeyRaw))
        . $serverPublicKeyRaw
        . $ciphertext
        . $tag;

    return [
        'body' => $binaryBody,
        'server_public_key' => $serverPublicKeyRaw,
    ];
}

function sendSingleWebPush(array $subscription, string $title, string $body, string $openUrl): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'hard_invalid' => false];
    }

    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $p256dhKey = base64UrlDecode((string) ($subscription['p256dh_key'] ?? ''));
    $authKey = base64UrlDecode((string) ($subscription['auth_key'] ?? ''));
    if ($endpoint === '' || $p256dhKey === '' || $authKey === '') {
        return ['success' => false, 'hard_invalid' => true];
    }

    if (WEB_PUSH_VAPID_PRIVATE_PEM === '' || WEB_PUSH_VAPID_PUBLIC_KEY === '') {
        return ['success' => false, 'hard_invalid' => false];
    }

    $vapid = createVapidJwt($endpoint, WEB_PUSH_SUBJECT, WEB_PUSH_VAPID_PRIVATE_PEM);
    if ($vapid === null) {
        return ['success' => false, 'hard_invalid' => false];
    }

    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'open_url' => $openUrl,
        'tag' => 'ticket-push-' . (string) ($subscription['user_email'] ?? 'user'),
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['success' => false, 'hard_invalid' => false];
    }

    $encrypted = encryptWebPushPayload($payload, $p256dhKey, $authKey);
    if ($encrypted === null) {
        return ['success' => false, 'hard_invalid' => true];
    }

    $authorization = 'vapid t=' . $vapid['jwt'] . ', k=' . WEB_PUSH_VAPID_PUBLIC_KEY;
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'TTL: 120',
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: ' . $authorization,
        ],
        CURLOPT_POSTFIELDS => $encrypted['body'],
    ]);

    curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'hard_invalid' => in_array($httpCode, [404, 410], true),
    ];
}

function sendWebPushNotifications(
    ?TicketStore $store,
    array $ictUsers,
    array $recipientEmails,
    int $ticketId,
    string $title,
    string $body
): void {
    if (!$store instanceof TicketStore || $ticketId <= 0) {
        return;
    }

    if (WEB_PUSH_VAPID_PRIVATE_PEM === '' || WEB_PUSH_VAPID_PUBLIC_KEY === '') {
        return;
    }

    $subscriptions = $store->getWebPushSubscriptionsByUserEmails($recipientEmails);
    if ($subscriptions === []) {
        return;
    }

    $ictLookup = array_fill_keys(extractIctUserEmails($ictUsers), true);
    $invalidEndpoints = [];

    foreach ($subscriptions as $subscription) {
        $subscriptionUser = strtolower(trim((string) ($subscription['user_email'] ?? '')));
        $openUrl = isset($ictLookup[$subscriptionUser])
            ? 'admin.php?open=' . $ticketId
            : 'index.php?open=' . $ticketId;

        $result = sendSingleWebPush($subscription, $title, $body, $openUrl);
        if (!($result['success'] ?? false) && ($result['hard_invalid'] ?? false)) {
            $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
            if ($endpoint !== '') {
                $invalidEndpoints[$endpoint] = $endpoint;
            }
        }
    }

    if ($invalidEndpoints !== []) {
        $store->removeWebPushSubscriptionsByEndpoints(array_values($invalidEndpoints));
    }
}

function sendTicketNotification(
    ?TicketStore $store,
    array $ictUsers,
    array $recipients,
    string $subject,
    string $message,
    ?string $excludeEmail = null,
    ?string $ticketCategory = null,
    ?int $ticketId = null,
    ?string $browserActorEmail = null
): void {
    $routing = routeNotificationRecipients($store, $ictUsers, $recipients, $ticketCategory);
    $routedRecipients = $routing['recipients'] ?? $recipients;
    $note = ($routing['note'] ?? '');

    $finalMessage = $message;

    if ($note !== '') {
        ['plain' => $plain, 'html' => $html] = splitMailBody($message);
        $plain .= PHP_EOL . PHP_EOL . $note;
        if ($html !== null) {
            $noteHtml = '<p style="margin-top:16px;font-size:12px;color:#5b6b82;border-top:1px solid #d8e0eb;padding-top:12px;">'
                . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</p>';
            $html = str_replace('</body>', $noteHtml . '</body>', $html);
        }

        // Herverpak als HTML-mailpakket
        $finalMessage = $html !== null
            ? "ASCLEPIUS_HTML_MAIL\x00" . $plain . "\x00" . $html
            : $plain;
    }

    sendTicketEmail($routedRecipients, $subject, $finalMessage, $excludeEmail);

    if (!$store instanceof TicketStore || $ticketId === null || $ticketId <= 0) {
        return;
    }

    $browserRecipients = normalizeNotificationRecipients($routedRecipients, $excludeEmail);
    if ($browserActorEmail !== null) {
        $browserRecipients = normalizeNotificationRecipients($browserRecipients, $browserActorEmail);
    }

    if ($browserRecipients === []) {
        return;
    }

    $body = extractDesktopNotificationBody($finalMessage);
    $store->queueBrowserNotifications($browserRecipients, $ticketId, $subject, $body);
    sendWebPushNotifications($store, $ictUsers, $browserRecipients, $ticketId, $subject, $body);
}
