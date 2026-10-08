<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$vendorAutoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TicketStore.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'constants.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'localization.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'ict_roles.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'janus_sync.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'TranslationProvider.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'LaraTranslationProvider.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'translation.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'mail.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'changelog.php';

if (!isset($ictUserColors) || !is_array($ictUserColors)) {
    $ictUserColors = [];
}
normalizeIctUsersConfig($ictUsers, $ictUserColors);

function ensureApiSessionStarted(): void
{
    static $done = false;
    if ($done || session_status() === PHP_SESSION_ACTIVE) {
        $done = true;

        return;
    }

    $appSessionConfigPaths = [
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'session_config.php',
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'session_config.php',
    ];
    foreach ($appSessionConfigPaths as $appSessionConfigPath) {
        if (!is_file($appSessionConfigPath)) {
            continue;
        }

        require_once $appSessionConfigPath;
        configure_app_session();
        break;
    }

    $sessionCookieName = session_name();
    if ($sessionCookieName !== '' && !empty($_COOKIE[$sessionCookieName])) {
        session_start(['read_and_close' => true]);
    }

    $done = true;
}

/**
 * Functions
 */
function sendJson(int $statusCode, array $payload): void
{
    $payload = enrichApiResponseWithUserNames($payload);
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

    $expiresAt = trim((string) ($decoded['expires_at'] ?? ''));
    if ($expiresAt !== '') {
        $expiresTs = strtotime($expiresAt);
        if ($expiresTs === false || $expiresTs < time()) {
            @unlink($apiClientFile);

            return null;
        }
    }

    return [
        'oid' => strtolower(trim((string) ($decoded['oid'] ?? ''))),
        'api_key' => $storedApiKey,
        'email' => strtolower(trim((string) ($decoded['email'] ?? ''))),
        'is_admin' => !empty($decoded['is_admin']),
        'kind' => trim((string) ($decoded['kind'] ?? '')),
        'default_name' => trim((string) ($decoded['default_name'] ?? '')),
        'default_title' => trim((string) ($decoded['default_title'] ?? '')),
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

    $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    $currentDate = $now->format('d-m-Y');
    $previousDate = $now->modify('-1 day')->format('d-m-Y');
    $currentDateKey = buildRotatingApiKeyForDate($oid, $currentDate);
    $previousDateKey = buildRotatingApiKeyForDate($oid, $previousDate);

    if ($currentDateKey !== '' && !hash_equals($currentDateKey, $normalizedKey) && $previousDateKey !== '' && hash_equals($previousDateKey, $normalizedKey)) {
        return 'session_expired_refresh_required';
    }

    return null;
}

/**
 * Persoonlijke key van de gedeelde login (login/session_user.php: sha256(oid|d-m-Y UTC),
 * in $_SESSION['user']['api_key']). Andere sleutels-apps kennen geen api_clients-bestand
 * van Asclepius; daarom accepteren we die key hier ook zonder dat de gebruiker vandaag
 * Asclepius heeft geopend — maar alleen om namens die gebruiker zelf een ticket aan te maken.
 *
 * Vereist naast de key ook `oid` en `user_email` (body/query of header X-User-Oid /
 * X-User-Email). Kent Asclepius dit oid al (api_clients), dan moet het e-mailadres kloppen.
 *
 * @return array<string, mixed>|null
 */
function resolveLoginRotatingApiClient(string $providedKey, array $payload, array $server, ?int $now = null): ?array
{
    $apiKey = strtolower(trim($providedKey));
    if ($apiKey === '' || preg_match('/^[a-f0-9]{64}$/', $apiKey) !== 1) {
        return null;
    }

    $oid = strtolower(trim((string) ($server['HTTP_X_USER_OID'] ?? ($payload['oid'] ?? ''))));
    $email = strtolower(trim((string) ($server['HTTP_X_USER_EMAIL'] ?? ($payload['user_email'] ?? ''))));
    if ($oid === '' || preg_match('/^[a-z0-9-]{8,128}$/', $oid) !== 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $now = $now ?? time();
    $todayKey = buildRotatingApiKeyForDate($oid, gmdate('d-m-Y', $now));
    $yesterdayKey = buildRotatingApiKeyForDate($oid, gmdate('d-m-Y', $now - 86400));
    if (!hash_equals($todayKey, $apiKey) && !hash_equals($yesterdayKey, $apiKey)) {
        return null;
    }

    $knownEmail = findKnownApiClientEmailForOid($oid);
    if ($knownEmail !== '' && !hash_equals($knownEmail, $email)) {
        return null;
    }

    return [
        'oid' => $oid,
        'api_key' => $apiKey,
        'email' => $email,
        'is_admin' => false,
        'kind' => 'login_rotating',
        'default_name' => '',
        'default_title' => '',
    ];
}

function findKnownApiClientEmailForOid(string $oid, ?string $directory = null): string
{
    $directory = $directory ?? (__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_clients');
    if (!is_dir($directory)) {
        return '';
    }

    foreach ((array) glob($directory . DIRECTORY_SEPARATOR . '*.json') as $file) {
        if (!is_string($file) || !is_file($file)) {
            continue;
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded) || strtolower(trim((string) ($decoded['oid'] ?? ''))) !== $oid) {
            continue;
        }
        $email = strtolower(trim((string) ($decoded['email'] ?? '')));
        if ($email !== '') {
            return $email;
        }
    }

    return '';
}

function isCreateTicketApiRequest(array $payload, array $server): bool
{
    return strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')) === 'POST'
        && trim((string) ($payload['action'] ?? '')) === '';
}

function getRequestBody(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    $payload = [];

    if (str_contains($contentType, 'application/json')) {
        $rawBody = file_get_contents('php://input');
        if (is_string($rawBody) && trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
    }

    if ($payload === [] && $_POST !== []) {
        $payload = $_POST;
    }

    return merge_api_request_payload($payload);
}

function merge_api_request_payload(array $payload): array
{
    $queryParams = [];
    $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    if ($queryString !== '') {
        parse_str($queryString, $queryParams);
    }

    foreach (['action', 'page_name', 'viewer_email', 'user_email', 'from_date', 'to_date', 'start_date', 'end_date', 'from', 'to', 'start', 'end'] as $key) {
        $currentValue = trim((string) ($payload[$key] ?? ''));
        $queryValue = trim((string) ($queryParams[$key] ?? ''));
        if ($currentValue === '' && $queryValue !== '') {
            $payload[$key] = $queryValue;
        }
    }

    return $payload;
}

function buildTicketPollApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    global $ictUsers;

    $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'index.php'));
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
    $ictAccess = resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true);
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin'])
        || !empty($ictAccess['is_full_ict_admin']) || !empty($ictAccess['is_limited_ict']);
    $isAdminPortal = !empty($payload['is_admin_portal']);
    $canManageTickets = $isAdminPortal && $userIsAdmin;
    $accessCategories = ($canManageTickets && !empty($ictAccess['is_limited_ict']))
        ? ($ictAccess['access_categories'] ?? [])
        : null;
    $csrfToken = (string) ($payload['csrf_token'] ?? '');
    $openTicketId = max(0, (int) ($payload['open_ticket_id'] ?? 0));
    $view = trim((string) ($payload['view'] ?? 'overview'));
    $browseMode = trim((string) ($payload['browse_mode'] ?? 'default'));
    if ($browseMode !== 'all_completed_public') {
        $browseMode = 'default';
    }
    if (!$canManageTickets && $view === 'all_tickets') {
        $browseMode = 'all_completed_public';
    }
    $currentLanguage = strtolower(trim((string) ($payload['current_language'] ?? 'nl')));
    if (!array_key_exists($currentLanguage, SUPPORTED_LANGUAGES)) {
        $currentLanguage = 'nl';
    }
    $assignedFilter = trim((string) ($payload['assigned_filter'] ?? ''));
    $searchQuery = trim((string) ($payload['search_query'] ?? ''));
    $statusFilters = array_values(array_filter(
        array_map('trim', (array) ($payload['status_filters'] ?? [])),
        static fn(string $status): bool => $status !== ''
    ));
    $activeCustomStatuses = $store->getActiveCustomStatuses($accessCategories);
    $statusFiltersSelected = array_values(array_filter(
        array_map('trim', (array) ($payload['status_filters_selected'] ?? [])),
        static fn(string $status): bool => isAllowedTicketStatusValue(
            $status,
            array_values(array_map(
                static fn(array $row): string => (string) ($row['display_label'] ?? ''),
                $activeCustomStatuses
            ))
        )
    ));
    $categoryFilters = array_values(array_filter(
        array_map('trim', (array) ($payload['category_filters'] ?? [])),
        static fn(string $category): bool => $category !== ''
    ));
    $categoryFiltersSelected = array_values(array_filter(
        array_map('trim', (array) ($payload['category_filters_selected'] ?? [])),
        static fn(string $category): bool => in_array($category, TICKET_CATEGORIES, true)
    ));
    $statusFilterRequestActive = !empty($payload['status_filter_active']);
    $categoryFilterRequestActive = !empty($payload['category_filter_active']);
    $lastSignature = trim((string) ($payload['last_signature'] ?? ''));
    $ticketPage = max(1, (int) ($payload['page'] ?? 1));
    $perPage = normalizeTicketsPerPage((int) ($payload['per_page'] ?? DEFAULT_TICKETS_PER_PAGE));

    $ticketTotalCount = $store->countTickets(
        $canManageTickets,
        $viewerEmail,
        $statusFilters,
        $assignedFilter,
        $categoryFilters,
        $searchQuery,
        $browseMode,
        $accessCategories
    );
    $ticketTotalPages = max(1, (int) ceil($ticketTotalCount / $perPage));
    if ($ticketPage > $ticketTotalPages) {
        $ticketPage = $ticketTotalPages;
    }
    $ticketPageOffset = ($ticketPage - 1) * $perPage;

    $sortRules = $canManageTickets ? loadTicketSortPreferences($viewerEmail) : null;
    $tickets = $store->getTickets(
        $canManageTickets,
        $viewerEmail,
        $statusFilters,
        $assignedFilter,
        $categoryFilters,
        $searchQuery,
        $browseMode,
        $perPage,
        $ticketPageOffset,
        $accessCategories,
        $sortRules
    );
    $tickets = array_map(
        fn(array $ticket): array => localizeTicketForViewer($ticket, $store, $currentLanguage, true),
        $tickets
    );
    $tickets = prependLinkedOpenTicketIfMissing(
        $store,
        $tickets,
        $openTicketId,
        $canManageTickets,
        $viewerEmail,
        $browseMode,
        shouldIncludeGhostMessages($canManageTickets, $isAdminPortal, $view),
        $accessCategories,
        $currentLanguage
    );
    $signature = buildTicketSnapshotSignature($tickets);
    if ($lastSignature !== '' && hash_equals($lastSignature, $signature)) {
        return [
            'success' => true,
            'signature' => $signature,
            'unchanged' => true,
        ];
    }
    $pollContext = [
        'currentPage' => $currentPage,
        'canManageTickets' => $canManageTickets,
        'userIsAdmin' => $userIsAdmin,
        'isAdminPortal' => $isAdminPortal,
        'ictUsers' => $ictUsersList,
        'store' => $store,
        'csrfToken' => $csrfToken,
        'openTicketId' => $openTicketId,
        'view' => $view,
        'isReadOnlyTicket' => $browseMode === 'all_completed_public',
        'viewerEmail' => $viewerEmail,
        'activeCustomStatuses' => $activeCustomStatuses,
        'recentCustomStatuses' => $canManageTickets ? getRecentCustomStatusesForUser($viewerEmail) : [],
        'includeGhostMessages' => shouldIncludeGhostMessages($canManageTickets, $isAdminPortal, $view),
        'showGhostToggle' => shouldIncludeGhostMessages($canManageTickets, $isAdminPortal, $view),
    ];

    $isAllTicketsView = $browseMode === 'all_completed_public';
    $paginationHtml = buildTicketPollPaginationHtml(
        $currentPage,
        $ticketPage,
        $ticketTotalPages,
        $statusFiltersSelected,
        $categoryFiltersSelected,
        $assignedFilter,
        $searchQuery,
        $isAllTicketsView ? 'all_tickets' : $view,
        $isAdminPortal,
        $statusFilterRequestActive,
        $categoryFilterRequestActive,
        $openTicketId
    );

    return [
        'success' => true,
        'signature' => $signature,
        'tickets' => buildTicketPollItemsFromTickets($store, $tickets, $pollContext, $currentLanguage),
        'is_empty' => $tickets === [],
        'empty_html' => '<div class="empty-state">' . ($isAdminPortal ? h(__('tickets.empty_admin')) : ($isAllTicketsView ? h(__('tickets.empty_all')) : ($view === 'my_tickets' ? h(__('tickets.empty_my')) : h(__('tickets.empty_user'))))) . '</div>',
        'page' => $ticketPage,
        'total_pages' => $ticketTotalPages,
        'total_count' => $ticketTotalCount,
        'pagination_html' => $paginationHtml,
    ];
}

function buildRelatedCompletedTicketsApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    $title = trim((string) ($payload['title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $currentLanguage = strtolower(trim((string) ($payload['current_language'] ?? 'nl')));
    if (!array_key_exists($currentLanguage, SUPPORTED_LANGUAGES)) {
        $currentLanguage = 'nl';
    }

    $matches = $store->searchRelatedCompletedPublicTickets($title, $description, 8);
    $tickets = [];
    foreach ($matches as $match) {
        $localized = localizeTicketForViewer($match, $store, $currentLanguage, true);
        $tickets[] = [
            'id' => (int) ($localized['id'] ?? $match['id'] ?? 0),
            'title' => (string) ($localized['title'] ?? $match['title'] ?? ''),
        ];
    }

    return [
        'success' => true,
        'tickets' => $tickets,
    ];
}

function buildUserProfileStatsApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    global $ictUsers;

    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
    $ictAccess = resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true);
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin'])
        || !empty($ictAccess['is_full_ict_admin']) || !empty($ictAccess['is_limited_ict']);
    $isAdminPortal = !empty($payload['is_admin_portal']);

    if (!$isAdminPortal || !$userIsAdmin) {
        return [
            'success' => false,
            'error' => 'forbidden',
        ];
    }

    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_email',
        ];
    }

    $stats = $store->getUserProfileStats($email);

    return [
        'success' => true,
        'email' => $stats['email'],
        'display_name' => formatUserDisplayName($stats['email']),
        'ticket_count' => $stats['ticket_count'],
        'open_ticket_count' => $stats['open_ticket_count'],
        'average_response_seconds' => $stats['average_response_seconds'],
        'average_response_label' => formatDurationSeconds($stats['average_response_seconds']),
        'average_wait_seconds' => $stats['average_wait_seconds'],
        'average_wait_label' => formatDurationSeconds($stats['average_wait_seconds']),
    ];
}

function buildRelatedTicketPreviewApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    global $ictUsers;

    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'index.php'));
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $csrfToken = (string) ($payload['csrf_token'] ?? '');
    $currentLanguage = strtolower(trim((string) ($payload['current_language'] ?? 'nl')));
    if (!array_key_exists($currentLanguage, SUPPORTED_LANGUAGES)) {
        $currentLanguage = 'nl';
    }

    $ticket = $store->getTicket($ticketId, false, $viewerEmail, 'all_completed_public', false, null);
    if (!is_array($ticket)) {
        return [
            'success' => false,
            'error' => 'ticket_not_found',
        ];
    }

    $ticket = localizeTicketForViewer($ticket, $store, $currentLanguage, true);
    $ticketDetail = localizeTicketDetailForViewer($ticket, $store, $currentLanguage, true);
    $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
    $cardHtml = renderTicketCardHtml($ticket, $ticketDetail, [
        'currentPage' => $currentPage,
        'canManageTickets' => false,
        'userIsAdmin' => false,
        'isAdminPortal' => false,
        'ictUsers' => $ictUsersList,
        'store' => $store,
        'csrfToken' => $csrfToken,
        'openTicketId' => $ticketId,
        'view' => 'all_tickets',
        'isReadOnlyTicket' => true,
        'viewerEmail' => $viewerEmail,
        'includeMessages' => true,
        'lazyMessages' => false,
        'includeGhostMessages' => false,
        'showGhostToggle' => false,
        'activeCustomStatuses' => [],
        'recentCustomStatuses' => [],
    ]);

    return [
        'success' => true,
        'ticket_id' => $ticketId,
        'card_html' => $cardHtml,
    ];
}

function buildOpenTicketDuplicateTipApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $title = trim((string) ($payload['title'] ?? ''));
    $match = $store->findSimilarOpenTicketForRequester($viewerEmail, $title, 80.0);

    return [
        'success' => true,
        'match' => $match,
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

function handleSetMessageReactionApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $sessionCookieName = session_name();
        if ($sessionCookieName !== '' && !empty($_COOKIE[$sessionCookieName])) {
            session_start(['read_and_close' => true]);
        }
    }

    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
            'error_code' => 'csrf',
        ];
    }

    $sessionEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    $viewerEmail = ($sessionEmail !== '' && filter_var($sessionEmail, FILTER_VALIDATE_EMAIL))
        ? $sessionEmail
        : strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    if ($viewerEmail === '' || !filter_var($viewerEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'viewer_missing',
            'error_code' => 'viewer_missing',
        ];
    }

    $ticketId = max(0, (int) ($payload['ticket_id'] ?? 0));
    $messageId = max(0, (int) ($payload['message_id'] ?? 0));
    if ($ticketId <= 0 || $messageId <= 0) {
        return [
            'success' => false,
            'error' => 'message_id_required',
            'error_code' => 'message_id_required',
        ];
    }
    if (!array_key_exists('value', $payload) || !is_numeric($payload['value'])) {
        return [
            'success' => false,
            'error' => 'invalid_reaction',
            'error_code' => 'invalid_reaction',
        ];
    }
    $value = (int) $payload['value'];
    if (!in_array($value, [-1, 0, 1], true)) {
        return [
            'success' => false,
            'error' => 'invalid_reaction',
            'error_code' => 'invalid_reaction',
        ];
    }

    $trusted = isTrustedApiRequester();
    $ictUsersList = is_array($GLOBALS['ictUsers'] ?? null) ? $GLOBALS['ictUsers'] : [];
    $ictAccess = resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true);
    $isLimitedIct = !empty($ictAccess['is_limited_ict']) && empty($ictAccess['is_full_ict_admin']);
    $isAdminViewer = $trusted
        || !empty($apiClient['is_admin'])
        || !empty($ictAccess['is_full_ict_admin'])
        || $isLimitedIct;
    $accessCategories = ($isLimitedIct && !$trusted && is_array($ictAccess['access_categories'] ?? null))
        ? $ictAccess['access_categories']
        : null;
    $ticket = $store->getTicket(
        $ticketId,
        $isAdminViewer,
        $viewerEmail,
        'default',
        $isAdminViewer,
        $accessCategories
    );
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    $message = $store->getTicketMessage($messageId);
    if ($message === null || (int) ($message['ticket_id'] ?? 0) !== $ticketId || (!empty($message['is_ghost']) && !$isAdminViewer)) {
        return [
            'success' => false,
            'error' => 'message_not_found',
            'error_code' => 'message_not_found',
        ];
    }

    $summary = $store->setMessageReaction($ticketId, $messageId, $viewerEmail, $value);
    if ($summary === null) {
        return [
            'success' => false,
            'error' => 'message_not_found',
            'error_code' => 'message_not_found',
        ];
    }

    return [
        'success' => true,
        'ticket_id' => $ticketId,
        'message_id' => $messageId,
        'value' => (int) ($summary['mine'] ?? 0),
        'plus' => (int) ($summary['plus'] ?? 0),
        'minus' => (int) ($summary['minus'] ?? 0),
        'likes' => (int) ($summary['likes'] ?? 0),
        'dislikes' => (int) ($summary['dislikes'] ?? 0),
        'plus_users' => array_values(is_array($summary['plus_users'] ?? null) ? $summary['plus_users'] : []),
        'minus_users' => array_values(is_array($summary['minus_users'] ?? null) ? $summary['minus_users'] : []),
    ];
}

function handleManageTicketParticipantsApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $trusted = isTrustedApiRequester();
    if ($viewerEmail === '' || !filter_var($viewerEmail, FILTER_VALIDATE_EMAIL)) {
        if (!$trusted) {
            return [
                'success' => false,
                'error' => __('flash.settings_admin_only'),
            ];
        }
        $viewerEmail = strtolower(trim((string) ($payload['viewer_email'] ?? '')));
    }

    $ictUsersList = is_array($GLOBALS['ictUsers'] ?? null) ? $GLOBALS['ictUsers'] : [];
    $ictAccess = $viewerEmail !== ''
        ? resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true)
        : [];
    $isLimitedIct = !empty($ictAccess['is_limited_ict']) && empty($ictAccess['is_full_ict_admin']);
    $isAdminViewer = $trusted
        || !empty($apiClient['is_admin'])
        || !empty($ictAccess['is_full_ict_admin'])
        || $isLimitedIct;
    $accessCategories = ($isLimitedIct && !$trusted && is_array($ictAccess['access_categories'] ?? null))
        ? $ictAccess['access_categories']
        : null;

    $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $ticket = $store->getTicket(
        $ticketId,
        $isAdminViewer,
        $viewerEmail !== '' ? $viewerEmail : 'ict@kvt.nl',
        'default',
        false,
        $accessCategories
    );
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $participantInput = trim((string) ($payload['participant_emails'] ?? ''));
    $removeParticipantEmailsRaw = $payload['remove_participant_emails'] ?? [];

    if ($operation === 'add') {
        $removeParticipantEmailsRaw = [];
    } elseif ($operation === 'remove') {
        $singleEmail = strtolower(trim((string) ($payload['participant_email'] ?? '')));
        $removeParticipantEmailsRaw = $singleEmail !== '' ? [$singleEmail] : [];
        $participantInput = '';
    } elseif ($operation !== 'apply') {
        return [
            'success' => false,
            'error' => __('flash.unknown_action'),
        ];
    }

    $invalidTokens = findInvalidEmailListTokens($participantInput);
    if ($invalidTokens !== []) {
        return [
            'success' => false,
            'error' => __('flash.invalid_email_list', implode(', ', $invalidTokens)),
        ];
    }

    $participantEmailsToAdd = parseEmailListInput($participantInput);

    $removeParticipantEmails = [];
    if (is_string($removeParticipantEmailsRaw)) {
        $removeParticipantEmails[] = strtolower(trim($removeParticipantEmailsRaw));
    } elseif (is_array($removeParticipantEmailsRaw)) {
        foreach ($removeParticipantEmailsRaw as $emailRaw) {
            $removeParticipantEmails[] = strtolower(trim((string) $emailRaw));
        }
    }
    $removeParticipantEmails = array_values(array_unique(array_filter(
        $removeParticipantEmails,
        static fn(string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
    )));

    if ($participantEmailsToAdd === [] && $removeParticipantEmails === []) {
        return [
            'success' => false,
            'error' => __('flash.ticket_participant_add_none'),
        ];
    }

    $participantsBefore = is_array($ticket['participant_emails'] ?? null) ? $ticket['participant_emails'] : [];
    $actorEmail = $viewerEmail;
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = strtolower(trim((string) ($ticket['assigned_email'] ?? $ticket['user_email'] ?? '')));
    }
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = 'ict@kvt.nl';
    }
    $participantsBeforeLookup = array_fill_keys(array_map('strtolower', $participantsBefore), true);

    $addedCount = 0;
    if ($participantEmailsToAdd !== []) {
        $addedCount = $store->addTicketParticipants($ticketId, $participantEmailsToAdd, $viewerEmail);
    }

    $removedCount = 0;
    foreach ($removeParticipantEmails as $removeEmail) {
        if ($store->removeTicketParticipant($ticketId, $removeEmail)) {
            $removedCount++;
        }
    }

    $updatedTicket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $participantsAfter = is_array($updatedTicket['participant_emails'] ?? null) ? $updatedTicket['participant_emails'] : [];
    if ($participantsAfter === []) {
        return [
            'success' => false,
            'error' => __('flash.ticket_participant_minimum'),
        ];
    }

    $newParticipants = array_values(array_filter(
        array_map('strtolower', $participantsAfter),
        static fn(string $email): bool => $email !== '' && !isset($participantsBeforeLookup[$email])
    ));
    $participantsAfterLookup = array_fill_keys(array_map('strtolower', $participantsAfter), true);
    $removedParticipants = array_values(array_filter(
        array_map('strtolower', $participantsBefore),
        static fn(string $email): bool => $email !== '' && !isset($participantsAfterLookup[$email])
    ));

    $participantChangeNotifiedViaUpdate = false;
    $participantChangeNote = buildParticipantChangeNote($newParticipants, $removedParticipants);
    if ($participantChangeNote !== '') {
        $store->addMessage($ticketId, $actorEmail, $isAdminViewer ? 'admin' : 'user', $participantChangeNote);
        $updatedTicket = $store->getTicket($ticketId, true, $actorEmail) ?? $updatedTicket;
        $participantsAfter = is_array($updatedTicket['participant_emails'] ?? null) ? $updatedTicket['participant_emails'] : $participantsAfter;

        $requesterRecipients = is_array($updatedTicket['participant_emails'] ?? null)
            ? $updatedTicket['participant_emails']
            : [(string) ($updatedTicket['user_email'] ?? '')];
        $reqLang = getUserMailLang((string) ($updatedTicket['user_email'] ?? ''));
        sendTicketNotification(
            $store,
            $GLOBALS['ictUsers'] ?? [],
            $requesterRecipients,
            __mail('email.subject_update', $reqLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_update', $participantChangeNote, false, $reqLang, __mail('email.intro_update_no_status', $reqLang)),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            null,
            $actorEmail
        );
        $participantChangeNotifiedViaUpdate = true;
    }

    foreach ($newParticipants as $newParticipantEmail) {
        if ($participantChangeNotifiedViaUpdate) {
            continue;
        }

        $participantLang = getUserMailLang($newParticipantEmail);
        sendTicketNotification(
            $store,
            $GLOBALS['ictUsers'] ?? [],
            [$newParticipantEmail],
            __mail('email.subject_participant_added', $participantLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_participant_added', '', false, $participantLang),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            null,
            $actorEmail
        );
    }

    if ($addedCount <= 0 && $removedCount <= 0) {
        if ($removeParticipantEmails !== [] && count($participantsAfter) <= 1) {
            return [
                'success' => false,
                'error' => __('flash.ticket_participant_minimum'),
            ];
        }

        return [
            'success' => false,
            'error' => __('flash.ticket_participant_add_none'),
        ];
    }

    $requesterSummary = buildRequesterSummary($participantsAfter, (string) ($updatedTicket['user_email'] ?? ''));
    return [
        'success' => true,
        'message' => __('flash.ticket_participants_saved'),
        'participant_emails' => array_values($participantsAfter),
        'requester_label' => (string) ($requesterSummary['label'] ?? ''),
        'requester_tooltip' => (string) ($requesterSummary['tooltip'] ?? ''),
        'requester_extra_count' => (int) ($requesterSummary['extra_count'] ?? 0),
        'creator_email' => strtolower(trim((string) ($updatedTicket['user_email'] ?? ''))),
    ];
}

function handleChangeTicketCategoryApiAction(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey = false): array
{
    global $ictUsers;

    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
    $ictAccess = resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true);
    $userIsAdmin = $hasValidServiceApiKey || !empty($apiClient['is_admin']) || !empty($payload['user_is_admin'])
        || !empty($ictAccess['is_full_ict_admin']) || !empty($ictAccess['is_limited_ict']);
    if (!$userIsAdmin && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $category = trim((string) ($payload['category'] ?? ''));
    $reassign = !empty($payload['reassign']);
    $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'admin.php'));
    $accessCategories = !empty($ictAccess['is_limited_ict'])
        ? ($ictAccess['access_categories'] ?? [])
        : null;

    if (!in_array($category, TICKET_CATEGORIES, true)) {
        return [
            'success' => false,
            'error' => __('flash.invalid_category'),
        ];
    }

    $ticket = $store->getTicket($ticketId, true, $viewerEmail, 'default', false, $accessCategories);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $losesAccess = $accessCategories !== null && !in_array($category, $accessCategories, true);
    if ($losesAccess) {
        $reassign = true;
    }

    try {
        $changeResult = $store->changeTicketCategory($ticketId, $category, $reassign);
    } catch (Throwable $exception) {
        return [
            'success' => false,
            'error' => $exception->getMessage(),
        ];
    }

    if (empty($changeResult['changed'])) {
        return [
            'success' => false,
            'error' => __('flash.ticket_category_unchanged'),
        ];
    }

    $actorEmail = $viewerEmail;
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = strtolower(trim((string) ($ticket['assigned_email'] ?? $ticket['user_email'] ?? '')));
    }
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = 'ict@kvt.nl';
    }

    $categoryChangeNote = buildCategoryChangeNote(
        (string) ($changeResult['old_category'] ?? ''),
        (string) ($changeResult['new_category'] ?? ''),
        !empty($changeResult['assignee_changed']),
        (string) ($changeResult['assigned_email'] ?? '')
    );
    $messageId = $store->addMessage($ticketId, $actorEmail, 'admin', $categoryChangeNote);

    // After an out-of-scope move, limited ICT may no longer read the ticket via access filters.
    $updatedTicket = $store->getTicket($ticketId, true, $actorEmail);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $requesterRecipients = is_array($updatedTicket['participant_emails'] ?? null)
        ? $updatedTicket['participant_emails']
        : [(string) ($updatedTicket['user_email'] ?? '')];
    $reqLang = getUserMailLang((string) ($updatedTicket['user_email'] ?? ''));
    sendTicketNotification(
        $store,
        $GLOBALS['ictUsers'] ?? [],
        $requesterRecipients,
        __mail('email.subject_update', $reqLang, $ticketId),
        buildNotificationBody($updatedTicket, 'email.intro_update', $categoryChangeNote, false, $reqLang, __mail('email.intro_update_no_status', $reqLang)),
        $actorEmail,
        (string) ($updatedTicket['category'] ?? ''),
        $ticketId,
        null,
        $actorEmail
    );

    if (!empty($changeResult['assignee_changed']) && trim((string) ($changeResult['assigned_email'] ?? '')) !== '') {
        $assigneeEmail = strtolower(trim((string) $changeResult['assigned_email']));
        $assigneeLang = getUserMailLang($assigneeEmail);
        sendTicketNotification(
            $store,
            $GLOBALS['ictUsers'] ?? [],
            [$assigneeEmail],
            __mail('email.subject_assigned', $assigneeLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_assigned', $categoryChangeNote, true, $assigneeLang),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            'assigned',
            $actorEmail
        );
    }

    $assignedEmail = strtolower(trim((string) ($updatedTicket['assigned_email'] ?? '')));
    $messageForRender = [
        'id' => $messageId,
        'sender_email' => $actorEmail,
        'sender_role' => 'admin',
        'message_text' => $categoryChangeNote,
        'message_text_raw' => $categoryChangeNote,
        'attachments' => [],
    ];

    return [
        'success' => true,
        'message' => __('flash.ticket_category_changed'),
        'ticket_id' => $ticketId,
        'category' => (string) ($updatedTicket['category'] ?? ''),
        'category_label' => translateCategory((string) ($updatedTicket['category'] ?? '')),
        'assigned_email' => $assignedEmail,
        'assigned_label' => $assignedEmail !== '' ? formatUserDisplayName($assignedEmail) : __('ticket.unassigned'),
        'assigned_color' => emailToHexColor($assignedEmail !== '' ? $assignedEmail : 'onbekend@kvt.nl'),
        'message_id' => $messageId,
        'message_html' => renderTicketMessageHtml($messageForRender, $currentPage, !empty($payload['is_admin_portal'])),
        'lost_access' => $losesAccess,
    ];
}

function handleChangeTicketTitleApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $title = trim((string) ($payload['title'] ?? ''));

    if ($title === '') {
        return [
            'success' => false,
            'error' => __('flash.ticket_title_required'),
        ];
    }

    $ticket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $currentTitle = trim((string) ($ticket['title'] ?? ''));
    if ($title === $currentTitle) {
        return [
            'success' => false,
            'error' => __('flash.ticket_title_unchanged'),
        ];
    }

    if (!$store->updateTicketTitle($ticketId, $title)) {
        return [
            'success' => false,
            'error' => __('flash.db_error_prefix'),
        ];
    }

    $store->deleteTextTranslationsForEntity('ticket_title', $ticketId);

    return [
        'success' => true,
        'message' => __('flash.ticket_title_changed'),
        'ticket_id' => $ticketId,
        'title' => $title,
    ];
}

function handleUpdateTicketPrivateApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    $isAdminPortal = !empty($payload['is_admin_portal']);
    $canManageTickets = $isAdminPortal && $userIsAdmin;
    if (!$canManageTickets && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $isPrivate = !empty($payload['is_private']);

    $ticket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $currentPrivate = !empty($ticket['is_private']);
    if ($isPrivate === $currentPrivate) {
        return [
            'success' => true,
            'ticket_id' => $ticketId,
            'is_private' => $isPrivate,
        ];
    }

    if (!$store->updateTicketPrivate($ticketId, $isPrivate)) {
        return [
            'success' => false,
            'error' => __('flash.db_error_prefix'),
        ];
    }

    return [
        'success' => true,
        'ticket_id' => $ticketId,
        'is_private' => $isPrivate,
    ];
}

function handleRequestAiAdviceApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    $isAdminPortal = !empty($payload['is_admin_portal']);
    $canManageTickets = $isAdminPortal && $userIsAdmin;
    if (!$canManageTickets && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $advicePrompt = trim((string) ($payload['advice_prompt'] ?? $payload['prompt'] ?? $payload['note'] ?? ''));
    if (function_exists('mb_substr')) {
        $advicePrompt = mb_substr($advicePrompt, 0, 4000);
    } else {
        $advicePrompt = substr($advicePrompt, 0, 4000);
    }

    $ticket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    if (!$store->isAiAdviceAvailable($ticketId)) {
        return [
            'success' => false,
            'error' => __('flash.ai_advice_unavailable'),
            'ai_advice_available' => false,
            'ai_advice_pending' => (int) ($ticket['ai_advice_pending'] ?? 0),
            'last_message_is_ai' => !empty($ticket['last_message_is_ai']),
        ];
    }

    if (!$store->markAiAdviceRequested($ticketId)) {
        return [
            'success' => false,
            'error' => __('flash.ai_advice_unavailable'),
            'ai_advice_available' => false,
        ];
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'GrokBot.php';
    $sent = GrokBot::notifyReEvaluateAndAdvise($store, $ticketId, $advicePrompt, null, [
        'actor_email' => $viewerEmail,
        'assigned_email' => strtolower(trim((string) ($ticket['assigned_email'] ?? ''))),
    ]);
    if (!$sent) {
        $store->clearAiAdvicePending($ticketId);
        return [
            'success' => false,
            'error' => __('flash.ai_advice_failed'),
            'ai_advice_available' => true,
        ];
    }

    $updated = $store->getTicket($ticketId, true, $viewerEmail);

    return [
        'success' => true,
        'message' => __('flash.ai_advice_sent'),
        'ticket_id' => $ticketId,
        'ai_advice_available' => false,
        'ai_advice_pending' => (int) ($updated['ai_advice_pending'] ?? TicketStore::AI_ADVICE_AWAITING_AI),
        'last_message_is_ai' => !empty($updated['last_message_is_ai']),
    ];
}

/**
 * @return array{allowed: bool, viewer_email: string, ict_access: array, error?: array}
 */
function resolveIctTicketMutationAccess(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey): array
{
    global $ictUsers;

    $viewerEmail = strtolower(trim((string) (
        $apiClient['email']
        ?? $payload['viewer_email']
        ?? $payload['user_email']
        ?? $payload['sender_email']
        ?? ''
    )));
    $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
    $ictAccess = resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true);
    $userIsAdmin = $hasValidServiceApiKey || !empty($apiClient['is_admin'])
        || !empty($ictAccess['is_full_ict_admin']) || !empty($ictAccess['is_limited_ict']);
    if (!$userIsAdmin && !isTrustedApiRequester()) {
        return [
            'allowed' => false,
            'viewer_email' => $viewerEmail,
            'ict_access' => $ictAccess,
            'error' => [
                'success' => false,
                'error' => __('flash.settings_admin_only'),
                'error_code' => 'forbidden',
            ],
        ];
    }

    return [
        'allowed' => true,
        'viewer_email' => $viewerEmail,
        'ict_access' => $ictAccess,
    ];
}

function resolveApiMutationActorEmail(array $ticket, string $viewerEmail): string
{
    $actorEmail = strtolower(trim($viewerEmail));
    if (filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        return $actorEmail;
    }

    $actorEmail = strtolower(trim((string) ($ticket['assigned_email'] ?? $ticket['user_email'] ?? '')));
    if (filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        return $actorEmail;
    }

    return 'ict@kvt.nl';
}

function parseApiTicketId(array $payload): int
{
    return max(0, (int) ($payload['ticket_id'] ?? $payload['id'] ?? 0));
}

function payloadRequestsStatusChange(array $payload): bool
{
    return array_key_exists('status', $payload) || array_key_exists('ticket_status', $payload);
}

function payloadRequestsAssigneeChange(array $payload): bool
{
    return array_key_exists('assigned_email', $payload)
        || array_key_exists('assignee', $payload)
        || array_key_exists('assigned', $payload);
}

function readApiStatusValue(array $payload): string
{
    return trim((string) ($payload['status'] ?? $payload['ticket_status'] ?? ''));
}

function readApiAssigneeEmail(array $payload): string
{
    return strtolower(trim((string) (
        $payload['assigned_email']
        ?? $payload['assignee']
        ?? $payload['assigned']
        ?? ''
    )));
}

/**
 * @return array<string, mixed>|null
 */
function loadTicketForIctMutation(
    TicketStore $store,
    int $ticketId,
    string $viewerEmail,
    array $ictAccess
): ?array {
    $accessCategories = !empty($ictAccess['is_limited_ict'])
        ? ($ictAccess['access_categories'] ?? [])
        : null;

    return $store->getTicket($ticketId, true, $viewerEmail, 'default', false, $accessCategories);
}

function persistTicketFieldUpdate(TicketStore $store, array $ticket, array $overrides): void
{
    $ticketId = (int) ($ticket['id'] ?? 0);
    $status = (string) ($overrides['status'] ?? ($ticket['status'] ?? ''));
    $assignedRaw = array_key_exists('assigned_email', $overrides)
        ? $overrides['assigned_email']
        : ($ticket['assigned_email'] ?? null);
    $assignedEmail = $assignedRaw !== null ? strtolower(trim((string) $assignedRaw)) : '';
    $dueDate = array_key_exists('due_date', $overrides)
        ? $overrides['due_date']
        : normalizeDueDateInput((string) ($ticket['due_date'] ?? ''));
    if (!is_string($dueDate) || $dueDate === '') {
        $dueDate = null;
    }

    $priority = (int) ($overrides['priority'] ?? ($ticket['priority'] ?? 0));
    if ($dueDate !== null && strtolower($status) !== 'afgehandeld' && !array_key_exists('priority', $overrides)) {
        $priority = getPriorityFromDueDate($dueDate);
    }

    $store->updateTicket(
        $ticketId,
        $status,
        $assignedEmail !== '' ? $assignedEmail : null,
        $priority,
        $dueDate
    );
}

function sendTicketMutationApiJson(array $response): void
{
    $statusCode = 200;
    if (empty($response['success'])) {
        $errorCode = (string) ($response['error_code'] ?? '');
        $statusCode = match ($errorCode) {
            'forbidden' => 403,
            'ticket_not_found', 'message_not_found' => 404,
            default => 422,
        };
    }
    sendJson($statusCode, $response);
}

function notifyTicketFieldChangeViaApi(
    TicketStore $store,
    array $updatedTicket,
    int $ticketId,
    string $actorEmail,
    string $visibleNote,
    bool $statusChanged,
    bool $assigneeChanged,
    string $newAssignee
): void {
    $ictUsersList = is_array($GLOBALS['ictUsers'] ?? null) ? $GLOBALS['ictUsers'] : [];
    if ($statusChanged || $assigneeChanged || $visibleNote !== '') {
        $requesterRecipients = is_array($updatedTicket['participant_emails'] ?? null)
            ? $updatedTicket['participant_emails']
            : [(string) ($updatedTicket['user_email'] ?? '')];
        $reqLang = getUserMailLang((string) ($updatedTicket['user_email'] ?? ''));
        $updateIntroSuffix = $statusChanged
            ? __mail('email.intro_update_status', $reqLang)
            : __mail('email.intro_update_no_status', $reqLang);
        sendTicketNotification(
            $store,
            $ictUsersList,
            $requesterRecipients,
            __mail('email.subject_update', $reqLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_update', $visibleNote, false, $reqLang, $updateIntroSuffix),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            null,
            $actorEmail,
            requesterUpdateMailHasHighImportance(
                $statusChanged,
                (string) ($updatedTicket['status'] ?? '')
            )
        );
    }

    if ($assigneeChanged && $newAssignee !== '') {
        $assigneeLang = getUserMailLang($newAssignee);
        sendTicketNotification(
            $store,
            $ictUsersList,
            [$newAssignee],
            __mail('email.subject_assigned', $assigneeLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_assigned', $visibleNote, true, $assigneeLang),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            'assigned',
            $actorEmail
        );
    }
}

function handleChangeTicketStatusApiAction(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey = false): array
{
    $access = resolveIctTicketMutationAccess($store, $payload, $apiClient, $hasValidServiceApiKey);
    if (empty($access['allowed'])) {
        return $access['error'];
    }

    $ticketId = parseApiTicketId($payload);
    if ($ticketId <= 0) {
        return [
            'success' => false,
            'error' => 'ticket_id_required',
            'error_code' => 'ticket_id_required',
        ];
    }

    $viewerEmail = (string) $access['viewer_email'];
    $ticket = loadTicketForIctMutation($store, $ticketId, $viewerEmail, $access['ict_access']);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    $actorEmail = resolveApiMutationActorEmail($ticket, $viewerEmail);
    $requestedStatus = readApiStatusValue($payload);
    $resolvedStatus = resolveTicketStatusValue($requestedStatus, $store, $actorEmail);
    if ($resolvedStatus === null) {
        return [
            'success' => false,
            'error' => __('flash.invalid_status'),
            'error_code' => 'invalid_status',
        ];
    }

    $currentStatus = (string) ($ticket['status'] ?? '');
    if ($resolvedStatus === $currentStatus) {
        return [
            'success' => true,
            'unchanged' => true,
            'message' => __('flash.ticket_status_changed'),
            'ticket_id' => $ticketId,
            'status' => $resolvedStatus,
            'status_label' => translateStatus($resolvedStatus),
            'status_color' => getStatusColor($resolvedStatus),
            'resolved_at' => $ticket['resolved_at'] ?? null,
        ];
    }

    if (matchBuiltInTicketStatus($resolvedStatus) === null) {
        rememberCustomTicketStatusForActor($store, $actorEmail, $resolvedStatus);
    }

    persistTicketFieldUpdate($store, $ticket, ['status' => $resolvedStatus]);

    $statusChangeNote = buildStatusChangeNote($resolvedStatus, $actorEmail);
    $messageId = $store->addMessage($ticketId, $actorEmail, 'admin', $statusChangeNote);
    $updatedTicket = $store->getTicket($ticketId, true, $actorEmail);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    notifyTicketFieldChangeViaApi(
        $store,
        $updatedTicket,
        $ticketId,
        $actorEmail,
        $statusChangeNote,
        true,
        false,
        ''
    );

    $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'admin.php'));
    $messageForRender = [
        'id' => $messageId,
        'sender_email' => $actorEmail,
        'sender_role' => 'admin',
        'message_text' => $statusChangeNote,
        'message_text_raw' => $statusChangeNote,
        'attachments' => [],
    ];

    $response = [
        'success' => true,
        'unchanged' => false,
        'message' => __('flash.ticket_status_changed'),
        'ticket_id' => $ticketId,
        'status' => (string) ($updatedTicket['status'] ?? $resolvedStatus),
        'status_label' => translateStatus((string) ($updatedTicket['status'] ?? $resolvedStatus)),
        'status_color' => getStatusColor((string) ($updatedTicket['status'] ?? $resolvedStatus)),
        'resolved_at' => $updatedTicket['resolved_at'] ?? null,
        'message_id' => $messageId,
        'message_html' => renderTicketMessageHtml($messageForRender, $currentPage, !empty($payload['is_admin_portal'])),
    ];

    if ($store->hasRecentApiTicketEvent($ticketId, 'message', apiStatusMessagePairWindowSeconds())) {
        $statusHint = apiSeparateStatusChangeHint();
        appendApiResponseHint($response, $statusHint['hint'], $statusHint['explanation']);
    }
    $store->recordApiTicketEvent($ticketId, 'status');

    return $response;
}

function handlePublishGhostMessageApiAction(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey = false): array
{
    $access = resolveIctTicketMutationAccess($store, $payload, $apiClient, $hasValidServiceApiKey);
    if (empty($access['allowed'])) {
        return $access['error'];
    }

    $messageId = max(0, (int) ($payload['message_id'] ?? 0));
    if ($messageId <= 0) {
        return [
            'success' => false,
            'error' => 'message_id_required',
            'error_code' => 'message_id_required',
        ];
    }

    $message = $store->getTicketMessage($messageId);
    if ($message === null) {
        return [
            'success' => false,
            'error' => 'message_not_found',
            'error_code' => 'message_not_found',
        ];
    }
    if (empty($message['is_ghost'])) {
        return [
            'success' => false,
            'error' => 'not_ghost',
            'error_code' => 'not_ghost',
        ];
    }

    $ticketId = (int) ($message['ticket_id'] ?? 0);
    $viewerEmail = (string) $access['viewer_email'];
    $ticket = loadTicketForIctMutation($store, $ticketId, $viewerEmail, $access['ict_access']);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    $replacementText = null;
    if (array_key_exists('message_text', $payload) || array_key_exists('message', $payload)) {
        $rawReplacement = array_key_exists('message_text', $payload)
            ? $payload['message_text']
            : ($payload['message'] ?? '');
        if (is_array($rawReplacement) || is_object($rawReplacement)) {
            $rawReplacement = '';
        }
        $replacementText = str_replace(["\r\n", "\r"], "\n", (string) $rawReplacement);
    }

    $published = $store->publishGhostMessage($messageId, $replacementText);
    if ($published === null) {
        return [
            'success' => false,
            'error' => 'not_ghost',
            'error_code' => 'not_ghost',
        ];
    }

    $actorEmail = resolveApiMutationActorEmail($ticket, $viewerEmail);
    $updatedTicket = $store->getTicket($ticketId, true, $actorEmail, 'default', true);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    $storedText = (string) ($published['message_text'] ?? '');
    $messageText = trim($storedText);
    $hasAttachments = $store->messageHasAttachments($messageId);
    if ($messageText !== '' || $hasAttachments) {
        $ictUsersList = is_array($GLOBALS['ictUsers'] ?? null) ? $GLOBALS['ictUsers'] : [];
        $requesterRecipients = is_array($updatedTicket['participant_emails'] ?? null)
            ? $updatedTicket['participant_emails']
            : [(string) ($updatedTicket['user_email'] ?? '')];
        $reqLang = getUserMailLang((string) ($updatedTicket['user_email'] ?? ''));
        sendTicketNotification(
            $store,
            $ictUsersList,
            $requesterRecipients,
            __mail('email.subject_update', $reqLang, $ticketId),
            buildNotificationBody(
                $updatedTicket,
                'email.intro_update',
                $storedText,
                false,
                $reqLang,
                __mail('email.intro_update_no_status', $reqLang)
            ),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            null,
            $actorEmail,
            false
        );
    }

    $messageHtml = '';
    foreach (($updatedTicket['messages'] ?? []) as $ticketMessage) {
        if ((int) ($ticketMessage['id'] ?? 0) !== $messageId) {
            continue;
        }
        $attachments = is_array($ticketMessage['attachments'] ?? null) ? $ticketMessage['attachments'] : [];
        $messageHtml = formatTicketMessageText($storedText, $messageId, $attachments);
        break;
    }

    return [
        'success' => true,
        'ticket_id' => $ticketId,
        'message_id' => $messageId,
        'is_ghost' => false,
        'notified' => $messageText !== '' || $hasAttachments,
        'message_text' => $storedText,
        'message_html' => $messageHtml,
    ];
}

