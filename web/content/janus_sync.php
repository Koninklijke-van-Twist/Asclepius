<?php

/**
 * Janus vacation/contract sync for Asclepius ICT availability.
 */

if (!defined('JANUS_HOURS_API_URL')) {
    define('JANUS_HOURS_API_URL', '');
}

/**
 * Resolve the Janus hours_api.php URL (localhost-first for trusted auth).
 */
function resolveJanusHoursApiUrl(): string
{
    $override = trim((string) JANUS_HOURS_API_URL);
    if ($override !== '') {
        return $override;
    }

    $htdocsRoot = dirname(__DIR__, 3);
    $siblingApi = $htdocsRoot . DIRECTORY_SEPARATOR . 'Janus' . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'hours_api.php';
    if (is_file($siblingApi)) {
        return 'http://127.0.0.1/Janus/web/hours_api.php';
    }

    $siblingApiLower = $htdocsRoot . DIRECTORY_SEPARATOR . 'janus' . DIRECTORY_SEPARATOR . 'hours_api.php';
    if (is_file($siblingApiLower)) {
        return 'http://127.0.0.1/janus/hours_api.php';
    }

    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === 'sleutels.kvt.nl') {
        return 'http://127.0.0.1/janus/hours_api.php';
    }

    return 'http://127.0.0.1/Janus/web/hours_api.php';
}

/**
 * @param list<string> $emails
 * @return array{ok: bool, date: string, weekday: string, users: array<string, array<string, mixed>>}|null
 */
function fetchJanusAwayStatus(array $emails, ?string $date = null): ?array
{
    static $cache = [];

    $normalizedEmails = [];
    foreach ($emails as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $normalizedEmails[$email] = $email;
        }
    }
    $normalizedEmails = array_values($normalizedEmails);
    if ($normalizedEmails === []) {
        return [
            'ok' => true,
            'date' => $date ?? (new DateTimeImmutable('today'))->format('Y-m-d'),
            'weekday' => strtolower((new DateTimeImmutable('today'))->format('l')),
            'users' => [],
        ];
    }

    sort($normalizedEmails, SORT_STRING);
    $dateKey = trim((string) ($date ?? ''));
    if ($dateKey === '') {
        $dateKey = (new DateTimeImmutable('today'))->format('Y-m-d');
    }
    $cacheKey = $dateKey . '|' . implode(',', $normalizedEmails);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $url = resolveJanusHoursApiUrl() . '?' . http_build_query([
        'date' => $dateKey,
        'emails' => implode(',', $normalizedEmails),
    ]);

    $body = null;
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $curlHandle = curl_init($url);
        if ($curlHandle !== false) {
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 3);
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $body = curl_exec($curlHandle);
            $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
            curl_close($curlHandle);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $matches) === 1) {
            $statusCode = (int) $matches[1];
        }
    }

    if ($body === false || $body === null || $statusCode < 200 || $statusCode >= 300) {
        $cache[$cacheKey] = null;

        return null;
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded) || empty($decoded['ok']) || !is_array($decoded['users'] ?? null)) {
        $cache[$cacheKey] = null;

        return null;
    }

    $cache[$cacheKey] = $decoded;

    return $decoded;
}

/**
 * @param array<string, bool> $storedAvailability
 * @param array{users?: array<string, array<string, mixed>}|null $janus
 * @return array{availability: array<string, bool>, locks: array<string, string>}
 */
function mergeIctAvailabilityWithJanus(array $storedAvailability, ?array $janus): array
{
    $availability = [];
    foreach ($storedAvailability as $email => $isAvailable) {
        $availability[strtolower((string) $email)] = (bool) $isAvailable;
    }

    $locks = [];
    if ($janus === null || !is_array($janus['users'] ?? null)) {
        return [
            'availability' => $availability,
            'locks' => $locks,
        ];
    }

    foreach ($janus['users'] as $email => $status) {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !is_array($status)) {
            continue;
        }
        if (empty($status['known']) || empty($status['locked'])) {
            continue;
        }

        $reason = trim((string) ($status['reason'] ?? ''));
        if ($reason === '') {
            $reason = !empty($status['holiday']) ? 'janus_holiday' : 'contract_off';
        }

        $availability[$email] = false;
        $locks[$email] = $reason;
    }

    return [
        'availability' => $availability,
        'locks' => $locks,
    ];
}

/**
 * Fetch Janus status, merge with stored availability, and apply forced-away on the store.
 *
 * @return array{
 *   availability: array<string, bool>,
 *   locks: array<string, string>,
 *   stored: array<string, bool>,
 *   connected: bool
 * }
 */
function applyJanusSyncToStore(?TicketStore $store, array $ictUsers, ?string $date = null): array
{
    $emails = function_exists('extractIctUserEmails')
        ? extractIctUserEmails($ictUsers)
        : array_values(array_filter(array_map(
            static fn(mixed $value): string => strtolower(trim((string) $value)),
            $ictUsers
        )));

    $stored = $store instanceof TicketStore
        ? $store->getIctUserAvailability()
        : array_fill_keys($emails, true);

    $janus = fetchJanusAwayStatus($emails, $date);
    $merged = mergeIctAvailabilityWithJanus($stored, $janus);

    if ($store instanceof TicketStore && method_exists($store, 'setForcedAwayEmails')) {
        $store->setForcedAwayEmails(array_keys($merged['locks']));
    }

    return [
        'availability' => $merged['availability'],
        'locks' => $merged['locks'],
        'stored' => $stored,
        'connected' => $janus !== null,
    ];
}

/**
 * Tooltip for a Janus lock reason.
 */
function janusAwayLockTooltip(string $reason, ?string $weekdayEnglish = null): string
{
    if ($reason === 'janus_holiday') {
        return (string) __('settings.vacation_locked_janus_tooltip');
    }

    if ($reason === 'contract_off') {
        $weekdayKey = 'weekday.' . strtolower(trim((string) $weekdayEnglish));
        $weekdayLabel = __($weekdayKey);
        if ($weekdayLabel === $weekdayKey || $weekdayLabel === '') {
            $weekdayLabel = strtolower(trim((string) $weekdayEnglish));
        }

        return (string) __('settings.vacation_locked_contract_tooltip', $weekdayLabel);
    }

    return (string) __('settings.vacation_locked_janus_tooltip');
}
