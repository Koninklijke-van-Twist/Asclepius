<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'logincheck.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TicketStore.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
    $sessionCookieName = session_name();
    if (!isset($_COOKIE[$sessionCookieName])) {
        setcookie($sessionCookieName, session_id(), [
            'expires' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$sessionCookieName] = session_id();
    }
}

if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $value) {
            if ($callback($value)) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Constants
 */
const DATABASE_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'asclepius.sqlite';
const UPLOAD_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ticket_uploads';
const MAX_ATTACHMENT_BYTES = 20971520;
const LONG_OPEN_NOTIFICATION_FALLBACK_DAYS = 7;
const TICKET_CATEGORIES = [
    'hardware bestellen',
    'software bestellen',
    'Business Central',
    'Hardwareproblemen',
    'Softwareproblemen',
    'sleutels.kvt.nl web-applicatieproblemen',
    'Anders',
];
const TICKET_STATUSES = [
    'ingediend',
    'in behandeling',
    'afwachtende op gebruiker',
    'afwachtende op bestelling',
    'afgehandeld',
];
const STATUS_COLORS = [
    'ingediend' => '#2563eb',
    'in behandeling' => '#d97706',
    'afwachtende op gebruiker' => '#7c3aed',
    'afwachtende op bestelling' => '#b45309',
    'afgehandeld' => '#15803d',
];
const PRIORITY_LABELS = [
    0 => 'Normaal',
    1 => 'Belemmerd',
    2 => 'Geblokkeerd',
];
const PRIORITY_COLORS = [
    0 => '#0f766e',
    1 => '#d97706',
    2 => '#b91c1c',
];
const CATEGORY_COLORS = [
    'hardware bestellen' => '#0f766e',
    'software bestellen' => '#1d4ed8',
    'Business Central' => '#7c3aed',
    'Hardwareproblemen' => '#dc2626',
    'Softwareproblemen' => '#ea580c',
    'sleutels.kvt.nl web-applicatieproblemen' => '#0891b2',
    'Anders' => '#475569',
];

/**
 * Variabelen
 */
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
$isAdminPortal = ($asclepiusPageMode ?? '') === 'admin' || $currentPage === 'admin.php';
$localRequester = in_array($_SERVER['REMOTE_ADDR'] ?? '', [$_SERVER['SERVER_ADDR'] ?? '', '127.0.0.1', '::1'], true);

if ($localRequester && isset($_GET['dev_user']) && filter_var((string) $_GET['dev_user'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['user']['email'] = strtolower(trim((string) $_GET['dev_user']));
}

if ($localRequester && isset($_GET['dev_admin'])) {
    $_SESSION['user']['admin'] = $_GET['dev_admin'] === '1';
}

if (!isset($_SESSION['user']['email']) || trim((string) $_SESSION['user']['email']) === '') {
    $_SESSION['user']['email'] = $ictUsers[0] ?? 'developer@kvt.nl';
}

if (!isset($_SESSION['user']['admin'])) {
    $_SESSION['user']['admin'] = in_array(strtolower((string) $_SESSION['user']['email']), array_map('strtolower', $ictUsers), true);
}

$userEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? 'developer@kvt.nl')));
$userIsAdmin = (bool) ($_SESSION['user']['admin'] ?? false);
$canManageTickets = $isAdminPortal && $userIsAdmin;
$_SESSION['user']['email'] = $userEmail;
$_SESSION['user']['admin'] = $userIsAdmin;

$flashMessages = $_SESSION['flash_messages'] ?? [];
unset($_SESSION['flash_messages']);

$storeError = null;
$store = null;

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers, TICKET_CATEGORIES);
} catch (Throwable $exception) {
    $storeError = $exception->getMessage();
}

$storageDiagnostics = [
    'database_path' => DATABASE_FILE,
    'database_exists' => is_file(DATABASE_FILE),
    'database_writable' => is_file(DATABASE_FILE) ? is_writable(DATABASE_FILE) : is_writable(dirname(DATABASE_FILE)),
    'database_directory' => dirname(DATABASE_FILE),
    'database_directory_writable' => is_dir(dirname(DATABASE_FILE)) && is_writable(dirname(DATABASE_FILE)),
];

$longOpenNotificationDays = max(
    1,
    (int) (
        $_ENV['ASCLEPIUS_LONG_OPEN_DAYS']
        ?? $_SERVER['ASCLEPIUS_LONG_OPEN_DAYS']
        ?? LONG_OPEN_NOTIFICATION_FALLBACK_DAYS
    )
);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_token'];

$statusFilterRequestActive = $isAdminPortal && (isset($_GET['status_filter_mode']) || isset($_GET['status']));
$statusFilters = $statusFilterRequestActive
    ? array_values(array_filter(
        array_map('trim', (array) ($_GET['status'] ?? [])),
        static fn(string $status): bool => in_array($status, TICKET_STATUSES, true)
    ))
    : [];
