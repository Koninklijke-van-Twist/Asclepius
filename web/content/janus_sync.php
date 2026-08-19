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

    $decoded = janusHoursApiRequest($url);
    $cache[$cacheKey] = $decoded;

    return $decoded;
}

/**
 * @return array{ok: bool, date?: string, weekday?: string, users: array<string, array<string, mixed>>}|null
 */
function janusHoursApiRequest(string $url): ?array
{
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
        return null;
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded) || empty($decoded['ok']) || !is_array($decoded['users'] ?? null)) {
        return null;
    }

    return $decoded;
}

/**
 * Fetch Janus presence rows for the Asclepius sidebar (visible full-tracker users only).
 *
 * @param list<string> $emails Optional filter; empty = all Janus users with data
 * @return list<array{email: string, name: string, status: string, holidayUntil: string|null, startTime: string|null, endTime: string|null}>|null
 */
function fetchJanusPresence(?array $emails = null, ?string $date = null): ?array
{
    static $cache = [];

    $normalizedEmails = [];
    foreach ($emails ?? [] as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $normalizedEmails[$email] = $email;
        }
    }
    $normalizedEmails = array_values($normalizedEmails);
    sort($normalizedEmails, SORT_STRING);

    $dateKey = trim((string) ($date ?? ''));
    if ($dateKey === '') {
        $dateKey = (new DateTimeImmutable('today'))->format('Y-m-d');
    }
    $cacheKey = 'presence|' . $dateKey . '|' . implode(',', $normalizedEmails);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $query = [
        'action' => 'presence',
        'date' => $dateKey,
    ];
    if ($normalizedEmails !== []) {
        $query['emails'] = implode(',', $normalizedEmails);
    }
    $decoded = janusHoursApiRequest(resolveJanusHoursApiUrl() . '?' . http_build_query($query));
    if ($decoded === null) {
        $cache[$cacheKey] = null;

        return null;
    }

    $rows = [];
    foreach ($decoded['users'] as $email => $status) {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !is_array($status) || empty($status['visible'])) {
            continue;
        }
        $statusKey = trim((string) ($status['status'] ?? ''));
        if ($statusKey === '') {
            continue;
        }
        $name = trim((string) ($status['name'] ?? ''));
        if ($name === '' && function_exists('formatUserDisplayName')) {
            $name = formatUserDisplayName($email);
        }
        if ($name === '') {
            $name = $email;
        }
        $holidayUntil = isset($status['holidayUntil']) ? trim((string) $status['holidayUntil']) : '';
        if ($statusKey === 'holiday') {
            $holidayUntil = expandJanusHolidayUntilIncludingContractFreeDays($email, $dateKey, $holidayUntil);
        }
        $rows[] = [
            'email' => $email,
            'name' => $name,
            'status' => $statusKey,
            'holidayUntil' => $holidayUntil !== '' ? $holidayUntil : null,
            'startTime' => isset($status['startTime']) ? (string) $status['startTime'] : null,
            'endTime' => isset($status['endTime']) ? (string) $status['endTime'] : null,
            'office' => !empty($status['office']),
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return strcasecmp((string) $a['name'], (string) $b['name']);
    });

    $cache[$cacheKey] = $rows;

    return $rows;
}

function expandJanusHolidayUntilIncludingContractFreeDays(string $email, string $startDate, string $holidayUntil): string
{
    $email = strtolower(trim($email));
    $startDate = trim($startDate);
    $holidayUntil = trim($holidayUntil);
    if ($email === '' || $startDate === '') {
        return $holidayUntil;
    }

    try {
        $cursor = new DateTimeImmutable($holidayUntil !== '' ? $holidayUntil : $startDate);
    } catch (Throwable $exception) {
        try {
            $cursor = new DateTimeImmutable($startDate);
        } catch (Throwable $innerException) {
            return $holidayUntil;
        }
    }

    $lastMatchingDate = $cursor->format('Y-m-d');
    for ($dayOffset = 0; $dayOffset < 31; $dayOffset++) {
        $candidate = $cursor->modify('+1 day');
        if (!$candidate instanceof DateTimeImmutable) {
            break;
        }

        $candidateDate = $candidate->format('Y-m-d');
        $away = fetchJanusAwayStatus([$email], $candidateDate);
        $userStatus = is_array($away['users'][$email] ?? null) ? $away['users'][$email] : null;
        if (!is_array($userStatus) || empty($userStatus['known']) || empty($userStatus['locked'])) {
            break;
        }

        $reason = trim((string) ($userStatus['reason'] ?? ''));
        $isHoliday = !empty($userStatus['holiday']) || $reason === 'janus_holiday';
        $isContractFree = $reason === 'contract_off'
            || (
                empty($userStatus['holiday'])
                && empty($userStatus['sick'])
                && !empty($userStatus['locked'])
            );
        if (!$isHoliday && !$isContractFree) {
            break;
        }

        $lastMatchingDate = $candidateDate;
        $cursor = $candidate;
    }

    return $lastMatchingDate;
}

