<?php

const USER_DIRECTORY_PERSISTENT_CACHE_FILE = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'user_directory_emails.json';

function normalizeDirectoryEmail(string $email): string
{
    return strtolower(trim($email));
}

function getUserDirectoryPersistentCachePath(): string
{
    return USER_DIRECTORY_PERSISTENT_CACHE_FILE;
}

function loadUserDirectoryPersistentCache(): array
{
    static $loaded = false;
    static $cache = [];

    if ($loaded) {
        return $cache;
    }

    $loaded = true;
    $path = getUserDirectoryPersistentCachePath();
    if (!is_file($path)) {
        return $cache;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return $cache;
    }

    foreach ($decoded as $email => $entry) {
        $normalizedEmail = normalizeDirectoryEmail((string) $email);
        if ($normalizedEmail === '' || !is_array($entry)) {
            continue;
        }

        $name = trim((string) ($entry['name'] ?? ''));
        if ($name !== '') {
            $cache[$normalizedEmail] = $name;
        }
    }

    return $cache;
}

function saveUserDirectoryPersistentCache(array $emailToName): void
{
    $path = getUserDirectoryPersistentCachePath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $payload = [];
    foreach ($emailToName as $email => $name) {
        $normalizedEmail = normalizeDirectoryEmail((string) $email);
        $normalizedName = trim((string) $name);
        if ($normalizedEmail === '' || $normalizedName === '') {
            continue;
        }

        $payload[$normalizedEmail] = [
            'name' => $normalizedName,
            'updated_at' => gmdate('c'),
        ];
    }

    file_put_contents(
        $path,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function rememberUserDirectoryName(string $email, string $name): void
{
    $normalizedEmail = normalizeDirectoryEmail($email);
    $normalizedName = trim($name);
    if ($normalizedEmail === '' || $normalizedName === '') {
        return;
    }

    $runtimeMap = &getUserDirectoryRuntimeMapRef();
    $runtimeMap[$normalizedEmail] = $normalizedName;

    $persistent = loadUserDirectoryPersistentCache();
    if (($persistent[$normalizedEmail] ?? '') === $normalizedName) {
        return;
    }

    $persistent[$normalizedEmail] = $normalizedName;
    saveUserDirectoryPersistentCache($persistent);
}

function &getUserDirectoryRuntimeMapRef(): array
{
    static $runtimeMap = null;
    if (!is_array($runtimeMap)) {
        $runtimeMap = loadUserDirectoryPersistentCache();
    }

    return $runtimeMap;
}

function getUserDisplayNameMap(): array
{
    return getUserDirectoryRuntimeMapRef();
}

function buildEmailToNameMapFromGraphUsers(array $graphUsers): array
{
    $map = [];

    foreach ($graphUsers as $user) {
        if (!is_array($user)) {
            continue;
        }

        $email = normalizeDirectoryEmail((string) ($user['Email'] ?? ''));
        $name = trim((string) ($user['Naam'] ?? ''));
        if ($email === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $map[$email] = $name;
    }

    return $map;
}

function loadGraphUserDirectory(): array
{
    static $graphUsers = null;
    if ($graphUsers !== null) {
        return $graphUsers;
    }

    $graphUsers = [];

    try {
        global $graphCredentials;

        if (!isset($graphCredentials) || !is_array($graphCredentials)) {
            $authPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'auth.php';
            if (!is_file($authPath)) {
                return $graphUsers;
            }

            require_once $authPath;
        }

        if (empty($graphCredentials['tenantId']) || empty($graphCredentials['clientId']) || empty($graphCredentials['clientSecret'])) {
            return $graphUsers;
        }

        $loaded = include __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'getusers_fetch.php';
        if (is_array($loaded)) {
            $graphUsers = $loaded;
        }
    } catch (Throwable $exception) {
        $graphUsers = [];
    }

    return $graphUsers;
}

function syncGraphDirectoryToPersistentCache(): void
{
    static $synced = false;
    if ($synced) {
        return;
    }

    $synced = true;
    $graphMap = buildEmailToNameMapFromGraphUsers(loadGraphUserDirectory());
    if ($graphMap === []) {
        return;
    }

    $runtimeMap = &getUserDirectoryRuntimeMapRef();
    $changed = false;

    foreach ($graphMap as $email => $name) {
        if (($runtimeMap[$email] ?? '') === $name) {
            continue;
        }

        $runtimeMap[$email] = $name;
        $changed = true;
    }

    if ($changed) {
        saveUserDirectoryPersistentCache($runtimeMap);
    }
}

function prefetchUserDisplayNames(array $emails): void
{
    $pending = [];
    $runtimeMap = &getUserDirectoryRuntimeMapRef();

    foreach ($emails as $email) {
        $normalizedEmail = normalizeDirectoryEmail((string) $email);
        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        if (!isset($runtimeMap[$normalizedEmail])) {
            $pending[$normalizedEmail] = $normalizedEmail;
        }
    }

    if ($pending === []) {
        return;
    }

    syncGraphDirectoryToPersistentCache();
}

function formatUserDisplayName(string $email): string
{
    $normalizedEmail = normalizeDirectoryEmail($email);
    if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        return trim($email);
    }

    $runtimeMap = getUserDirectoryRuntimeMapRef();
    if (isset($runtimeMap[$normalizedEmail])) {
        return (string) $runtimeMap[$normalizedEmail];
    }

    prefetchUserDisplayNames([$normalizedEmail]);
    $runtimeMap = getUserDirectoryRuntimeMapRef();

    return (string) ($runtimeMap[$normalizedEmail] ?? $normalizedEmail);
}

function warmUserDirectoryForContext(array $emails): void
{
    prefetchUserDisplayNames($emails);
}

function collectEmailsForUserDirectoryWarmup(array $tickets, array $participantsByTicketId, array $ictUsers, array $messagesByTicketId = []): array
{
    $emails = extractIctUserEmails($ictUsers);

    foreach ($tickets as $ticket) {
        $emails[] = (string) ($ticket['user_email'] ?? '');
        $emails[] = (string) ($ticket['assigned_email'] ?? '');
    }

    foreach ($participantsByTicketId as $participantEmails) {
        if (!is_array($participantEmails)) {
            continue;
        }

        foreach ($participantEmails as $participantEmail) {
            $emails[] = (string) $participantEmail;
        }
    }

    foreach ($messagesByTicketId as $messages) {
        if (!is_array($messages)) {
            continue;
        }

        foreach ($messages as $message) {
            $emails[] = (string) ($message['sender_email'] ?? '');
        }
    }

    return $emails;
}

function renderUserDisplayLabel(string $email): string
{
    $normalizedEmail = normalizeDirectoryEmail($email);
    $display = formatUserDisplayName($normalizedEmail);
    if ($display !== $normalizedEmail && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        return '<span title="' . h($normalizedEmail) . '">' . h($display) . '</span>';
    }

    return h($display !== '' ? $display : $email);
}

function mapBigscreenIctStatRow(array $row, array $availability): array
{
    $email = strtolower((string) ($row['user_email'] ?? ''));

    return [
        'user_email' => $email,
        'user_label' => formatUserDisplayName($email),
        'user_color' => emailToHexColor($email),
        'available' => !empty($availability[$email]),
        'handled_count' => (int) ($row['handled_count'] ?? 0),
        'average_open' => formatDurationSeconds($row['average_open_seconds'] ?? null),
        'max_open' => formatDurationSeconds($row['max_open_seconds'] ?? null),
        'open_count' => (int) ($row['open_count'] ?? 0),
        'waiting_order_count' => (int) ($row['waiting_order_count'] ?? 0),
        'waiting_user_count' => (int) ($row['waiting_user_count'] ?? 0),
        'waiting_third_party_count' => (int) ($row['waiting_third_party_count'] ?? 0),
    ];
}

function mapBigscreenRequesterStatRow(array $row): array
{
    $email = (string) ($row['user_email'] ?? '');

    return [
        'user_email' => $email,
        'user_label' => formatUserDisplayName($email),
        'submitted_count' => (int) ($row['submitted_count'] ?? 0),
        'average_wait' => formatDurationSeconds($row['average_wait_seconds'] ?? null),
        'max_wait' => formatDurationSeconds($row['max_wait_seconds'] ?? null),
        'average_response' => formatDurationSeconds($row['average_response_seconds'] ?? null),
    ];
}

function mapBigscreenOpenTicketRow(array $ticket): array
{
    $userEmail = (string) ($ticket['user_email'] ?? '');
    $assignedEmail = (string) ($ticket['assigned_email'] ?? '');

    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'title' => (string) ($ticket['title'] ?? ''),
        'status' => (string) ($ticket['status'] ?? ''),
        'status_label' => translateStatus((string) ($ticket['status'] ?? '')),
        'status_color' => getStatusColor((string) ($ticket['status'] ?? '')),
        'user_email' => $userEmail,
        'user_label' => formatUserDisplayName($userEmail),
        'assigned_email' => $assignedEmail,
        'assigned_label' => $assignedEmail !== '' ? formatUserDisplayName($assignedEmail) : '',
        'priority' => (int) ($ticket['priority'] ?? 0),
    ];
}

function warmUserDirectoryForBigscreenPoll(array $tickets, array $ictUsers): void
{
    $emails = extractIctUserEmails($ictUsers);
    foreach ($tickets as $ticket) {
        $emails[] = (string) ($ticket['user_email'] ?? '');
        $emails[] = (string) ($ticket['assigned_email'] ?? '');
    }

    warmUserDirectoryForContext($emails);
}

function collectEmailsFromApiPayload(mixed $value, array &$emails): void
{
    if (!is_array($value)) {
        return;
    }

    foreach ($value as $key => $item) {
        if (is_string($key) && is_string($item) && ($key === 'email' || str_ends_with($key, '_email'))) {
            $emails[] = $item;
        }

        if ($key === 'participant_emails' && is_array($item)) {
            foreach ($item as $participantEmail) {
                if (is_string($participantEmail)) {
                    $emails[] = $participantEmail;
                }
            }
        }

        if (is_array($item)) {
            collectEmailsFromApiPayload($item, $emails);
        }
    }
}

function enrichApiValueWithUserNames(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map(
            static fn(mixed $item): mixed => enrichApiValueWithUserNames($item),
            $value
        );
    }

    $result = [];
    foreach ($value as $key => $item) {
        $result[$key] = is_array($item) ? enrichApiValueWithUserNames($item) : $item;
    }

    foreach ($result as $key => $item) {
        if (!is_string($key) || (!is_string($item) && $item !== null)) {
            continue;
        }

        if ($key !== 'email' && !str_ends_with($key, '_email')) {
            continue;
        }

        $nameKey = $key === 'email' ? 'name' : (substr($key, 0, -strlen('_email')) . '_name');
        if (array_key_exists($nameKey, $result)) {
            continue;
        }

        if ($item === null || trim((string) $item) === '') {
            $result[$nameKey] = $item === null ? null : '';
            continue;
        }

        $result[$nameKey] = formatUserDisplayName((string) $item);
    }

    if (
        isset($result['participant_emails'])
        && is_array($result['participant_emails'])
        && !isset($result['participants'])
    ) {
        $participants = [];
        foreach ($result['participant_emails'] as $participantEmail) {
            if (!is_string($participantEmail)) {
                continue;
            }

            $normalizedEmail = normalizeDirectoryEmail($participantEmail);
            $participants[] = [
                'email' => $normalizedEmail,
                'name' => $normalizedEmail !== '' ? formatUserDisplayName($normalizedEmail) : '',
            ];
        }

        $result['participants'] = $participants;
    }

    return $result;
}

function enrichApiResponseWithUserNames(array $payload): array
{
    $emails = [];
    collectEmailsFromApiPayload($payload, $emails);
    if ($emails !== []) {
        warmUserDirectoryForContext($emails);
    }

    /** @var array $enriched */
    $enriched = enrichApiValueWithUserNames($payload);

    return $enriched;
}