$effectiveStatusFilters = $statusFilterRequestActive && $statusFilters === [] ? ['__no_matching_status__'] : $statusFilters;
$categoryFilterRequestActive = $isAdminPortal && (isset($_GET['category_filter_mode']) || isset($_GET['category']));
$categoryFilters = $categoryFilterRequestActive
    ? array_values(array_filter(
        array_map('trim', (array) ($_GET['category'] ?? [])),
        static fn(string $category): bool => in_array($category, TICKET_CATEGORIES, true)
    ))
    : [];
$effectiveCategoryFilters = $categoryFilterRequestActive && $categoryFilters === [] ? ['__no_matching_category__'] : $categoryFilters;
$assignedFilter = $canManageTickets ? trim((string) ($_GET['assigned'] ?? '')) : '';
$requestedView = trim((string) ($_GET['view'] ?? ''));
$view = $canManageTickets && in_array($requestedView, ['settings', 'stats'], true) ? $requestedView : 'overview';
$openTicketId = max(0, (int) ($_GET['open'] ?? 0));
$baseQuery = buildNavigationQuery(
    $statusFilters,
    $categoryFilters,
    $assignedFilter,
    $view,
    $isAdminPortal,
    $statusFilterRequestActive,
    $categoryFilterRequestActive
);

/**
 * Functies
 */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pushFlash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function normalizeReturnPage(?string $page): string
{
    return basename((string) $page) === 'admin.php' ? 'admin.php' : 'index.php';
}

function buildNavigationQuery(array $statusFilters, array $categoryFilters, string $assignedFilter, string $view, bool $isAdminPortal, bool $statusFilterRequestActive = false, bool $categoryFilterRequestActive = false, int $openTicketId = 0): array
{
    $query = [];

    if ($statusFilterRequestActive) {
        $query['status_filter_mode'] = 'manual';
    }

    if ($statusFilters !== []) {
        $query['status'] = $statusFilters;
    }

    if ($categoryFilterRequestActive) {
        $query['category_filter_mode'] = 'manual';
    }

    if ($categoryFilters !== []) {
        $query['category'] = $categoryFilters;
    }

    if ($assignedFilter !== '') {
        $query['assigned'] = $assignedFilter;
    }

    if ($isAdminPortal && $view !== 'overview') {
        $query['view'] = $view;
    }

    if ($openTicketId > 0) {
        $query['open'] = $openTicketId;
    }

    return $query;
}

function isStatusFilterSelected(string $status, array $statusFilters, bool $statusFilterRequestActive): bool
{
    return !$statusFilterRequestActive || in_array($status, $statusFilters, true);
}

function isCategoryFilterSelected(string $category, array $categoryFilters, bool $categoryFilterRequestActive): bool
{
    return !$categoryFilterRequestActive || in_array($category, $categoryFilters, true);
}

function getPriorityFromFlags(bool $isWorkBlocked, bool $isFullyBlocked): int
{
    if ($isWorkBlocked && $isFullyBlocked) {
        return 2;
    }

    if ($isWorkBlocked) {
        return 1;
    }

    return 0;
}

function formatPriorityLabel($priority): string
{
    $priority = max(0, min(2, (int) $priority));
    return PRIORITY_LABELS[$priority] ?? PRIORITY_LABELS[0];
}

function getPriorityColor($priority): string
{
    $priority = max(0, min(2, (int) $priority));
    return PRIORITY_COLORS[$priority] ?? PRIORITY_COLORS[0];
}

function getCategoryColor(string $category): string
{
    return CATEGORY_COLORS[$category] ?? '#334155';
}

function ticketIsOpenLongerThanDays(array $ticket, int $days): bool
{
    if ((string) ($ticket['status'] ?? '') === 'afgehandeld') {
        return false;
    }

    $createdAt = trim((string) ($ticket['created_at'] ?? ''));
    if ($createdAt === '') {
        return false;
    }

    try {
        $created = new DateTimeImmutable($createdAt);
        $threshold = (new DateTimeImmutable('now'))->modify('-' . max(1, $days) . ' days');
        return $created <= $threshold;
    } catch (Throwable) {
        return false;
    }
}

function redirectToPage(string $page = 'index.php', array $parameters = []): void
{
    $filtered = array_filter(
        $parameters,
        static fn($value): bool => $value !== null && $value !== '' && $value !== []
    );

    $queryString = http_build_query($filtered);
    $targetPage = normalizeReturnPage($page);
    header('Location: ' . $targetPage . ($queryString !== '' ? '?' . $queryString : ''));
    exit;
}