function handleChangeTicketAssigneeApiAction(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey = false): array
{
    $access = resolveIctTicketMutationAccess($store, $payload, $apiClient, $hasValidServiceApiKey);
    if (empty($access['allowed'])) {
        return $access['error'];
    }

    $ticketId = parseApiTicketId($payload);
    if ($ticketId <= 0) {
        return [
            'success' => false,
            'error' => 'ticket_id_required',
            'error_code' => 'ticket_id_required',
        ];
    }

    $viewerEmail = (string) $access['viewer_email'];
    $ticket = loadTicketForIctMutation($store, $ticketId, $viewerEmail, $access['ict_access']);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    $actorEmail = resolveApiMutationActorEmail($ticket, $viewerEmail);
    $requestedAssignee = readApiAssigneeEmail($payload);
    $currentAssignee = strtolower(trim((string) ($ticket['assigned_email'] ?? '')));
    if ($requestedAssignee === $currentAssignee) {
        $assignedLabel = $requestedAssignee !== '' ? formatUserDisplayName($requestedAssignee) : __('ticket.unassigned');
        return [
            'success' => true,
            'unchanged' => true,
            'message' => __('flash.ticket_assignee_changed'),
            'ticket_id' => $ticketId,
            'assigned_email' => $requestedAssignee,
            'assigned_label' => $assignedLabel,
            'assigned_color' => emailToHexColor($requestedAssignee !== '' ? $requestedAssignee : 'onbekend@kvt.nl'),
        ];
    }

    $assigneeError = validateTicketAssigneeChange($store, $ticket, $requestedAssignee, $actorEmail);
    if ($assigneeError !== null) {
        return [
            'success' => false,
            'error' => $assigneeError['error'],
            'error_code' => $assigneeError['error_code'],
        ];
    }

    persistTicketFieldUpdate($store, $ticket, ['assigned_email' => $requestedAssignee]);
    $updatedTicket = $store->getTicket($ticketId, true, $actorEmail);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    $assignedEmail = strtolower(trim((string) ($updatedTicket['assigned_email'] ?? '')));
    $assigneeChanged = $assignedEmail !== $currentAssignee;
    if ($assigneeChanged) {
        notifyTicketFieldChangeViaApi(
            $store,
            $updatedTicket,
            $ticketId,
            $actorEmail,
            '',
            false,
            true,
            $assignedEmail
        );
    }

    return [
        'success' => true,
        'unchanged' => !$assigneeChanged,
        'message' => __('flash.ticket_assignee_changed'),
        'ticket_id' => $ticketId,
        'assigned_email' => $assignedEmail,
        'assigned_label' => $assignedEmail !== '' ? formatUserDisplayName($assignedEmail) : __('ticket.unassigned'),
        'assigned_color' => emailToHexColor($assignedEmail !== '' ? $assignedEmail : 'onbekend@kvt.nl'),
    ];
}

function handleChangeTicketPriorityApiAction(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey = false): array
{
    $access = resolveIctTicketMutationAccess($store, $payload, $apiClient, $hasValidServiceApiKey);
    if (empty($access['allowed'])) {
        return $access['error'];
    }

    $ticketId = parseApiTicketId($payload);
    if ($ticketId <= 0) {
        return [
            'success' => false,
            'error' => 'ticket_id_required',
            'error_code' => 'ticket_id_required',
        ];
    }

    $viewerEmail = (string) $access['viewer_email'];
    $ticket = loadTicketForIctMutation($store, $ticketId, $viewerEmail, $access['ict_access']);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    if (normalizeDueDateInput((string) ($ticket['due_date'] ?? '')) !== null) {
        return [
            'success' => false,
            'error' => __('flash.priority_follows_due_date'),
            'error_code' => 'priority_follows_due_date',
        ];
    }

    if (!array_key_exists('priority', $payload)) {
        return [
            'success' => false,
            'error' => __('flash.invalid_priority'),
            'error_code' => 'invalid_priority',
        ];
    }

    $priorityValue = $payload['priority'];
    $priorityIsValid = (is_int($priorityValue) && $priorityValue >= 0 && $priorityValue <= 2)
        || (is_string($priorityValue) && preg_match('/^[0-2]$/D', $priorityValue) === 1);
    if (!$priorityIsValid) {
        return [
            'success' => false,
            'error' => __('flash.invalid_priority'),
            'error_code' => 'invalid_priority',
        ];
    }
    $requestedPriority = (int) $priorityValue;

    $currentPriority = (int) ($ticket['priority'] ?? 0);
    if ($requestedPriority === $currentPriority) {
        return [
            'success' => true,
            'unchanged' => true,
            'message' => __('flash.ticket_priority_changed'),
            'ticket_id' => $ticketId,
            'priority' => $requestedPriority,
            'priority_label' => __('ticket.priority_' . $requestedPriority),
        ];
    }

    persistTicketFieldUpdate($store, $ticket, ['priority' => $requestedPriority]);
    $updatedTicket = $store->getTicket($ticketId, true, resolveApiMutationActorEmail($ticket, $viewerEmail));
    $priority = (int) ($updatedTicket['priority'] ?? $requestedPriority);

    return [
        'success' => true,
        'unchanged' => false,
        'message' => __('flash.ticket_priority_changed'),
        'ticket_id' => $ticketId,
        'priority' => $priority,
        'priority_label' => __('ticket.priority_' . $priority),
    ];
}

function handleChangeTicketDueDateApiAction(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey = false): array
{
    $access = resolveIctTicketMutationAccess($store, $payload, $apiClient, $hasValidServiceApiKey);
    if (empty($access['allowed'])) {
        return $access['error'];
    }

    $ticketId = parseApiTicketId($payload);
    if ($ticketId <= 0) {
        return [
            'success' => false,
            'error' => 'ticket_id_required',
            'error_code' => 'ticket_id_required',
        ];
    }

    $viewerEmail = (string) $access['viewer_email'];
    $ticket = loadTicketForIctMutation($store, $ticketId, $viewerEmail, $access['ict_access']);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
            'error_code' => 'ticket_not_found',
        ];
    }

    $requestedDueDate = normalizeDueDateInput((string) ($payload['due_date'] ?? $payload['due'] ?? ''));
    if ($requestedDueDate === null) {
        return [
            'success' => false,
            'error' => __('flash.template_due_date_required'),
            'error_code' => 'invalid_due_date',
        ];
    }

    $currentDueDate = normalizeDueDateInput((string) ($ticket['due_date'] ?? ''));
    if ($requestedDueDate === $currentDueDate) {
        return [
            'success' => true,
            'unchanged' => true,
            'message' => __('flash.ticket_due_date_changed'),
            'ticket_id' => $ticketId,
            'due_date' => $requestedDueDate,
            'priority' => (int) ($ticket['priority'] ?? 0),
        ];
    }

    persistTicketFieldUpdate($store, $ticket, ['due_date' => $requestedDueDate]);
    $updatedTicket = $store->getTicket($ticketId, true, resolveApiMutationActorEmail($ticket, $viewerEmail));
    $dueDate = normalizeDueDateInput((string) ($updatedTicket['due_date'] ?? $requestedDueDate));

    return [
        'success' => true,
        'unchanged' => false,
        'message' => __('flash.ticket_due_date_changed'),
        'ticket_id' => $ticketId,
        'due_date' => $dueDate,
        'priority' => (int) ($updatedTicket['priority'] ?? 0),
    ];
}

function isApiTruthy(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }

    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function buildTicketLookupsApiPayload(string $action = 'ticket_lookups'): array
{
    $normalized = strtolower(trim($action));
    $payload = [
        'success' => true,
    ];
    if ($normalized !== 'statuses') {
        $payload['categories'] = array_values(TICKET_CATEGORIES);
    }
    if ($normalized !== 'categories') {
        $payload['statuses'] = array_values(TICKET_STATUSES);
    }

    return $payload;
}

function apiCallerSuppliedIdentityField(array $payload, array $keys): bool
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }
        if (trim((string) $payload[$key]) !== '') {
            return true;
        }
    }

    return false;
}

function appendApiResponseHint(array &$response, string $hint, string $explanation): void
{
    if (!isset($response['hints']) || !is_array($response['hints'])) {
        $response['hints'] = [];
    }

    $response['hints'][] = [
        'hint' => $hint,
        'explanation' => $explanation,
    ];
}

/**
 * @return array{hint: string, explanation: string}
 */
function apiMissingIdentityHint(): array
{
    return [
        'hint' => 'Geef username, title en email expliciet mee.',
        'explanation' => 'Stuur bij add_ticket_message (JSON of form) de velden sender_email (email), sender_name (username) en sender_title (title). Voorbeeld: {"action":"add_ticket_message","ticket_id":123,"message":"Tekst","sender_email":"naam@kvt.nl","sender_name":"Naam","sender_title":"ICT"}.',
    ];
}

/**
 * @return array{hint: string, explanation: string}
 */
function apiSeparateStatusChangeHint(): array
{
    return [
        'hint' => 'Met voorkeur je statuswijziging in dezelfde POST als je bericht plaatsen',
        'explanation' => 'Zet status (of ticket_status) in dezelfde POST als het bericht: action add_ticket_message, met ticket_id (of id), message (of message_text) en status. Voorbeeld: {"action":"add_ticket_message","ticket_id":123,"message":"Tekst","status":"in behandeling"}.',
    ];
}

function apiStatusMessagePairWindowSeconds(): int
{
    return 60;
}

function apiMessageAttachmentMaxCount(): int
{
    return 10;
}

function apiMessageAttachmentMaxBytes(): int
{
    return min(10 * 1024 * 1024, (int) MAX_ATTACHMENT_BYTES);
}

function apiMessageAttachmentMaxTotalBytes(): int
{
    return 40 * 1024 * 1024;
}

/**
 * Toegestane extensies → toegestane (door finfo herkende) MIME-types.
 * Bewust zonder svg/html/scripts: die worden via de directe upload-URL getoond.
 *
 * @return array<string, list<string>>
 */
function apiMessageAttachmentAllowedTypes(): array
{
    $officeZip = ['application/zip', 'application/octet-stream'];

    return [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'log' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $officeZip),
        'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $officeZip),
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $officeZip),
    ];
}

function apiMessageAttachmentHint(): array
{
    return [
        'hint' => 'Stuur bijlagen als attachments: lijst van {filename, mime, data_base64}; zet afbeeldingen inline met {{attachment:0}} in message of "inline": true.',
        'explanation' => 'Max ' . apiMessageAttachmentMaxCount() . ' bestanden, max ' . (int) round(apiMessageAttachmentMaxBytes() / 1048576) . ' MB per bestand. Toegestaan: '
            . implode(', ', array_keys(apiMessageAttachmentAllowedTypes()))
            . '. De echte inhoud moet bij de extensie passen. Voorbeeld: {"action":"add_ticket_message","ticket_id":123,"message":"Zie screenshot:\n{{attachment:0}}","attachments":[{"filename":"retourlijst.png","mime":"image/png","data_base64":"iVBORw0KGgo..."}]}.',
    ];
}

/**
 * Bestandsnaam voor weergave (original_name). Geen paden, geen besturingstekens,
 * geen [ of ] (die breken de [[attachment:…]]-marker).
 */
function sanitizeApiAttachmentFilename(string $filename): string
{
    $name = str_replace('\\', '/', $filename);
    $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
    $segments = array_values(array_filter(explode('/', $name), static fn(string $segment): bool => trim($segment) !== ''));
    $name = $segments !== [] ? (string) end($segments) : '';
    $name = (string) preg_replace('/[<>:"|?*\[\]{}]+/u', '', $name);
    $name = (string) preg_replace('/\s+/u', ' ', $name);
    $name = trim($name, " .\t");

    if ($name === '' || $name === '..' || $name === '.') {
        return '';
    }

    if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 120) {
        $extension = (string) pathinfo($name, PATHINFO_EXTENSION);
        $base = (string) pathinfo($name, PATHINFO_FILENAME);
        $name = mb_substr($base, 0, 110, 'UTF-8') . ($extension !== '' ? '.' . mb_substr($extension, 0, 8, 'UTF-8') : '');
    } elseif (strlen($name) > 120) {
        $name = substr($name, 0, 120);
    }

    return $name;
}

function apiAttachmentError(string $code, string $message, ?int $index = null, string $filename = ''): array
{
    $response = [
        'success' => false,
        'error' => $code,
        'error_code' => $code,
        'error_message' => $message,
    ];
    if ($index !== null) {
        $response['attachment_index'] = $index;
    }
    if ($filename !== '') {
        $response['attachment_filename'] = $filename;
    }
    $hint = apiMessageAttachmentHint();
    appendApiResponseHint($response, $hint['hint'], $hint['explanation']);

    return $response;
}

function cleanupApiAttachmentTempFiles(array $files): void
{
    foreach ($files as $file) {
        if (!empty($file['api_temp']) && is_string($file['tmp_name'] ?? null) && is_file($file['tmp_name'])) {
            @unlink($file['tmp_name']);
        }
    }
}

/**
 * Leest de bijlagen uit het verzoek en valideert ze volledig vóórdat er iets wordt opgeslagen.
 * Bronnen: payload['attachments'] (array of JSON-string) en multipart-uploads attachments[] ($_FILES).
 *
 * Elk resultaat-bestand heeft het formaat van normalizeUploadedFiles() (name, type, tmp_name, error, size),
 * zodat TicketStore::addMessage() het exact zo opslaat als een UI-upload.
 *
 * @return array{ok: bool, files: list<array<string, mixed>>, inline: list<bool>, aliases: list<list<string>>, error?: array}
 */
