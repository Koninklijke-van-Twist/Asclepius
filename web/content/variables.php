<?php

/**
 * Variables
 * Verwerkt request-context, sessie, store-initialisatie en filter-staat.
 * Vereist: constants.php, bootstrap.php, helpers.php
 */

function buildWeeklyApiClientKey(string $oid): string
{
    $normalizedOid = strtolower(trim($oid));
    if ($normalizedOid === '') {
        return '';
    }

    return hash('sha256', $normalizedOid . '|' . gmdate('o-W'));
}

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

$apiClientOid = strtolower(trim((string) ($_SESSION['user']['oid'] ?? ($_SESSION['users']['oid'] ?? ''))));
$apiClientKey = '';
if ($apiClientOid !== '' && preg_match('/^[a-z0-9-]{8,128}$/', $apiClientOid) === 1) {
    $apiClientKey = buildWeeklyApiClientKey($apiClientOid);
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
            'rotation_week' => gmdate('o-W'),
            'email' => $userEmail,
            'is_admin' => $userIsAdmin,
            'updated_at' => gmdate('c'),
        ];
        @file_put_contents($apiClientFile, json_encode($apiClientBlob, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

$userPrefs = loadUserPrefs($userEmail);
$resetOverviewFilters = $canManageTickets && isset($_GET['reset_filters']) && (string) $_GET['reset_filters'] === '1';
$savedOverviewFilters = $canManageTickets && !$resetOverviewFilters
    ? normalizeSavedTicketOverviewFilters($userPrefs)
    : [
        'status_filter_active' => false,
        'status_filters' => [],
        'category_filter_active' => false,
        'category_filters' => [],
        'assigned_filter' => '',
    ];

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

$statusFilterRequestActive = $canManageTickets
    ? (isset($_GET['status_filter_mode']) || isset($_GET['status'])
        ? true
        : $savedOverviewFilters['status_filter_active'])
    : false;
$statusFilters = $statusFilterRequestActive
    ? array_values(array_filter(
        array_map('trim', (array) ((isset($_GET['status_filter_mode']) || isset($_GET['status'])) ? ($_GET['status'] ?? []) : $savedOverviewFilters['status_filters'])),
        static fn(string $status): bool => in_array($status, TICKET_STATUSES, true)
    ))
    : [];
$effectiveStatusFilters = $statusFilterRequestActive && $statusFilters === [] ? ['__no_matching_status__'] : $statusFilters;

$categoryFilterRequestActive = $canManageTickets
    ? (isset($_GET['category_filter_mode']) || isset($_GET['category'])
        ? true
        : $savedOverviewFilters['category_filter_active'])
    : false;
$categoryFilters = $categoryFilterRequestActive
    ? array_values(array_filter(
        array_map('trim', (array) ((isset($_GET['category_filter_mode']) || isset($_GET['category'])) ? ($_GET['category'] ?? []) : $savedOverviewFilters['category_filters'])),
        static fn(string $category): bool => in_array($category, TICKET_CATEGORIES, true)
    ))
    : [];
$effectiveCategoryFilters = $categoryFilterRequestActive && $categoryFilters === [] ? ['__no_matching_category__'] : $categoryFilters;

$assignedFilter = $canManageTickets
    ? trim((string) (array_key_exists('assigned', $_GET) ? $_GET['assigned'] : $savedOverviewFilters['assigned_filter']))
    : '';
$validAssignedFilters = array_merge(['', '__unassigned__'], array_map('strtolower', $ictUsers));
if (!in_array($assignedFilter, $validAssignedFilters, true)) {
    $assignedFilter = '';
}
$requestedView = trim((string) ($_GET['view'] ?? ''));
$view = $canManageTickets && in_array($requestedView, ['settings', 'stats'], true) ? $requestedView : 'overview';
$openTicketId = max(0, (int) ($_GET['open'] ?? 0));

if ($canManageTickets) {
    saveUserPref($userEmail, 'ticket_overview_filters', [
        'status_filter_active' => $resetOverviewFilters ? false : $statusFilterRequestActive,
        'status_filters' => $resetOverviewFilters ? [] : $statusFilters,
        'category_filter_active' => $resetOverviewFilters ? false : $categoryFilterRequestActive,
        'category_filters' => $resetOverviewFilters ? [] : $categoryFilters,
        'assigned_filter' => $resetOverviewFilters ? '' : $assignedFilter,
    ]);
}

$baseQuery = buildNavigationQuery(
    $statusFilters,
    $categoryFilters,
    $assignedFilter,
    $view,
    $isAdminPortal,
    $statusFilterRequestActive,
    $categoryFilterRequestActive
);