function normalizeUploadedFiles(string $fieldName): array
{
    if (!isset($_FILES[$fieldName])) {
        return [];
    }

    $input = $_FILES[$fieldName];

    if (!is_array($input['name'])) {
        if (($input['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || (string) ($input['name'] ?? '') === '') {
            return [];
        }

        return [$input];
    }

    $files = [];
    $fileCount = count($input['name']);

    for ($index = 0; $index < $fileCount; $index++) {
        $error = (int) ($input['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        $name = (string) ($input['name'][$index] ?? '');

        if ($error === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }

        $files[] = [
            'name' => $name,
            'type' => (string) ($input['type'][$index] ?? ''),
            'tmp_name' => (string) ($input['tmp_name'][$index] ?? ''),
            'error' => $error,
            'size' => (int) ($input['size'][$index] ?? 0),
        ];
    }

    return $files;
}

function validateUploadedFiles(array $files): array
{
    $errors = [];

    foreach ($files as $file) {
        $name = (string) ($file['name'] ?? 'bestand');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        $size = (int) ($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'Bijlage "' . $name . '" kon niet worden geüpload.';
            continue;
        }

        if ($size > MAX_ATTACHMENT_BYTES) {
            $errors[] = 'Bijlage "' . $name . '" is groter dan 20 MB.';
        }
    }

    return $errors;
}

function formatDateTime(string $value): string
{
    if ($value === '') {
        return 'Onbekend';
    }

    try {
        return (new DateTimeImmutable($value))->format('d-m-Y H:i');
    } catch (Throwable) {
        return $value;
    }
}

function formatDurationSeconds($seconds): string
{
    if ($seconds === null || !is_numeric($seconds)) {
        return '—';
    }

    $seconds = max(0, (int) round((float) $seconds));

    $units = [
        ['seconds' => 31536000, 'singular' => 'jaar', 'plural' => 'jaar'],
        ['seconds' => 2592000, 'singular' => 'maand', 'plural' => 'maanden'],
        ['seconds' => 604800, 'singular' => 'week', 'plural' => 'weken'],
        ['seconds' => 86400, 'singular' => 'dag', 'plural' => 'dagen'],
        ['seconds' => 3600, 'singular' => 'uur', 'plural' => 'uur'],
        ['seconds' => 60, 'singular' => 'minuut', 'plural' => 'minuten'],
    ];

    foreach ($units as $unit) {
        if ($seconds >= $unit['seconds']) {
            $value = (int) round($seconds / $unit['seconds']);
            $label = $value === 1 ? $unit['singular'] : $unit['plural'];
            return $value . ' ' . $label;
        }
    }

    return 'minder dan 1 minuut';
}

function getTicketOpenDurationSeconds(array $ticket): ?int
{
    $createdAt = trim((string) ($ticket['created_at'] ?? ''));
    if ($createdAt === '') {
        return null;
    }

    try {
        $start = new DateTimeImmutable($createdAt);
        $status = (string) ($ticket['status'] ?? '');
        $resolvedAt = trim((string) ($ticket['resolved_at'] ?? ''));
        $updatedAt = trim((string) ($ticket['updated_at'] ?? ''));

        if ($status === 'afgehandeld' && $resolvedAt !== '') {
            $end = new DateTimeImmutable($resolvedAt);
        } elseif ($status === 'afgehandeld' && $updatedAt !== '') {
            $end = new DateTimeImmutable($updatedAt);
        } else {
            $end = new DateTimeImmutable('now');
        }

        return max(0, $end->getTimestamp() - $start->getTimestamp());
    } catch (Throwable) {
        return null;
    }
}

function getStatusColor(string $status): string
{
    return STATUS_COLORS[$status] ?? '#475569';
}

function emailToHexColor(string $email): string
{
    return '#' . substr(md5(strtolower(trim($email))), 0, 6);
}

function buildStatusChangeNote(string $status, string $changedByEmail): string
{
    return 'Status gewijzigd naar ' . $status . '.';
}

function makeTextInteractive(string $text): string
{
    $escapedText = h($text);

    $escapedText = preg_replace_callback(
        '~(?:(https?://|www\.)[^\s<]+)~i',
        static function (array $matches): string {
            $displayValue = $matches[0];
            $href = str_starts_with(strtolower($displayValue), 'www.') ? 'https://' . $displayValue : $displayValue;
            $safeHref = h($href);
            $safeLabel = h($displayValue);

            return '<a href="' . $safeHref . '" target="_blank" rel="noopener noreferrer">' . $safeLabel . '</a>';
        },
        $escapedText
    ) ?? $escapedText;

    $escapedText = preg_replace(
        '/(?<![\w.@])([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})(?![^<]*>)/i',
        '<a href="mailto:$1">$1</a>',
        $escapedText
    ) ?? $escapedText;

    $escapedText = preg_replace_callback(
        '/(?<![\w>])((?:\+?[0-9][0-9\s()\/.-]{6,}[0-9]))(?![^<]*>)/',
        static function (array $matches): string {
            $phoneText = trim($matches[1]);
            $phoneHref = preg_replace('/[^0-9+]/', '', $phoneText) ?? '';
            if ($phoneHref === '') {
                return $phoneText;
            }

            return '<a href="tel:' . h($phoneHref) . '">' . h($phoneText) . '</a>';
        },
        $escapedText
    ) ?? $escapedText;

    return $escapedText;
}

function formatTicketMessageText(?string $messageText): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $messageText));
    if ($normalized === '') {
        return '';
    }

    $formattedLines = [];
    foreach (explode("\n", $normalized) as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            $formattedLines[] = '';
            continue;
        }

        $interactiveLine = makeTextInteractive($line);
        if (str_starts_with($trimmedLine, 'Status gewijzigd naar ')) {
            $formattedLines[] = '<small>' . $interactiveLine . '</small>';
            continue;
        }

        $formattedLines[] = $interactiveLine;
    }

    return implode('<br>', $formattedLines);
}

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