function janusPresenceStatusLabel(string $status): string
{
    $key = 'presence.status.' . $status;
    $label = __($key);

    return $label === $key ? $status : $label;
}

function janusPresenceStatusDetail(array $row): string
{
    $status = (string) ($row['status'] ?? '');
    if ($status === 'holiday') {
        $until = trim((string) ($row['holidayUntil'] ?? ''));
        if ($until !== '') {
            return (string) __('presence.holiday_until', formatDisplayDate($until));
        }
    }
    if (in_array($status, ['present_office', 'present_home', 'absent'], true)) {
        $start = trim((string) ($row['startTime'] ?? ''));
        $end = trim((string) ($row['endTime'] ?? ''));
        if ($start !== '' && $end !== '') {
            return $start . ' – ' . $end;
        }
    }

    return '';
}

/**
 * @param array<string, mixed> $row
 * @return array{email: string, name: string, status: string, label: string, detail: string}
 */
function mapJanusPresenceDisplayRow(array $row): array
{
    return [
        'email' => (string) ($row['email'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'label' => janusPresenceStatusLabel((string) ($row['status'] ?? '')),
        'detail' => janusPresenceStatusDetail($row),
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function sortJanusPresenceRowsByName(array $rows): array
{
    usort($rows, static function (array $left, array $right): int {
        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $rows;
}

/**
 * Group Janus presence: global admins under ICT, then each role that has members in Janus.
 *
 * @param list<array<string, mixed>>|null $rows
 * @return list<array{id: string, name: string, rows: list<array<string, mixed>>}>
 */
function groupJanusPresenceRows(?array $rows, ?TicketStore $store, array $ictUsers): array
{
    $byEmail = [];
    foreach ($rows ?? [] as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '') {
            continue;
        }
        $byEmail[$email] = $row;
    }

    $claimed = [];
    $takeEmails = static function (array $emails) use (&$byEmail, &$claimed): array {
        $picked = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || isset($claimed[$email]) || !isset($byEmail[$email])) {
                continue;
            }
            $claimed[$email] = true;
            $picked[] = $byEmail[$email];
        }

        return sortJanusPresenceRowsByName($picked);
    };

    $groups = [];
    $ictRows = $takeEmails(getFullIctAdminEmailsFromConfig($ictUsers));
    if ($ictRows !== []) {
        $groups[] = [
            'id' => 'ict',
            'name' => (string) __('ticket.role_admin'),
            'rows' => $ictRows,
        ];
    }

    if ($store instanceof TicketStore) {
        foreach ($store->listIctRoles() as $role) {
            $roleId = (int) ($role['id'] ?? 0);
            $roleName = trim((string) ($role['name'] ?? ''));
            if ($roleId <= 0 || $roleName === '') {
                continue;
            }
            $roleRows = $takeEmails($store->listIctRoleMemberEmails($roleId));
            if ($roleRows === []) {
                continue;
            }
            $groups[] = [
                'id' => 'role-' . $roleId,
                'name' => $roleName,
                'rows' => $roleRows,
            ];
        }
    }

    return $groups;
}

/**
 * @param list<array{id: string, name: string, rows: list<array<string, mixed>>}> $groups
 * @return list<array{id: string, name: string, rows: list<array{email: string, name: string, status: string, label: string, detail: string}>}>
 */
function mapJanusPresenceGroupsForDisplay(array $groups): array
{
    $mapped = [];
    foreach ($groups as $group) {
        $mapped[] = [
            'id' => (string) ($group['id'] ?? ''),
            'name' => (string) ($group['name'] ?? ''),
            'rows' => array_map('mapJanusPresenceDisplayRow', is_array($group['rows'] ?? null) ? $group['rows'] : []),
        ];
    }

    return $mapped;
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
            if (!empty($status['holiday'])) {
                $reason = 'janus_holiday';
            } elseif (!empty($status['sick'])) {
                $reason = 'janus_sick';
            } else {
                $reason = 'contract_off';
            }
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

    if ($reason === 'janus_sick') {
        return (string) __('settings.vacation_locked_janus_sick_tooltip');
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
