<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TicketStore.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'constants.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'localization.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'helpers.php';

if (!isset($ictUserColors) || !is_array($ictUserColors)) {
    $ictUserColors = [];
}
normalizeIctUsersConfig($ictUsers, $ictUserColors);

/**
 * Functions
 */
function sendJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getApiKeyFromRequest(): string
{
    $headerKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($headerKey !== '') {
        return $headerKey;
    }

    $requestKey = trim((string) ($_REQUEST['api_key'] ?? ''));
    if ($requestKey !== '') {
        return $requestKey;
    }

    return '';
}

function isValidApiKey(string $providedKey, array $apiKeys): bool
{
    if ($providedKey === '') {
        return false;
    }

    foreach ($apiKeys as $apiKey) {
        if (!is_string($apiKey)) {
            continue;
        }

        if (hash_equals($apiKey, $providedKey)) {
            return true;
        }
    }

    return false;
}

function isTrustedApiRequester(): bool
{
    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $serverAddress = trim((string) ($_SERVER['SERVER_ADDR'] ?? ''));

    if ($remoteAddress !== '' && $remoteAddress === $serverAddress) {
        return true;
    }

    return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
}

function loadApiClientByToken(string $providedKey): ?array
{
    $apiKey = strtolower(trim($providedKey));
    if ($apiKey === '' || preg_match('/^[a-f0-9]{64}$/', $apiKey) !== 1) {
        return null;
    }

    $apiClientFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_clients' . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json';
    if (!is_file($apiClientFile)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($apiClientFile), true);
    if (!is_array($decoded)) {
        return null;
    }

    $storedApiKey = strtolower(trim((string) ($decoded['api_key'] ?? '')));
    if ($storedApiKey === '' || !hash_equals($storedApiKey, $apiKey)) {
        return null;
    }

    return [
        'oid' => strtolower(trim((string) ($decoded['oid'] ?? ''))),
        'api_key' => $storedApiKey,
        'email' => strtolower(trim((string) ($decoded['email'] ?? ''))),
        'is_admin' => !empty($decoded['is_admin']),
    ];
}

function buildRotatingApiKeyForDate(string $oid, string $dateKey): string
{
    $normalizedOid = strtolower(trim($oid));
    $normalizedDateKey = trim($dateKey);
    if ($normalizedOid === '' || $normalizedDateKey === '') {
        return '';
    }

    return hash('sha256', $normalizedOid . '|' . $normalizedDateKey);
}

function getRefreshRequiredUnauthorizedReason(string $providedKey): ?string
{
    $normalizedKey = strtolower(trim($providedKey));
    if ($normalizedKey === '' || preg_match('/^[a-f0-9]{64}$/', $normalizedKey) !== 1) {
        return null;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $sessionCookieName = session_name();
        if ($sessionCookieName === '' || empty($_COOKIE[$sessionCookieName])) {
            return null;
        }

        session_start(['read_and_close' => true]);
    }

    $oid = strtolower(trim((string) ($_SESSION['user']['oid'] ?? '')));
    if ($oid === '' || preg_match('/^[a-z0-9-]{8,128}$/', $oid) !== 1) {
        return null;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $currentDate = $now->format('d-m-Y');
    $previousDate = $now->modify('-1 day')->format('d-m-Y');
    $currentDateKey = buildRotatingApiKeyForDate($oid, $currentDate);
    $previousDateKey = buildRotatingApiKeyForDate($oid, $previousDate);

    if ($currentDateKey !== '' && !hash_equals($currentDateKey, $normalizedKey) && $previousDateKey !== '' && hash_equals($previousDateKey, $normalizedKey)) {
        return 'session_expired_refresh_required';
    }

    return null;
}

function getRequestBody(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (str_contains($contentType, 'application/json')) {
        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function buildTicketPollApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'index.php'));
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    $isAdminPortal = !empty($payload['is_admin_portal']);
    $canManageTickets = $isAdminPortal && $userIsAdmin;
    $csrfToken = (string) ($payload['csrf_token'] ?? '');
    $openTicketId = max(0, (int) ($payload['open_ticket_id'] ?? 0));
    $view = trim((string) ($payload['view'] ?? 'overview'));
    $assignedFilter = trim((string) ($payload['assigned_filter'] ?? ''));
    $statusFilters = array_values(array_filter(
        array_map('trim', (array) ($payload['status_filters'] ?? [])),
        static fn(string $status): bool => $status !== ''
    ));
    $categoryFilters = array_values(array_filter(
        array_map('trim', (array) ($payload['category_filters'] ?? [])),
        static fn(string $category): bool => $category !== ''
    ));

    $tickets = $store->getTickets($canManageTickets, $viewerEmail, $statusFilters, $assignedFilter, $categoryFilters);
    $ticketPollItems = [];

    foreach ($tickets as $ticket) {
        $ticketDetail = $store->getTicket((int) ($ticket['id'] ?? 0), $canManageTickets, $viewerEmail);
        $ticketPollItems[] = buildTicketPollEntry($ticket, $ticketDetail, [
            'currentPage' => $currentPage,
            'canManageTickets' => $canManageTickets,
            'userIsAdmin' => $userIsAdmin,
            'isAdminPortal' => $isAdminPortal,
            'ictUsers' => $GLOBALS['ictUsers'] ?? [],
            'csrfToken' => $csrfToken,
            'openTicketId' => $openTicketId,
            'view' => $view,
        ]);
    }

    return [
        'success' => true,
        'signature' => buildTicketSnapshotSignature($tickets),
        'tickets' => $ticketPollItems,
        'is_empty' => $tickets === [],
        'empty_html' => '<div class="empty-state">' . ($isAdminPortal ? h(__('tickets.empty_admin')) : h(__('tickets.empty_user'))) . '</div>',
    ];
}

function buildBrowserNotificationsApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    $targetPage = $userIsAdmin ? 'admin.php' : 'index.php';
    $notificationItems = $store->pullBrowserNotifications($viewerEmail, 25);

    return [
        'success' => true,
        'notifications' => array_map(
            static fn(array $notification): array => [
                'id' => (int) ($notification['id'] ?? 0),
                'ticket_id' => (int) ($notification['ticket_id'] ?? 0),
                'title' => (string) ($notification['title'] ?? ''),
                'body' => (string) ($notification['body'] ?? ''),
                'open_url' => $targetPage . '?open=' . (int) ($notification['ticket_id'] ?? 0),
                'created_at' => (string) ($notification['created_at'] ?? ''),
            ],
            $notificationItems
        ),
    ];
}

function handleWebPushSubscriptionApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    if ($viewerEmail === '') {
        return [
            'success' => false,
            'error' => 'viewer_missing',
        ];
    }

    $action = trim((string) ($payload['subscription_action'] ?? 'subscribe'));
    $subscription = is_array($payload['subscription'] ?? null) ? $payload['subscription'] : [];
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));

    if ($action === 'unsubscribe') {
        if ($endpoint !== '') {
            $store->removeWebPushSubscription($viewerEmail, $endpoint);
        }

        return ['success' => true];
    }

    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
    $p256dhKey = trim((string) ($keys['p256dh'] ?? ''));
    $authKey = trim((string) ($keys['auth'] ?? ''));

    $store->saveWebPushSubscription(
        $viewerEmail,
        $endpoint,
        $p256dhKey,
        $authKey,
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
    );

    return ['success' => true];
}

function buildBigscreenPollApiPayload(TicketStore $store): array
{
    $allTicketsForPoll = $store->getTickets(true, '');
    $pollMaxId = 0;
    $pollLatest = null;
    $pollSnapshot = [];
    $pollOpenTickets = [];

    foreach ($allTicketsForPoll as $ticket) {
        $ticketId = (int) ($ticket['id'] ?? 0);
        if ($ticketId > $pollMaxId) {
            $pollMaxId = $ticketId;
            $pollLatest = $ticket;
        }

        $pollSnapshot[] = [
            'id' => $ticketId,
            'updated_at' => (string) ($ticket['updated_at'] ?? ''),
            'status' => (string) ($ticket['status'] ?? ''),
            'assigned_email' => (string) ($ticket['assigned_email'] ?? ''),
            'message_count' => (int) ($ticket['message_count'] ?? 0),
        ];

        if ((string) ($ticket['status'] ?? '') !== 'afgehandeld') {
            $pollOpenTickets[] = [
                'id' => $ticketId,
                'title' => (string) ($ticket['title'] ?? ''),
                'status' => (string) ($ticket['status'] ?? ''),
                'status_label' => translateStatus((string) ($ticket['status'] ?? '')),
                'status_color' => getStatusColor((string) ($ticket['status'] ?? '')),
                'user_email' => (string) ($ticket['user_email'] ?? ''),
                'assigned_email' => (string) ($ticket['assigned_email'] ?? ''),
                'priority' => (int) ($ticket['priority'] ?? 0),
            ];
        }
    }

    $pollOverallStats = $store->getOverallStats();
    $pollIctStats = $store->getIctUserStats();
    $pollRequesterStats = $store->getRequesterStats();
    $pollAvailability = $store->getIctUserAvailability();

    $pollIctStatsMapped = array_map(function (array $row) use ($pollAvailability): array {
        $email = strtolower((string) ($row['user_email'] ?? ''));
        return [
            'user_email' => $email,
            'user_color' => emailToHexColor($email),
            'available' => !empty($pollAvailability[$email]),
            'handled_count' => (int) ($row['handled_count'] ?? 0),
            'average_open' => formatDurationSeconds($row['average_open_seconds'] ?? null),
            'max_open' => formatDurationSeconds($row['max_open_seconds'] ?? null),
            'open_count' => (int) ($row['open_count'] ?? 0),
            'waiting_order_count' => (int) ($row['waiting_order_count'] ?? 0),
        ];
    }, $pollIctStats);

    $pollRequesterStatsMapped = array_map(function (array $row): array {
        return [
            'user_email' => (string) ($row['user_email'] ?? ''),
            'average_wait' => formatDurationSeconds($row['average_wait_seconds'] ?? null),
            'max_wait' => formatDurationSeconds($row['max_wait_seconds'] ?? null),
            'average_response' => formatDurationSeconds($row['average_response_seconds'] ?? null),
        ];
    }, $pollRequesterStats);

    return [
        'success' => true,
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
    ];
}

