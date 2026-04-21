<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'TicketStore.php';

// Korte read-only peek in de sessie om te controleren of bigscreen-bypass van toepassing is.
// read_and_close voorkomt dat lib.php later een "session already active" notice krijgt.
$_bigscreenRequest = isset($_GET['bigscreen']) && (string) $_GET['bigscreen'] === 'true';
$_bigscreenAlreadyAuthenticated = false;

if ($_bigscreenRequest && session_status() !== PHP_SESSION_ACTIVE) {
    session_start(['read_and_close' => true]);
    $_bigscreenAlreadyAuthenticated =
        isset($_SESSION['user']['email'])
        && trim((string) $_SESSION['user']['email']) !== '';
}
unset($_bigscreenRequest);

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
