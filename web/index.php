<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/TicketStore.php';

/**
 * Constants
 */
const DATABASE_FILE = __DIR__ . 'data/asclepius.sqlite';
const UPLOAD_DIRECTORY = __DIR__ . 'data/ticket_uploads';
const MAX_ATTACHMENT_BYTES = 20971520;
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
$assignedFilter = $canManageTickets ? trim((string) ($_GET['assigned'] ?? '')) : '';
$view = $canManageTickets && (($_GET['view'] ?? '') === 'settings') ? 'settings' : 'overview';
$openTicketId = max(0, (int) ($_GET['open'] ?? 0));
$baseQuery = buildNavigationQuery($statusFilters, $assignedFilter, $view, $isAdminPortal, $statusFilterRequestActive);

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

function buildNavigationQuery(array $statusFilters, string $assignedFilter, string $view, bool $isAdminPortal, bool $statusFilterRequestActive = false, int $openTicketId = 0): array
{
    $query = [];

    if ($statusFilterRequestActive) {
        $query['status_filter_mode'] = 'manual';
    }

    if ($statusFilters !== []) {
        $query['status'] = $statusFilters;
    }

    if ($assignedFilter !== '') {
        $query['assigned'] = $assignedFilter;
    }

    if ($isAdminPortal && $view === 'settings') {
        $query['view'] = 'settings';
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

        $escapedLine = h($line);
        if (str_starts_with($trimmedLine, 'Status gewijzigd naar ')) {
            $formattedLines[] = '<small>' . $escapedLine . '</small>';
            continue;
        }

        $formattedLines[] = $escapedLine;
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

    $ticket = $store->getTicket((int) $attachment['ticket_id'], $canManageTickets, $userEmail);
    if ($ticket === null) {
        http_response_code(403);
        exit('Geen toegang tot deze bijlage.');
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

    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'create_ticket') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
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

            if ($errors !== []) {
                throw new RuntimeException(implode(' ', $errors));
            }

            $result = $store->createTicket($title, $category, $userEmail, $description, $files);
            $ticketId = (int) $result['ticket_id'];
            $ticket = $store->getTicket($ticketId, true, $userEmail);

            if ($ticket !== null) {
                $recipients = !empty($result['assigned_email']) ? [$result['assigned_email']] : $ictUsers;
                sendTicketEmail(
                    $recipients,
                    'Nieuw ticket #' . $ticketId,
                    buildNotificationBody($ticket, 'Er is een nieuw ICT-ticket ingediend.', $description, true),
                    $userEmail
                );
            }

            pushFlash('success', 'Ticket #' . $ticketId . ' is aangemaakt en automatisch toegewezen.');
            redirectToPage($returnPage, array_merge($baseQuery, ['open' => $ticketId]));
        }

        if ($action === 'reply_ticket') {
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
            $statusChanged = false;
            $assigneeChanged = false;

            if ($canManageTickets) {
                $requestedStatus = trim((string) ($_POST['status'] ?? $ticket['status']));
                if (!in_array($requestedStatus, TICKET_STATUSES, true)) {
                    $errors[] = 'Kies een geldige status.';
                } else {
                    $newStatus = $requestedStatus;
                    $statusChanged = $newStatus !== (string) $ticket['status'];
                }

                $requestedAssignee = strtolower(trim((string) ($_POST['assigned_email'] ?? (string) ($ticket['assigned_email'] ?? ''))));
                if ($requestedAssignee !== '' && !in_array($requestedAssignee, array_map('strtolower', $ictUsers), true)) {
                    $errors[] = 'Kies een geldige ICT-medewerker.';
                } else {
                    $newAssignee = $requestedAssignee;
                    $assigneeChanged = $newAssignee !== strtolower((string) ($ticket['assigned_email'] ?? ''));
                }
            }

            if ($message === '' && $files === [] && !$statusChanged && !$assigneeChanged) {
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

            if ($canManageTickets && ($statusChanged || $assigneeChanged)) {
                $store->updateTicket($ticketId, $newStatus, $newAssignee !== '' ? $newAssignee : null);
            }

            if ($messageForStorage !== '' || $files !== []) {
                $store->addMessage($ticketId, $userEmail, $canManageTickets ? 'admin' : 'user', $messageForStorage, $files);
            }

            $updatedTicket = $store->getTicket($ticketId, true, $userEmail);
            if ($updatedTicket !== null) {
                if ($canManageTickets) {
                    sendTicketEmail(
                        [$updatedTicket['user_email']],
                        'Update op ticket #' . $ticketId,
                        buildNotificationBody(
                            $updatedTicket,
                            'ICT heeft je ticket bijgewerkt' . ($statusChanged ? ' en de status aangepast.' : '.'),
                            $messageForStorage,
                            false
                        ),
                        $userEmail
                    );

                    if ($assigneeChanged && $newAssignee !== '') {
                        sendTicketEmail(
                            [$newAssignee],
                            'Ticket #' . $ticketId . ' is aan jou toegewezen',
                            buildNotificationBody($updatedTicket, 'Een ICT-ticket is opnieuw aan jou toegewezen.', $message, true),
                            $userEmail
                        );
                    }
                } else {
                    $recipients = !empty($updatedTicket['assigned_email']) ? [$updatedTicket['assigned_email']] : $ictUsers;
                    sendTicketEmail(
                        $recipients,
                        'Reactie van gebruiker op ticket #' . $ticketId,
                        buildNotificationBody($updatedTicket, 'De aanvrager heeft gereageerd op een ticket.', $message, true),
                        $userEmail
                    );
                }
            }

            pushFlash('success', 'Ticket #' . $ticketId . ' is bijgewerkt.');
            redirectToPage($returnPage, array_merge($baseQuery, ['open' => $ticketId]));
        }

        if ($action === 'save_settings') {
            if (!$canManageTickets) {
                throw new RuntimeException('Alleen admins kunnen instellingen aanpassen.');
            }

            $postedSettings = $_POST['settings'] ?? [];
            $matrix = [];

            foreach ($ictUsers as $ictUser) {
                $ictUser = strtolower($ictUser);
                foreach (TICKET_CATEGORIES as $category) {
                    $matrix[$ictUser][$category] = !empty($postedSettings[$ictUser][$category]);
                }
            }

            $store->saveCategoryMatrix($matrix);
            pushFlash('success', 'De categorie-instellingen voor ICT zijn opgeslagen.');
            redirectToPage('admin.php', ['view' => 'settings']);
        }

        throw new RuntimeException('Onbekende actie ontvangen.');
    } catch (Throwable $exception) {
        pushFlash('error', $exception->getMessage());
        redirectToPage($returnPage, array_merge($baseQuery, $action === 'reply_ticket' ? ['open' => max(1, (int) ($_POST['ticket_id'] ?? 0))] : []));
    }
}

