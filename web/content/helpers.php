<?php

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pushFlash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function normalizeReturnPage(?string $page): string
{
    return basename((string) $page) === 'admin.php' ? 'admin.php' : 'index.php';
}

function buildNavigationQuery(array $statusFilters, array $categoryFilters, string $assignedFilter, string $view, bool $isAdminPortal, bool $statusFilterRequestActive = false, bool $categoryFilterRequestActive = false, int $openTicketId = 0): array
{
    $query = [];

    if ($statusFilterRequestActive) {
        $query['status_filter_mode'] = 'manual';
    }

    if ($statusFilters !== []) {
        $query['status'] = $statusFilters;
    }

    if ($categoryFilterRequestActive) {
        $query['category_filter_mode'] = 'manual';
    }

    if ($categoryFilters !== []) {
        $query['category'] = $categoryFilters;
    }

    if ($assignedFilter !== '') {
        $query['assigned'] = $assignedFilter;
    }

    if ($isAdminPortal && $view !== 'overview') {
        $query['view'] = $view;
    }

    if ($openTicketId > 0) {
        $query['open'] = $openTicketId;
    }

    return $query;
}

function isStatusFilterSelected(string $status, array $statusFilters, bool $statusFilterRequestActive): bool
{
    return !$statusFilterRequestActive || in_array($status, $statusFilters, true);
}

function isCategoryFilterSelected(string $category, array $categoryFilters, bool $categoryFilterRequestActive): bool
{
    return !$categoryFilterRequestActive || in_array($category, $categoryFilters, true);
}

function getPriorityFromFlags(bool $isWorkBlocked, bool $isFullyBlocked): int
{
    if ($isWorkBlocked && $isFullyBlocked) {
        return 2;
    }

    if ($isWorkBlocked) {
        return 1;
    }

    return 0;
}

function formatPriorityLabel($priority): string
{
    $priority = max(0, min(2, (int) $priority));
    return PRIORITY_LABELS[$priority] ?? PRIORITY_LABELS[0];
}

function getPriorityColor($priority): string
{
    $priority = max(0, min(2, (int) $priority));
    return PRIORITY_COLORS[$priority] ?? PRIORITY_COLORS[0];
}

function getCategoryColor(string $category): string
{
    return CATEGORY_COLORS[$category] ?? '#334155';
}

function ticketIsOpenLongerThanDays(array $ticket, int $days): bool
{
    if ((string) ($ticket['status'] ?? '') === 'afgehandeld') {
        return false;
    }

    $createdAt = trim((string) ($ticket['created_at'] ?? ''));
    if ($createdAt === '') {
        return false;
    }

    try {
        $created = new DateTimeImmutable($createdAt);
        $threshold = (new DateTimeImmutable('now'))->modify('-' . max(1, $days) . ' days');
        return $created <= $threshold;
    } catch (Throwable) {
        return false;
    }
}

function redirectToPage(string $page = 'index.php', array $parameters = []): void
{
    $filtered = array_filter(
        $parameters,
        static fn($value): bool => $value !== null && $value !== '' && $value !== []
    );

    $queryString = http_build_query($filtered);
    $targetPage = normalizeReturnPage($page);
    header('Location: ' . $targetPage . ($queryString !== '' ? '?' . $queryString : ''));
    exit;
}