function readApiMessageAttachments(array $payload, ?array $uploadedFiles = null): array
{
    $result = ['ok' => true, 'files' => [], 'inline' => [], 'aliases' => []];

    $raw = $payload['attachments'] ?? null;
    if (is_string($raw)) {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            $raw = null;
        } else {
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'files' => [], 'inline' => [], 'aliases' => [], 'error' => apiAttachmentError('invalid_attachments', 'attachments moet een lijst van {filename, mime, data_base64} zijn.')];
            }
            $raw = $decoded;
        }
    }
    if ($raw !== null && !is_array($raw)) {
        return ['ok' => false, 'files' => [], 'inline' => [], 'aliases' => [], 'error' => apiAttachmentError('invalid_attachments', 'attachments moet een lijst van {filename, mime, data_base64} zijn.')];
    }
    if (is_array($raw) && $raw !== [] && (isset($raw['data_base64']) || isset($raw['filename']))) {
        $raw = [$raw];
    }
    $entries = is_array($raw) ? array_values($raw) : [];

    if ($uploadedFiles === null) {
        $uploadedFiles = isset($_FILES['attachments']) && function_exists('normalizeUploadedFiles')
            ? normalizeUploadedFiles('attachments')
            : [];
    }

    $total = count($entries) + count($uploadedFiles);
    if ($total === 0) {
        return $result;
    }
    if ($total > apiMessageAttachmentMaxCount()) {
        return ['ok' => false, 'files' => [], 'inline' => [], 'aliases' => [], 'error' => apiAttachmentError('too_many_attachments', 'Maximaal ' . apiMessageAttachmentMaxCount() . ' bijlagen per bericht.')];
    }

    $maxBytes = apiMessageAttachmentMaxBytes();
    $allowed = apiMessageAttachmentAllowedTypes();
    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    if (!$finfo instanceof finfo) {
        return ['ok' => false, 'files' => [], 'inline' => [], 'aliases' => [], 'error' => apiAttachmentError('attachment_type_check_unavailable', 'Bestandstype kan niet worden gecontroleerd (finfo ontbreekt).')];
    }

    $files = [];
    $usedNames = [];
    $totalBytes = 0;
    $fail = static function (array $error) use (&$files): array {
        cleanupApiAttachmentTempFiles($files);
        return ['ok' => false, 'files' => [], 'inline' => [], 'aliases' => [], 'error' => $error];
    };

    $candidates = [];
    foreach ($entries as $entry) {
        $candidates[] = ['kind' => 'base64', 'entry' => $entry];
    }
    foreach ($uploadedFiles as $upload) {
        $candidates[] = ['kind' => 'upload', 'entry' => $upload];
    }

    foreach ($candidates as $index => $candidate) {
        $entry = $candidate['entry'];
        if (!is_array($entry)) {
            return $fail(apiAttachmentError('invalid_attachment', 'Bijlage ' . $index . ' is geen object met filename, mime en data_base64.', $index));
        }

        $givenName = (string) ($candidate['kind'] === 'upload'
            ? ($entry['name'] ?? '')
            : ($entry['filename'] ?? $entry['name'] ?? ''));
        $declaredMime = strtolower(trim((string) ($candidate['kind'] === 'upload'
            ? ($entry['type'] ?? '')
            : ($entry['mime'] ?? $entry['mime_type'] ?? $entry['type'] ?? ''))));
        $name = sanitizeApiAttachmentFilename($givenName);
        if ($name === '') {
            return $fail(apiAttachmentError('invalid_attachment_filename', 'Bijlage ' . $index . ' heeft geen geldige bestandsnaam.', $index));
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!isset($allowed[$extension])) {
            return $fail(apiAttachmentError('attachment_type_not_allowed', 'Bestandstype .' . $extension . ' is niet toegestaan voor ' . $name . '.', $index, $name));
        }

        if ($candidate['kind'] === 'upload') {
            $uploadError = (int) ($entry['error'] ?? UPLOAD_ERR_OK);
            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                return $fail(apiAttachmentError('attachment_too_large', $name . ' is groter dan toegestaan.', $index, $name));
            }
            $tmpName = (string) ($entry['tmp_name'] ?? '');
            if ($uploadError !== UPLOAD_ERR_OK || $tmpName === '' || !is_file($tmpName)) {
                return $fail(apiAttachmentError('attachment_upload_error', $name . ' kon niet worden geüpload.', $index, $name));
            }
            $size = (int) filesize($tmpName);
            $isApiTemp = false;
        } else {
            $data = $entry['data_base64'] ?? $entry['data'] ?? $entry['content_base64'] ?? null;
            if (!is_string($data) || trim($data) === '') {
                return $fail(apiAttachmentError('attachment_data_required', 'Bijlage ' . $name . ' mist data_base64.', $index, $name));
            }
            $data = trim($data);
            if (preg_match('/^data:([^;,]*)(;[^,]*)?,/i', $data, $dataUrl) === 1) {
                if ($declaredMime === '' && trim($dataUrl[1]) !== '') {
                    $declaredMime = strtolower(trim($dataUrl[1]));
                }
                $data = substr($data, strlen($dataUrl[0]));
            }
            $data = (string) preg_replace('/\s+/', '', $data);
            if (strlen($data) > (int) (ceil($maxBytes / 3) * 4) + 4) {
                return $fail(apiAttachmentError('attachment_too_large', $name . ' is groter dan ' . (int) round($maxBytes / 1048576) . ' MB.', $index, $name));
            }
            $binary = base64_decode(strtr($data, '-_', '+/'), true);
            if (!is_string($binary) || $binary === '') {
                return $fail(apiAttachmentError('invalid_attachment_data', 'data_base64 van ' . $name . ' is geen geldige base64.', $index, $name));
            }
            $size = strlen($binary);
            if ($size > $maxBytes) {
                return $fail(apiAttachmentError('attachment_too_large', $name . ' is groter dan ' . (int) round($maxBytes / 1048576) . ' MB.', $index, $name));
            }
            $tmpName = tempnam(sys_get_temp_dir(), 'asc_api_');
            if ($tmpName === false || file_put_contents($tmpName, $binary) !== $size) {
                if (is_string($tmpName)) {
                    @unlink($tmpName);
                }
                return $fail(apiAttachmentError('attachment_store_failed', $name . ' kon niet tijdelijk worden opgeslagen.', $index, $name));
            }
            // Zelfde rechten als move_uploaded_file() bij een UI-upload.
            @chmod($tmpName, 0666 & ~umask());
            unset($binary);
            $isApiTemp = true;
        }

        $file = [
            'name' => $name,
            'type' => $declaredMime,
            'tmp_name' => $tmpName,
            'error' => UPLOAD_ERR_OK,
            'size' => $size,
            'api_temp' => $isApiTemp,
        ];
        $files[] = $file;

        if ($size <= 0) {
            return $fail(apiAttachmentError('invalid_attachment_data', $name . ' is leeg.', $index, $name));
        }
        if ($size > $maxBytes) {
            return $fail(apiAttachmentError('attachment_too_large', $name . ' is groter dan ' . (int) round($maxBytes / 1048576) . ' MB.', $index, $name));
        }
        $totalBytes += $size;
        if ($totalBytes > apiMessageAttachmentMaxTotalBytes()) {
            return $fail(apiAttachmentError('attachments_too_large', 'Alle bijlagen samen mogen maximaal ' . (int) round(apiMessageAttachmentMaxTotalBytes() / 1048576) . ' MB zijn.', $index, $name));
        }

        $detectedMime = strtolower((string) $finfo->file($tmpName));
        if (!in_array($detectedMime, $allowed[$extension], true)) {
            return $fail(apiAttachmentError(
                'attachment_content_mismatch',
                'De inhoud van ' . $name . ' (' . ($detectedMime !== '' ? $detectedMime : 'onbekend') . ') past niet bij .' . $extension . '.',
                $index,
                $name
            ));
        }
        $normalizedDeclared = $declaredMime === 'image/jpg' ? 'image/jpeg' : $declaredMime;
        if ($normalizedDeclared !== ''
            && $normalizedDeclared !== 'application/octet-stream'
            && $normalizedDeclared !== $detectedMime
            && !in_array($normalizedDeclared, $allowed[$extension], true)
        ) {
            return $fail(apiAttachmentError(
                'attachment_mime_mismatch',
                'Opgegeven mime ' . $declaredMime . ' komt niet overeen met de inhoud van ' . $name . ' (' . $detectedMime . ').',
                $index,
                $name
            ));
        }

        // Unieke weergavenaam binnen het bericht, zodat [[attachment:naam]] precies één bijlage aanwijst.
        $uniqueName = $name;
        $suffix = 2;
        while (isset($usedNames[strtolower($uniqueName)])) {
            $base = (string) pathinfo($name, PATHINFO_FILENAME);
            $uniqueName = $base . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
            $suffix++;
        }
        $usedNames[strtolower($uniqueName)] = true;
        $files[count($files) - 1]['name'] = $uniqueName;

        $result['inline'][] = isApiTruthy($entry['inline'] ?? false);
        $aliases = [$uniqueName, $name];
        $trimmedGiven = trim($givenName);
        if ($trimmedGiven !== '') {
            $aliases[] = $trimmedGiven;
        }
        $result['aliases'][] = array_values(array_unique($aliases));
    }

    $result['files'] = $files;

    return $result;
}

/**
 * Zet verwijzingen naar meegestuurde bijlagen om naar de UI-marker [[attachment:naam]] op een eigen regel
 * (exact wat de UI bij geplakte/ingevoegde afbeeldingen opslaat).
 * - {{attachment:0}} (index, 0-based) of {{attachment:bestandsnaam}} in message
 * - inline: true per bijlage → marker onderaan, als hij nog niet in de tekst staat
 * - [[attachment:bestandsnaam]] op een eigen regel werkt ook direct
 *
 * @return array{ok: bool, message: string, inline_names: list<string>, error?: array}
 */
function applyApiMessageAttachmentReferences(string $message, array $files, array $inlineFlags, array $aliases): array
{
    $names = array_map(static fn(array $file): string => (string) ($file['name'] ?? ''), $files);
    $tokens = [];
    $invalidReference = null;

    $message = (string) preg_replace_callback(
        '/\{\{\s*attachment\s*:\s*([^{}]+?)\s*\}\}/iu',
        static function (array $match) use ($names, $aliases, &$tokens, &$invalidReference): string {
            $reference = trim((string) $match[1]);
            $targetIndex = null;
            if (preg_match('/^\d+$/', $reference) === 1 && isset($names[(int) $reference])) {
                $targetIndex = (int) $reference;
            } else {
                foreach ($aliases as $index => $aliasList) {
                    foreach ($aliasList as $alias) {
                        if (strcasecmp($alias, $reference) === 0) {
                            $targetIndex = (int) $index;
                            break 2;
                        }
                    }
                }
            }
            if ($targetIndex === null) {
                $invalidReference ??= $reference;
                return $match[0];
            }
            $token = "\x1A" . count($tokens) . "\x1A";
            $tokens[$token] = buildAttachmentMessageMarker($names[$targetIndex]);
            return $token;
        },
        $message
    );

    if ($invalidReference !== null) {
        return [
            'ok' => false,
            'message' => $message,
            'inline_names' => [],
            'error' => apiAttachmentError('attachment_reference_invalid', 'Verwijzing {{attachment:' . $invalidReference . '}} wijst niet naar een meegestuurde bijlage.'),
        ];
    }

    if ($tokens !== []) {
        $lines = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $message)) as $line) {
            if (!str_contains($line, "\x1A")) {
                $lines[] = $line;
                continue;
            }
            $parts = preg_split('/(\x1A\d+\x1A)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $part) {
                if (isset($tokens[$part])) {
                    $lines[] = $tokens[$part];
                } elseif (trim($part) !== '') {
                    $lines[] = trim($part);
                }
            }
        }
        $message = implode("\n", $lines);
    }

    foreach ($names as $index => $name) {
        if (empty($inlineFlags[$index])) {
            continue;
        }
        $marker = buildAttachmentMessageMarker($name);
        if (in_array($name, extractReferencedAttachmentNames($message), true)) {
            continue;
        }
        $message = rtrim($message);
        $message = ($message !== '' ? $message . "\n" : '') . $marker;
    }

    $referenced = extractReferencedAttachmentNames($message);

    return [
        'ok' => true,
        'message' => $message,
        'inline_names' => array_values(array_filter($names, static fn(string $name): bool => in_array($name, $referenced, true))),
    ];
}

function buildApiAttachmentAbsoluteUrl(string $relativePath): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'sleutels.kvt.nl'));
    if ($host === '') {
        $host = 'sleutels.kvt.nl';
    }

    return $scheme . '://' . $host . buildAsclepiusWebBasePath() . '/' . ltrim($relativePath, '/');
}

/**
 * @return list<array<string, mixed>>
 */
function buildApiMessageAttachmentsResponse(?array $message, array $inlineNames): array
{
    $rows = [];
    foreach ((is_array($message['attachments'] ?? null) ? $message['attachments'] : []) as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $attachmentId = (int) ($attachment['id'] ?? 0);
        $name = (string) ($attachment['original_name'] ?? '');
        $directUrl = buildAttachmentDirectUrl($attachment);
        $rows[] = [
            'id' => $attachmentId,
            'filename' => $name,
            'mime_type' => (string) ($attachment['mime_type'] ?? ''),
            'size' => (int) ($attachment['file_size'] ?? 0),
            'inline' => in_array($name, $inlineNames, true),
            'marker' => buildAttachmentMessageMarker($name),
            'url' => $directUrl !== '' ? buildApiAttachmentAbsoluteUrl($directUrl) : '',
            'download_url' => $attachmentId > 0 ? buildApiAttachmentAbsoluteUrl('index.php?download=' . $attachmentId) : '',
        ];
    }

    return $rows;
}

function payloadHasApiMessageAttachments(array $payload): bool
{
    $raw = $payload['attachments'] ?? null;
    if (is_array($raw) && $raw !== []) {
        return true;
    }
    if (is_string($raw) && trim($raw) !== '' && trim($raw) !== '[]') {
        return true;
    }

    return isset($_FILES['attachments']) && function_exists('normalizeUploadedFiles') && normalizeUploadedFiles('attachments') !== [];
}

