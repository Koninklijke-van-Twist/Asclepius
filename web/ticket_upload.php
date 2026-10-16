<?php

/**
 * Serveert één ticketbijlage.
 * Browser: login/lib.php, en $allowedUsers wanneer die lijst in auth.php staat.
 * API: dezelfde sleutel als api.php (header X-API-Key of api_key). De map
 * data/ticket_uploads/ blijft afgesloten.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'constants.php';

function ticket_upload_is_direct_request(): bool
{
    $self = basename(__FILE__);
    $scriptFilename = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $phpSelf = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

    return $scriptFilename === $self || $scriptName === $self || $phpSelf === $self;
}

/**
 * @param mixed $allowedUsers
 */
function ticket_upload_email_is_allowed(string $email, $allowedUsers): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !is_array($allowedUsers)) {
        return false;
    }

    foreach ($allowedUsers as $emailKey => $value) {
        $allowedEmail = is_int($emailKey) ? (string) $value : (string) $emailKey;
        if (strtolower(trim($allowedEmail)) === $email) {
            return true;
        }
    }

    return false;
}

function ticket_upload_stored_name_is_safe(string $storedName): bool
{
    $storedName = basename(str_replace('\\', '/', trim($storedName)));
    if ($storedName === '' || $storedName === '.' || $storedName === '..') {
        return false;
    }

    return preg_match('/^ticket_[A-Za-z0-9._-]+$/', $storedName) === 1;
}

function ticket_upload_resolve_file(array $attachment, ?string $uploadDirectory = null): ?string
{
    $ticketId = (int) ($attachment['ticket_id'] ?? 0);
    $storedName = basename(str_replace('\\', '/', trim((string) ($attachment['stored_name'] ?? ''))));
    if ($ticketId <= 0 || !ticket_upload_stored_name_is_safe($storedName)) {
        return null;
    }

    $root = $uploadDirectory ?? (defined('UPLOAD_DIRECTORY') ? UPLOAD_DIRECTORY : '');
    if ($root === '') {
        return null;
    }
    $rootReal = realpath($root);
    if ($rootReal === false || !is_dir($rootReal)) {
        return null;
    }

    $candidate = $rootReal . DIRECTORY_SEPARATOR . $ticketId . DIRECTORY_SEPARATOR . $storedName;
    $real = realpath($candidate);
    if ($real === false || !is_file($real)) {
        return null;
    }

    $prefix = $rootReal . DIRECTORY_SEPARATOR;
    if (!str_starts_with($real, $prefix)) {
        return null;
    }

    $storedPath = trim((string) ($attachment['stored_path'] ?? ''));
    if ($storedPath !== '') {
        $storedReal = realpath($storedPath);
        if ($storedReal === false || $storedReal !== $real) {
            return null;
        }
    }

    return $real;
}

