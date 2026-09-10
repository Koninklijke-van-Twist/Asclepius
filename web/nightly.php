<?php

/**
 * Nightly endpoint (GET).
 * Called by an external scheduler around 01:00 for daily maintenance tasks (theevraagje).
 * Open-ticket trend snapshots are collected hourly via hourly.php.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TicketStore.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'constants.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'method_not_allowed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!isset($ictUsers) || !is_array($ictUsers)) {
        $ictUsers = [];
    }

    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers, TICKET_CATEGORIES);
    $theevraagje = $store->refreshTheevraagje();

    echo json_encode([
        'success' => true,
        'recorded_at' => date('c'),
        'theevraagje' => [
            'success' => !empty($theevraagje['success']),
            'fetched' => !empty($theevraagje['fetched']),
            'cleared_messages' => !empty($theevraagje['cleared_messages']),
            'error' => (string) ($theevraagje['error'] ?? ''),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'nightly_failed',
        'details' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
