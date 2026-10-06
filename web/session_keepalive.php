<?php

/**
 * Includes/requires
 *
 * Licht endpoint: ververst alleen de PHP-sessie. Geen ticketdata en geen
 * secrets in het antwoord. logincheck.php slaat bij ASCLEPIUS_SESSION_KEEPALIVE
 * de Entra-redirect (login/lib.php) over en antwoordt 401 als de sessie leeg is.
 * De functies (inclusief array_any) laden vóór bootstrap, omdat keepalive
 * login/lib.php niet meeneemt.
 */

define('ASCLEPIUS_SESSION_KEEPALIVE', true);

require_once __DIR__ . '/content/session_keepalive.php';
require_once __DIR__ . '/content/bootstrap.php';

/**
 * Page load
 */

$sessionEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
if ($sessionEmail === '' || !filter_var($sessionEmail, FILTER_VALIDATE_EMAIL)) {
    asclepiusFinishKeepaliveResponse(401, [
        'ok' => false,
        'reason' => 'session_expired',
    ]);
}

$refreshedCookie = asclepiusTouchSessionKeepalive();
asclepiusApplySessionCookieRefresh($refreshedCookie);
session_write_close();
asclepiusFinishKeepaliveResponse(204);