function sendViaSmtp(array $smtp, string $fromEmail, string $fromName, array $recipients, string $subject, string $message): bool
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

        $headers = [
            'From: ' . formatMailAddress($fromName, $fromEmail),
            'To: ' . implode(', ', $recipients),
            'Subject: ' . encodeMailHeader($subject),
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . uniqid('ticket-', true) . '@kvt.nl>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", str_replace(["\r\n", "\r"], "\n", $message)) . "\r\n.\r\n";
        fwrite($socket, $body);
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

    if ($smtp !== [] && sendViaSmtp($smtp, $fromEmail, $fromName, array_values($normalizedRecipients), $fullSubject, $message)) {
        return;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . formatMailAddress($fromName, $fromEmail),
    ];

    @mail(
        implode(', ', array_values($normalizedRecipients)),
        encodeMailHeader($fullSubject),
        $message,
        implode("\r\n", $headers)
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
    $messageWithRouting = $message;

    if (($routing['note'] ?? '') !== '') {
        $messageWithRouting .= PHP_EOL . PHP_EOL . ($routing['note'] ?? '');
    }

    sendTicketEmail($routing['recipients'] ?? $recipients, $subject, $messageWithRouting, $excludeEmail);
}

function buildAbsoluteTicketUrl(int $ticketId, bool $adminPage = false): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/index.php')), '/.');
    $targetPage = $adminPage ? 'admin.php' : 'index.php';

    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '') . '/' . $targetPage . '?open=' . $ticketId;
}

function buildNotificationBody(array $ticket, string $intro, string $messageText = '', bool $adminPage = false): string
{
    $lines = [
        $intro,
        '',
        'Ticket: #' . $ticket['id'] . ' - ' . $ticket['title'],
        'Categorie: ' . $ticket['category'],
        'Aanvrager: ' . $ticket['user_email'],
        'Toegewezen aan: ' . (($ticket['assigned_email'] ?? '') !== '' ? $ticket['assigned_email'] : 'Nog niet toegewezen'),
        'Status: ' . $ticket['status'],
        'Laatst bijgewerkt: ' . formatDateTime((string) ($ticket['updated_at'] ?? $ticket['created_at'] ?? '')),
    ];

    if (trim($messageText) !== '') {
        $lines[] = '';
        $lines[] = 'Bericht:';
        $lines[] = $messageText;
    }

    $lines[] = '';
    $lines[] = 'Open ticket: ' . buildAbsoluteTicketUrl((int) $ticket['id'], $adminPage);

    return implode(PHP_EOL, $lines);
}

/**
 * Page load
 */
$returnPage = normalizeReturnPage((string) ($_POST['return_page'] ?? ($isAdminPortal ? 'admin.php' : 'index.php')));

if ($isAdminPortal && !$userIsAdmin) {
    pushFlash('error', 'Alleen ICT-gebruikers hebben toegang tot het ticketoverzicht.');
    redirectToPage('index.php');
}

