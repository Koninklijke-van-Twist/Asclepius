<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'TicketStore.php';

// Sessie starten vóór logincheck zodat we de bestaande state kunnen inspecteren
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

// Bigscreen-bypass: als de gebruiker al eerder heeft ingelogd (sessie heeft e-mail)
// én we zijn in bigscreen-modus, dan slaan we de logincheck over zodat de pagina
// zichzelf kan herladen zonder opnieuw door de auth-flow te gaan.
$_bigscreenAlreadyAuthenticated =
    isset($_GET['bigscreen']) && (string) $_GET['bigscreen'] === 'true'
    && isset($_SESSION['user']['email'])
    && trim((string) $_SESSION['user']['email']) !== '';

if (!$_bigscreenAlreadyAuthenticated) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logincheck.php';
}
unset($_bigscreenAlreadyAuthenticated);

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
