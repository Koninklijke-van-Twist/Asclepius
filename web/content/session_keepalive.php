<?php

/**
 * Sessie-keepalive
 * Houdt een open tabblad ingelogd: timestamp in de sessie bijwerken en,
 * als de sessiecookie een eindtijd heeft, die levensduur verlengen.
 * Vereist een al gestarte sessie (logincheck / bootstrap).
 * Levert ook de eigenaar-sleutel waarmee bewaarde concepten in de browser
 * aan de ingelogde gebruiker gekoppeld worden.
 */

/**
 * Functions
 */

if (!function_exists('array_any')) {
    /**
     * Polyfill voor PHP < 8.4: true zodra één element aan de callback voldoet.
     */
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

/**
 * Zet de verse sessiecookie uit asclepiusSessionCookieRefresh(). false als
 * er niets te verlengen is of de headers al verstuurd zijn.
 *
 * @param array{name: string, value: string, expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}|null $cookie
 */
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

/**
 * Leest het servergeheim voor concept-eigenaarschap, of maakt het aan als het
 * nog niet bestaat. Het bestand is PHP dat meteen stopt, zodat een directe
 * HTTP-aanvraag het geheim niet toont. Leeg als lezen en aanmaken mislukken.
 */
function asclepiusSessionDraftSecret(string $path): string
{
    static $cache = [];
    if (isset($cache[$path])) {
        return $cache[$path];
    }

    $read = static function () use ($path): string {
        $raw = is_file($path) ? @file_get_contents($path) : false;
        if (!is_string($raw) || preg_match('/\b([a-f0-9]{64})\b/', $raw, $match) !== 1) {
            return '';
        }

        return $match[1];
    };

    $secret = $read();
    if ($secret === '') {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $handle = @fopen($path, 'x');
        if ($handle !== false) {
            $fresh = bin2hex(random_bytes(32));
            $written = fwrite($handle, "<?php\n// Geheim voor het koppelen van bewaarde concepten aan een gebruiker. Niet delen.\nhttp_response_code(404);\nexit;\n// " . $fresh . "\n");
            fclose($handle);
            if ($written !== false) {
                @chmod($path, 0640);
                $secret = $fresh;
            }
        } else {
            // Een gelijktijdig verzoek maakt het bestand net aan.
            for ($attempt = 0; $attempt < 5 && $secret === ''; $attempt++) {
                usleep(20000);
                clearstatcache(true, $path);
                $secret = $read();
            }
        }
    }

    if ($secret !== '') {
        $cache[$path] = $secret;
    }

    return $secret;
}

/**
 * Eigenaar-sleutel voor concepten die de browser bij een verlopen sessie
 * bewaart: HMAC-SHA256 van het genormaliseerde e-mailadres met een
 * servergeheim. Het e-mailadres staat zo nooit leesbaar in de browseropslag
 * en is niet met een lijst adressen terug te rekenen. Zonder geheim (map niet
 * schrijfbaar) valt de functie terug op een gewone SHA-256, zodat concepten
 * nog steeds per gebruiker blijven. Leeg zonder geldig e-mailadres.
 */
function asclepiusSessionDraftOwnerKey(string $email, ?string $secretFile = null): string
{
    $normalized = strtolower(trim($email));
    if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
        return '';
    }

    $path = $secretFile ?? (defined('SESSION_DRAFT_SECRET_FILE') ? (string) constant('SESSION_DRAFT_SECRET_FILE') : '');
    $secret = $path !== '' ? asclepiusSessionDraftSecret($path) : '';
    if ($secret === '') {
        return hash('sha256', 'asclepius-session-draft|' . $normalized);
    }

    return hash_hmac('sha256', $normalized, $secret);
}
