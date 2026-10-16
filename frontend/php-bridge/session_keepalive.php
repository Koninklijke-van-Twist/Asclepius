<?php
/**
 * Reuse Asclepius session keepalive against the live app session.
 */
declare(strict_types=1);

$configured = getenv('ASTROPIUS_ASCLEPIUS_WEB') ?: (string) ($_SERVER['ASTROPIUS_ASCLEPIUS_WEB'] ?? '');
$candidates = array_values(array_filter([
    $configured !== '' ? rtrim($configured, '/\\') : '',
    dirname(__DIR__) . '/asclepius',
    dirname(__DIR__) . '/web',
    __DIR__ . '/../asclepius',
    __DIR__ . '/../web',
]));

foreach ($candidates as $root) {
    $path = $root . DIRECTORY_SEPARATOR . 'session_keepalive.php';
    if (is_file($path)) {
        require $path;
        exit;
    }
}

http_response_code(500);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'error' => 'asclepius_keepalive_not_found']);
