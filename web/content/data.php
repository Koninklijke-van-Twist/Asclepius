<?php

/**
 * Data
 * Haalt alle weergavedata op uit de store en verwerkt de bigscreen poll (JSON, exit).
 * Vereist: variables.php, helpers.php
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

if (isset($_GET['_tickets_poll'])) {
    $ticketPollItems = [];
    foreach ($tickets as $ticket) {
        $ticketDetail = $store instanceof TicketStore ? $store->getTicket((int) $ticket['id'], $canManageTickets, $userEmail) : null;
        $ticketPollItems[] = buildTicketPollEntry($ticket, $ticketDetail, [
            'currentPage' => $currentPage,
            'canManageTickets' => $canManageTickets,
            'userIsAdmin' => $userIsAdmin,
            'isAdminPortal' => $isAdminPortal,
            'ictUsers' => $ictUsers,
            'csrfToken' => $csrfToken,
            'openTicketId' => $openTicketId,
            'view' => $view,
        ]);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'signature' => $ticketSnapshotSignature,
        'tickets' => $ticketPollItems,
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