if (isset($_GET['download']) && $store instanceof TicketStore) {
    $attachmentId = max(0, (int) $_GET['download']);
    $attachment = $store->getAttachment($attachmentId);

    if ($attachment === null) {
        http_response_code(404);
        exit('Bijlage niet gevonden.');
    }

    $storedPath = (string) ($attachment['stored_path'] ?? '');
    if (!is_file($storedPath)) {
        http_response_code(404);
        exit('Het bestand ontbreekt op de server.');
    }

    $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($attachment['original_name'] ?? 'bijlage')) ?: 'bijlage';
    header('Content-Description: File Transfer');
    header('Content-Type: ' . ((string) ($attachment['mime_type'] ?? '') !== '' ? $attachment['mime_type'] : 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string) filesize($storedPath));
    readfile($storedPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        pushFlash('error', 'Je sessie is verlopen. Ververs de pagina en probeer het opnieuw.');
        redirectToPage($returnPage, $baseQuery);
    }

    if (!$store instanceof TicketStore) {
        pushFlash('error', 'De database kon niet worden geopend: ' . $storeError);
        redirectToPage($returnPage, $baseQuery);
    }

    $formAction = trim((string) ($_POST['form_action'] ?? ($_POST['action'] ?? '')));

    try {
        if ($formAction === 'create_ticket') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $isWorkBlocked = !empty($_POST['priority_blocked']);
            $isFullyBlocked = !empty($_POST['priority_fully_blocked']);
            $priority = getPriorityFromFlags($isWorkBlocked, $isFullyBlocked);
            $requesterEmailInput = strtolower(trim((string) ($_POST['requester_email'] ?? '')));
            $requesterEmail = $userEmail;
            $files = normalizeUploadedFiles('ticket_attachments');
            $errors = validateUploadedFiles($files);

            if ($title === '') {
                $errors[] = 'Vul een titel in voor het ticket.';
            }
            if (!in_array($category, TICKET_CATEGORIES, true)) {
                $errors[] = 'Kies een geldige categorie.';
            }
            if ($description === '') {
                $errors[] = 'Vul een beschrijving in.';
            }
            if ($isFullyBlocked && !$isWorkBlocked) {
                $errors[] = 'Je kunt alleen aangeven dat je niet verder kunt werken als werkzaamheden al belemmerd zijn.';
            }
            if ($userIsAdmin && $requesterEmailInput !== '') {
                if (!filter_var($requesterEmailInput, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Vul een geldig e-mailadres in bij Gebruiker.';
                } else {
                    $requesterEmail = $requesterEmailInput;
                }
            }

            if ($errors !== []) {
                throw new RuntimeException(implode(' ', $errors));
            }

            $result = $store->createTicket($title, $category, $requesterEmail, $description, $files, $priority);
            $ticketId = (int) $result['ticket_id'];
            $ticket = $store->getTicket($ticketId, true, $userEmail);

            if ($ticket !== null) {
                $recipients = !empty($result['assigned_email']) ? [$result['assigned_email']] : $ictUsers;
                sendTicketNotification(
                    $store,
                    $ictUsers,
                    $recipients,
                    'Nieuw ticket #' . $ticketId,
                    buildNotificationBody($ticket, 'Er is een nieuw ICT-ticket ingediend.', $description, true),
                    $requesterEmail,
                    (string) ($ticket['category'] ?? $category)
                );

                $userIntro = strtolower($requesterEmail) === strtolower($userEmail)
                    ? 'Je ticket is ontvangen door ICT.'
                    : 'Er is een ticket namens u aangemaakt.';

                sendTicketNotification(
                    $store,
                    $ictUsers,
                    [$requesterEmail],
                    'Ticket #' . $ticketId . ' is aangemaakt',
                    buildNotificationBody($ticket, $userIntro, $description, false),
                    null,
                    (string) ($ticket['category'] ?? $category)
                );
            }

            pushFlash('success', 'Ticket #' . $ticketId . ' is aangemaakt en automatisch toegewezen.');
            redirectToPage($returnPage, array_merge($baseQuery, ['open' => $ticketId]));
        }

        if ($formAction === 'reply_ticket') {
            $ticketId = max(1, (int) ($_POST['ticket_id'] ?? 0));
            $ticket = $store->getTicket($ticketId, $canManageTickets, $userEmail);
            if ($ticket === null) {
                throw new RuntimeException('Ticket niet gevonden of niet toegankelijk.');
            }

            $message = trim((string) ($_POST['message'] ?? ''));
            $messageForStorage = $message;
            $files = normalizeUploadedFiles('reply_attachments');
            $errors = validateUploadedFiles($files);

            $newStatus = (string) $ticket['status'];
            $newAssignee = (string) ($ticket['assigned_email'] ?? '');
            $newPriority = max(0, min(2, (int) ($ticket['priority'] ?? 0)));
            $statusChanged = false;
            $assigneeChanged = false;
            $priorityChanged = false;
            $reopenRequested = !empty($_POST['reopen_ticket']);

            if ($canManageTickets) {
                $requestedStatus = trim((string) ($_POST['status'] ?? $ticket['status']));
                if (!in_array($requestedStatus, TICKET_STATUSES, true)) {
                    $errors[] = 'Kies een geldige status.';
                } else {
                    $newStatus = $requestedStatus;
                    $statusChanged = $newStatus !== (string) $ticket['status'];
                }

                $requestedAssignee = strtolower(trim((string) ($_POST['assigned_email'] ?? (string) ($ticket['assigned_email'] ?? ''))));
                $currentAssignee = strtolower((string) ($ticket['assigned_email'] ?? ''));
                $availabilityByUser = $store->getIctUserAvailability();
                if ($requestedAssignee !== '' && !in_array($requestedAssignee, array_map('strtolower', $ictUsers), true)) {
                    $errors[] = 'Kies een geldige ICT-medewerker.';
                } elseif ($requestedAssignee !== '' && empty($availabilityByUser[$requestedAssignee]) && $requestedAssignee !== $currentAssignee) {
                    $errors[] = 'Deze ICT-medewerker staat als afwezig gemarkeerd en kan geen nieuwe tickets ontvangen.';
                } else {
                    $newAssignee = $requestedAssignee;
                    $assigneeChanged = $newAssignee !== $currentAssignee;
                }

                $requestedPriority = (int) ($_POST['priority'] ?? $newPriority);
                if ($requestedPriority < 0 || $requestedPriority > 2) {
                    $errors[] = 'Kies een geldige prioriteit.';
                } else {
                    $newPriority = $requestedPriority;
                    $priorityChanged = $newPriority !== (int) ($ticket['priority'] ?? 0);
                }
            }

            if (!$canManageTickets && $message !== '' && (string) $ticket['status'] === 'afwachtende op gebruiker') {
                $newStatus = 'in behandeling';
                $statusChanged = true;
            }

            if (!$canManageTickets && $reopenRequested && (string) $ticket['status'] === 'afgehandeld') {
                $newStatus = 'ingediend';
                $statusChanged = true;
            }

            if ($message === '' && $files === [] && !$statusChanged && !$assigneeChanged && !$priorityChanged) {
                $errors[] = 'Voeg een bericht, bijlage of statuswijziging toe.';
            }

            if ($canManageTickets && $statusChanged) {
                $statusChangeNote = buildStatusChangeNote($newStatus, $userEmail);
                $messageForStorage = $message !== ''
                    ? rtrim($message) . PHP_EOL . PHP_EOL . $statusChangeNote
                    : $statusChangeNote;
            }

            if ($errors !== []) {
                throw new RuntimeException(implode(' ', $errors));
            }

            if ($statusChanged || ($canManageTickets && ($assigneeChanged || $priorityChanged))) {
                $store->updateTicket($ticketId, $newStatus, $newAssignee !== '' ? $newAssignee : null, $newPriority);
            }

            if ($messageForStorage !== '' || $files !== []) {
                $store->addMessage($ticketId, $userEmail, $canManageTickets ? 'admin' : 'user', $messageForStorage, $files);
            }

            $updatedTicket = $store->getTicket($ticketId, true, $userEmail);
            if ($updatedTicket !== null) {
                if ($canManageTickets) {
                    $shouldNotifyRequester = $statusChanged || $assigneeChanged || $messageForStorage !== '' || $files !== [];
                    if ($shouldNotifyRequester) {
                        sendTicketNotification(
                            $store,
                            $ictUsers,
                            [$updatedTicket['user_email']],
                            'Update op ticket #' . $ticketId,
                            buildNotificationBody(
                                $updatedTicket,
                                'ICT heeft je ticket bijgewerkt' . ($statusChanged ? ' en de status aangepast.' : '.'),
                                $messageForStorage,
                                false
                            ),
                            $userEmail,
                            (string) ($updatedTicket['category'] ?? '')
                        );
                    }

                    if ($assigneeChanged && $newAssignee !== '') {
                        sendTicketNotification(
                            $store,
                            $ictUsers,
                            [$newAssignee],
                            'Ticket #' . $ticketId . ' is aan jou toegewezen',
                            buildNotificationBody($updatedTicket, 'Een ICT-ticket is opnieuw aan jou toegewezen.', $message, true),
                            $userEmail,
                            (string) ($updatedTicket['category'] ?? '')
                        );
                    }
                } else {
                    $recipients = !empty($updatedTicket['assigned_email']) ? [$updatedTicket['assigned_email']] : $ictUsers;
                    sendTicketNotification(
                        $store,
                        $ictUsers,
                        $recipients,
                        'Reactie van gebruiker op ticket #' . $ticketId,
                        buildNotificationBody($updatedTicket, 'De aanvrager heeft gereageerd op een ticket.', $message, true),
                        $userEmail,
                        (string) ($updatedTicket['category'] ?? '')
                    );

                    if ($message !== '' && ticketIsOpenLongerThanDays($updatedTicket, $longOpenNotificationDays)) {
                        $escalationRecipients = $recipients;
                        $escalationRecipients[] = 'ict@kvt.nl';
                        sendTicketNotification(
                            $store,
                            $ictUsers,
                            array_values(array_unique($escalationRecipients)),
                            'Escalatie ticket #' . $ticketId,
                            buildNotificationBody($updatedTicket, 'ticket staat lange tijd onbeantwoord open', $message, true),
                            null,
                            (string) ($updatedTicket['category'] ?? '')
                        );
                    }
                }
            }

            pushFlash('success', 'Ticket #' . $ticketId . ' is bijgewerkt.');
            redirectToPage($returnPage, array_merge($baseQuery, ['open' => $ticketId]));
        }

        if ($formAction === 'save_settings') {
            if (!$canManageTickets) {
                throw new RuntimeException('Alleen admins kunnen instellingen aanpassen.');
            }

            $postedSettings = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
            $postedAvailability = is_array($_POST['availability'] ?? null) ? $_POST['availability'] : [];
            $postedEnabledPairs = array_filter((array) ($_POST['settings_enabled'] ?? []), static fn($value): bool => is_string($value) && $value !== '');
            $enabledLookup = [];

            foreach ($postedEnabledPairs as $postedPair) {
                $decodedPair = base64_decode((string) $postedPair, true);
                if ($decodedPair === false) {
                    continue;
                }

                $parts = explode('|', $decodedPair, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                [$postedUserEmail, $postedCategory] = $parts;
                $enabledLookup[strtolower(trim($postedUserEmail))][trim($postedCategory)] = true;
            }

            $matrix = [];
            $availability = [];
            foreach ($ictUsers as $ictUser) {
                $ictUser = strtolower($ictUser);
                $availability[$ictUser] = !empty($postedAvailability[$ictUser]);
                foreach (TICKET_CATEGORIES as $category) {
                    $matrix[$ictUser][$category] = !empty($postedSettings[$ictUser][$category]) || !empty($enabledLookup[$ictUser][$category]);
                }
            }

            $store->saveCategoryMatrix($matrix, $availability);
            pushFlash('success', 'De categorie-instellingen en afwezigheid voor ICT zijn opgeslagen.');
            redirectToPage('admin.php', ['view' => 'settings']);
        }

        throw new RuntimeException('Onbekende actie ontvangen.');
    } catch (Throwable $exception) {
        if ($formAction === 'save_settings') {
            error_log('[Asclepius save_settings] ' . $exception->getMessage() . ' | db=' . DATABASE_FILE . ' | dir_writable=' . (is_writable(dirname(DATABASE_FILE)) ? '1' : '0') . ' | file_writable=' . ((is_file(DATABASE_FILE) && is_writable(DATABASE_FILE)) ? '1' : '0'));
        }

        pushFlash('error', $exception->getMessage());
        redirectToPage($returnPage, array_merge($baseQuery, $formAction === 'reply_ticket' ? ['open' => max(1, (int) ($_POST['ticket_id'] ?? 0))] : []));
    }
}