function handleAddTicketMessageApiAction(TicketStore $store, array $payload, ?array $apiClient, bool $hasValidServiceApiKey): array
{
    $viewerEmail = strtolower(trim((string) (
        $apiClient['email']
        ?? $payload['sender_email']
        ?? $payload['viewer_email']
        ?? $payload['user_email']
        ?? ''
    )));
    $isServiceActor = $hasValidServiceApiKey || isTrustedApiRequester();
    $userIsAdmin = $isServiceActor || !empty($apiClient['is_admin']);

    $ticketId = parseApiTicketId($payload);
    $message = trim((string) ($payload['message'] ?? $payload['message_text'] ?? ''));
    $isGhost = isApiTruthy($payload['ghost'] ?? $payload['is_ghost'] ?? $payload['ghost_mode'] ?? false);
    $wantsStatus = payloadRequestsStatusChange($payload);
    $wantsAssignee = payloadRequestsAssigneeChange($payload);
    $wantsFieldChange = $wantsStatus || $wantsAssignee;

    if ($ticketId <= 0) {
        return [
            'success' => false,
            'error' => 'ticket_id_required',
            'error_code' => 'ticket_id_required',
        ];
    }
    $hasAttachments = payloadHasApiMessageAttachments($payload);
    if ($message === '' && !$hasAttachments) {
        return [
            'success' => false,
            'error' => 'message_required',
            'error_code' => 'message_required',
        ];
    }
    if ($isGhost && !$userIsAdmin) {
        return [
            'success' => false,
            'error' => 'ghost_forbidden',
            'error_code' => 'ghost_forbidden',
        ];
    }

    $mutationAccess = null;
    if ($wantsFieldChange) {
        $mutationAccess = resolveIctTicketMutationAccess($store, $payload, $apiClient, $hasValidServiceApiKey);
        if (empty($mutationAccess['allowed'])) {
            return $mutationAccess['error'];
        }
        $ticket = loadTicketForIctMutation(
            $store,
            $ticketId,
            (string) $mutationAccess['viewer_email'],
            $mutationAccess['ict_access']
        );
    } else {
        $ticket = $store->getTicket($ticketId, $userIsAdmin, $viewerEmail, 'default', $userIsAdmin);
    }

    if ($ticket === null) {
        return [
            'success' => false,
            'error' => $wantsFieldChange ? __('flash.ticket_not_found') : 'ticket_not_found',
            'error_code' => 'ticket_not_found',
        ];
    }

    $callerSuppliedEmail = apiCallerSuppliedIdentityField($payload, ['sender_email', 'viewer_email', 'user_email']);
    $callerSuppliedName = apiCallerSuppliedIdentityField($payload, ['sender_name', 'display_name', 'sender_display_name']);
    $callerSuppliedTitle = apiCallerSuppliedIdentityField($payload, ['sender_title', 'role_title', 'function_title', 'sender_role_title']);

    $senderEmail = $viewerEmail;
    if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        if (!$userIsAdmin && $mutationAccess === null) {
            return [
                'success' => false,
                'error' => 'invalid_user',
                'error_code' => 'invalid_user',
            ];
        }
        $senderEmail = $mutationAccess !== null
            ? resolveApiMutationActorEmail($ticket, (string) $mutationAccess['viewer_email'])
            : 'ict@kvt.nl';
    }

    $canPostAsAdmin = $userIsAdmin || $mutationAccess !== null;
    $senderRole = $canPostAsAdmin ? 'admin' : 'user';
    $senderDisplayName = null;
    $senderRoleTitle = null;
    $isGrokWebhookPost = is_array($apiClient) && (
        (string) ($apiClient['kind'] ?? '') === 'grok_bot_ephemeral'
        || (string) ($apiClient['oid'] ?? '') === 'grok-bot'
    );
    if ($canPostAsAdmin) {
        $senderDisplayName = trim((string) (
            $payload['sender_name']
            ?? $payload['display_name']
            ?? $payload['sender_display_name']
            ?? ''
        ));
        $senderRoleTitle = trim((string) (
            $payload['sender_title']
            ?? $payload['role_title']
            ?? $payload['function_title']
            ?? $payload['sender_role_title']
            ?? ''
        ));
        if ($senderDisplayName === '' || $senderRoleTitle === '') {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'GrokBot.php';
            $defaults = GrokBot::resolveDefaultIdentity(
                $senderEmail,
                [],
                $isGrokWebhookPost ? $apiClient : []
            );
            if ($senderDisplayName === '') {
                $senderDisplayName = $defaults['name'];
            }
            if ($senderRoleTitle === '') {
                $senderRoleTitle = $defaults['title'];
            }
        }
        if ($senderDisplayName === '') {
            $senderDisplayName = null;
        }
        if ($senderRoleTitle === '') {
            $senderRoleTitle = null;
        }
    }

    $emailDefaulted = $canPostAsAdmin && !$callerSuppliedEmail && $senderEmail !== '';
    $nameDefaulted = $canPostAsAdmin && !$callerSuppliedName;
    $titleDefaulted = $canPostAsAdmin
        && !$callerSuppliedTitle
        && $senderRoleTitle !== null
        && $senderRoleTitle !== '';

    $actorEmail = $mutationAccess !== null
        ? resolveApiMutationActorEmail($ticket, (string) $mutationAccess['viewer_email'])
        : $senderEmail;
    $newStatus = (string) ($ticket['status'] ?? '');
    $newAssignee = strtolower(trim((string) ($ticket['assigned_email'] ?? '')));
    $statusChanged = false;
    $assigneeChanged = false;

    if ($wantsStatus) {
        $resolvedStatus = resolveTicketStatusValue(readApiStatusValue($payload), $store, $actorEmail);
        if ($resolvedStatus === null) {
            return [
                'success' => false,
                'error' => __('flash.invalid_status'),
                'error_code' => 'invalid_status',
            ];
        }
        $newStatus = $resolvedStatus;
        $statusChanged = $newStatus !== (string) ($ticket['status'] ?? '');
        if ($statusChanged) {
            rememberCustomTicketStatusForActor($store, $actorEmail, $newStatus);
        }
    }

    if ($wantsAssignee) {
        $requestedAssignee = readApiAssigneeEmail($payload);
        if ($requestedAssignee !== $newAssignee) {
            $assigneeError = validateTicketAssigneeChange($store, $ticket, $requestedAssignee, $actorEmail);
            if ($assigneeError !== null) {
                return [
                    'success' => false,
                    'error' => $assigneeError['error'],
                    'error_code' => $assigneeError['error_code'],
                ];
            }
            $newAssignee = $requestedAssignee;
            $assigneeChanged = true;
        }
    }

    // Bijlagen: volledig valideren (aantal, grootte, type via finfo, naam, verwijzingen)
    // vóórdat het ticket of een bericht wordt aangepast.
    $attachmentFiles = [];
    $inlineAttachmentNames = [];
    if ($hasAttachments) {
        $attachmentRead = readApiMessageAttachments($payload);
        if (empty($attachmentRead['ok'])) {
            return $attachmentRead['error'];
        }
        $attachmentFiles = $attachmentRead['files'];
        $referenceResult = applyApiMessageAttachmentReferences(
            $message,
            $attachmentFiles,
            $attachmentRead['inline'],
            $attachmentRead['aliases']
        );
        if (empty($referenceResult['ok'])) {
            cleanupApiAttachmentTempFiles($attachmentFiles);
            return $referenceResult['error'];
        }
        $message = $referenceResult['message'];
        $inlineAttachmentNames = $referenceResult['inline_names'];
        $attachmentFiles = array_map(static function (array $file): array {
            unset($file['api_temp']);
            return $file;
        }, $attachmentFiles);
    }

    $composedReply = composeTicketReplyMessageForStorage(
        $message,
        $isGhost,
        $statusChanged,
        $newStatus,
        $actorEmail
    );
    $messageForStorage = $composedReply['message_for_storage'];

    if ($statusChanged || $assigneeChanged) {
        $overrides = [];
        if ($statusChanged) {
            $overrides['status'] = $newStatus;
        }
        if ($assigneeChanged) {
            $overrides['assigned_email'] = $newAssignee;
        }
        persistTicketFieldUpdate($store, $ticket, $overrides);
    }

    try {
        $persistedReply = persistTicketReplyMessages(
            $store,
            $ticketId,
            $senderEmail,
            $senderRole,
            $message,
            $attachmentFiles,
            $isGhost,
            $messageForStorage,
            $senderDisplayName,
            $senderRoleTitle,
            (string) ($ticket['status'] ?? ''),
            $isGrokWebhookPost
        );
    } catch (Throwable $exception) {
        if ($attachmentFiles === []) {
            throw $exception;
        }
        return apiAttachmentError('attachment_store_failed', 'Het bericht met bijlagen kon niet worden opgeslagen; er is niets geplaatst.');
    } finally {
        foreach ($attachmentFiles as $file) {
            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($tmpName !== '' && is_file($tmpName) && !is_uploaded_file($tmpName)) {
                @unlink($tmpName);
            }
        }
    }
    $messageId = (int) $persistedReply['message_id'];

    $updatedTicket = $store->getTicket($ticketId, true, $senderEmail, 'default', true);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => $wantsFieldChange ? __('flash.ticket_not_found') : 'ticket_not_found',
            'error_code' => 'ticket_not_found',
        ];
    }

    if ($statusChanged || $assigneeChanged) {
        notifyTicketFieldChangeViaApi(
            $store,
            $updatedTicket,
            $ticketId,
            $actorEmail,
            (string) $persistedReply['visible_message_for_mail'],
            $statusChanged,
            $assigneeChanged,
            $assigneeChanged ? $newAssignee : ''
        );
    }

    $createdMessage = null;
    foreach (($updatedTicket['messages'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $messageId) {
            $createdMessage = $row;
            break;
        }
    }

    $response = [
        'success' => true,
        'ticket_id' => $ticketId,
        'message_id' => $messageId,
        'is_ghost' => $isGhost,
        'sender_email' => $senderEmail,
        'sender_name' => $senderDisplayName !== null && $senderDisplayName !== ''
            ? $senderDisplayName
            : formatUserDisplayName($senderEmail),
        'sender_role' => $senderRole,
        'sender_title' => $senderRoleTitle,
        'message' => $createdMessage,
    ];

    if ($attachmentFiles !== []) {
        $response['attachments'] = buildApiMessageAttachmentsResponse($createdMessage, $inlineAttachmentNames);
        $response['ticket_url'] = buildAsclepiusTicketUrl($ticketId);
        if (is_array($createdMessage) && is_array($createdMessage['attachments'] ?? null)) {
            // Geen serverpaden in het antwoord.
            $response['message']['attachments'] = array_map(static function (array $attachment): array {
                unset($attachment['stored_path']);
                return $attachment;
            }, $createdMessage['attachments']);
        }
    }

    if ($wantsFieldChange) {
        $assignedEmail = strtolower(trim((string) ($updatedTicket['assigned_email'] ?? $newAssignee)));
        $status = (string) ($updatedTicket['status'] ?? $newStatus);
        $response['status'] = $status;
        $response['status_changed'] = $statusChanged;
        $response['assigned_email'] = $assignedEmail;
        $response['assignee_changed'] = $assigneeChanged;
        $response['unchanged'] = !$statusChanged && !$assigneeChanged;
        if (!empty($persistedReply['status_message_id'])) {
            $response['status_message_id'] = (int) $persistedReply['status_message_id'];
        }
    }

    if ($emailDefaulted || $nameDefaulted || $titleDefaulted) {
        $identityHint = apiMissingIdentityHint();
        appendApiResponseHint($response, $identityHint['hint'], $identityHint['explanation']);
    }

    if (!$wantsStatus && $store->hasRecentApiTicketEvent($ticketId, 'status', apiStatusMessagePairWindowSeconds())) {
        $statusHint = apiSeparateStatusChangeHint();
        appendApiResponseHint($response, $statusHint['hint'], $statusHint['explanation']);
    }

    $store->recordApiTicketEvent($ticketId, 'message');
    if ($statusChanged) {
        $store->recordApiTicketEvent($ticketId, 'status');
    }

    return $response;
}

function buildBigscreenPollApiPayload(TicketStore $store, ?array $apiClient = null): array
{
    global $ictUsers;

    $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? '')));
    $ictAccess = $viewerEmail !== ''
        ? resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true)
        : [
            'is_full_ict_admin' => true,
            'is_limited_ict' => false,
            'role' => null,
            'access_categories' => null,
        ];
    $accessCategories = !empty($ictAccess['is_limited_ict'])
        ? ($ictAccess['access_categories'] ?? [])
        : null;

    $allTicketsForPoll = $store->getTickets(true, '', [], null, [], null, 'default', null, null, $accessCategories);
    $warmupEmails = $accessCategories !== null && is_array($ictAccess['role'] ?? null)
        ? $store->listIctRoleMemberEmails((int) ($ictAccess['role']['role_id'] ?? 0))
        : $store->getAllIctCapableEmails();
    warmUserDirectoryForBigscreenPoll($allTicketsForPoll, $warmupEmails);

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
            $pollOpenTickets[] = mapBigscreenOpenTicketRow($ticket);
        }
    }

    if (!empty($ictAccess['is_limited_ict'])) {
        $scopedStats = buildLimitedRoleStatsBundle(
            $store,
            is_array($ictAccess['role'] ?? null) ? (int) ($ictAccess['role']['role_id'] ?? 0) : 0,
            is_array($accessCategories) ? $accessCategories : []
        );
        $pollOverallStats = $scopedStats['overall'];
        $pollIctStats = $scopedStats['ict'];
        $pollRequesterStats = $scopedStats['requester'];
    } else {
        $pollOverallStats = $store->getOverallStats();
        $pollIctStats = $store->getIctUserStats();
        $pollRequesterStats = $store->getRequesterStats();
    }

    $pollAvailability = $store->getEffectiveIctUserAvailability();

    $pollIctStatsMapped = array_map(
        static fn(array $row): array => mapBigscreenIctStatRow($row, $pollAvailability),
        $pollIctStats
    );

    $pollRequesterStatsMapped = array_map(
        static fn(array $row): array => mapBigscreenRequesterStatRow($row),
        $pollRequesterStats
    );

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
            'user_label' => formatUserDisplayName((string) ($pollLatest['user_email'] ?? '')),
            'assigned_email' => (string) ($pollLatest['assigned_email'] ?? ''),
            'assigned_color' => emailToHexColor((string) ($pollLatest['assigned_email'] ?? '')),
            'priority' => (int) ($pollLatest['priority'] ?? 0),
        ] : null,
    ];
}