function ticket_upload_release_session_lock(): void
{
    if (function_exists('session_status') && function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * @param array<string, mixed>|null $apiClient
 */
function ticket_upload_api_may_read(bool $serviceOrTrusted, ?array $apiClient, bool $isGhost): bool
{
    if ($isGhost) {
        return $serviceOrTrusted || !empty($apiClient['is_admin']);
    }

    return $serviceOrTrusted || is_array($apiClient);
}

function ticket_upload_browser_may_read(TicketStore $store, array $attachment, string $email, array $ictUsers): bool
{
    $ticketId = (int) ($attachment['ticket_id'] ?? 0);
    $attachmentId = (int) ($attachment['id'] ?? 0);
    $email = strtolower(trim($email));
    if ($ticketId <= 0 || $email === '') {
        return false;
    }

    $context = resolveIctAccessContext($store, $ictUsers, $email, true);
    $isIct = !empty($context['is_full_ict_admin']) || !empty($context['is_limited_ict']);
    $categories = resolveTicketAccessCategoriesForActor(
        !empty($context['is_limited_ict']),
        $context['access_categories'] ?? null
    );

    $ictTicket = null;
    if ($isIct) {
        $ictTicket = $store->getTicket($ticketId, true, $email, 'default', false, $categories);
    }
    $ticket = $ictTicket;
    if ($ticket === null) {
        $ticket = $store->getTicket($ticketId, false, $email, 'default');
    }
    if ($ticket === null) {
        $ticket = $store->getTicket($ticketId, false, $email, 'all_completed_public');
    }
    if ($ticket === null) {
        return false;
    }

    if ($attachmentId > 0 && $store->isGhostAttachment($attachmentId) && $ictTicket === null) {
        return false;
    }

    return true;
}

function ticket_upload_deny(int $status, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }
    echo $message;
    exit;
}

function ticket_upload_send_file(array $attachment, ?string $uploadDirectory = null): void
{
    $path = ticket_upload_resolve_file($attachment, $uploadDirectory);
    if ($path === null) {
        ticket_upload_deny(404, 'Niet gevonden');
    }

    $mime = trim((string) ($attachment['mime_type'] ?? ''));
    if ($mime === '' || preg_match('#^[A-Za-z0-9!#$&^_.+-]+/[A-Za-z0-9!#$&^_.+-]+$#', $mime) !== 1) {
        $mime = 'application/octet-stream';
    }
    $size = filesize($path);
    if ($size === false) {
        ticket_upload_deny(404, 'Niet gevonden');
    }

    $name = basename(str_replace('\\', '/', (string) ($attachment['original_name'] ?? 'bestand')));
    $name = str_replace(["\r", "\n", '"'], '', $name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'bestand';
    }

    ticket_upload_release_session_lock();
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . (string) $size);
    header('Content-Disposition: inline; filename="' . $name . '"');
    header('Cache-Control: private, max-age=300');
    readfile($path);
    exit;
}

if (!ticket_upload_is_direct_request()) {
    return;
}

define('ASCLEPIUS_API_SKIP_ROUTER', true);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'api.php';

$attachmentId = max(0, (int) ($_GET['id'] ?? 0));
$ticketId = max(0, (int) ($_GET['ticket'] ?? 0));
$storedName = basename(str_replace('\\', '/', (string) ($_GET['file'] ?? '')));

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, is_array($ictUsers ?? null) ? $ictUsers : [], TICKET_CATEGORIES);
} catch (Throwable $exception) {
    ticket_upload_deny(500, 'Database kon niet worden geopend.');
}

$attachment = null;
if ($attachmentId > 0) {
    $attachment = $store->getAttachment($attachmentId);
} elseif ($ticketId > 0 && ticket_upload_stored_name_is_safe($storedName)) {
    $attachment = $store->getAttachmentByStoredName($ticketId, $storedName);
}
if (!is_array($attachment)) {
    ticket_upload_deny(404, 'Niet gevonden');
}

$attachmentId = (int) ($attachment['id'] ?? 0);
$isGhost = $attachmentId > 0 && $store->isGhostAttachment($attachmentId);
$providedKey = getApiKeyFromRequest();
if ($providedKey !== '') {
    $apiClient = loadApiClientByToken($providedKey);
    $hasServiceKey = isValidApiKey($providedKey, is_array($apiKeys ?? null) ? $apiKeys : []);
    if ($apiClient === null && !$hasServiceKey) {
        ticket_upload_deny(401, 'Ongeldige API-key.');
    }
    if (!ticket_upload_api_may_read($hasServiceKey, $apiClient, $isGhost)) {
        ticket_upload_deny(404, 'Niet gevonden');
    }
    ticket_upload_send_file($attachment);
}

$loginLib = __DIR__ . '/../login/lib.php';
if (!is_file($loginLib)) {
    ticket_upload_deny(403, 'Geen toegang');
}
require_once $loginLib;

global $allowedUsers;
$email = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
if (isset($allowedUsers) && is_array($allowedUsers)) {
    if (!ticket_upload_email_is_allowed($email, $allowedUsers)) {
        $forbidden = __DIR__ . '/../login/403.php';
        if (is_file($forbidden)) {
            require $forbidden;
            die();
        }
        ticket_upload_deny(403, 'Geen toegang');
    }
} elseif ($email === '') {
    ticket_upload_deny(403, 'Geen toegang');
}

ticket_upload_release_session_lock();

if (!ticket_upload_browser_may_read($store, $attachment, $email, is_array($ictUsers ?? null) ? $ictUsers : [])) {
    ticket_upload_deny(404, 'Niet gevonden');
}

ticket_upload_send_file($attachment);