$tickets = $store instanceof TicketStore
    ? $store->getTickets($canManageTickets, $userEmail, $effectiveStatusFilters, $assignedFilter, $effectiveCategoryFilters)
    : [];
$settingsMatrix = $store instanceof TicketStore ? $store->getCategorySettings() : [];
$loadByIctUser = $store instanceof TicketStore ? $store->getIctUserLoads() : [];
$availabilityByIctUser = $store instanceof TicketStore
    ? $store->getIctUserAvailability()
    : array_fill_keys(array_map('strtolower', $ictUsers), true);
$overallStats = $canManageTickets && $view === 'stats' && $store instanceof TicketStore
    ? $store->getOverallStats()
    : [
        'total_tickets' => 0,
        'open_tickets' => 0,
        'resolved_tickets' => 0,
        'waiting_order_tickets' => 0,
    ];
$ictStats = $canManageTickets && $view === 'stats' && $store instanceof TicketStore
    ? $store->getIctUserStats()
    : [];
$requesterStats = $canManageTickets && $view === 'stats' && $store instanceof TicketStore
    ? $store->getRequesterStats()
    : [];
$statsOpenTickets = $canManageTickets && $view === 'stats' && $store instanceof TicketStore
    ? $store->getTickets(true, '', array_filter(TICKET_STATUSES, fn(string $s) => $s !== 'afgehandeld'))
    : [];

