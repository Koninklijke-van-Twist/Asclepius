<?php

/**
 * Variables
 * Verwerkt request-context, sessie, store-initialisatie en filter-staat.
 * Vereist: constants.php, bootstrap.php, helpers.php
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