function handleSaveAdminEmailPreferencesApiAction(array $payload, ?array $apiClient): array
{
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    ensureApiSessionStarted();
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    $notificationType = trim((string) ($payload['notification_type'] ?? ''));
    if (!in_array($notificationType, ADMIN_EMAIL_NOTIFICATION_TYPES, true)) {
        return [
            'success' => false,
            'error' => 'invalid_notification_type',
        ];
    }

    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($_SESSION['user']['email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    saveAdminEmailPreference($userEmail, $notificationType, !empty($payload['enabled']));

    return [
        'success' => true,
        'preferences' => loadAdminEmailPreferences($userEmail),
    ];
}

function handleSaveTicketAppearancePreferencesApiAction(array $payload, ?array $apiClient): array
{
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    ensureApiSessionStarted();
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($_SESSION['user']['email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    $appearance = is_array($payload['appearance'] ?? null) ? $payload['appearance'] : $payload;
    $saved = saveTicketAppearancePreferences($userEmail, $appearance);

    return [
        'success' => true,
        'appearance' => $saved,
    ];
}

function handleSaveAskResolutionNoteApiAction(array $payload, ?array $apiClient): array
{
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    ensureApiSessionStarted();
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($_SESSION['user']['email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    $enabled = !empty($payload['enabled']);
    saveUserPref($userEmail, 'ask_resolution_note', $enabled);

    return [
        'success' => true,
        'ask_resolution_note' => $enabled,
    ];
}

function handleSaveGrokWebhookApiAction(array $payload, ?array $apiClient): array
{
    ensureApiSessionStarted();
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    $sessionEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    $sessionIsAdmin = !empty($_SESSION['user']['admin']);
    $apiClientIsAdmin = is_array($apiClient) && !empty($apiClient['is_admin']);
    $apiClientEmail = strtolower(trim((string) ($apiClient['email'] ?? '')));
    if ($sessionEmail === '' && $apiClientIsAdmin && filter_var($apiClientEmail, FILTER_VALIDATE_EMAIL)) {
        $sessionEmail = $apiClientEmail;
    }
    if (!$sessionIsAdmin && !$apiClientIsAdmin) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }
    if ($sessionEmail === '' || !filter_var($sessionEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }
    if ($apiClientEmail !== '' && $apiClientEmail !== $sessionEmail && !$sessionIsAdmin) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'GrokBot.php';
    if (isApiTruthy($payload['clear'] ?? false)) {
        GrokBot::clearUserWebhook($sessionEmail);

        return [
            'success' => true,
            'grok_webhook' => GrokBot::publicUserWebhook($sessionEmail),
        ];
    }

    $saved = GrokBot::saveUserWebhook(
        $sessionEmail,
        (string) ($payload['webhook_url'] ?? ''),
        (string) ($payload['send_key'] ?? '')
    );
    if (empty($saved['ok'])) {
        $errorCode = (string) ($saved['error_code'] ?? 'invalid_webhook_url');
        $errorKey = $errorCode === 'send_key_required'
            ? 'grok_webhook.key_required'
            : ($errorCode === 'invalid_user' ? 'invalid_user' : 'grok_webhook.invalid_url');

        return [
            'success' => false,
            'error' => __($errorKey) === $errorKey ? $errorCode : __($errorKey),
            'error_code' => $errorCode,
            'grok_webhook' => $saved['webhook'] ?? GrokBot::publicUserWebhook($sessionEmail),
        ];
    }

    return [
        'success' => true,
        'grok_webhook' => $saved['webhook'],
    ];
}

function handleCategoryOpenSnapshotsApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    global $ictUsers;

    $fromDate = trim((string) (
        $payload['from_date']
        ?? $payload['start_date']
        ?? $payload['from']
        ?? $payload['start']
        ?? ''
    ));
    $toDate = trim((string) (
        $payload['to_date']
        ?? $payload['end_date']
        ?? $payload['to']
        ?? $payload['end']
        ?? ''
    ));

    if ($fromDate === '') {
        return [
            'success' => false,
            'error' => 'from_date_required',
            'message' => 'from_date is verplicht (YYYY-MM-DD).',
        ];
    }

    if ($toDate === '') {
        $toDate = (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        return [
            'success' => false,
            'error' => 'invalid_date_range',
            'message' => 'Datums moeten YYYY-MM-DD zijn.',
        ];
    }

    if ($fromDate > $toDate) {
        [$fromDate, $toDate] = [$toDate, $fromDate];
    }

    $viewerEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? '')
    )));
    $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
    $ictAccess = $viewerEmail !== ''
        ? resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true)
        : null;
    $categories = null;
    if (is_array($ictAccess) && !empty($ictAccess['is_limited_ict'])) {
        $categories = is_array($ictAccess['access_categories'] ?? null)
            ? array_values($ictAccess['access_categories'])
            : [];
    }

    $snapshot = $store->getCategoryOpenSnapshots($fromDate, $toDate, $categories);
    $series = [];
    foreach ($snapshot['series'] as $row) {
        $category = (string) ($row['category'] ?? '');
        $series[] = [
            'category' => $category,
            'label' => translateCategory($category),
            'color' => (string) ($row['color'] ?? '#64748b'),
            'points' => array_values(array_map(
                static fn($point) => $point === null ? null : (int) $point,
                is_array($row['points'] ?? null) ? $row['points'] : []
            )),
        ];
    }

    $timestamps = array_values(array_map('strval', $snapshot['timestamps'] ?? ($snapshot['dates'] ?? [])));

    return [
        'success' => true,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'timestamps' => $timestamps,
        'dates' => $timestamps,
        'series' => $series,
    ];
}

function mapTheevraagjeMessageForApi(array $message): array
{
    $email = strtolower(trim((string) ($message['user_email'] ?? '')));

    return [
        'id' => (int) ($message['id'] ?? 0),
        'user_email' => $email,
        'user_label' => $email !== '' ? formatUserDisplayName($email) : '',
        'message_text' => (string) ($message['message_text'] ?? ''),
        'created_at' => (string) ($message['created_at'] ?? ''),
        'created_at_label' => formatDateTime((string) ($message['created_at'] ?? '')),
        'colors' => asclepius_chat_colors_for_email($email),
        'user_color' => emailToHexColor($email),
    ];
}

function handleTheevraagjeStateApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? '')
    )));
    if ($viewerEmail === '' || !filter_var($viewerEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    $ensure = $store->ensureTheevraagjeImage();
    $messages = array_map('mapTheevraagjeMessageForApi', $store->getTheevraagjeMessages());
    $hasImage = $store->hasTheevraagjeImage();

    return [
        'success' => true,
        'has_image' => $hasImage,
        'image_url' => $hasImage
            ? ('theevraagje_image.php?t=' . (string) filemtime(THEEVRAAGJE_IMAGE_FILE))
            : '',
        'fetched' => !empty($ensure['fetched']),
        'image_error' => $hasImage ? '' : (string) ($ensure['error'] ?? 'missing'),
        'messages' => $messages,
    ];
}

function handleTheevraagjeSendApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    ensureApiSessionStarted();
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    $viewerEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($_SESSION['user']['email'] ?? ''))
    )));
    if ($viewerEmail === '' || !filter_var($viewerEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    $text = trim((string) ($payload['message_text'] ?? ($payload['text'] ?? '')));
    if ($text === '') {
        return [
            'success' => false,
            'error' => 'empty_message',
        ];
    }

    $store->ensureTheevraagjeImage();
    $message = $store->addTheevraagjeMessage($viewerEmail, $text);
    if ($message === null) {
        return [
            'success' => false,
            'error' => 'save_failed',
        ];
    }

    return [
        'success' => true,
        'message' => mapTheevraagjeMessageForApi($message),
    ];
}

function handleSaveTicketOverviewSearchApiAction(array $payload, ?array $apiClient): array
{
    ensureApiSessionStarted();
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    $userEmail = resolveAuthenticatedUserEmail($apiClient, $payload);
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    $userPrefs = loadUserPrefs($userEmail);
    $activeCustomLabels = [];
    $validAssigneeEmails = [];
    try {
        global $ictUsers;
        $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, is_array($ictUsers) ? $ictUsers : [], TICKET_CATEGORIES);
        $activeCustomLabels = $store->getActiveCustomStatusLabels();
        $validAssigneeEmails = $store->getAllIctCapableEmails();
    } catch (Throwable) {
        $activeCustomLabels = [];
        $validAssigneeEmails = extractIctUserEmails(is_array($ictUsers ?? null) ? $ictUsers : []);
    }
    $savedFilters = normalizeSavedTicketOverviewFilters($userPrefs, $activeCustomLabels, $validAssigneeEmails, $userEmail);
    $savedFilters['search_query'] = trim((string) ($payload['search_query'] ?? ''));
    saveUserPref($userEmail, 'ticket_overview_filters', stampTicketOverviewFiltersForUser($savedFilters, $userEmail));

    return [
        'success' => true,
        'search_query' => $savedFilters['search_query'],
    ];
}

function resolveChangelogApiActor(array $payload, ?array $apiClient): array
{
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    ensureApiSessionStarted();
    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($_SESSION['user']['email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    return [
        'success' => true,
        'email' => $userEmail,
    ];
}

function verifyChangelogApiCsrf(array $payload): ?array
{
    ensureApiSessionStarted();

    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    return null;
}

function handleMarkChangelogReadApiAction(array $payload, ?array $apiClient): array
{
    $actor = resolveChangelogApiActor($payload, $apiClient);
    if (empty($actor['success'])) {
        return $actor;
    }

    $csrfError = verifyChangelogApiCsrf($payload);
    if ($csrfError !== null) {
        return $csrfError;
    }

    $userEmail = (string) ($actor['email'] ?? '');
    $entryId = trim((string) ($payload['entry_id'] ?? ''));
    if ($entryId === '') {
        return [
            'success' => false,
            'error' => 'invalid_entry_id',
        ];
    }

    return [
        'success' => true,
        'read_ids' => markChangelogEntryRead($userEmail, $entryId),
    ];
}

function handleMarkAllChangelogsReadApiAction(array $payload, ?array $apiClient): array
{
    $actor = resolveChangelogApiActor($payload, $apiClient);
    if (empty($actor['success'])) {
        return $actor;
    }

    $csrfError = verifyChangelogApiCsrf($payload);
    if ($csrfError !== null) {
        return $csrfError;
    }

    $userEmail = (string) ($actor['email'] ?? '');
    $entryIds = $payload['entry_ids'] ?? [];
    if (!is_array($entryIds)) {
        $entryIds = [];
    }

    return [
        'success' => true,
        'read_ids' => markAllChangelogEntriesRead($userEmail, $entryIds),
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

function buildPageAccessTicketTitle(string $pageName): string
{
    return 'Aanvraag toegang tot ' . $pageName;
}

function buildPageAccessTicketDescription(string $pageName): string
{
    return 'Ik wil graag toegang krijgen tot de pagina ' . $pageName . ' op sleutels.kvt.nl.';
}

function buildAsclepiusWebBasePath(): string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === 'sleutels.kvt.nl') {
        return '/asclepius';
    }

    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $webDir = realpath(__DIR__);
    if (
        is_string($docRoot) && $docRoot !== '' &&
        is_string($webDir) && $webDir !== '' &&
        str_starts_with(str_replace('\\', '/', $webDir), str_replace('\\', '/', $docRoot))
    ) {
        $relative = substr(str_replace('\\', '/', $webDir), strlen(str_replace('\\', '/', $docRoot)));

        return '/' . trim($relative, '/');
    }

    return '/asclepius';
}

function buildAsclepiusTicketUrl(int $ticketId): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'sleutels.kvt.nl'));
    if ($host === '') {
        $host = 'sleutels.kvt.nl';
    }

    return $scheme . '://' . $host . buildAsclepiusWebBasePath() . '/index.php?open=' . $ticketId;
}

function buildPageAccessTicketApiResponse(array $ticket, bool $created): array
{
    $ticketId = (int) ($ticket['id'] ?? 0);
    $messages = [];
        foreach ($ticket['messages'] ?? [] as $message) {
        if (!is_array($message) || !empty($message['is_ghost'])) {
            continue;
        }

        $senderEmail = (string) ($message['sender_email'] ?? '');
        $messages[] = [
            'id' => (int) ($message['id'] ?? 0),
            'sender_email' => $senderEmail,
            'sender_label' => formatUserDisplayName($senderEmail),
            'sender_role' => (string) ($message['sender_role'] ?? 'user'),
            'message_text' => (string) ($message['message_text'] ?? ''),
            'created_at' => formatDateTime((string) ($message['created_at'] ?? '')),
        ];
    }

    return [
        'success' => true,
        'created' => $created,
        'message' => $created
            ? 'Uw aanvraag is ingediend. U ontvangt ook een bevestiging per e-mail.'
            : 'Er is al een open ticket voor deze toegangsaanvraag.',
        'ticket' => [
            'id' => $ticketId,
            'title' => (string) ($ticket['title'] ?? ''),
            'status' => (string) ($ticket['status'] ?? ''),
            'status_label' => translateStatus((string) ($ticket['status'] ?? '')),
            'category' => (string) ($ticket['category'] ?? ''),
            'category_label' => translateCategory((string) ($ticket['category'] ?? '')),
            'created_at' => formatDateTime((string) ($ticket['created_at'] ?? '')),
            'updated_at' => formatDateTime((string) ($ticket['updated_at'] ?? '')),
        ],
        'messages' => $messages,
        'ticket_url' => buildAsclepiusTicketUrl($ticketId),
    ];
}

function handleRequestPageAccessApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    global $ictUsers;

    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($payload['user_email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    if ($apiClient === null && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => 'unauthorized',
        ];
    }

    $pageName = trim((string) ($payload['page_name'] ?? ''));
    if ($pageName === '') {
        return [
            'success' => false,
            'error' => 'page_name_required',
        ];
    }

    $category = 'sleutels.kvt.nl web-applicatieproblemen';
    $title = buildPageAccessTicketTitle($pageName);
    $description = buildPageAccessTicketDescription($pageName);

    $existingTicket = null;
    foreach ($store->getTickets(false, $userEmail) as $ticket) {
        if (!is_array($ticket)) {
            continue;
        }

        if (
            trim((string) ($ticket['title'] ?? '')) === $title
            && strtolower(trim((string) ($ticket['status'] ?? ''))) !== 'afgehandeld'
        ) {
            $existingTicket = $ticket;
            break;
        }
    }

    $created = false;
    if ($existingTicket !== null) {
        $ticketId = (int) ($existingTicket['id'] ?? 0);
    } else {
        try {
            $result = $store->createTicket($title, $category, $userEmail, $description);
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'error' => 'create_failed',
                'details' => $exception->getMessage(),
            ];
        }

        $ticketId = (int) ($result['ticket_id'] ?? 0);
        $created = true;

        $createdTicket = $store->getTicket($ticketId, true, $userEmail);
        if ($createdTicket !== null) {
            $assignedEmail = trim((string) ($result['assigned_email'] ?? ''));
            $ictRecipients = resolveIctNotifyRecipients(
                $store,
                $assignedEmail,
                (string) ($createdTicket['category'] ?? $category),
                is_array($ictUsers) ? $ictUsers : []
            );
            $ictLang = $assignedEmail !== '' ? getUserMailLang($assignedEmail) : 'nl';
            sendTicketNotification(
                $store,
                is_array($ictUsers) ? $ictUsers : [],
                $ictRecipients,
                __mail('email.subject_new_ticket', $ictLang, $ticketId),
                buildNotificationBody($createdTicket, 'email.intro_new_ict', $description, true, $ictLang),
                $userEmail,
                (string) ($createdTicket['category'] ?? $category),
                $ticketId,
                'new_ticket',
                $userEmail
            );

            $requesterRecipients = is_array($createdTicket['participant_emails'] ?? null)
                ? $createdTicket['participant_emails']
                : [$userEmail];
            $requesterLang = getUserMailLang($userEmail);
            sendTicketNotification(
                $store,
                is_array($ictUsers) ? $ictUsers : [],
                $requesterRecipients,
                __mail('email.subject_created', $requesterLang, $ticketId),
                buildNotificationBody($createdTicket, 'email.intro_created_self', $description, false, $requesterLang),
                null,
                (string) ($createdTicket['category'] ?? $category),
                $ticketId,
                null,
                $userEmail
            );
        }
    }

    $ticket = $store->getTicket($ticketId, false, $userEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => 'ticket_not_found',
        ];
    }

    return buildPageAccessTicketApiResponse($ticket, $created);
}

/**
 * Page load
 */