$tickets = $store instanceof TicketStore
    ? $store->getTickets($canManageTickets, $userEmail, $effectiveStatusFilters, $assignedFilter)
    : [];
$settingsMatrix = $store instanceof TicketStore ? $store->getCategorySettings() : [];
$loadByIctUser = $store instanceof TicketStore ? $store->getIctUserLoads() : [];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asclepius - ICT tickets</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --panel: #ffffff;
            --text: #10233f;
            --muted: #5b6b82;
            --line: #d8e0eb;
            --accent: #0b65c2;
            --accent-soft: #e8f1fb;
            --danger: #b42318;
            --danger-soft: #fee4e2;
            --success: #067647;
            --success-soft: #d1fadf;
            --shadow: 0 16px 40px rgba(15, 35, 63, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a {
            color: var(--accent);
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 16px;
        }

        .hero {
            background: linear-gradient(135deg, #0e2c52, #0b65c2);
            color: #fff;
            border-radius: 20px;
            padding: 18px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .brand {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.08);
            object-fit: contain;
            padding: 8px;
        }

        .eyebrow {
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 12px;
            opacity: 0.8;
        }

        .hero h1 {
            margin: 0;
            font-size: 28px;
        }

        .hero p {
            margin: 6px 0 0;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .user-chip,
        .nav-link,
        .status-pill,
        .assignee-badge,
        .count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .user-chip {
            background: rgba(255, 255, 255, 0.16);
        }

        .nav-link {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-link.active {
            background: #fff;
            color: var(--accent);
        }

        .flash-stack {
            margin: 16px 0;
            display: grid;
            gap: 10px;
        }

        .flash {
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 14px;
            box-shadow: var(--shadow);
        }

        .flash.success {
            background: var(--success-soft);
            color: var(--success);
        }

        .flash.error {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .layout {
            display: grid;
            gap: 16px;
            margin-top: 16px;
        }

        .panel {
            background: var(--panel);
            border-radius: 18px;
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .panel h2 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .panel-intro {
            margin-top: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .dev-note {
            margin-top: 8px;
            font-size: 13px;
            color: #dbeafe;
        }

        .form-grid {
            display: grid;
            gap: 12px;
        }

        .form-grid.two-columns,
        .admin-grid,
        .meta-grid {
            grid-template-columns: 1fr;
        }

        label {
            display: grid;
            gap: 6px;
            font-weight: 700;
            font-size: 14px;
        }

        input[type="text"],
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 11px 12px;
            font: inherit;
            background: #fff;
            color: var(--text);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input[type="file"] {
            font: inherit;
        }

        .hint {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        button,
        .secondary-button {
            border: 0;
            border-radius: 12px;
            padding: 11px 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        button {
            background: var(--accent);
            color: #fff;
        }

        .secondary-button {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .filters-form {
            display: grid;
            gap: 12px;
            padding: 12px;
            background: #f8fbff;
            border-radius: 14px;
            border: 1px solid var(--line);
            margin-bottom: 14px;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .checkbox-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--status-color, #fff);
            border: 1px solid transparent;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s ease, filter 0.15s ease, transform 0.15s ease;
        }

        .checkbox-chip:hover {
            transform: translateY(-1px);
        }

        .checkbox-chip.is-inactive {
            opacity: 0.45;
            filter: saturate(0.65) brightness(1.2);
        }

        .checkbox-chip input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .checkbox-chip span {
            pointer-events: none;
        }

        .ticket-list {
            display: grid;
            gap: 12px;
        }

        .ticket-card {
            border-radius: 16px;
            background: #fff;
            border: 1px solid var(--line);
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(15, 35, 63, 0.04);
            border-top: 8px solid var(--ticket-color, #2563eb);
        }

        .ticket-card summary {
            list-style: none;
            cursor: pointer;
            padding: 14px;
        }

        .ticket-card summary::-webkit-details-marker {
            display: none;
        }

        .ticket-summary {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ticket-main-title {
            margin: 0;
            font-size: 18px;
        }

        .ticket-subtitle {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .status-pill {
            color: #fff;
            background: var(--ticket-color, #2563eb);
            width: fit-content;
        }

        .assignee-badge {
            color: #fff;
            background: var(--assignee-color, #475569);
            width: fit-content;
        }

        .count-badge {
            background: #eef2f7;
            color: var(--muted);
            width: fit-content;
        }

        .ticket-body {
            padding: 0 14px 14px;
            display: grid;
            gap: 14px;
        }

        .meta-grid {
            display: grid;
            gap: 10px;
        }

        .meta-item {
            padding: 10px;
            border-radius: 12px;
            background: #f8fbff;
            border: 1px solid var(--line);
        }

        .meta-label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .thread {
            display: grid;
            gap: 10px;
        }

        .message {
            border-radius: 14px;
            padding: 12px;
            border: 1px solid var(--line);
            background: #fbfdff;
        }

        .message.admin {
            background: #eff6ff;
        }

        .message-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            align-items: center;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .message-role {
            font-weight: 700;
            color: var(--accent);
        }

        .message-text {
            color: var(--text);
            line-height: 1.5;
        }

        .message-text small {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 0.82em;
        }

        .attachment-list {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .reply-form {
            padding-top: 6px;
            border-top: 1px solid var(--line);
            display: grid;
            gap: 12px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 10px 8px;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }

        .user-color-cell {
            background: linear-gradient(90deg, var(--assignee-color, #0b65c2) 0 12px, #f8fbff 12px 100%);
        }

        .empty-state {
            padding: 20px;
            border-radius: 14px;
            background: #f8fbff;
            border: 1px dashed var(--line);
            color: var(--muted);
            text-align: center;
        }

        @media (min-width: 760px) {
            .form-grid.two-columns,
            .admin-grid,
            .meta-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ticket-summary {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="hero">
        <div class="brand">
            <img class="brand-logo" src="kvtlogo.png" alt="KVT logo">
            <div>
                <p class="eyebrow">Asclepius</p>
                <h1>ICT ticketsysteem</h1>
                <p><?= $userIsAdmin ? 'Beheer alle tickets, behandel reacties en verdeel werk slim over ICT.' : 'Maak eenvoudig een ICT-ticket aan en volg je meldingen.' ?></p>
                <?php if ($localRequester): ?>
                    <p class="dev-note">Ontwikkelen/testen: gebruik eventueel <code>?dev_user=naam@kvt.nl&amp;dev_admin=0</code> of <code>1</code> om rollen lokaal te wisselen.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-actions">
            <span class="user-chip"><?= h($userEmail) ?><?= $userIsAdmin ? ' · admin' : '' ?></span>
            <a class="nav-link <?= !$isAdminPortal ? 'active' : '' ?>" href="index.php">Nieuw ticket</a>
            <?php if ($userIsAdmin): ?>
                <a class="nav-link <?= $isAdminPortal && $view === 'overview' ? 'active' : '' ?>" href="admin.php">ICT-overzicht</a>
                <a class="nav-link <?= $isAdminPortal && $view === 'settings' ? 'active' : '' ?>" href="admin.php?view=settings">Instellingen</a>
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
                <p class="panel-intro">Een ticket krijgt automatisch een ICT-medewerker toegewezen op basis van categorie en actuele openstaande werkdruk.</p>
                <form method="post" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_ticket">
                    <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">

                    <div class="form-grid two-columns">
                        <label>
                            Titel
                            <input type="text" name="title" maxlength="150" placeholder="Bijvoorbeeld: Nieuwe scanner nodig" required>
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
                        <textarea name="description" placeholder="Beschrijf het probleem of de aanvraag zo duidelijk mogelijk." required></textarea>
                    </label>

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
                <p class="panel-intro">Zet per ICT-collega categorieën aan of uit. Nieuwe tickets worden automatisch toegewezen aan de minst belaste collega die de gekozen categorie aan heeft staan.</p>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_settings">
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
                            <?php foreach ($ictUsers as $ictUser): $ictUser = strtolower($ictUser); ?>
                                <tr>
                                    <td class="user-color-cell" style="--assignee-color: <?= h(emailToHexColor($ictUser)) ?>;">
                                        <span class="assignee-badge" style="--assignee-color: <?= h(emailToHexColor($ictUser)) ?>;">
                                            <?= h($ictUser) ?>
                                        </span>
                                    </td>
                                    <td><?= (int) ($loadByIctUser[$ictUser] ?? 0) ?></td>
                                    <?php foreach (TICKET_CATEGORIES as $category): ?>
                                        <td>
                                            <input
                                                type="checkbox"
                                                name="settings[<?= h($ictUser) ?>][<?= h($category) ?>]"
                                                value="1"
                                                <?= !empty($settingsMatrix[$ictUser][$category]) ? 'checked' : '' ?>
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="button-row">
                        <button type="submit">Instellingen opslaan</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="panel">
            <h2><?= $isAdminPortal ? 'ICT ticketoverzicht' : 'Mijn tickets' ?></h2>
           
            <?php if ($isAdminPortal): ?>
                <form method="get" class="filters-form">
                    <?php if ($view === 'settings'): ?>
                        <input type="hidden" name="view" value="settings">
                    <?php endif; ?>

                    <input type="hidden" name="status_filter_mode" value="manual">

                    <div>
                        <label>Status filter</label>
                        <div class="checkbox-group">
                            <?php foreach (TICKET_STATUSES as $status): ?>
                                <?php $statusSelected = isStatusFilterSelected($status, $statusFilters, $statusFilterRequestActive); ?>
                                <label class="checkbox-chip <?= $statusSelected ? 'is-active' : 'is-inactive' ?>" style="--status-color: <?= h(getStatusColor($status)) ?>;">
                                    <input type="checkbox" name="status[]" value="<?= h($status) ?>" <?= $statusSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span><?= h($status) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label>
                        ICT-medewerker
                        <select name="assigned" onchange="this.form.submit()">
                            <option value="">Alle toegewezen</option>
                            <option value="__unassigned__" <?= $assignedFilter === '__unassigned__' ? 'selected' : '' ?>>Nog niet toegewezen</option>
                            <?php foreach ($ictUsers as $ictUser): $ictUser = strtolower($ictUser); ?>
                                <option value="<?= h($ictUser) ?>" <?= $assignedFilter === $ictUser ? 'selected' : '' ?>><?= h($ictUser) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="button-row">
                        <a class="secondary-button" href="<?= h($currentPage) ?><?= $view === 'settings' ? '?view=settings' : '' ?>">Reset filters</a>
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
                        ?>
                        <details class="ticket-card" style="--ticket-color: <?= h($ticketColor) ?>;" <?= $shouldOpen ? 'open' : '' ?>>
                            <summary>
                                <div class="ticket-summary">
                                    <div>
                                        <p class="ticket-main-title"><strong>#<?= (int) $ticket['id'] ?> · <?= h((string) $ticket['title']) ?></strong></p>
                                        <div class="ticket-subtitle">
                                            <span><?= h((string) $ticket['user_email']) ?></span>
                                            <span><?= h((string) $ticket['category']) ?></span>
                                            <span><?= h(formatDateTime((string) $ticket['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="ticket-subtitle">
                                        <span class="status-pill" style="--ticket-color: <?= h($ticketColor) ?>;"><?= h((string) $ticket['status']) ?></span>
                                        <span class="assignee-badge" style="--assignee-color: <?= h(emailToHexColor((string) ($ticket['assigned_email'] ?? 'onbekend@kvt.nl'))) ?>;">
                                            <?= h((string) (($ticket['assigned_email'] ?? '') !== '' ? $ticket['assigned_email'] : 'Nog niet toegewezen')) ?>
                                        </span>
                                        <span class="count-badge"><?= (int) ($ticket['message_count'] ?? 0) ?> berichten</span>
                                    </div>
                                </div>
                            </summary>

                            <div class="ticket-body">
                                <div class="meta-grid">
                                    <div class="meta-item">
                                        <span class="meta-label">Omschrijving</span>
                                        <?= nl2br(h((string) $ticket['description'])) ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Laatst bijgewerkt</span>
                                        <?= h(formatDateTime((string) $ticket['updated_at'])) ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Aanvrager</span>
                                        <?= h((string) $ticket['user_email']) ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Toegewezen ICT-medewerker</span>
                                        <span class="assignee-badge" style="--assignee-color: <?= h(emailToHexColor((string) ($ticket['assigned_email'] ?? 'onbekend@kvt.nl'))) ?>;">
                                            <?= h((string) (($ticket['assigned_email'] ?? '') !== '' ? $ticket['assigned_email'] : 'Nog niet toegewezen')) ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($ticketDetail !== null && !empty($ticketDetail['messages'])): ?>
                                    <div>
                                        <h3>Berichten</h3>
                                        <div class="thread">
                                            <?php foreach ($ticketDetail['messages'] as $message): ?>
                                                <article class="message <?= ($message['sender_role'] ?? '') === 'admin' ? 'admin' : 'user' ?>">
                                                    <div class="message-meta">
                                                        <strong><?= h((string) $message['sender_email']) ?></strong>
                                                        <span class="message-role"><?= ($message['sender_role'] ?? '') === 'admin' ? 'ICT' : 'Gebruiker' ?></span>
                                                        <span><?= h(formatDateTime((string) $message['created_at'])) ?></span>
                                                    </div>

                                                    <?php if (trim((string) ($message['message_text'] ?? '')) !== ''): ?>
                                                        <div class="message-text"><?= formatTicketMessageText((string) $message['message_text']) ?></div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($message['attachments'])): ?>
                                                        <ul class="attachment-list">
                                                            <?php foreach ($message['attachments'] as $attachment): ?>
                                                                <li>
                                                                    <a href="index.php?download=<?= (int) $attachment['id'] ?>">
                                                                        <?= h((string) $attachment['original_name']) ?>
                                                                    </a>
                                                                    (<?= number_format(((int) $attachment['file_size']) / 1024 / 1024, 2, ',', '.') ?> MB)
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form method="post" enctype="multipart/form-data" class="reply-form">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="reply_ticket">
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
                                                    <?php foreach ($ictUsers as $ictUser): $ictUser = strtolower($ictUser); ?>
                                                        <option value="<?= h($ictUser) ?>" <?= strtolower((string) ($ticket['assigned_email'] ?? '')) === $ictUser ? 'selected' : '' ?>><?= h($ictUser) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        </div>
                                    <?php endif; ?>

                                    <label>
                                        Nieuw bericht
                                        <textarea name="message" placeholder="Typ hier een update of aanvullende informatie."></textarea>
                                    </label>

                                    <label>
                                        Bijlagen toevoegen
                                        <input type="file" name="reply_attachments[]" multiple>
                                        <span class="hint">Per bestand maximaal 20 MB.</span>
                                    </label>

                                    <div class="button-row">
                                        <button type="submit"><?= $canManageTickets ? 'Opslaan en gebruiker mailen' : 'Reactie plaatsen en ICT mailen' ?></button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>