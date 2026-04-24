<?php

/**
 * Data
 * Haalt alle weergavedata op uit de store en verwerkt de bigscreen poll (JSON, exit).
 * Vereist: variables.php, helpers.php
 */

/**
 * Functies
 */

function buildLocalApiUrl(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $serverAddress = trim((string) ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1'));
    if ($serverAddress === '') {
        $serverAddress = '127.0.0.1';
    }

    $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    $defaultPort = $scheme === 'https' ? 443 : 80;
    $portSuffix = ($serverPort > 0 && $serverPort !== $defaultPort) ? ':' . $serverPort : '';
    $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['PHP_SELF'] ?? '/index.php'))), '/.');

    return $scheme . '://' . $serverAddress . $portSuffix . ($basePath !== '' ? $basePath : '') . '/' . ltrim($path, '/');
}

function postLocalApiJson(string $path, array $payload): ?array
{
    $url = buildLocalApiUrl($path);
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($jsonPayload)) {
        return null;
    }

    $hostHeader = 'Host: ' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($jsonPayload),
        $hostHeader,
    ];
    $isHttps = str_starts_with($url, 'https://');

    if (function_exists('curl_init')) {
        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            return null;
        }

        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
        if ($isHttps) {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $body = curl_exec($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        curl_close($curlHandle);

        if (!is_string($body) || $statusCode <= 0) {
            return null;
        }

        return [
            'status' => $statusCode,
            'body' => $body,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $jsonPayload,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => !$isHttps,
            'verify_peer_name' => !$isHttps,
            'allow_self_signed' => $isHttps,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if (!is_string($body)) {
        return null;
    }

    $statusCode = 200;
    foreach ($http_response_header ?? [] as $responseHeader) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string) $responseHeader, $matches) === 1) {
            $statusCode = (int) ($matches[1] ?? 200);
            break;
        }
    }

    return [
        'status' => $statusCode,
        'body' => $body,
    ];
}

/**
 * Page load
 */

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
$ticketSnapshotSignature = buildTicketSnapshotSignature($tickets);

if (isset($_GET['_webpush_subscription'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$store instanceof TicketStore) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'store_unavailable'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $payload = json_decode((string) $rawInput, true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $postedCsrfToken = (string) ($payload['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrfToken)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'invalid_csrf'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = trim((string) ($payload['action'] ?? 'subscribe'));
    $subscription = is_array($payload['subscription'] ?? null) ? $payload['subscription'] : [];
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));

    if ($action === 'unsubscribe') {
        if ($endpoint !== '') {
            $store->removeWebPushSubscription($userEmail, $endpoint);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
    $p256dhKey = trim((string) ($keys['p256dh'] ?? ''));
    $authKey = trim((string) ($keys['auth'] ?? ''));

    $store->saveWebPushSubscription(
        $userEmail,
        $endpoint,
        $p256dhKey,
        $authKey,
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
    );

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['_browser_notifications_poll'])) {
    $apiResponse = postLocalApiJson('api.php', [
        'action' => 'browser_notifications_poll',
        'viewer_email' => $userEmail,
        'user_is_admin' => $userIsAdmin,
    ]);

    if (is_array($apiResponse) && (int) ($apiResponse['status'] ?? 0) >= 200 && (int) ($apiResponse['status'] ?? 0) < 300) {
        http_response_code((int) ($apiResponse['status'] ?? 200));
        header('Content-Type: application/json; charset=utf-8');
        echo (string) ($apiResponse['body'] ?? '');
        exit;
    }

    $notificationItems = $store instanceof TicketStore ? $store->pullBrowserNotifications($userEmail, 25) : [];
    $targetPage = $userIsAdmin ? 'admin.php' : 'index.php';

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
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
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['_tickets_poll'])) {
    $apiResponse = postLocalApiJson('api.php', [
        'action' => 'ticket_poll',
        'current_page' => $currentPage,
        'viewer_email' => $userEmail,
        'can_manage_tickets' => $canManageTickets,
        'user_is_admin' => $userIsAdmin,
        'is_admin_portal' => $isAdminPortal,
        'csrf_token' => $csrfToken,
        'open_ticket_id' => $openTicketId,
        'view' => $view,
        'assigned_filter' => $assignedFilter,
        'status_filters' => $effectiveStatusFilters,
        'category_filters' => $effectiveCategoryFilters,
    ]);

    if (is_array($apiResponse) && (int) ($apiResponse['status'] ?? 0) >= 200 && (int) ($apiResponse['status'] ?? 0) < 300) {
        http_response_code((int) ($apiResponse['status'] ?? 200));
        header('Content-Type: application/json; charset=utf-8');
        echo (string) ($apiResponse['body'] ?? '');
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'signature' => $ticketSnapshotSignature,
        'tickets' => array_map(function (array $ticket) use ($store, $canManageTickets, $userEmail, $currentPage, $userIsAdmin, $isAdminPortal, $ictUsers, $csrfToken, $openTicketId, $view): array {
            $ticketDetail = $store instanceof TicketStore ? $store->getTicket((int) $ticket['id'], $canManageTickets, $userEmail) : null;

            return buildTicketPollEntry($ticket, $ticketDetail, [
                'currentPage' => $currentPage,
                'canManageTickets' => $canManageTickets,
                'userIsAdmin' => $userIsAdmin,
                'isAdminPortal' => $isAdminPortal,
                'ictUsers' => $ictUsers,
                'csrfToken' => $csrfToken,
                'openTicketId' => $openTicketId,
                'view' => $view,
            ]);
        }, $tickets),
        'is_empty' => $tickets === [],
        'empty_html' => '<div class="empty-state">' . ($isAdminPortal ? h(__('tickets.empty_admin')) : h(__('tickets.empty_user'))) . '</div>',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['_partial']) && (string) $_GET['_partial'] === 'tickets') {
    ob_start();
    require __DIR__ . '/views/view_tickets.php';
    $ticketSectionHtml = ob_get_clean();

    header('Content-Type: text/html; charset=utf-8');
    echo $ticketSectionHtml;
    exit;
}

if ($canManageTickets && $view === 'stats' && isset($_GET['_bigscreen_version'])) {
    $versionFiles = [
        dirname(__DIR__) . '/index.php',
        __FILE__,
        __DIR__ . '/views/bigscreen_js.php',
        __DIR__ . '/views/page_js.php',
    ];

    $versionSource = [];
    foreach ($versionFiles as $versionFile) {
        if (is_file($versionFile)) {
            $versionSource[] = basename($versionFile) . ':' . (string) filemtime($versionFile);
        }
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo sha1(implode('|', $versionSource));
    exit;
}

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
                'status_label' => translateStatus((string) ($t['status'] ?? '')),
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
            'average_wait' => formatDurationSeconds($r['average_wait_seconds'] ?? null),
            'max_wait' => formatDurationSeconds($r['max_wait_seconds'] ?? null),
            'average_response' => formatDurationSeconds($r['average_response_seconds'] ?? null),
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
