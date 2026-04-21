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


<body<?= $isBigscreen ? ' style="overflow:hidden;"' : '' ?></body>>
    <div class="page">
        <header class="hero">
            <div class="brand">
                <img class="brand-logo" src="kvtlogo.png" alt="KVT logo">
                <div>
                    <p class="eyebrow">Asclepius</p>
                    <h1>ICT ticketsysteem</h1>
                    <p><?= $userIsAdmin ? 'Beheer alle tickets, behandel reacties en verdeel werk slim over ICT.' : 'Maak eenvoudig een ICT-ticket aan en volg je meldingen.' ?>
                    </p>
                    <?php if ($localRequester): ?>
                        <p class="dev-note">Ontwikkelen/testen: gebruik eventueel
                            <code>?dev_user=naam@kvt.nl&amp;dev_admin=0</code> of <code>1</code> om rollen lokaal te
                            wisselen.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-actions" <?= $isBigscreen ? ' hidden' : '' ?>>
                <span class="user-chip"><?= h($userEmail) ?><?= $userIsAdmin ? ' · admin' : '' ?></span>
                <a class="nav-link <?= !$isAdminPortal ? 'active' : '' ?>" href="index.php">Nieuw ticket</a>
                <?php if ($userIsAdmin): ?>
                    <a class="nav-link <?= $isAdminPortal && $view === 'overview' ? 'active' : '' ?>"
                        href="admin.php">ICT-overzicht</a>
                    <a class="nav-link <?= $isAdminPortal && $view === 'settings' ? 'active' : '' ?>"
                        href="admin.php?view=settings">Instellingen</a>
                    <a class="nav-link <?= $isAdminPortal && $view === 'stats' ? 'active' : '' ?>"
                        href="admin.php?view=stats">ICT-stats</a>
                <?php endif; ?>
            </div>
        </header>

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
            <?php if (!$isAdminPortal): ?>
                <section class="panel">
                    <h2>Nieuw ticket maken</h2>
                    <p class="panel-intro">Een ticket krijgt automatisch een ICT-medewerker toegewezen op basis van
                        categorie en actuele openstaande werkdruk.</p>
                    <form method="post" action="<?= h($currentPage) ?>" enctype="multipart/form-data" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="create_ticket">
                        <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">

                        <div class="form-grid two-columns">
                            <label>
                                Titel
                                <input type="text" name="title" maxlength="150"
                                    placeholder="Bijvoorbeeld: Nieuwe scanner nodig" required>
                            </label>
                            <label>
                                Categorie
                                <select name="category" required>
                                    <option value="">Kies een categorie</option>
                                    <?php foreach (TICKET_CATEGORIES as $category): ?>
                                        <option value="<?= h($category) ?>"><?= h($category) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <label>
                            Beschrijving
                            <textarea name="description"
                                placeholder="Beschrijf het probleem of de aanvraag zo duidelijk mogelijk."
                                required></textarea>
                        </label>

                        <div class="checkbox-stack">
                            <label class="checkbox-line">
                                <input type="checkbox" name="priority_blocked" id="priority_blocked" value="1">
                                <span>Mijn werkzaamheden worden belemmerd</span>
                            </label>
                            <label class="checkbox-line" id="priority_fully_blocked_wrap" hidden>
                                <input type="checkbox" name="priority_fully_blocked" id="priority_fully_blocked" value="1">
                                <span>Ik kan niet verder werken tot dit opgelost is</span>
                            </label>
                        </div>

                        <?php if ($userIsAdmin): ?>
                            <label>
                                Gebruiker
                                <input type="email" name="requester_email" maxlength="200"
                                    placeholder="naam@kvt.nl (optioneel, voor ticket namens iemand anders)">
                                <span class="hint">Alleen voor ICT: dit e-mailadres wordt als aanvrager gebruikt als je het
                                    invult.</span>
                            </label>
                        <?php endif; ?>

                        <label>
                            Screenshots of documenten
                            <input type="file" name="ticket_attachments[]" multiple>
                            <span class="hint">Per bestand maximaal 20 MB.</span>
                        </label>

                        <div class="button-row">
                            <button type="submit">Ticket indienen</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($canManageTickets && $view === 'settings'): ?>
                <section class="panel">
                    <h2>Instellingen per ICT-gebruiker</h2>
                    <p class="panel-intro">Zet per ICT-collega categorieën aan of uit en markeer medewerkers als afwezig.
                        Nieuwe tickets worden automatisch toegewezen aan de minst belaste beschikbare collega.</p>
                    <?php if ($localRequester): ?>
                        <p class="hint">
                            DB: <code><?= h($storageDiagnostics['database_path']) ?></code><br>
                            Bestand: <?= $storageDiagnostics['database_exists'] ? 'bestaat' : 'ontbreekt' ?> ·
                            map schrijfbaar: <?= $storageDiagnostics['database_directory_writable'] ? 'ja' : 'nee' ?> ·
                            bestand schrijfbaar: <?= $storageDiagnostics['database_writable'] ? 'ja' : 'nee' ?>
                        </p>
                    <?php endif; ?>
                    <form method="post" action="admin.php?view=settings" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ICT-gebruiker</th>
                                        <th>Open tickets</th>
                                        <?php foreach (TICKET_CATEGORIES as $category): ?>
                                            <th><?= h($category) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ictUsers as $ictUser):
                                        $ictUser = strtolower($ictUser);
                                        $isAvailable = !empty($availabilityByIctUser[$ictUser]); ?>
                                        <tr class="settings-row <?= $isAvailable ? '' : 'is-away' ?>" data-settings-row>
                                            <td class="user-color-cell settings-user-cell"
                                                style="--assignee-color: <?= h(emailToHexColor($ictUser)) ?>;">
                                                <label class="vacation-toggle">
                                                    <span class="availability-slot">
                                                        <input type="checkbox" class="availability-checkbox"
                                                            name="availability[<?= h($ictUser) ?>]" value="1" <?= $isAvailable ? 'checked' : '' ?>>
                                                    </span>
                                                    <span
                                                        class="assignee-badge vacation-badge <?= $isAvailable ? '' : 'is-away' ?>"
                                                        style="--assignee-color: <?= h($isAvailable ? emailToHexColor($ictUser) : '#94a3b8') ?>;">
                                                        <?= h($ictUser) ?>
                                                    </span>
                                                    <span class="vacation-indicator" <?= $isAvailable ? 'hidden' : '' ?>>🌴</span>
                                                </label>
                                            </td>
                                            <td class="open-load-cell"><?= (int) ($loadByIctUser[$ictUser] ?? 0) ?></td>
                                            <?php foreach (TICKET_CATEGORIES as $category): ?>
                                                <td class="setting-checkbox-cell">
                                                    <input type="checkbox" name="settings[<?= h($ictUser) ?>][<?= h($category) ?>]"
                                                        value="1" <?= !empty($settingsMatrix[$ictUser][$category]) ? 'checked' : '' ?>>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="button-row">
                            <button type="submit" name="form_action" value="save_settings">Instellingen
                                opslaan</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($canManageTickets && $view === 'stats'): ?>
                <section class="panel">
                    <h2>ICT-statistieken</h2>
                    <p class="panel-intro">Bekijk hier de totalen, prestaties per ICT-medewerker en wachttijden per normale
                        gebruiker. Voor afgehandelde tickets meten we van <strong>aangemaakt</strong> tot
                        <strong>afgehandeld</strong>; open tickets tellen mee tot <strong>nu</strong>.
                    </p>

                    <?php if ($isBigscreen ?? false): ?>
                        <div class="stats-layout">
                            <div class="stats-main">

                                <div class="stats-grid">
                                    <div class="stats-card">
                                        <span>Totaal aantal tickets</span>
                                        <strong id="stat-total"><?= (int) ($overallStats['total_tickets'] ?? 0) ?></strong>
                                    </div>
                                    <div class="stats-card">
                                        <span>Openstaande tickets</span>
                                        <strong id="stat-open"><?= (int) ($overallStats['open_tickets'] ?? 0) ?></strong>
                                    </div>
                                    <div class="stats-card">
                                        <span>Afgehandelde tickets</span>
                                        <strong
                                            id="stat-resolved"><?= (int) ($overallStats['resolved_tickets'] ?? 0) ?></strong>
                                    </div>
                                    <div class="stats-card">
                                        <span>Wacht op bestelling</span>
                                        <strong
                                            id="stat-waiting"><?= (int) ($overallStats['waiting_order_tickets'] ?? 0) ?></strong>
                                    </div>
                                </div>

                                <h3 class="stats-section-title">Per ICT-medewerker</h3>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ICT-medewerker</th>
                                                <th>Afgehandeld</th>
                                                <th>Gemiddelde tijd open</th>
                                                <th>Langste open tijd</th>
                                                <th>Openstaand</th>
                                                <th>Wacht op bestelling</th>
                                            </tr>
                                        </thead>
                                        <tbody id="stats-ict-tbody">
                                            <?php foreach ($ictStats as $statsRow): ?>
                                                <tr>
                                                    <td class="user-color-cell"
                                                        style="--assignee-color: <?= h(emailToHexColor((string) $statsRow['user_email'])) ?>;">
                                                        <?php $statsUserEmail = strtolower((string) $statsRow['user_email']); ?>
                                                        <span
                                                            class="assignee-badge <?= empty($availabilityByIctUser[$statsUserEmail]) ? 'vacation-badge is-away' : '' ?>"
                                                            style="--assignee-color: <?= h(!empty($availabilityByIctUser[$statsUserEmail]) ? emailToHexColor($statsUserEmail) : '#94a3b8') ?>;">
                                                            <?= h($statsUserEmail) ?>
                                                            <?= empty($availabilityByIctUser[$statsUserEmail]) ? ' 🌴' : '' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= (int) ($statsRow['handled_count'] ?? 0) ?></td>
                                                    <td><?= h(formatDurationSeconds($statsRow['average_open_seconds'] ?? null)) ?>
                                                    </td>
                                                    <td><?= h(formatDurationSeconds($statsRow['max_open_seconds'] ?? null)) ?></td>
                                                    <td><?= (int) ($statsRow['open_count'] ?? 0) ?></td>
                                                    <td><?= (int) ($statsRow['waiting_order_count'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <h3 class="stats-section-title">Per normale gebruiker</h3>
                                <div id="stats-requester-wrap">
                                    <?php if ($requesterStats === []): ?>
                                        <div class="empty-state">Er zijn nog geen statistieken voor normale gebruikers beschikbaar.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-wrap">
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Gebruiker</th>
                                                        <th>Tickets ingediend</th>
                                                        <th>Gemiddelde wachttijd</th>
                                                        <th>Langste wachttijd</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="stats-requester-tbody">
                                                    <?php foreach ($requesterStats as $statsRow): ?>
                                                        <tr>
                                                            <td><?= h((string) $statsRow['user_email']) ?></td>
                                                            <td><?= (int) ($statsRow['submitted_count'] ?? 0) ?></td>
                                                            <td><?= h(formatDurationSeconds($statsRow['average_wait_seconds'] ?? null)) ?>
                                                            </td>
                                                            <td><?= h(formatDurationSeconds($statsRow['max_wait_seconds'] ?? null)) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p class="stats-note">Wachttijden bij gebruikers worden berekend op basis van tickets met
                                            status
                                            <strong>afgehandeld</strong>.
                                        </p>
                                    <?php endif; ?>
                                </div><!-- /#stats-requester-wrap -->
                            </div><!-- /.stats-main -->
                            <aside class="stats-sidebar" id="stats-sidebar">
                                <h3>Openstaande tickets</h3>
                                <div id="stats-sidebar-list">
                                    <?php if ($statsOpenTickets === []): ?>
                                        <p style="color:var(--muted);font-size:13px;">Geen openstaande tickets.</p>
                                    <?php else: ?>
                                        <?php foreach ($statsOpenTickets as $sideTicket): ?>
                                            <?php $sideColor = getStatusColor((string) $sideTicket['status']); ?>
                                            <?php $sidePrio = (int) ($sideTicket['priority'] ?? 0); ?>
                                            <div class="stats-ticket-item" style="--ticket-color: <?= h($sideColor) ?>;">
                                                <div class="sti-body">
                                                    <span class="sti-title">#<?= (int) $sideTicket['id'] ?>
                                                        <?= h((string) $sideTicket['title']) ?></span>
                                                    <span class="sti-meta"><?= h((string) $sideTicket['status']) ?> &middot;
                                                        <?= h((string) $sideTicket['user_email']) ?></span>
                                                    <span class="sti-meta"><?= h((string) (($sideTicket['assigned_email'] ?? '') !== '' ? $sideTicket['assigned_email'] : 'Niet toegewezen')) ?></span>
                                                </div>
                                                <span class="sti-prio sti-prio-<?= $sidePrio ?>"><?= $sidePrio ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </aside>
                            </div><!-- /.stats-layout -->
                        <?php endif; ?>

                </section>
            <?php endif; ?>

            <?php if ($canManageTickets && $view === 'stats'): ?>
                <div id="bs-updates" aria-live="polite" aria-relevant="additions"></div>

                <div id="bigscreen-overlay" aria-live="assertive" aria-atomic="true">
                    <div class="bs-hazard-strip bs-hazard-top" id="bs-hazard-top"></div>
                    <span class="bs-warning-emoji" id="bs-warning-emoji" aria-hidden="true">⚠️</span>
                    <div class="bs-max-prio-label" id="bs-max-prio-label" hidden>MAXIMUM PRIORITEIT TICKET</div>
                    <div class="bs-hazard-strip bs-hazard-bottom" id="bs-hazard-bottom"></div>
                    <div class="bs-ticket-info" id="bs-ticket-info" hidden>
                        <div class="bs-headline" id="bs-headline"></div>
                        <div class="bs-title" id="bs-title"></div>
                        <div id="bs-assignee"></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$isAdminPortal || ($isAdminPortal && $view === 'overview')): ?>
                <section class="panel">
                    <h2><?= $isAdminPortal ? 'ICT ticketoverzicht' : 'Mijn tickets' ?></h2>

                    <?php if ($isAdminPortal): ?>
                        <form method="get" class="filters-form">
                            <?php if ($view === 'settings'): ?>
                                <input type="hidden" name="view" value="settings">
                            <?php endif; ?>

                            <input type="hidden" name="status_filter_mode" value="manual">
                            <input type="hidden" name="category_filter_mode" value="manual">

                            <div>
                                <label>Status filter</label>
                                <div class="checkbox-group">
                                    <?php foreach (TICKET_STATUSES as $status): ?>
                                        <?php $statusSelected = isStatusFilterSelected($status, $statusFilters, $statusFilterRequestActive); ?>
                                        <label class="checkbox-chip <?= $statusSelected ? 'is-active' : 'is-inactive' ?>"
                                            style="--status-color: <?= h(getStatusColor($status)) ?>;">
                                            <input type="checkbox" name="status[]" value="<?= h($status) ?>" <?= $statusSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <span><?= h($status) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <label>Categorie filter</label>
                                <div class="checkbox-group">
                                    <?php foreach (TICKET_CATEGORIES as $category): ?>
                                        <?php $categorySelected = isCategoryFilterSelected($category, $categoryFilters, $categoryFilterRequestActive); ?>
                                        <label class="checkbox-chip <?= $categorySelected ? 'is-active' : 'is-inactive' ?>"
                                            style="--status-color: <?= h(getCategoryColor($category)) ?>;">
                                            <input type="checkbox" name="category[]" value="<?= h($category) ?>"
                                                <?= $categorySelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <span><?= h($category) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <label>
                                ICT-medewerker
                                <select name="assigned" onchange="this.form.submit()">
                                    <option value="">Alle toegewezen</option>
                                    <option value="__unassigned__" <?= $assignedFilter === '__unassigned__' ? 'selected' : '' ?>>
                                        Nog niet toegewezen</option>
                                    <?php foreach ($ictUsers as $ictUser):
                                        $ictUser = strtolower($ictUser); ?>
                                        <option value="<?= h($ictUser) ?>" <?= $assignedFilter === $ictUser ? 'selected' : '' ?>>
                                            <?= h($ictUser) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div class="button-row">
                                <a class="secondary-button"
                                    href="<?= h($currentPage) ?><?= $view !== 'overview' ? '?view=' . h($view) : '' ?>">Reset
                                    filters</a>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($tickets === []): ?>
                        <div class="empty-state">
                            <?= $isAdminPortal ? 'Er zijn nog geen tickets die aan deze filters voldoen.' : 'Je hebt nog geen tickets.' ?>
                        </div>
                    <?php else: ?>
                        <div class="ticket-list">
                            <?php foreach ($tickets as $index => $ticket): ?>
                                <?php
                                $ticketColor = getStatusColor((string) $ticket['status']);
                                $ticketDetail = $store instanceof TicketStore ? $store->getTicket((int) $ticket['id'], $canManageTickets, $userEmail) : null;
                                $shouldOpen = $openTicketId > 0 && (int) $ticket['id'] === $openTicketId;
                                $ticketOpenDuration = getTicketOpenDurationSeconds($ticket);
                                $replyFormId = 'reply-form-' . (int) $ticket['id'];
                                ?>
                                <details class="ticket-card" style="--ticket-color: <?= h($ticketColor) ?>;" <?= $shouldOpen ? 'open' : '' ?>>
                                    <summary>
                                        <div class="ticket-summary">
                                            <div>
                                                <p class="ticket-main-title"><strong>#<?= (int) $ticket['id'] ?> ·
                                                        <?= h((string) $ticket['title']) ?></strong></p>
                                                <div class="ticket-subtitle">
                                                    <span><?= h((string) $ticket['user_email']) ?></span>
                                                    <span><?= h((string) $ticket['category']) ?></span>
                                                    <span><?= h(formatDateTime((string) $ticket['created_at'])) ?></span>
                                                </div>
                                            </div>
                                            <div class="ticket-subtitle">
                                                <?php if ($isAdminPortal): ?>
                                                    <span class="status-pill"
                                                        style="--ticket-color: <?= h($ticketColor) ?>;"><?= h((string) $ticket['status']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($userIsAdmin && $isAdminPortal): ?>
                                                    <span class="status-pill"
                                                        style="--ticket-color: <?= h(getPriorityColor((int) ($ticket['priority'] ?? 0))) ?>;">Prioriteit
                                                        <?= (int) ($ticket['priority'] ?? 0) ?> ·
                                                        <?= h(formatPriorityLabel((int) ($ticket['priority'] ?? 0))) ?></span>
                                                <?php endif; ?>
                                                <span class="assignee-badge"
                                                    style="--assignee-color: <?= h(emailToHexColor((string) ($ticket['assigned_email'] ?? 'onbekend@kvt.nl'))) ?>;">
                                                    <?= h((string) (($ticket['assigned_email'] ?? '') !== '' ? $ticket['assigned_email'] : 'Nog niet toegewezen')) ?>
                                                </span>
                                                <span class="count-badge"><?= (int) ($ticket['message_count'] ?? 0) ?>
                                                    berichten</span>
                                                <?php if ($isAdminPortal): ?>
                                                    <span class="count-badge">tijd open
                                                        <?= h(formatDurationSeconds($ticketOpenDuration)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </summary>

                                    <div class="ticket-body">
                                        <div class="meta-grid">
                                            <div class="meta-item">
                                                <span class="meta-label">Aangemaakt op · Tijd open</span>
                                                <?= h(formatDateTime((string) $ticket['created_at'])) ?> ·
                                                <?= h(formatDurationSeconds($ticketOpenDuration)) ?>
                                            </div>
                                            <div class="meta-item">
                                                <span class="meta-label">Laatst bijgewerkt</span>
                                                <?= h(formatDateTime((string) $ticket['updated_at'])) ?>
                                            </div>
                                            <?php if ($userIsAdmin && $isAdminPortal): ?>
                                                <div class="meta-item">
                                                    <span class="meta-label">Prioriteit</span>
                                                    <select name="priority" form="<?= h($replyFormId) ?>">
                                                        <option value="0" <?= (int) ($ticket['priority'] ?? 0) === 0 ? 'selected' : '' ?>>0
                                                            · Normaal</option>
                                                        <option value="1" <?= (int) ($ticket['priority'] ?? 0) === 1 ? 'selected' : '' ?>>1
                                                            · Belemmerd</option>
                                                        <option value="2" <?= (int) ($ticket['priority'] ?? 0) === 2 ? 'selected' : '' ?>>2
                                                            · Geblokkeerd</option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($ticketDetail !== null && !empty($ticketDetail['messages'])): ?>
                                            <div>
                                                <h3>Berichten</h3>
                                                <div class="thread">
                                                    <?php foreach ($ticketDetail['messages'] as $message): ?>
                                                        <article
                                                            class="message <?= ($message['sender_role'] ?? '') === 'admin' ? 'admin' : 'user' ?>">
                                                            <div class="message-meta">
                                                                <strong><?= h((string) $message['sender_email']) ?></strong>
                                                                <span
                                                                    class="message-role"><?= ($message['sender_role'] ?? '') === 'admin' ? 'ICT' : 'Gebruiker' ?></span>
                                                                <span><?= h(formatDateTime((string) $message['created_at'])) ?></span>
                                                            </div>

                                                            <?php if (trim((string) ($message['message_text'] ?? '')) !== ''): ?>
                                                                <div class="message-text">
                                                                    <?= formatTicketMessageText((string) $message['message_text']) ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($message['attachments'])): ?>
                                                                <ul class="attachment-list">
                                                                    <?php foreach ($message['attachments'] as $attachment): ?>
                                                                        <li>
                                                                            <a
                                                                                href="<?= h($currentPage) ?>?download=<?= (int) $attachment['id'] ?>">
                                                                                <?= h((string) $attachment['original_name']) ?>
                                                                            </a>
                                                                            (<?= number_format(((int) $attachment['file_size']) / 1024 / 1024, 2, ',', '.') ?>
                                                                            MB)
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <form method="post"
                                            action="<?= h($currentPage) ?><?= $isAdminPortal && $view === 'settings' ? '?view=settings' : '' ?>"
                                            enctype="multipart/form-data" class="reply-form" id="<?= h($replyFormId) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="form_action" value="reply_ticket">
                                            <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                                            <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">

                                            <?php if ($canManageTickets): ?>
                                                <div class="admin-grid">
                                                    <label>
                                                        Status
                                                        <select name="status">
                                                            <?php foreach (TICKET_STATUSES as $status): ?>
                                                                <option value="<?= h($status) ?>" <?= (string) $ticket['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>
                                                        Toewijzen aan
                                                        <select name="assigned_email">
                                                            <option value="">Nog niet toegewezen</option>
                                                            <?php foreach ($ictUsers as $ictUser):
                                                                $ictUser = strtolower($ictUser); ?>
                                                                <option value="<?= h($ictUser) ?>" <?= strtolower((string) ($ticket['assigned_email'] ?? '')) === $ictUser ? 'selected' : '' ?>>
                                                                    <?= h($ictUser) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                </div>
                                            <?php endif; ?>

                                            <label>
                                                Nieuw bericht
                                                <textarea name="message"
                                                    placeholder="Typ hier een update of aanvullende informatie."></textarea>
                                            </label>

                                            <?php if (!$canManageTickets && (string) $ticket['status'] === 'afgehandeld'): ?>
                                                <label class="checkbox-line">
                                                    <input type="checkbox" name="reopen_ticket" value="1">
                                                    <span>Ticket weer openen</span>
                                                </label>
                                            <?php endif; ?>

                                            <label>
                                                Bijlagen toevoegen
                                                <input type="file" name="reply_attachments[]" multiple>
                                                <span class="hint">Per bestand maximaal 20 MB.</span>
                                            </label>

                                            <div class="button-row">
                                                <button
                                                    type="submit"><?= $canManageTickets ? 'Opslaan' : 'Reactie plaatsen en ICT mailen' ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function ()
        {
            var blockedCheckbox = document.getElementById('priority_blocked');
            var fullyBlockedCheckbox = document.getElementById('priority_fully_blocked');
            var fullyBlockedWrap = document.getElementById('priority_fully_blocked_wrap');

            var syncPriorityVisibility = function ()
            {
                if (!blockedCheckbox || !fullyBlockedWrap || !fullyBlockedCheckbox)
                {
                    return;
                }

                var wasHidden = fullyBlockedWrap.hidden;
                var showFullyBlocked = blockedCheckbox.checked;
                fullyBlockedWrap.hidden = !showFullyBlocked;

                if (showFullyBlocked && wasHidden)
                {
                    fullyBlockedWrap.classList.remove('flash-blue');
                    void fullyBlockedWrap.offsetWidth;
                    fullyBlockedWrap.classList.add('flash-blue');
                }

                if (!showFullyBlocked)
                {
                    fullyBlockedCheckbox.checked = false;
                    fullyBlockedWrap.classList.remove('flash-blue');
                }
            };

            if (blockedCheckbox)
            {
                blockedCheckbox.addEventListener('change', syncPriorityVisibility);
                syncPriorityVisibility();
            }

            document.querySelectorAll('[data-settings-row]').forEach(function (row)
            {
                var availabilityCheckbox = row.querySelector('.availability-checkbox');
                var vacationIndicator = row.querySelector('.vacation-indicator');
                var vacationBadge = row.querySelector('.vacation-badge');

                if (!availabilityCheckbox)
                {
                    return;
                }

                var syncAvailabilityState = function ()
                {
                    var isAvailable = availabilityCheckbox.checked;
                    row.classList.toggle('is-away', !isAvailable);
                    if (vacationIndicator)
                    {
                        vacationIndicator.hidden = isAvailable;
                    }
                    if (vacationBadge)
                    {
                        vacationBadge.classList.toggle('is-away', !isAvailable);
                    }
                };

                availabilityCheckbox.addEventListener('change', syncAvailabilityState);
                syncAvailabilityState();
            });
        });
    </script>

    <?php if ($canManageTickets && $view === 'stats'): ?>
        <?php
        $isMockAlert = isset($_GET['mock-alert']);
        if ($isBigscreen):
            $bsAllTickets = $store instanceof TicketStore ? $store->getTickets(true, '') : [];
            $bsMaxId = 0;
            $bsMockList = [];
            foreach ($bsAllTickets as $t) {
                $tid = (int) $t['id'];
                if ($tid > $bsMaxId) {
                    $bsMaxId = $tid;
                }
                if ($isMockAlert) {
                    $bsMockList[] = [
                        'id' => $tid,
                        'title' => (string) ($t['title'] ?? ''),
                        'user_email' => (string) ($t['user_email'] ?? ''),
                        'assigned_email' => (string) ($t['assigned_email'] ?? ''),
                        'assigned_color' => emailToHexColor((string) ($t['assigned_email'] ?? '')),
                    ];
                }
            }
            ?>
            <script>
                (function ()
                {
                    var POLL_URL = 'admin.php?view=stats&_bigscreen_poll=1';
                    var VERSION_URL = 'version';
                    var CURRENT_VER = null;
                    var MOCK_ALERT = <?= $isMockAlert ? 'true' : 'false' ?>;
                    var MOCK_TICKETS = <?= json_encode($bsMockList, JSON_UNESCAPED_UNICODE) ?>;
                    var currentMaxId = <?= $bsMaxId ?>;
                    var alertActive = false;
                    var ticketSnapshot = {};
                    var snapshotReady = false;

                    var CARD_LIFETIME_MS = 70000;

                    /* ---- Update-kaarten links ---- */
                    var updatesContainer = document.getElementById('bs-updates');

                    function pushUpdateCard (lines)
                    {
                        var card = document.createElement('div');
                        card.className = 'bs-update-card';
                        var main = document.createElement('span');
                        main.textContent = lines[0];
                        card.appendChild(main);
                        for (var i = 1; i < lines.length; i++)
                        {
                            var sub = document.createElement('div');
                            sub.className = 'bsuc-sub';
                            sub.textContent = lines[i];
                            card.appendChild(sub);
                        }
                        updatesContainer.appendChild(card);
                        // Auto-verwijder na CARD_LIFETIME_MS
                        setTimeout(function ()
                        {
                            if (card.parentNode) { card.parentNode.removeChild(card); }
                        }, CARD_LIFETIME_MS);
                    }

                    /* ---- Bigscreen alert overlay ---- */
                    function showPhase1 ()
                    {
                        alertActive = true;
                        var overlay = document.getElementById('bigscreen-overlay');
                        var hazTop = document.getElementById('bs-hazard-top');
                        var hazBot = document.getElementById('bs-hazard-bottom');
                        var emoji = document.getElementById('bs-warning-emoji');
                        var label = document.getElementById('bs-max-prio-label');
                        var info = document.getElementById('bs-ticket-info');
                        overlay.className = 'bs-phase1';
                        hazTop.hidden = false;
                        hazBot.hidden = false;
                        emoji.hidden = false;
                        label.hidden = false;
                        info.hidden = true;
                    }

                    function showPhase2 (ticket)
                    {
                        var overlay = document.getElementById('bigscreen-overlay');
                        var hazTop = document.getElementById('bs-hazard-top');
                        var hazBot = document.getElementById('bs-hazard-bottom');
                        var emoji = document.getElementById('bs-warning-emoji');
                        var label = document.getElementById('bs-max-prio-label');
                        var info = document.getElementById('bs-ticket-info');
                        var headline = document.getElementById('bs-headline');
                        var titleEl = document.getElementById('bs-title');
                        var assigneeEl = document.getElementById('bs-assignee');

                        headline.textContent = 'Nieuw ticket van ' + ticket.user_email + '!';
                        titleEl.textContent = ticket.title;

                        assigneeEl.innerHTML = '';
                        if (ticket.assigned_email)
                        {
                            var pill = document.createElement('span');
                            pill.className = 'bs-assignee-pill';
                            pill.style.background = ticket.assigned_color || '#0b65c2';
                            pill.textContent = 'Toegewezen aan: ' + ticket.assigned_email;
                            assigneeEl.appendChild(pill);
                        } else
                        {
                            assigneeEl.textContent = 'Nog niet toegewezen';
                        }

                        overlay.className = 'bs-phase2';
                        hazTop.hidden = true;
                        hazBot.hidden = true;
                        emoji.hidden = true;
                        label.hidden = true;
                        info.hidden = false;
                    }

                    function runAlert (ticket)
                    {
                        var isMaxPrio = (ticket.priority || 0) >= 2;
                        if (isMaxPrio)
                        {
                            showPhase1();
                            setTimeout(function ()
                            {
                                showPhase2(ticket);
                                setTimeout(function ()
                                {
                                    alertActive = false;
                                    hideOverlay();
                                }, 10000);
                            }, 5000);
                        } else
                        {
                            alertActive = true;
                            showPhase2(ticket);
                            setTimeout(function ()
                            {
                                alertActive = false;
                                hideOverlay();
                            }, 10000);
                        }
                    }

                    function hideOverlay ()
                    {
                        var overlay = document.getElementById('bigscreen-overlay');
                        if (overlay) { overlay.className = ''; }
                    }

                    /* ---- Live DOM-updates voor stats ---- */
                    function setText (id, val)
                    {
                        var el = document.getElementById(id);
                        if (el) { el.textContent = val; }
                    }

                    function updateStatsDOM (data)
                    {
                        var os = data.overall_stats;
                        if (os)
                        {
                            setText('stat-total', os.total_tickets || 0);
                            setText('stat-open', os.open_tickets || 0);
                            setText('stat-resolved', os.resolved_tickets || 0);
                            setText('stat-waiting', os.waiting_order_tickets || 0);
                        }

                        var ictTbody = document.getElementById('stats-ict-tbody');
                        if (ictTbody && data.ict_stats)
                        {
                            var rows = '';
                            data.ict_stats.forEach(function (r)
                            {
                                var color = r.available ? r.user_color : '#94a3b8';
                                var badge = r.available ? '' : ' vacation-badge is-away';
                                var palm = r.available ? '' : ' 🌴';
                                rows += '<tr>'
                                    + '<td class="user-color-cell" style="--assignee-color:' + esc(r.user_color) + ';">'
                                    + '<span class="assignee-badge' + badge + '" style="--assignee-color:' + esc(color) + ';">'
                                    + esc(r.user_email) + palm + '</span></td>'
                                    + '<td>' + r.handled_count + '</td>'
                                    + '<td>' + esc(r.average_open) + '</td>'
                                    + '<td>' + esc(r.max_open) + '</td>'
                                    + '<td>' + r.open_count + '</td>'
                                    + '<td>' + r.waiting_order_count + '</td>'
                                    + '</tr>';
                            });
                            ictTbody.innerHTML = rows;
                        }

                        var reqWrap = document.getElementById('stats-requester-wrap');
                        if (reqWrap && data.requester_stats)
                        {
                            if (data.requester_stats.length === 0)
                            {
                                reqWrap.innerHTML = '<div class="empty-state">Er zijn nog geen statistieken voor normale gebruikers beschikbaar.</div>';
                            } else
                            {
                                var rrows = '';
                                data.requester_stats.forEach(function (r)
                                {
                                    rrows += '<tr>'
                                        + '<td>' + esc(r.user_email) + '</td>'
                                        + '<td>' + r.submitted_count + '</td>'
                                        + '<td>' + esc(r.average_wait) + '</td>'
                                        + '<td>' + esc(r.max_wait) + '</td>'
                                        + '</tr>';
                                });
                                reqWrap.innerHTML = '<div class="table-wrap"><table>'
                                    + '<thead><tr><th>Gebruiker</th><th>Tickets ingediend</th><th>Gemiddelde wachttijd</th><th>Langste wachttijd</th></tr></thead>'
                                    + '<tbody>' + rrows + '</tbody></table></div>'
                                    + '<p class="stats-note">Wachttijden bij gebruikers worden berekend op basis van tickets met status <strong>afgehandeld</strong>.</p>';
                            }
                        }

                        var sideList = document.getElementById('stats-sidebar-list');
                        if (sideList && data.open_tickets)
                        {
                            if (data.open_tickets.length === 0)
                            {
                                sideList.innerHTML = '<p style="color:var(--muted);font-size:13px;">Geen openstaande tickets.</p>';
                            } else
                            {
                                var sitems = '';
                                data.open_tickets.forEach(function (t)
                                {
                                    var assigned = t.assigned_email || 'Niet toegewezen';
                                    sitems += '<div class="stats-ticket-item" style="--ticket-color:' + esc(t.status_color) + ';">'
                                        + '<div class="sti-body">'
                                        + '<span class="sti-title">#' + t.id + ' ' + esc(t.title) + '</span>'
                                        + '<span class="sti-meta">' + esc(t.status) + ' · ' + esc(t.user_email) + '</span>'
                                        + '<span class="sti-meta">' + esc(assigned) + '</span>'
                                        + '</div>'
                                        + '<span class="sti-prio sti-prio-' + t.priority + '">' + t.priority + '</span>'
                                        + '</div>';
                                });
                                sideList.innerHTML = sitems;
                            }
                        }
                    }

                    function esc (str)
                    {
                        return String(str)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;');
                    }

                    /* ---- Wijzigingsdetectie via snapshot ---- */
                    function applySnapshot (snapshotArr)
                    {
                        if (!snapshotArr) { return; }
                        snapshotArr.forEach(function (t)
                        {
                            var id = t.id;
                            var prev = ticketSnapshot[id];
                            if (!prev)
                            {
                                ticketSnapshot[id] = t;
                                return;
                            }
                            if (!snapshotReady) { return; }
                            var changes = [];
                            if (t.status !== prev.status)
                            { changes.push('Status: ' + t.status); }
                            if (t.assigned_email !== prev.assigned_email)
                            { changes.push('Toegewezen aan: ' + (t.assigned_email || 'Niemand')); }
                            if (t.message_count !== prev.message_count)
                            { changes.push('Nieuw bericht'); }
                            if (changes.length > 0)
                            { pushUpdateCard(['#' + id + ' gewijzigd'].concat(changes)); }
                            ticketSnapshot[id] = t;
                        });
                        snapshotReady = true;
                    }

                    /* ---- Poll ---- */
                    function poll ()
                    {
                        if (alertActive) { return; }
                        fetch(POLL_URL, { credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (data)
                            {
                                if (!data) { return; }
                                applySnapshot(data.snapshot || null);
                                updateStatsDOM(data);
                                if (data.max_id > currentMaxId && data.latest)
                                {
                                    currentMaxId = data.max_id;
                                    runAlert(data.latest);
                                }
                            })
                            .catch(function () { });
                    }

                    function pollVersion ()
                    {
                        fetch(VERSION_URL, { credentials: 'same-origin', cache: 'no-store' })
                            .then(function (r) { return r.ok ? r.text() : null; })
                            .then(function (ver)
                            {
                                if (!ver) { return; }
                                ver = ver.trim();
                                if (CURRENT_VER === null) { CURRENT_VER = ver; return; }
                                if (ver !== CURRENT_VER)
                                {
                                    CURRENT_VER = ver;
                                    setTimeout(function () { location.reload(); }, 120000);
                                }
                            })
                            .catch(function () { });
                    }

                    setInterval(poll, 2000);
                    setInterval(pollVersion, 10000);

                    if (MOCK_ALERT && MOCK_TICKETS.length > 0)
                    {
                        setTimeout(function ()
                        {
                            if (!alertActive)
                            {
                                var t = MOCK_TICKETS[Math.floor(Math.random() * MOCK_TICKETS.length)];
                                runAlert(t);
                            }
                        }, 3000);
                    }
                }());
            </script>
        <?php endif; ?>
    <?php endif; ?>
</body>

</html>
