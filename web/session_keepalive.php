<?php

define('ASCLEPIUS_SESSION_KEEPALIVE', true);

require_once __DIR__ . '/content/bootstrap.php';
require_once __DIR__ . '/content/constants.php';

if (!isset($_SESSION['user']['email']) || trim((string) $_SESSION['user']['email']) === '') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'reason' => 'session_expired'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiClientOid = strtolower(trim((string) ($_SESSION['user']['oid'] ?? ($_SESSION['users']['oid'] ?? ''))));
$apiClientKey = '';
if ($apiClientOid !== '' && preg_match('/^[a-z0-9-]{8,128}$/', $apiClientOid) === 1) {
    $apiClientKey = hash('sha256', $apiClientOid . '|' . gmdate('d-m-Y'));
    $_SESSION['user']['api_key'] = $apiClientKey;
}

$_SESSION['last_keepalive_at'] = time();

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'api_key' => $apiClientKey,
], JSON_UNESCAPED_UNICODE);
exit;