if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
$providedApiKey = getApiKeyFromRequest();
$apiClient = loadApiClientByToken($providedApiKey);
$hasValidServiceApiKey = isValidApiKey($providedApiKey, $apiKeys ?? []);
if ($apiClient === null && !$hasValidServiceApiKey && $providedApiKey !== '') {
    // Persoonlijke key van de gedeelde login: alleen voor ticket aanmaken namens jezelf.
    $loginKeyPayload = getRequestBody();
    if (isCreateTicketApiRequest($loginKeyPayload, $_SERVER)) {
        $apiClient = resolveLoginRotatingApiClient($providedApiKey, $loginKeyPayload, $_SERVER);
    }
}
if (!isTrustedApiRequester() && $apiClient === null && !$hasValidServiceApiKey) {
    $unauthorizedReason = getRefreshRequiredUnauthorizedReason($providedApiKey);
    sendJson(401, [
        'success' => false,
        'error' => 'Ongeldige API-key.',
        'reason' => $unauthorizedReason,
    ]);
}

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers ?? [], TICKET_CATEGORIES);
    applyJanusSyncToStore($store, is_array($ictUsers) ? $ictUsers : []);
} catch (Throwable $exception) {
    sendJson(500, [
        'success' => false,
        'error' => 'Database kon niet worden geopend.',
        'details' => $exception->getMessage(),
    ]);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $getAction = trim((string) ($_GET['action'] ?? ''));
    if ($getAction === 'category_open_snapshots') {
        $snapshotResponse = handleCategoryOpenSnapshotsApiAction($store, $_GET, $apiClient);
        sendJson(!empty($snapshotResponse['success']) ? 200 : 422, $snapshotResponse);
    }
    if (in_array($getAction, ['ticket_lookups', 'categories', 'statuses'], true)) {
        sendJson(200, buildTicketLookupsApiPayload($getAction));
    }

    $ticketId = max(0, (int) ($_GET['id'] ?? 0));

    if ($ticketId > 0) {
        $includeGhosts = isApiTruthy($_GET['include_ghosts'] ?? $_GET['ghosts'] ?? false);
        $ticket = $store->getTicket($ticketId, true, '', 'default', $includeGhosts);
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

    if (in_array($action, ['ticket_lookups', 'categories', 'statuses'], true)) {
        sendJson(200, buildTicketLookupsApiPayload($action));
    }

    if ($action === 'add_ticket_message') {
        $messageResponse = handleAddTicketMessageApiAction($store, $payload, $apiClient, $hasValidServiceApiKey);
        $error = (string) ($messageResponse['error_code'] ?? $messageResponse['error'] ?? '');
        $statusCode = 200;
        if (empty($messageResponse['success'])) {
            $statusCode = match ($error) {
                'ticket_not_found' => 404,
                'ghost_forbidden', 'forbidden' => 403,
                'attachment_too_large', 'attachments_too_large' => 413,
                'attachment_store_failed' => 500,
                default => 422,
            };
        }
        sendJson($statusCode, $messageResponse);
    }

    if ($action === 'ticket_poll') {
        sendJson(200, buildTicketPollApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'related_completed_tickets') {
        sendJson(200, buildRelatedCompletedTicketsApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'user_profile_stats') {
        $profilePayload = buildUserProfileStatsApiPayload($store, $payload, $apiClient);
        if (empty($profilePayload['success'])) {
            $error = (string) ($profilePayload['error'] ?? 'error');
            sendJson($error === 'forbidden' ? 403 : 422, $profilePayload);
        }
        sendJson(200, $profilePayload);
    }

    if ($action === 'related_ticket_preview') {
        sendJson(200, buildRelatedTicketPreviewApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'open_ticket_duplicate_tip') {
        sendJson(200, buildOpenTicketDuplicateTipApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'presence_poll') {
        global $ictUsers;

        $rows = fetchJanusPresence();
        $groups = [];
        $mapped = [];
        if (is_array($rows)) {
            $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
            $groups = mapJanusPresenceGroupsForDisplay(groupJanusPresenceRows($rows, $store, $ictUsersList));
            foreach ($groups as $group) {
                foreach ($group['rows'] as $row) {
                    $mapped[] = $row;
                }
            }
        }
        sendJson(200, [
            'success' => true,
            'connected' => $rows !== null,
            'groups' => $groups,
            'rows' => $mapped,
        ]);
    }

    if ($action === 'ticket_thread') {
        global $ictUsers;

        $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
        $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'index.php'));
        $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
        $ictUsersList = is_array($ictUsers ?? null) ? $ictUsers : [];
        $ictAccess = resolveIctAccessContextForEmail($store, $ictUsersList, $viewerEmail, true);
        $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin'])
            || !empty($ictAccess['is_full_ict_admin']) || !empty($ictAccess['is_limited_ict']);
        $isAdminPortal = !empty($payload['is_admin_portal']);
        $canManageTickets = $isAdminPortal && $userIsAdmin;
        $accessCategories = ($canManageTickets && !empty($ictAccess['is_limited_ict']))
            ? ($ictAccess['access_categories'] ?? [])
            : null;
        $currentLanguage = strtolower(trim((string) ($payload['current_language'] ?? 'nl')));
        if (!array_key_exists($currentLanguage, SUPPORTED_LANGUAGES)) {
            $currentLanguage = 'nl';
        }
        $view = trim((string) ($payload['view'] ?? 'overview'));
        $browseMode = trim((string) ($payload['browse_mode'] ?? 'default'));
        if ($browseMode !== 'all_completed_public') {
            $browseMode = 'default';
        }
        if (!$canManageTickets && $view === 'all_tickets') {
            $browseMode = 'all_completed_public';
        }

        $ticketDetail = $store->getTicket(
            $ticketId,
            $canManageTickets,
            $viewerEmail,
            $browseMode,
            shouldIncludeGhostMessages($canManageTickets, $isAdminPortal, $view),
            $accessCategories
        );
        if (!is_array($ticketDetail)) {
            sendJson(404, ['success' => false, 'error' => 'ticket_not_found']);
        }

        $ticketDetail = localizeTicketDetailForViewer($ticketDetail, $store, $currentLanguage, true);
        $messages = array_map(
            static fn(array $message): array => [
                'id' => (int) ($message['id'] ?? 0),
                'html' => renderTicketMessageHtml($message, $currentPage, $isAdminPortal),
            ],
            $ticketDetail['messages'] ?? []
        );

        sendJson(200, [
            'success' => true,
            'ticket_id' => $ticketId,
            'messages' => $messages,
        ]);
    }

    if ($action === 'browser_notifications_poll') {
        sendJson(200, buildBrowserNotificationsApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'webpush_subscription') {
        sendJson(200, handleWebPushSubscriptionApiAction($store, $payload, $apiClient));
    }

    if ($action === 'manage_ticket_participants') {
        sendJson(200, handleManageTicketParticipantsApiAction($store, $payload, $apiClient));
    }

    if ($action === 'set_message_reaction') {
        $reactionResponse = handleSetMessageReactionApiAction($store, $payload, $apiClient);
        if (!empty($reactionResponse['success'])) {
            sendJson(200, $reactionResponse);
        }
        $reactionError = (string) ($reactionResponse['error_code'] ?? '');
        $reactionStatus = match ($reactionError) {
            'csrf' => 403,
            'ticket_not_found', 'message_not_found' => 404,
            default => 422,
        };
        sendJson($reactionStatus, $reactionResponse);
    }

    if ($action === 'change_ticket_category') {
        sendJson(200, handleChangeTicketCategoryApiAction($store, $payload, $apiClient, $hasValidServiceApiKey));
    }

    if ($action === 'change_ticket_title') {
        sendJson(200, handleChangeTicketTitleApiAction($store, $payload, $apiClient));
    }

    if ($action === 'change_ticket_status') {
        sendTicketMutationApiJson(handleChangeTicketStatusApiAction($store, $payload, $apiClient, $hasValidServiceApiKey));
    }

    if ($action === 'change_ticket_assignee') {
        sendTicketMutationApiJson(handleChangeTicketAssigneeApiAction($store, $payload, $apiClient, $hasValidServiceApiKey));
    }

    if ($action === 'publish_ghost_message') {
        sendTicketMutationApiJson(handlePublishGhostMessageApiAction($store, $payload, $apiClient, $hasValidServiceApiKey));
    }

    if ($action === 'change_ticket_priority') {
        sendTicketMutationApiJson(handleChangeTicketPriorityApiAction($store, $payload, $apiClient, $hasValidServiceApiKey));
    }

    if ($action === 'change_ticket_due_date') {
        sendTicketMutationApiJson(handleChangeTicketDueDateApiAction($store, $payload, $apiClient, $hasValidServiceApiKey));
    }

    if ($action === 'update_ticket_private') {
        sendJson(200, handleUpdateTicketPrivateApiAction($store, $payload, $apiClient));
    }

    if ($action === 'request_ai_advice') {
        sendJson(200, handleRequestAiAdviceApiAction($store, $payload, $apiClient));
    }

    if ($action === 'save_admin_email_preferences') {
        sendJson(200, handleSaveAdminEmailPreferencesApiAction($payload, $apiClient));
    }

    if ($action === 'save_ticket_appearance_preferences') {
        sendJson(200, handleSaveTicketAppearancePreferencesApiAction($payload, $apiClient));
    }

    if ($action === 'save_ask_resolution_note') {
        sendJson(200, handleSaveAskResolutionNoteApiAction($payload, $apiClient));
    }

    if ($action === 'save_grok_webhook') {
        sendJson(200, handleSaveGrokWebhookApiAction($payload, $apiClient));
    }

    if ($action === 'save_ticket_overview_search') {
        sendJson(200, handleSaveTicketOverviewSearchApiAction($payload, $apiClient));
    }

    if ($action === 'mark_changelog_read') {
        sendJson(200, handleMarkChangelogReadApiAction($payload, $apiClient));
    }

    if ($action === 'mark_all_changelogs_read') {
        sendJson(200, handleMarkAllChangelogsReadApiAction($payload, $apiClient));
    }

    if ($action === 'manage_ticket_template') {
        $userIsAdmin = !empty($apiClient['is_admin']);
        if (!$userIsAdmin && !isTrustedApiRequester()) {
            sendJson(403, ['success' => false, 'error' => __('flash.settings_admin_only')]);
        }

        $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
        $sessionToken = '';
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionCookieName = session_name();
            if ($sessionCookieName !== '' && !empty($_COOKIE[$sessionCookieName])) {
                session_start(['read_and_close' => true]);
            }
        }
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
            sendJson(403, ['success' => false, 'error' => 'csrf']);
        }

        $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
        $authorEmail = strtolower(trim((string) ($apiClient['email'] ?? '')));

        if ($operation === 'create') {
            $name = trim((string) ($payload['name'] ?? ''));
            $body = trim((string) ($payload['body'] ?? ''));
            if ($name === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_name_required')]);
            }
            if ($body === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_body_required')]);
            }
            $store->createTicketTemplate($name, $body, $authorEmail);
        } elseif ($operation === 'update') {
            $id = max(1, (int) ($payload['id'] ?? 0));
            $name = trim((string) ($payload['name'] ?? ''));
            $body = trim((string) ($payload['body'] ?? ''));
            if ($name === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_name_required')]);
            }
            if ($body === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_body_required')]);
            }
            if (!$store->updateTicketTemplate($id, $name, $body, $authorEmail)) {
                sendJson(404, ['success' => false, 'error' => __('flash.template_not_found')]);
            }
        } elseif ($operation === 'delete') {
            $id = max(1, (int) ($payload['id'] ?? 0));
            if (!$store->deleteTicketTemplate($id)) {
                sendJson(404, ['success' => false, 'error' => __('flash.template_not_found')]);
            }
        } elseif ($operation === 'reorder') {
            $orderedIds = array_map('intval', (array) ($payload['ordered_ids'] ?? []));
            $store->reorderTicketTemplates($orderedIds);
        } elseif ($operation !== 'list') {
            sendJson(422, ['success' => false, 'error' => 'unknown_operation']);
        }

        sendJson(200, ['success' => true, 'templates' => $store->getTicketTemplates()]);
    }

    if ($action === 'update_ticket_message_checkbox') {
        $userIsAdmin = !empty($apiClient['is_admin']);
        if (!$userIsAdmin && !isTrustedApiRequester()) {
            sendJson(403, ['success' => false, 'error' => __('flash.settings_admin_only')]);
        }

        $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionCookieName = session_name();
            if ($sessionCookieName !== '' && !empty($_COOKIE[$sessionCookieName])) {
                session_start(['read_and_close' => true]);
            }
        }
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
            sendJson(403, ['success' => false, 'error' => 'csrf']);
        }

        $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
        $messageId = max(1, (int) ($payload['message_id'] ?? 0));
        $lineIndex = max(0, (int) ($payload['line_index'] ?? 0));
        $checked = !empty($payload['checked']);
        $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));

        $updatedMessageText = $store->updateTicketMessageCheckboxState($ticketId, $messageId, $lineIndex, $checked, true, $viewerEmail);
        if ($updatedMessageText === null) {
            sendJson(422, ['success' => false, 'error' => 'update_failed']);
        }

        sendJson(200, [
            'success' => true,
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'message_text' => $updatedMessageText,
        ]);
    }

    if ($action === 'bigscreen_poll') {
        if (!($apiClient['is_admin'] ?? false) && !isTrustedApiRequester()) {
            sendJson(403, [
                'success' => false,
                'error' => 'forbidden',
            ]);
        }
        sendJson(200, buildBigscreenPollApiPayload($store, $apiClient));
    }

    if ($action === 'category_open_snapshots') {
        $snapshotResponse = handleCategoryOpenSnapshotsApiAction($store, $payload, $apiClient);
        sendJson(!empty($snapshotResponse['success']) ? 200 : 422, $snapshotResponse);
    }

    if ($action === 'theevraagje_state') {
        sendJson(200, handleTheevraagjeStateApiAction($store, $payload, $apiClient));
    }

    if ($action === 'theevraagje_send') {
        $sendResponse = handleTheevraagjeSendApiAction($store, $payload, $apiClient);
        sendJson(!empty($sendResponse['success']) ? 200 : 422, $sendResponse);
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

    if ($action === 'request_page_access') {
        $effectiveApiClient = $apiClient;
        if ($effectiveApiClient === null && $hasValidServiceApiKey) {
            $viewerEmail = strtolower(trim((string) ($payload['viewer_email'] ?? ($payload['user_email'] ?? ''))));
            $effectiveApiClient = [
                'email' => $viewerEmail,
                'is_admin' => false,
                'oid' => '',
                'api_key' => $providedApiKey,
            ];
        }

        sendJson(200, handleRequestPageAccessApiAction($store, $payload, $effectiveApiClient));
    }

    if ($action === 'translate_ticket') {
        $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
        $language = strtolower(trim((string) ($payload['language'] ?? 'nl')));
        if (!array_key_exists($language, SUPPORTED_LANGUAGES)) {
            $language = 'nl';
        }
        $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
        $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
        $isAdminPortal = !empty($payload['is_admin_portal']);
        $canManageTickets = $isAdminPortal && $userIsAdmin;

        $ticketDetail = $store->getTicket($ticketId, $canManageTickets, $viewerEmail);
        if (!is_array($ticketDetail)) {
            sendJson(404, ['success' => false, 'error' => 'ticket_not_found']);
        }

        $ticketDetail = localizeTicketDetailForViewer($ticketDetail, $store, $language, false);

        $messages = [];
        foreach (($ticketDetail['messages'] ?? []) as $message) {
            $rawText = (string) ($message['message_text_raw'] ?? ($message['message_text'] ?? ''));
            $displayText = (string) ($message['message_text'] ?? '');
            $messageAttachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
            $messageId = (int) ($message['id'] ?? 0);
            $messages[] = [
                'id' => $messageId,
                'message_text' => $displayText,
                'message_text_raw' => $rawText,
                'message_text_html' => formatTicketMessageText($displayText, $messageId, $messageAttachments),
                'message_text_raw_html' => formatTicketMessageText($rawText, $messageId, $messageAttachments),
                'message_is_translated' => !empty($message['message_is_translated']),
                'translation_error' => (string) ($message['translation_error'] ?? ''),
                'translation_error_detail' => (string) ($message['translation_error_detail'] ?? ''),
            ];
        }

        $rawTitle = (string) ($ticketDetail['title_raw'] ?? ($ticketDetail['title'] ?? ''));
        sendJson(200, [
            'success' => true,
            'ticket_id' => $ticketId,
            'title' => (string) ($ticketDetail['title'] ?? ''),
            'title_raw' => $rawTitle,
            'title_is_translated' => !empty($ticketDetail['title_is_translated']),
            'title_translation_error' => (string) ($ticketDetail['title_translation_error'] ?? ''),
            'title_translation_error_detail' => (string) ($ticketDetail['title_translation_error_detail'] ?? ''),
            'messages' => $messages,
        ]);
    }

    if ($action !== '') {
        sendJson(422, [
            'success' => false,
            'error' => 'unknown_action',
            'action' => $action,
        ]);
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $category = trim((string) ($payload['category'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $userEmail = strtolower(trim((string) ($payload['user_email'] ?? '')));
    $priority = max(0, min(2, (int) ($payload['priority'] ?? 0)));
    $participantEmailsRaw = $payload['participant_emails'] ?? [];
    $participantEmails = [];

    if (is_string($participantEmailsRaw)) {
        $participantEmails = parseEmailListInput($participantEmailsRaw);
    } elseif (is_array($participantEmailsRaw)) {
        $participantEmails = parseEmailListInput(implode(',', array_map(static fn($value): string => (string) $value, $participantEmailsRaw)));
    }

    if ($userEmail === '') {
        $userEmail = strtolower(trim((string) ($payload['requester_email'] ?? '')));
    }

    // Met de persoonlijke login-key maak je alleen tickets op je eigen naam.
    if (is_array($apiClient ?? null) && ($apiClient['kind'] ?? '') === 'login_rotating') {
        $userEmail = (string) $apiClient['email'];
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
        $result = $store->createTicket($title, $category, $userEmail, $description, [], $priority, $participantEmails);
        $ticketId = (int) ($result['ticket_id'] ?? 0);
        $ticket = $store->getTicket($ticketId, true, '');

        sendJson(201, [
            'success' => true,
            'ticket_id' => $ticketId,
            'assigned_email' => $result['assigned_email'] ?? null,
            'ticket_url' => buildAsclepiusTicketUrl($ticketId),
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
}