function buildBigscreenVersionApiPayload(): array
{
    $versionFiles = [
        __DIR__ . DIRECTORY_SEPARATOR . 'index.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'data.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'bigscreen_js.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'page_js.php',
    ];

    $versionSource = [];
    foreach ($versionFiles as $versionFile) {
        if (is_file($versionFile)) {
            $versionSource[] = basename($versionFile) . ':' . (string) filemtime($versionFile);
        }
    }

    return [
        'success' => true,
        'version' => sha1(implode('|', $versionSource)),
    ];
}

/**
 * Page load
 */
$providedApiKey = getApiKeyFromRequest();
$apiClient = loadApiClientByToken($providedApiKey);
if (!isTrustedApiRequester() && $apiClient === null && !isValidApiKey($providedApiKey, $apiKeys ?? [])) {
    $unauthorizedReason = getRefreshRequiredUnauthorizedReason($providedApiKey);
    sendJson(401, [
        'success' => false,
        'error' => 'Ongeldige API-key.',
        'reason' => $unauthorizedReason,
    ]);
}

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers ?? [], TICKET_CATEGORIES);
} catch (Throwable $exception) {
    sendJson(500, [
        'success' => false,
        'error' => 'Database kon niet worden geopend.',
        'details' => $exception->getMessage(),
    ]);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $ticketId = max(0, (int) ($_GET['id'] ?? 0));

    if ($ticketId > 0) {
        $ticket = $store->getTicket($ticketId, true, '');
        if ($ticket === null) {
            sendJson(404, [
                'success' => false,
                'error' => 'Ticket niet gevonden.',
            ]);
        }

        sendJson(200, [
            'success' => true,
            'ticket' => $ticket,
        ]);
    }

    $tickets = $store->getTickets(true, '', [], null, []);
    sendJson(200, [
        'success' => true,
        'count' => count($tickets),
        'tickets' => $tickets,
    ]);
}

if ($method === 'POST') {
    $payload = getRequestBody();
    $action = trim((string) ($payload['action'] ?? ''));

    if ($action === 'ticket_poll') {
        sendJson(200, buildTicketPollApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'browser_notifications_poll') {
        sendJson(200, buildBrowserNotificationsApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'webpush_subscription') {
        sendJson(200, handleWebPushSubscriptionApiAction($store, $payload, $apiClient));
    }

    if ($action === 'bigscreen_poll') {
        if (!($apiClient['is_admin'] ?? false) && !isTrustedApiRequester()) {
            sendJson(403, [
                'success' => false,
                'error' => 'forbidden',
            ]);
        }
        sendJson(200, buildBigscreenPollApiPayload($store));
    }

    if ($action === 'bigscreen_version') {
        if (!($apiClient['is_admin'] ?? false) && !isTrustedApiRequester()) {
            sendJson(403, [
                'success' => false,
                'error' => 'forbidden',
            ]);
        }
        sendJson(200, buildBigscreenVersionApiPayload());
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $category = trim((string) ($payload['category'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $userEmail = strtolower(trim((string) ($payload['user_email'] ?? '')));
    $priority = max(0, min(2, (int) ($payload['priority'] ?? 0)));

    if ($userEmail === '') {
        $userEmail = strtolower(trim((string) ($payload['requester_email'] ?? '')));
    }

    $errors = [];
    if ($title === '') {
        $errors[] = 'Titel is verplicht.';
    }
    if (!in_array($category, TICKET_CATEGORIES, true)) {
        $errors[] = 'Categorie is ongeldig.';
    }
    if ($description === '') {
        $errors[] = 'Beschrijving is verplicht.';
    }
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'user_email moet een geldig e-mailadres zijn.';
    }

    if ($errors !== []) {
        sendJson(422, [
            'success' => false,
            'errors' => $errors,
        ]);
    }

    try {
        $result = $store->createTicket($title, $category, $userEmail, $description, [], $priority);
        $ticketId = (int) ($result['ticket_id'] ?? 0);
        $ticket = $store->getTicket($ticketId, true, '');

        sendJson(201, [
            'success' => true,
            'ticket_id' => $ticketId,
            'assigned_email' => $result['assigned_email'] ?? null,
            'ticket' => $ticket,
        ]);
    } catch (Throwable $exception) {
        sendJson(500, [
            'success' => false,
            'error' => 'Ticket kon niet worden aangemaakt.',
            'details' => $exception->getMessage(),
        ]);
    }
}

sendJson(405, [
    'success' => false,
    'error' => 'Method niet toegestaan.',
]);
