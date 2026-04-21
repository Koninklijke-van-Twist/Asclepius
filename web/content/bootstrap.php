<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logincheck.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'TicketStore.php';

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
