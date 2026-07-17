<?php

/**
 * Variables
 * Verwerkt request-context, sessie, store-initialisatie en filter-staat.
 * Vereist: constants.php, bootstrap.php, helpers.php
 */

function buildRotatingApiClientKey(string $oid): string
{
    $normalizedOid = strtolower(trim($oid));
    if ($normalizedOid === '') {
        return '';
    }

    return hash('sha256', $normalizedOid . '|' . gmdate('d-m-Y'));
}

if (!isset($ictUserColors) || !is_array($ictUserColors)) {
    $ictUserColors = [];
}
normalizeIctUsersConfig($ictUsers, $ictUserColors);

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
    $_SESSION['user']['admin'] = in_array(strtolower((string) $_SESSION['user']['email']), extractIctUserEmails($ictUsers), true);
}

$userEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? 'developer@kvt.nl')));
$userIsAdmin = (bool) ($_SESSION['user']['admin'] ?? false);
$canManageTickets = $isAdminPortal && $userIsAdmin;
$_SESSION['user']['email'] = $userEmail;
$_SESSION['user']['admin'] = $userIsAdmin;

$apiClientOid = strtolower(trim((string) ($_SESSION['user']['oid'] ?? ($_SESSION['users']['oid'] ?? ''))));
$apiClientKey = '';
if ($apiClientOid !== '' && preg_match('/^[a-z0-9-]{8,128}$/', $apiClientOid) === 1) {
    $apiClientKey = buildRotatingApiClientKey($apiClientOid);
    $_SESSION['user']['api_key'] = $apiClientKey;
    $apiClientsDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_clients';
    if (!is_dir($apiClientsDir)) {
        @mkdir($apiClientsDir, 0750, true);
    }

    if (is_dir($apiClientsDir) && is_writable($apiClientsDir)) {
        foreach ((array) glob($apiClientsDir . DIRECTORY_SEPARATOR . '*.json') as $existingApiClientFile) {
            if (!is_string($existingApiClientFile) || !is_file($existingApiClientFile)) {
                continue;
            }

            $existingApiClient = json_decode((string) file_get_contents($existingApiClientFile), true);
            if (!is_array($existingApiClient)) {
                continue;
            }

            $existingOid = strtolower(trim((string) ($existingApiClient['oid'] ?? '')));
            $existingApiKey = strtolower(trim((string) ($existingApiClient['api_key'] ?? '')));
            if ($existingOid === $apiClientOid && $existingApiKey !== strtolower($apiClientKey)) {
                @unlink($existingApiClientFile);
            }
        }

        $apiClientFile = $apiClientsDir . DIRECTORY_SEPARATOR . sha1($apiClientKey) . '.json';
        $apiClientBlob = [
            'oid' => $apiClientOid,
            'api_key' => $apiClientKey,
            'rotation_day' => gmdate('d-m-Y'),
            'email' => $userEmail,
            'is_admin' => $userIsAdmin,
            'updated_at' => gmdate('c'),
        ];
        @file_put_contents($apiClientFile, json_encode($apiClientBlob, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

$userPrefs = loadUserPrefs($userEmail);
$requestedView = trim((string) ($_GET['view'] ?? ''));
$isAllTicketsView = !$isAdminPortal && !$userIsAdmin && $requestedView === 'all_tickets';
$canUseTicketOverviewFilters = $canManageTickets || $isAllTicketsView;
$resetOverviewFilters = $canUseTicketOverviewFilters && isset($_GET['reset_filters']) && (string) $_GET['reset_filters'] === '1';
$savedOverviewFilters = $resetOverviewFilters
    ? [
        'status_filter_active' => false,
        'status_filters' => [],
        'category_filter_active' => false,
        'category_filters' => [],
        'assigned_filter' => '',
        'search_query' => '',
    ]
    : normalizeSavedTicketOverviewFilters($userPrefs);

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

$statusFilterRequestActive = false;
$statusFilters = [];
$effectiveStatusFilters = [];
$categoryFilterRequestActive = false;
$categoryFilters = [];
$effectiveCategoryFilters = [];
$assignedFilter = '';
$searchQuery = '';
$ticketOverviewFilterChangeRequested = $canUseTicketOverviewFilters
    && ($resetOverviewFilters || hasExplicitTicketFilterQueryParams());

if ($canUseTicketOverviewFilters) {
    if ($ticketOverviewFilterChangeRequested) {
        $statusFilterRequestActive = !$resetOverviewFilters
            && (isset($_GET['status_filter_mode']) || isset($_GET['status']));
        $statusFilters = $statusFilterRequestActive
            ? array_values(array_filter(
                array_map('trim', (array) ($_GET['status'] ?? [])),
                static fn(string $status): bool => in_array($status, TICKET_STATUSES, true)
            ))
            : [];

        $categoryFilterRequestActive = !$resetOverviewFilters
            && (isset($_GET['category_filter_mode']) || isset($_GET['category']));
        $categoryFilters = $categoryFilterRequestActive
            ? array_values(array_filter(
                array_map('trim', (array) ($_GET['category'] ?? [])),
                static fn(string $category): bool => in_array($category, TICKET_CATEGORIES, true)
            ))
            : [];

        $assignedFilter = !$resetOverviewFilters && array_key_exists('assigned', $_GET)
            ? trim((string) $_GET['assigned'])
            : '';
        $searchQuery = !$resetOverviewFilters && array_key_exists('search', $_GET)
            ? trim((string) $_GET['search'])
            : '';
    } else {
        $statusFilterRequestActive = !empty($savedOverviewFilters['status_filter_active']);
        $statusFilters = $statusFilterRequestActive
            ? array_values(array_filter(
                array_map('trim', (array) ($savedOverviewFilters['status_filters'] ?? [])),
                static fn(string $status): bool => in_array($status, TICKET_STATUSES, true)
            ))
            : [];

        $categoryFilterRequestActive = !empty($savedOverviewFilters['category_filter_active']);
        $categoryFilters = $categoryFilterRequestActive
            ? array_values(array_filter(
                array_map('trim', (array) ($savedOverviewFilters['category_filters'] ?? [])),
                static fn(string $category): bool => in_array($category, TICKET_CATEGORIES, true)
            ))
            : [];

        $assignedFilter = trim((string) ($savedOverviewFilters['assigned_filter'] ?? ''));
        $searchQuery = trim((string) ($savedOverviewFilters['search_query'] ?? ''));
    }

    $effectiveStatusFilters = $statusFilterRequestActive && $statusFilters === [] ? ['__no_matching_status__'] : $statusFilters;
    $effectiveCategoryFilters = $categoryFilterRequestActive && $categoryFilters === [] ? ['__no_matching_category__'] : $categoryFilters;

    $validAssignedFilters = array_merge(['', '__unassigned__'], extractIctUserEmails($ictUsers));
    if (!in_array($assignedFilter, $validAssignedFilters, true)) {
        $assignedFilter = '';
    }
}
if ($canManageTickets) {
    $view = in_array($requestedView, ['settings', 'stats', 'template_tickets', 'email_prefs', 'changelog', 'api'], true) ? $requestedView : 'overview';
} elseif ($isAllTicketsView) {
    $view = 'all_tickets';
} else {
    $view = 'overview';
}
$ticketBrowseMode = resolveTicketBrowseMode($canManageTickets, $isAllTicketsView);
$showTicketListSection = ($isAdminPortal && $view === 'overview')
    || $isAllTicketsView
    || (!$isAdminPortal && !$isAllTicketsView);
$ticketPage = $showTicketListSection ? max(1, (int) ($_GET['page'] ?? 1)) : 1;
$ticketsPerPageChanged = false;

if ($showTicketListSection && isset($_GET['per_page'])) {
    $normalizedTicketsPerPage = normalizeTicketsPerPage((int) $_GET['per_page']);
    if ($normalizedTicketsPerPage !== resolveTicketsPerPage($userPrefs)) {
        saveUserPref($userEmail, 'tickets_per_page', $normalizedTicketsPerPage);
        $userPrefs['tickets_per_page'] = $normalizedTicketsPerPage;
        $ticketsPerPageChanged = true;
    } else {
        $ticketsPerPageChanged = true;
    }
}

$ticketsPerPage = $showTicketListSection ? resolveTicketsPerPage($userPrefs) : DEFAULT_TICKETS_PER_PAGE;
$openTicketId = max(0, (int) ($_GET['open'] ?? 0));

if ($openTicketId > 0 && $store instanceof TicketStore) {
    maybeRedirectForOpenTicketLink($store, $userIsAdmin, $isAdminPortal, $openTicketId, $userEmail, $requestedView);
}

if ($openTicketId > 0 && $store instanceof TicketStore && isOpenTicketNavigationRequest()) {
    if (!validateOpenTicketLinkAccess($store, $openTicketId, $userIsAdmin, $isAdminPortal, $isAllTicketsView, $userEmail, $ticketBrowseMode)) {
        $openTicketId = 0;
        $flashMessages[] = [
            'type' => 'error',
            'message' => __('flash.ticket_link_unavailable'),
        ];
    }
}

if ($canUseTicketOverviewFilters && $ticketOverviewFilterChangeRequested) {
    saveUserPref($userEmail, 'ticket_overview_filters', [
        'status_filter_active' => $resetOverviewFilters ? false : $statusFilterRequestActive,
        'status_filters' => $resetOverviewFilters ? [] : $statusFilters,
        'category_filter_active' => $resetOverviewFilters ? false : $categoryFilterRequestActive,
        'category_filters' => $resetOverviewFilters ? [] : $categoryFilters,
        'assigned_filter' => $resetOverviewFilters ? '' : $assignedFilter,
        'search_query' => $resetOverviewFilters ? '' : $searchQuery,
    ]);
    $savedOverviewFilters = [
        'status_filter_active' => $resetOverviewFilters ? false : $statusFilterRequestActive,
        'status_filters' => $resetOverviewFilters ? [] : $statusFilters,
        'category_filter_active' => $resetOverviewFilters ? false : $categoryFilterRequestActive,
        'category_filters' => $resetOverviewFilters ? [] : $categoryFilters,
        'assigned_filter' => $resetOverviewFilters ? '' : $assignedFilter,
        'search_query' => $resetOverviewFilters ? '' : $searchQuery,
    ];
}

$overviewListView = $isAllTicketsView ? 'all_tickets' : 'overview';

if (
    isTicketOverviewListRequest()
    && $showTicketListSection
    && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST'
    && ($ticketOverviewFilterChangeRequested || $ticketsPerPageChanged || $resetOverviewFilters)
) {
    // Persist filter/per-page changes in prefs, then continue on a clean location URL.
    $cleanPage = $ticketOverviewFilterChangeRequested || $resetOverviewFilters ? 1 : $ticketPage;
    redirectToPage($currentPage, buildTicketListLocationQuery(
        $overviewListView,
        $isAdminPortal,
        $openTicketId,
        $cleanPage
    ));
}

$ticketListNavigationQuery = buildTicketListLocationQuery(
    $overviewListView,
    $isAdminPortal,
    $openTicketId,
    1
);

$baseQuery = buildTicketListLocationQuery(
    $view,
    $isAdminPortal,
    $openTicketId,
    $ticketPage
);
