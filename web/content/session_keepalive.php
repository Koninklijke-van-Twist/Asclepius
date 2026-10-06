<?php

/**
 * Sessie-keepalive
 * Houdt een open tabblad ingelogd: timestamp in de sessie bijwerken en,
 * als de sessiecookie een eindtijd heeft, die levensduur verlengen.
 * Vereist een al gestarte sessie (logincheck / bootstrap).
 */

/**
 * Functions
 */

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

/**
 * Berekent een verse sessiecookie. null als de cookie alleen tot het
 * sluiten van de browser leeft (lifetime 0): daar is niets te verlengen.
 *
 * @return array{name: string, value: string, expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}|null
 */
function asclepiusSessionCookieRefresh(int $now): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $params = session_get_cookie_params();
    $lifetime = (int) ($params['lifetime'] ?? 0);
    if ($lifetime <= 0) {
        return null;
    }

    $name = session_name();
    $id = session_id();
    if ($name === '' || $id === '') {
        return null;
    }

    $path = (string) ($params['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }

    $sameSite = trim((string) ($params['samesite'] ?? ''));
    if ($sameSite === '') {
        $sameSite = 'Lax';
    }

    return [
        'name' => $name,
        'value' => $id,
        'expires' => $now + $lifetime,
        'path' => $path,
        'domain' => (string) ($params['domain'] ?? ''),
        'secure' => !empty($params['secure']),
        'httponly' => array_key_exists('httponly', $params) ? (bool) $params['httponly'] : true,
        'samesite' => $sameSite,
    ];
}

function asclepiusApplySessionCookieRefresh(?array $cookie): bool
{
    if ($cookie === null || headers_sent()) {
        return false;
    }

    return setcookie((string) $cookie['name'], (string) $cookie['value'], [
        'expires' => (int) $cookie['expires'],
        'path' => (string) $cookie['path'],
        'domain' => (string) $cookie['domain'],
        'secure' => (bool) $cookie['secure'],
        'httponly' => (bool) $cookie['httponly'],
        'samesite' => (string) $cookie['samesite'],
    ]);
}

/**
 * Schrijft een timestamp zodat de sessieopslag (en gc_maxlifetime) de
 * sessie als recent ziet. Geeft de cookie-verversing terug, of null.
 */
function asclepiusTouchSessionKeepalive(?int $now = null): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $now = $now ?? time();
    $_SESSION['last_keepalive_at'] = $now;

    return asclepiusSessionCookieRefresh($now);
}

/**
 * @param array<string, mixed>|null $payload
 */
function asclepiusFinishKeepaliveResponse(int $statusCode, ?array $payload = null): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if ($statusCode === 204 || $payload === null) {
        ini_set('default_mimetype', '');
        header_remove('Content-Type');
        header('Content-Length: 0');
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