if ($canManageTickets && $view === 'stats' && isset($_GET['_bigscreen_poll'])) {
    $allTicketsForPoll = $store instanceof TicketStore ? $store->getTickets(true, '') : [];
    $pollMaxId = 0;
    $pollLatest = null;
    $pollSnapshot = [];
    $pollOpenTickets = [];
    foreach ($allTicketsForPoll as $t) {
        $tid = (int) $t['id'];
        if ($tid > $pollMaxId) {
            $pollMaxId = $tid;
            $pollLatest = $t;
        }
        $pollSnapshot[] = [
            'id' => $tid,
            'updated_at' => (string) ($t['updated_at'] ?? ''),
            'status' => (string) ($t['status'] ?? ''),
            'assigned_email' => (string) ($t['assigned_email'] ?? ''),
            'message_count' => (int) ($t['message_count'] ?? 0),
        ];
        if ((string) ($t['status'] ?? '') !== 'afgehandeld') {
            $pollOpenTickets[] = [
                'id' => $tid,
                'title' => (string) ($t['title'] ?? ''),
                'status' => (string) ($t['status'] ?? ''),
                'status_color' => getStatusColor((string) ($t['status'] ?? '')),
                'user_email' => (string) ($t['user_email'] ?? ''),
                'assigned_email' => (string) ($t['assigned_email'] ?? ''),
                'priority' => (int) ($t['priority'] ?? 0),
            ];
        }
    }
    $pollOverallStats = $store instanceof TicketStore ? $store->getOverallStats() : [];
    $pollIctStats = $store instanceof TicketStore ? $store->getIctUserStats() : [];
    $pollRequesterStats = $store instanceof TicketStore ? $store->getRequesterStats() : [];
    $pollAvailability = $store instanceof TicketStore ? $store->getIctUserAvailability() : [];

    $pollIctStatsMapped = array_map(function (array $r) use ($pollAvailability): array {
        $email = strtolower((string) ($r['user_email'] ?? ''));
        return [
            'user_email' => $email,
            'user_color' => emailToHexColor($email),
            'available' => !empty($pollAvailability[$email]),
            'handled_count' => (int) ($r['handled_count'] ?? 0),
            'average_open' => formatDurationSeconds($r['average_open_seconds'] ?? null),
            'max_open' => formatDurationSeconds($r['max_open_seconds'] ?? null),
            'open_count' => (int) ($r['open_count'] ?? 0),
            'waiting_order_count' => (int) ($r['waiting_order_count'] ?? 0),
        ];
    }, $pollIctStats);

    $pollRequesterStatsMapped = array_map(function (array $r): array {
        return [
            'user_email' => (string) ($r['user_email'] ?? ''),
            'submitted_count' => (int) ($r['submitted_count'] ?? 0),
            'average_wait' => formatDurationSeconds($r['average_wait_seconds'] ?? null),
            'max_wait' => formatDurationSeconds($r['max_wait_seconds'] ?? null),
        ];
    }, $pollRequesterStats);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'max_id' => $pollMaxId,
        'snapshot' => $pollSnapshot,
        'overall_stats' => $pollOverallStats,
        'ict_stats' => $pollIctStatsMapped,
        'requester_stats' => $pollRequesterStatsMapped,
        'open_tickets' => $pollOpenTickets,
        'latest' => $pollLatest !== null ? [
            'id' => $pollMaxId,
            'title' => (string) ($pollLatest['title'] ?? ''),
            'user_email' => (string) ($pollLatest['user_email'] ?? ''),
            'assigned_email' => (string) ($pollLatest['assigned_email'] ?? ''),
            'assigned_color' => emailToHexColor((string) ($pollLatest['assigned_email'] ?? '')),
            'priority' => (int) ($pollLatest['priority'] ?? 0),
        ] : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$isBigscreen = $canManageTickets && $view === 'stats' && isset($_GET['bigscreen']) && (string) $_GET['bigscreen'] === 'true';

?>
<!DOCTYPE html>
<html lang="nl">

<?php require __DIR__ . '/content/views/head.php'; ?>


<body<?= $isBigscreen ? ' style="overflow:hidden;"' : '' ?>>
    <div class="page">
        <?php require __DIR__ . '/content/views/header.php'; ?>

        <?php if ($flashMessages !== []): ?>
            <div class="flash-stack">
                <?php foreach ($flashMessages as $flashMessage): ?>
                    <div class="flash <?= h((string) ($flashMessage['type'] ?? 'success')) ?>">
                        <?= h((string) ($flashMessage['message'] ?? '')) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($storeError !== null): ?>
            <div class="flash-stack">
                <div class="flash error">Databasefout: <?= h($storeError) ?></div>
            </div>
        <?php endif; ?>

        <main class="layout">
            <?php require __DIR__ . '/content/views/view_new_ticket.php'; ?>

            <?php require __DIR__ . '/content/views/view_settings.php'; ?>

            <?php require __DIR__ . '/content/views/view_stats.php'; ?>

            <?php require __DIR__ . '/content/views/view_tickets.php'; ?>
        </main>
    </div>
    <?php require __DIR__ . '/content/views/page_js.php'; ?>
    <?php require __DIR__ . '/content/views/bigscreen_js.php'; ?>
    </body>

</html>