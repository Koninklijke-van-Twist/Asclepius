<?php
/**
 * Astropius API entry — does not own ticket data.
 * Loads Asclepius api.php so TicketStore + SQLite stay the live Asclepius store.
 */
declare(strict_types=1);

$configured = getenv('ASTROPIUS_ASCLEPIUS_WEB') ?: (string) ($_SERVER['ASTROPIUS_ASCLEPIUS_WEB'] ?? '');
$candidates = array_values(array_filter([
    $configured !== '' ? rtrim($configured, '/\\') : '',
    dirname(__DIR__) . '/asclepius',
    dirname(__DIR__) . '/Asclepius',
    // Local monorepo: bridge copied into astropius-dist/, Asclepius web is ../web
    dirname(__DIR__) . '/web',
    __DIR__ . '/../asclepius',
    __DIR__ . '/../web',
]));

$asclepiusApi = null;
foreach ($candidates as $root) {
    $apiPath = $root . DIRECTORY_SEPARATOR . 'api.php';
    if (is_file($apiPath)) {
        $asclepiusApi = $apiPath;
        break;
    }
}

if ($asclepiusApi === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'asclepius_api_not_found',
        'hint' => 'Set ASTROPIUS_ASCLEPIUS_WEB to the Asclepius web directory (the one with api.php and TicketStore.php).',
        'tried' => $candidates,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require $asclepiusApi;