function normalizeUploadedFiles(string $fieldName): array
{
    if (!isset($_FILES[$fieldName])) {
        return [];
    }

    $input = $_FILES[$fieldName];

    if (!is_array($input['name'])) {
        if (($input['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || (string) ($input['name'] ?? '') === '') {
            return [];
        }

        return [$input];
    }

    $files = [];
    $fileCount = count($input['name']);

    for ($index = 0; $index < $fileCount; $index++) {
        $error = (int) ($input['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        $name = (string) ($input['name'][$index] ?? '');

        if ($error === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }

        $files[] = [
            'name' => $name,
            'type' => (string) ($input['type'][$index] ?? ''),
            'tmp_name' => (string) ($input['tmp_name'][$index] ?? ''),
            'error' => $error,
            'size' => (int) ($input['size'][$index] ?? 0),
        ];
    }

    return $files;
}

function validateUploadedFiles(array $files): array
{
    $errors = [];

    foreach ($files as $file) {
        $name = (string) ($file['name'] ?? 'bestand');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        $size = (int) ($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'Bijlage "' . $name . '" kon niet worden geüpload.';
            continue;
        }

        if ($size > MAX_ATTACHMENT_BYTES) {
            $errors[] = 'Bijlage "' . $name . '" is groter dan 20 MB.';
        }
    }

    return $errors;
}

function formatDateTime(string $value): string
{
    if ($value === '') {
        return 'Onbekend';
    }

    try {
        return (new DateTimeImmutable($value))->format('d-m-Y H:i');
    } catch (Throwable) {
        return $value;
    }
}

function formatDurationSeconds($seconds): string
{
    if ($seconds === null || !is_numeric($seconds)) {
        return '—';
    }

    $seconds = max(0, (int) round((float) $seconds));

    $units = [
        ['seconds' => 31536000, 'singular' => 'jaar', 'plural' => 'jaar'],
        ['seconds' => 2592000, 'singular' => 'maand', 'plural' => 'maanden'],
        ['seconds' => 604800, 'singular' => 'week', 'plural' => 'weken'],
        ['seconds' => 86400, 'singular' => 'dag', 'plural' => 'dagen'],
        ['seconds' => 3600, 'singular' => 'uur', 'plural' => 'uur'],
        ['seconds' => 60, 'singular' => 'minuut', 'plural' => 'minuten'],
    ];

    foreach ($units as $unit) {
        if ($seconds >= $unit['seconds']) {
            $value = (int) round($seconds / $unit['seconds']);
            $label = $value === 1 ? $unit['singular'] : $unit['plural'];
            return $value . ' ' . $label;
        }
    }

    return 'minder dan 1 minuut';
}

function getTicketOpenDurationSeconds(array $ticket): ?int
{
    $createdAt = trim((string) ($ticket['created_at'] ?? ''));
    if ($createdAt === '') {
        return null;
    }

    try {
        $start = new DateTimeImmutable($createdAt);
        $status = (string) ($ticket['status'] ?? '');
        $resolvedAt = trim((string) ($ticket['resolved_at'] ?? ''));
        $updatedAt = trim((string) ($ticket['updated_at'] ?? ''));

        if ($status === 'afgehandeld' && $resolvedAt !== '') {
            $end = new DateTimeImmutable($resolvedAt);
        } elseif ($status === 'afgehandeld' && $updatedAt !== '') {
            $end = new DateTimeImmutable($updatedAt);
        } else {
            $end = new DateTimeImmutable('now');
        }

        return max(0, $end->getTimestamp() - $start->getTimestamp());
    } catch (Throwable) {
        return null;
    }
}

function getStatusColor(string $status): string
{
    return STATUS_COLORS[$status] ?? '#475569';
}

function emailToHexColor(string $email): string
{
    return '#' . substr(md5(strtolower(trim($email))), 0, 6);
}

function buildStatusChangeNote(string $status, string $changedByEmail): string
{
    return 'Status gewijzigd naar ' . $status . '.';
}

function makeTextInteractive(string $text): string
{
    $escapedText = h($text);

    $escapedText = preg_replace_callback(
        '~(?:(https?://|www\.)[^\s<]+)~i',
        static function (array $matches): string {
            $displayValue = $matches[0];
            $href = str_starts_with(strtolower($displayValue), 'www.') ? 'https://' . $displayValue : $displayValue;
            $safeHref = h($href);
            $safeLabel = h($displayValue);

            return '<a href="' . $safeHref . '" target="_blank" rel="noopener noreferrer">' . $safeLabel . '</a>';
        },
        $escapedText
    ) ?? $escapedText;

    $escapedText = preg_replace(
        '/(?<![\w.@])([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})(?![^<]*>)/i',
        '<a href="mailto:$1">$1</a>',
        $escapedText
    ) ?? $escapedText;

    $escapedText = preg_replace_callback(
        '/(?<![\w>])((?:\+?[0-9][0-9\s()\/.-]{6,}[0-9]))(?![^<]*>)/',
        static function (array $matches): string {
            $phoneText = trim($matches[1]);
            $phoneHref = preg_replace('/[^0-9+]/', '', $phoneText) ?? '';
            if ($phoneHref === '') {
                return $phoneText;
            }

            return '<a href="tel:' . h($phoneHref) . '">' . h($phoneText) . '</a>';
        },
        $escapedText
    ) ?? $escapedText;

    return $escapedText;
}

function formatTicketMessageText(?string $messageText): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $messageText));
    if ($normalized === '') {
        return '';
    }

    $formattedLines = [];
    foreach (explode("\n", $normalized) as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            $formattedLines[] = '';
            continue;
        }

        $interactiveLine = makeTextInteractive($line);
        if (str_starts_with($trimmedLine, 'Status gewijzigd naar ')) {
            $formattedLines[] = '<small>' . $interactiveLine . '</small>';
            continue;
        }

        $formattedLines[] = $interactiveLine;
    }

    return implode('<br>', $formattedLines);
}

function buildAbsoluteTicketUrl(int $ticketId, bool $adminPage = false): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/index.php')), '/.');
    $targetPage = $adminPage ? 'admin.php' : 'index.php';

    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '') . '/' . $targetPage . '?open=' . $ticketId;
}

function buildNotificationBody(array $ticket, string $intro, string $messageText = '', bool $adminPage = false): string
{
    $lines = [
        $intro,
        '',
        'Ticket: #' . $ticket['id'] . ' - ' . $ticket['title'],
        'Categorie: ' . $ticket['category'],
        'Aanvrager: ' . $ticket['user_email'],
        'Toegewezen aan: ' . (($ticket['assigned_email'] ?? '') !== '' ? $ticket['assigned_email'] : 'Nog niet toegewezen'),
        'Status: ' . $ticket['status'],
        'Laatst bijgewerkt: ' . formatDateTime((string) ($ticket['updated_at'] ?? $ticket['created_at'] ?? '')),
    ];

    if (trim($messageText) !== '') {
        $lines[] = '';
        $lines[] = 'Bericht:';
        $lines[] = $messageText;
    }

    $lines[] = '';
    $lines[] = 'Open ticket: ' . buildAbsoluteTicketUrl((int) $ticket['id'], $adminPage);

    return implode(PHP_EOL, $lines);
}
