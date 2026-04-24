<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

// Start output buffering vroeg zodat PHP-notices of whitespace de response niet corrumperen
// (met name belangrijk voor binaire downloads via ?download=)
ob_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'TicketStore.php';

// Korte read-only peek in de sessie om te controleren of async-bypass van toepassing is.
// read_and_close voorkomt dat lib.php later een "session already active" notice krijgt.
$_bigscreenRequest = isset($_GET['bigscreen']) && (string) $_GET['bigscreen'] === 'true';
$_asyncSessionBypassRequest =
    $_bigscreenRequest
    || isset($_GET['_bigscreen_poll'])
    || isset($_GET['_bigscreen_version'])
    || isset($_GET['_browser_notifications_poll'])
    || isset($_GET['_webpush_subscription'])
    || isset($_GET['_tickets_poll'])
    || (isset($_GET['_partial']) && (string) $_GET['_partial'] === 'tickets');
$_remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$_serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
$_trustedAsyncRequester = ($_remoteAddr !== '' && $_remoteAddr === $_serverAddr)
    || in_array($_remoteAddr, ['127.0.0.1', '::1'], true);
$_bigscreenAlreadyAuthenticated = false;

if ($_asyncSessionBypassRequest && session_status() !== PHP_SESSION_ACTIVE) {
    session_start(['read_and_close' => true]);
    $_bigscreenAlreadyAuthenticated =
        isset($_SESSION['user']['email'])
        && trim((string) $_SESSION['user']['email']) !== '';
}

if ($_asyncSessionBypassRequest && !$_bigscreenAlreadyAuthenticated && !$_trustedAsyncRequester) {
    http_response_code(401);

    if (isset($_GET['_partial']) && (string) ($_GET['_partial'] ?? '') === 'tickets') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="empty-state">Sessie verlopen. Ververs de pagina.</div>';
        exit;
    }

    if (isset($_GET['_bigscreen_version'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'unauthorized';
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'unauthorized',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

unset($_bigscreenRequest);
unset($_asyncSessionBypassRequest);
unset($_remoteAddr);
unset($_serverAddr);
unset($_trustedAsyncRequester);

if (!$_bigscreenAlreadyAuthenticated) {
    // Normale flow: logincheck (en lib.php daarin) start zelf de sessie
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logincheck.php';
}
unset($_bigscreenAlreadyAuthenticated);

// Sessie nu definitief starten (lib.php heeft dit al gedaan in de normale flow,
// of we doen het zelf voor de bigscreen-bypass-flow)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
    $sessionCookieName = session_name();
    if (!isset($_COOKIE[$sessionCookieName])) {
        setcookie($sessionCookieName, session_id(), [
            'expires' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$sessionCookieName] = session_id();
    }
}

if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $value) {
            if ($callback($value)) {
                return true;
            }
        }

        return false;
    }
}
