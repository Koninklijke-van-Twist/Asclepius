<?php

function encodeMailHeader(string $value): string
{
    if ($value === '') {
        return '';
    }

    // Keep ASCII headers readable; encode only when non-ASCII appears.
    if (!preg_match('/[^\x20-\x7E]/', $value)) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
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

function ticketStatusIsWaitingOnUser(string $status): bool
{
    return strtolower(trim($status)) === TICKET_STATUS_WAITING_ON_USER;
}

function requesterUpdateMailHasHighImportance(bool $statusChanged, string $newStatus): bool
{
    return $statusChanged && ticketStatusIsWaitingOnUser($newStatus);
}

/**
 * Bouwt een multipart/alternative MIME-body (plain + html) of een eenvoudige text/plain body.
 * Geeft [headers_string, body_string] terug.
 */
function buildMimeParts(
    string $fromEmail,
    string $fromName,
    array $recipients,
    string $subject,
    string $plain,
    ?string $html,
    bool $highImportance = false
): array {
    $baseHeaders = [
        'From: ' . formatMailAddress($fromName, $fromEmail),
        'To: ' . implode(', ', $recipients),
        'Subject: ' . encodeMailHeader($subject),
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . uniqid('ticket-', true) . '@kvt.nl>',
        'MIME-Version: 1.0',
    ];

    if ($highImportance) {
        $baseHeaders[] = 'Importance: high';
        $baseHeaders[] = 'X-Priority: 1';
    }

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

function sendViaSmtp(
    array $smtp,
    string $fromEmail,
    string $fromName,
    array $recipients,
    string $subject,
    string $plain,
    ?string $html = null,
    bool $highImportance = false
): bool {
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

        [$headersString, $bodyString] = buildMimeParts($fromEmail, $fromName, $recipients, $subject, $plain, $html, $highImportance);
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

function sendTicketEmail(
    array $recipients,
    string $subject,
    string $message,
    ?string $excludeEmail = null,
    bool $highImportance = false
): void {
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
    $prefix = trim((string) ($mailSettings['subject_prefix'] ?? 'Tickets'));
    $fullSubject = $prefix !== '' ? $prefix . ' - ' . $subject : $subject;
    $smtp = (array) ($mailSettings['smtp'] ?? []);

    ['plain' => $plain, 'html' => $html] = splitMailBody($message);

    if (isset($GLOBALS['asclepiusCapturedTicketEmails']) && is_array($GLOBALS['asclepiusCapturedTicketEmails'])) {
        $GLOBALS['asclepiusCapturedTicketEmails'][] = [
            'recipients' => array_values($normalizedRecipients),
            'subject' => $fullSubject,
            'plain' => $plain,
            'html' => $html,
            'high_importance' => $highImportance,
        ];

        return;
    }

    if ($smtp !== [] && sendViaSmtp($smtp, $fromEmail, $fromName, array_values($normalizedRecipients), $fullSubject, $plain, $html, $highImportance)) {
        return;
    }

    // Fallback: mail() — multipart als HTML beschikbaar
    [$headersString, $bodyString] = buildMimeParts($fromEmail, $fromName, array_values($normalizedRecipients), $fullSubject, $plain, $html, $highImportance);

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
    $forwards = [];
    $ictLookup = array_fill_keys(extractIctUserEmails($ictUsers), true);
    $availabilityByUser = $store instanceof TicketStore ? $store->getEffectiveIctUserAvailability() : [];

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
            $forwards[] = ['absent' => $email, 'to' => $forwardedTo];
            $notes[] = __mail('email.absence_forwarded', 'nl', $email, $forwardedTo);
        }
    }

    return [
        'recipients' => array_values($resolvedRecipients),
        'note' => implode(PHP_EOL, array_unique($notes)),
        'forwards' => $forwards,
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
        return substr($trimmed, 0, 220);
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

function getDefaultAdminEmailPreferences(): array
{
    $defaults = [];
    foreach (ADMIN_EMAIL_NOTIFICATION_TYPES as $type) {
        $defaults[$type] = true;
    }

    return $defaults;
}

function loadAdminEmailPreferences(string $email): array
{
    $saved = loadUserPrefs($email)['admin_email_preferences'] ?? [];
    if (!is_array($saved)) {
        return getDefaultAdminEmailPreferences();
    }

    $merged = getDefaultAdminEmailPreferences();
    foreach ($merged as $type => $defaultEnabled) {
        if (array_key_exists($type, $saved)) {
            $merged[$type] = !empty($saved[$type]);
        }
    }

    return $merged;
}

function saveAdminEmailPreference(string $email, string $type, bool $enabled): void
{
    if (!in_array($type, ADMIN_EMAIL_NOTIFICATION_TYPES, true)) {
        return;
    }

    $preferences = loadAdminEmailPreferences($email);
    $preferences[$type] = $enabled;
    saveUserPref($email, 'admin_email_preferences', $preferences);
}

function filterAdminEmailRecipients(array $ictUsers, array $recipients, ?string $notificationType): array
{
    if ($notificationType === null || !in_array($notificationType, ADMIN_EMAIL_NOTIFICATION_TYPES, true)) {
        return $recipients;
    }

    $ictLookup = array_fill_keys(extractIctUserEmails($ictUsers), true);
    $filtered = [];

    foreach ($recipients as $recipient) {
        $email = strtolower(trim((string) $recipient));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        if (!isset($ictLookup[$email]) || !empty(loadAdminEmailPreferences($email)[$notificationType])) {
            $filtered[$email] = $email;
        }
    }

    return array_values($filtered);
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
    ?string $adminNotificationType = null,
    ?string $browserActorEmail = null,
    bool $highImportance = false
): void {
    $routing = routeNotificationRecipients($store, $ictUsers, $recipients, $ticketCategory);
    $routedRecipients = $routing['recipients'] ?? $recipients;
    $emailRecipients = filterAdminEmailRecipients($ictUsers, $routedRecipients, $adminNotificationType);
    $finalMessage = appendMailNote($message, (string) ($routing['note'] ?? ''));

    sendTicketEmail($emailRecipients, $subject, $finalMessage, $excludeEmail, $highImportance);

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

/** Hoe lang een updatemail wacht op de vertaling van het bericht, daarna het origineel. */
function userUpdateMailWaitSeconds(): int
{
    return 180;
}

function appendMailNote(string $message, string $note): string
{
    $note = trim($note);
    if ($note === '') {
        return $message;
    }

    ['plain' => $plain, 'html' => $html] = splitMailBody($message);
    $plain .= PHP_EOL . PHP_EOL . $note;
    if ($html === null) {
        return $plain;
    }

    $noteHtml = '<p style="margin-top:16px;font-size:12px;color:#5b6b82;border-top:1px solid #d8e0eb;padding-top:12px;">'
        . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</p>';
    $html = str_replace('</body>', $noteHtml . '</body>', $html);

    return "ASCLEPIUS_HTML_MAIL\x00" . $plain . "\x00" . $html;
}

/**
 * @param list<array{absent?: string, to?: string}> $forwards
 */
function absenceForwardNote(array $forwards, string $lang): string
{
    if (!array_key_exists($lang, SUPPORTED_LANGUAGES)) {
        $lang = 'nl';
    }

    $lines = [];
    foreach ($forwards as $forward) {
        if (!is_array($forward)) {
            continue;
        }
        $absent = trim((string) ($forward['absent'] ?? ''));
        $to = trim((string) ($forward['to'] ?? ''));
        if ($absent === '' || $to === '') {
            continue;
        }
        $lines[] = __mail('email.absence_forwarded', $lang, $absent, $to);
    }

    return implode(PHP_EOL, array_unique($lines));
}

/**
 * Statusregels horen niet bij de vertaalde body. Die komen altijd uit de vaste
 * teksten in de taal van de ontvanger, ook als het bericht verder origineel blijft.
 */
function mailTextForRecipient(string $sourceText, string $bodyText, string $lang): string
{
    if (!function_exists('ticketTranslationDisplayText') || !function_exists('ticketStatusNotesInText') || ticketStatusNotesInText($sourceText) === []) {
        return $bodyText !== '' ? $bodyText : $sourceText;
    }

    return ticketTranslationDisplayText($sourceText, $bodyText, $lang);
}

/**
 * Tekst voor één taal: meteen, uit de cache, na time-out, of nog even wachten.
 *
 * @return array{text: string, fallback: bool}|null
 */
function localizedMailDelivery(TicketStore $store, int $messageId, string $lang, string $messageText, int $queuedAt, int $now): ?array
{
    if (!array_key_exists($lang, SUPPORTED_LANGUAGES)) {
        $lang = 'nl';
    }

    $decision = null;
    if (function_exists('ticketMessageIsStatusNoteOnly') && ticketMessageIsStatusNoteOnly($messageText)) {
        $decision = ['text' => '', 'fallback' => false];
    } else {
        $providerReady = function_exists('createTranslationProvider') && createTranslationProvider() !== null;
        $needsTranslation = $providerReady && trim($messageText) !== '' && $messageId > 0;
        $source = $needsTranslation
            ? userUpdateMailSourceLanguage($store, $messageId, $messageText)
            : '';
        if (!$needsTranslation || ($source !== '' && $source === $lang)) {
            $decision = ['text' => $messageText, 'fallback' => false];
        } else {
            $cached = $store->getTextTranslation('ticket_message', $messageId, $lang, hash('sha256', $messageText));
            if (is_array($cached)) {
                $cachedText = (string) ($cached['translated_text'] ?? '');
                $decision = ['text' => $cachedText !== '' ? $cachedText : $messageText, 'fallback' => false];
            } elseif ($queuedAt > 0 && ($now - $queuedAt) >= userUpdateMailWaitSeconds()) {
                $decision = ['text' => $messageText, 'fallback' => true];
            }
        }
    }

    if ($decision === null) {
        return null;
    }

    $decision['text'] = mailTextForRecipient($messageText, (string) $decision['text'], $lang);

    return $decision;
}

/**
 * Id van het bericht dat in de updatemail staat.
 * Bij een ghost is dat de zichtbare statusnotitie, anders het geplaatste bericht.
 */
function updateMailMessageId(string $messageText, int $messageId, int $statusMessageId = 0): int
{
    if (trim($messageText) === '') {
        return 0;
    }
    if ($statusMessageId > 0) {
        return $statusMessageId;
    }

    return $messageId > 0 ? $messageId : 0;
}

/**
 * Mail per taal van de ontvanger. Onderwerp, vaste teksten en het bericht
 * horen bij die taal. Ontvangers met dezelfde taal delen één mail.
 * Ontbreekt de vertaling, dan wacht die taal tot ze binnen is.
 *
 * @param list<string> $recipients
 * @param list<mixed> $ictUsers
 * @param list<array{absent?: string, to?: string}> $forwards
 */
function sendLocalizedTicketNotifications(
    ?TicketStore $store,
    array $ictUsers,
    array $ticket,
    array $recipients,
    string $messageText,
    int $messageId,
    string $subjectKey = 'email.subject_update',
    string $introKey = 'email.intro_update',
    bool $adminPage = false,
    ?string $excludeEmail = null,
    ?string $browserActorEmail = null,
    bool $highImportance = false,
    ?string $adminNotificationType = null,
    bool $statusChanged = false,
    string $introSuffixKey = ''
): void {
    if (!$store instanceof TicketStore) {
        return;
    }

    dispatchQueuedUserUpdateMails($store, null, null, $ictUsers);

    $ticketId = (int) ($ticket['id'] ?? 0);
    if ($ticketId <= 0) {
        return;
    }

    $category = (string) ($ticket['category'] ?? '');
    $recipients = normalizeNotificationRecipients($recipients, $excludeEmail);
    $routing = routeNotificationRecipients($store, $ictUsers, $recipients, $category);
    $routed = is_array($routing['recipients'] ?? null) ? $routing['recipients'] : [];
    $forwards = is_array($routing['forwards'] ?? null) ? $routing['forwards'] : [];
    $emailRecipients = filterAdminEmailRecipients($ictUsers, $routed, $adminNotificationType);
    $emailRecipients = normalizeNotificationRecipients($emailRecipients);

    $suffixKey = $introSuffixKey;
    if ($suffixKey === '' && $introKey === 'email.intro_update') {
        $suffixKey = $statusChanged ? 'email.intro_update_status' : 'email.intro_update_no_status';
    }

    $byLang = [];
    foreach ($emailRecipients as $email) {
        $lang = getUserMailLang($email);
        $byLang[$lang][] = $email;
    }

    $now = time();
    $sourceHash = hash('sha256', $messageText);
    $exclude = $excludeEmail !== null ? strtolower(trim($excludeEmail)) : '';
    $actor = $browserActorEmail !== null ? strtolower(trim($browserActorEmail)) : '';
    foreach ($byLang as $lang => $emails) {
        $decision = localizedMailDelivery($store, $messageId, $lang, $messageText, 0, $now);
        if ($decision === null) {
            if ($messageId <= 0) {
                continue;
            }
            foreach ($emails as $email) {
                $store->enqueueUserUpdateMail(
                    $ticketId,
                    $messageId,
                    $email,
                    $lang,
                    $sourceHash,
                    $messageText,
                    $statusChanged,
                    $exclude,
                    $actor,
                    $highImportance,
                    null,
                    $subjectKey,
                    $introKey,
                    $suffixKey,
                    $adminPage,
                    $adminNotificationType ?? ''
                );
            }
            continue;
        }

        sendRenderedLocalizedMails(
            $store,
            $ictUsers,
            $ticket,
            $emails,
            $lang,
            (string) ($decision['text'] ?? $messageText),
            $adminPage,
            $excludeEmail,
            $browserActorEmail,
            $highImportance,
            $subjectKey,
            $introKey,
            $suffixKey,
            $forwards
        );
    }
}

/**
 * @param list<string> $recipients
 * @param list<mixed> $ictUsers
 * @param list<array{absent?: string, to?: string}> $forwards
 */
function sendRenderedLocalizedMails(
    TicketStore $store,
    array $ictUsers,
    array $ticket,
    array $recipients,
    string $lang,
    string $messageText,
    bool $adminPage,
    ?string $excludeEmail,
    ?string $browserActorEmail,
    bool $highImportance,
    string $subjectKey,
    string $introKey,
    string $introSuffixKey,
    array $forwards = []
): void {
    $ticketId = (int) ($ticket['id'] ?? 0);
    $recipients = normalizeNotificationRecipients($recipients, $excludeEmail);
    if ($ticketId <= 0 || $recipients === []) {
        return;
    }
    if (!array_key_exists($lang, SUPPORTED_LANGUAGES)) {
        $lang = 'nl';
    }

    $subjectKey = isset(TRANSLATIONS['nl'][$subjectKey]) ? $subjectKey : 'email.subject_update';
    $introKey = isset(TRANSLATIONS['nl'][$introKey]) ? $introKey : 'email.intro_update';
    if ($introSuffixKey === '' && $introKey === 'email.intro_update') {
        $introSuffixKey = 'email.intro_update_no_status';
    }
    $introArgs = $introSuffixKey !== '' && isset(TRANSLATIONS['nl'][$introSuffixKey])
        ? [__mail($introSuffixKey, $lang)]
        : [];
    $body = appendMailNote(
        buildNotificationBody($ticket, $introKey, $messageText, $adminPage, $lang, ...$introArgs),
        absenceForwardNote($forwards, $lang)
    );
    $subject = __mail($subjectKey, $lang, $ticketId);
    sendTicketEmail($recipients, $subject, $body, $excludeEmail, $highImportance);

    $browserRecipients = $recipients;
    if ($browserActorEmail !== null) {
        $browserRecipients = normalizeNotificationRecipients($browserRecipients, $browserActorEmail);
    }
    if ($browserRecipients === []) {
        return;
    }

    $excerpt = extractDesktopNotificationBody($body);
    $store->queueBrowserNotifications($browserRecipients, $ticketId, $subject, $excerpt);
    sendWebPushNotifications($store, $ictUsers, $browserRecipients, $ticketId, $subject, $excerpt);
}

/**
 * Updatemail per ontvanger, in de taal uit user_prefs.
 *
 * @param list<string> $recipients
 * @param list<mixed> $ictUsers
 */
function sendUserUpdateNotifications(
    ?TicketStore $store,
    array $ictUsers,
    array $ticket,
    array $recipients,
    string $messageText,
    int $messageId,
    bool $statusChanged,
    ?string $excludeEmail,
    ?string $browserActorEmail,
    bool $highImportance,
    string $subjectKey = 'email.subject_update',
    string $introKey = 'email.intro_update'
): void {
    sendLocalizedTicketNotifications(
        $store,
        $ictUsers,
        $ticket,
        $recipients,
        $messageText,
        $messageId,
        $subjectKey,
        $introKey,
        false,
        $excludeEmail,
        $browserActorEmail,
        $highImportance,
        null,
        $statusChanged
    );
}

/**
 * Mail naar ICT of de behandelaar, in de taal van elke ontvanger.
 *
 * @param list<string> $recipients
 * @param list<mixed> $ictUsers
 */
function notifyIctAboutTicket(
    ?TicketStore $store,
    array $ictUsers,
    array $ticket,
    array $recipients,
    string $messageText,
    int $messageId,
    string $subjectKey,
    string $introKey,
    ?string $excludeEmail,
    ?string $actorEmail,
    string $notificationType
): void {
    sendLocalizedTicketNotifications(
        $store,
        $ictUsers,
        $ticket,
        $recipients,
        $messageText,
        $messageId,
        $subjectKey,
        $introKey,
        true,
        $excludeEmail,
        $actorEmail,
        false,
        $notificationType,
        false
    );
}

function userUpdateMailSourceLanguage(TicketStore $store, int $messageId, string $messageText): string
{
    if (function_exists('ticketTranslationSourceLanguage')) {
        return ticketTranslationSourceLanguage($store, 'ticket_message', $messageId, $messageText);
    }

    return $store->getTextTranslationSourceLanguage('ticket_message', $messageId, hash('sha256', $messageText));
}

function logUserUpdateMailFallback(int $ticketId, string $recipientEmail, string $lang): void
{
    $line = '[asclepius] updatemail ticket '
        . $ticketId
        . ' naar '
        . $recipientEmail
        . ': vertaling '
        . $lang
        . ' niet binnen '
        . userUpdateMailWaitSeconds()
        . 's, origineel verstuurd';
    if (isset($GLOBALS['asclepiusUpdateMailLog']) && is_array($GLOBALS['asclepiusUpdateMailLog'])) {
        $GLOBALS['asclepiusUpdateMailLog'][] = $line;
    }
    error_log($line);
}

/**
 * Verstuur wachtende updatemails waarvan de vertaling er is, of waarvan de wachttijd voorbij is.
 *
 * @param list<mixed>|null $ictUsers
 */
function dispatchQueuedUserUpdateMails(TicketStore $store, ?int $messageId = null, ?int $now = null, ?array $ictUsers = null): void
{
    $now ??= time();
    if ($ictUsers === null) {
        $ictUsers = is_array($GLOBALS['ictUsers'] ?? null) ? $GLOBALS['ictUsers'] : [];
    }

    $groups = [];
    foreach ($store->listUserUpdateMailQueue($messageId) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $decision = resolveQueuedUserUpdateMail($store, $row, $now);
        if ($decision === null) {
            continue;
        }
        if (!empty($decision['drop'])) {
            $store->claimUserUpdateMail((int) ($row['id'] ?? 0));
            continue;
        }

        $notificationType = trim((string) ($row['notification_type'] ?? ''));
        $recipient = strtolower(trim((string) ($row['recipient_email'] ?? '')));
        if ($notificationType !== '' && $recipient !== '') {
            $allowed = filterAdminEmailRecipients($ictUsers, [$recipient], $notificationType);
            if (!in_array($recipient, $allowed, true)) {
                $store->claimUserUpdateMail((int) ($row['id'] ?? 0));
                continue;
            }
        }

        $lang = strtolower(trim((string) ($row['recipient_lang'] ?? 'nl')));
        $groupKey = implode("\0", [
            (string) ((int) ($row['ticket_id'] ?? 0)),
            $lang,
            (string) ($row['subject_key'] ?? ''),
            (string) ($row['intro_key'] ?? ''),
            (string) ($row['intro_suffix_key'] ?? ''),
            (string) ($decision['text'] ?? ''),
            !empty($decision['fallback']) ? '1' : '0',
            !empty($row['admin_page']) ? '1' : '0',
            !empty($row['high_importance']) ? '1' : '0',
            !empty($row['status_changed']) ? '1' : '0',
            strtolower(trim((string) ($row['exclude_email'] ?? ''))),
            strtolower(trim((string) ($row['browser_actor_email'] ?? ''))),
        ]);
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'rows' => [],
                'text' => (string) ($decision['text'] ?? ''),
                'fallback' => !empty($decision['fallback']),
            ];
        }
        $groups[$groupKey]['rows'][] = $row;
    }

    foreach ($groups as $group) {
        $claimedRows = [];
        foreach ($group['rows'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $claimed = $store->claimUserUpdateMail((int) ($row['id'] ?? 0));
            if ($claimed !== null) {
                $claimedRows[] = $claimed;
            }
        }
        if ($claimedRows === []) {
            continue;
        }

        $first = $claimedRows[0];
        $ticketId = (int) ($first['ticket_id'] ?? 0);
        $lang = strtolower(trim((string) ($first['recipient_lang'] ?? 'nl')));
        $recipients = [];
        foreach ($claimedRows as $claimed) {
            $recipient = strtolower(trim((string) ($claimed['recipient_email'] ?? '')));
            if ($recipient === '') {
                continue;
            }
            $recipients[] = $recipient;
            if (!empty($group['fallback'])) {
                logUserUpdateMailFallback($ticketId, $recipient, $lang);
            }
        }

        $ticket = $store->getTicket($ticketId, true, '');
        if ($ticket === null) {
            foreach ($recipients as $recipient) {
                error_log('[asclepius] updatemail ticket ' . $ticketId . ' naar ' . $recipient . ': ticket ontbreekt, mail vervallen');
            }
            continue;
        }

        $exclude = trim((string) ($first['exclude_email'] ?? ''));
        $actor = trim((string) ($first['browser_actor_email'] ?? ''));
        $category = (string) ($ticket['category'] ?? '');
        $routing = routeNotificationRecipients($store, $ictUsers, $recipients, $category);
        $forwards = is_array($routing['forwards'] ?? null) ? $routing['forwards'] : [];
        sendRenderedLocalizedMails(
            $store,
            $ictUsers,
            $ticket,
            $recipients,
            $lang,
            (string) ($group['text'] ?? ''),
            !empty($first['admin_page']),
            $exclude !== '' ? $exclude : null,
            $actor !== '' ? $actor : null,
            !empty($first['high_importance']),
            (string) ($first['subject_key'] ?? 'email.subject_update'),
            (string) ($first['intro_key'] ?? 'email.intro_update'),
            (string) ($first['intro_suffix_key'] ?? ''),
            $forwards
        );
    }
}

/**
 * @param array<string, mixed> $row
 * @return array{text?: string, fallback?: bool, drop?: bool}|null
 */
function resolveQueuedUserUpdateMail(TicketStore $store, array $row, int $now): ?array
{
    $messageId = (int) ($row['message_id'] ?? 0);
    $lang = strtolower(trim((string) ($row['recipient_lang'] ?? '')));
    $sourceHash = trim((string) ($row['source_hash'] ?? ''));
    $original = (string) ($row['message_text'] ?? '');
    if ($messageId <= 0 || $lang === '' || $sourceHash === '') {
        return [
            'text' => mailTextForRecipient($original, $original, $lang !== '' ? $lang : 'nl'),
            'fallback' => true,
        ];
    }

    $message = $store->getTicketMessage($messageId);
    $currentText = is_array($message) ? (string) ($message['message_text'] ?? '') : '';
    if (!is_array($message) || hash('sha256', $currentText) !== $sourceHash) {
        return ['drop' => true];
    }

    return localizedMailDelivery($store, $messageId, $lang, $currentText, (int) ($row['queued_at'] ?? 0), $now);
}

/**
 * Zelfde updatemail voor de UI en de API, inclusief bots.
 * Een ghost-concept hoort hier niet in: geen zichtbare tekst en geen openbare bijlagen.
 *
 * @param list<mixed> $ictUsers
 */
function notifyTicketUpdateToParticipants(
    ?TicketStore $store,
    array $ictUsers,
    array $ticket,
    string $visibleMessage,
    int $messageId,
    bool $statusChanged,
    bool $assigneeChanged,
    bool $hasPublicAttachments,
    string $newStatus,
    ?string $actorEmail
): void {
    if (!$statusChanged && !$assigneeChanged && trim($visibleMessage) === '' && !$hasPublicAttachments) {
        return;
    }

    $recipients = is_array($ticket['participant_emails'] ?? null)
        ? $ticket['participant_emails']
        : [(string) ($ticket['user_email'] ?? '')];
    sendUserUpdateNotifications(
        $store,
        $ictUsers,
        $ticket,
        $recipients,
        $visibleMessage,
        $messageId,
        $statusChanged,
        $actorEmail,
        $actorEmail,
        requesterUpdateMailHasHighImportance($statusChanged, $newStatus)
    );
}

/**
 * Bevestiging naar deelnemers bij een nieuw ticket, UI en API.
 * De beschrijving is het eerste bericht; die mail wacht op dezelfde vertaling.
 *
 * @param list<mixed> $ictUsers
 */
function notifyRequesterOfCreatedTicket(
    ?TicketStore $store,
    array $ictUsers,
    array $ticket,
    string $description,
    int $messageId,
    string $introKey,
    ?string $actorEmail
): void {
    $recipients = is_array($ticket['participant_emails'] ?? null)
        ? $ticket['participant_emails']
        : [(string) ($ticket['user_email'] ?? '')];
    sendUserUpdateNotifications(
        $store,
        $ictUsers,
        $ticket,
        $recipients,
        $description,
        $messageId,
        false,
        null,
        $actorEmail,
        false,
        'email.subject_created',
        $introKey
    );
}

/**
 * Melding naar de nieuwe behandelaar. Geen gebruikersmail.
 *
 * @param list<mixed> $ictUsers
 */
function notifyAssigneeOfTicket(
    ?TicketStore $store,
    array $ictUsers,
    array $ticket,
    string $newAssignee,
    string $visibleNote,
    ?string $actorEmail,
    int $messageId = 0
): void {
    $newAssignee = strtolower(trim($newAssignee));
    if ($newAssignee === '' || !$store instanceof TicketStore) {
        return;
    }

    notifyIctAboutTicket(
        $store,
        $ictUsers,
        $ticket,
        [$newAssignee],
        $visibleNote,
        $messageId,
        'email.subject_assigned',
        'email.intro_assigned',
        $actorEmail,
        $actorEmail,
        'assigned'
    );
}

function firstTicketMessageId(array $ticket): int
{
    foreach (($ticket['messages'] ?? []) as $message) {
        if (!is_array($message)) {
            continue;
        }
        $messageId = (int) ($message['id'] ?? 0);
        if ($messageId > 0) {
            return $messageId;
        }
    }

    return 0;
}
