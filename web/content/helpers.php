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
    return __('priority.label.' . $priority);
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
            $errors[] = __('flash.attachment_upload_error', $name);
            continue;
        }

        if ($size > MAX_ATTACHMENT_BYTES) {
            $errors[] = __('flash.attachment_too_large', $name);
        }
    }

    return $errors;
}

function formatDateTime(string $value): string
{
    if ($value === '') {
        return __('datetime.unknown');
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
        ['seconds' => 31536000, 'singular' => 'duration.year', 'plural' => 'duration.years'],
        ['seconds' => 2592000, 'singular' => 'duration.month', 'plural' => 'duration.months'],
        ['seconds' => 604800, 'singular' => 'duration.week', 'plural' => 'duration.weeks'],
        ['seconds' => 86400, 'singular' => 'duration.day', 'plural' => 'duration.days'],
        ['seconds' => 3600, 'singular' => 'duration.hour', 'plural' => 'duration.hours'],
        ['seconds' => 60, 'singular' => 'duration.minute', 'plural' => 'duration.minutes'],
    ];

    foreach ($units as $unit) {
        if ($seconds >= $unit['seconds']) {
            $value = (int) round($seconds / $unit['seconds']);
            $label = __($value === 1 ? $unit['singular'] : $unit['plural']);
            return $value . ' ' . $label;
        }
    }

    return __('duration.less_than_minute');
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

/**
 * Vertaalt een DB-statuswaarde (altijd Nederlands) naar de actieve taal.
 */
function translateStatus(string $dbStatus): string
{
    $map = [
        'ingediend' => 'status.ingediend',
        'in behandeling' => 'status.in_behandeling',
        'afwachtende op gebruiker' => 'status.afwachtende_op_gebruiker',
        'afwachtende op bestelling' => 'status.afwachtende_op_bestelling',
        'afgehandeld' => 'status.afgehandeld',
    ];

    return isset($map[$dbStatus]) ? __($map[$dbStatus]) : $dbStatus;
}

/**
 * Vertaalt een DB-categoriewaarde (altijd Nederlands) naar de actieve taal.
 */
function translateCategory(string $dbCategory): string
{
    $map = [
        'hardware bestellen' => 'category.hardware_bestellen',
        'software bestellen' => 'category.software_bestellen',
        'Business Central' => 'category.business_central',
        'Hardwareproblemen' => 'category.hardwareproblemen',
        'Softwareproblemen' => 'category.softwareproblemen',
        'sleutels.kvt.nl web-applicatieproblemen' => 'category.web_app_problemen',
        'Anders' => 'category.anders',
    ];

    return isset($map[$dbCategory]) ? __($map[$dbCategory]) : $dbCategory;
}

function emailToHexColor(string $email): string
{
    return '#' . substr(md5(strtolower(trim($email))), 0, 6);
}

function buildStatusChangeNote(string $status, string $changedByEmail): string
{
    return __('flash.status_changed_to', translateStatus($status));
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

/**
 * Vertaalt een e-mail-sleutel in de opgegeven taal (onafhankelijk van actieve sessietaal).
 */
function __mail(string $key, string $lang, mixed ...$args): string
{
    $translations = TRANSLATIONS[$lang] ?? TRANSLATIONS['nl'];
    $string = $translations[$key] ?? (TRANSLATIONS['nl'][$key] ?? $key);
    return $args !== [] ? sprintf($string, ...$args) : $string;
}

/**
 * Geeft de opgeslagen taalvoorkeur van een e-mailadres terug, of 'nl' als fallback.
 */
function getUserMailLang(string $email): string
{
    $prefs = loadUserPrefs($email);
    $lang = $prefs['lang'] ?? 'nl';
    return array_key_exists($lang, SUPPORTED_LANGUAGES) ? $lang : 'nl';
}

function buildNotificationBody(array $ticket, string $introKey, string $messageText = '', bool $adminPage = false, string $lang = 'nl', mixed ...$introArgs): string
{
    $t = static fn(string $key, mixed ...$a) => __mail($key, $lang, ...$a);
    $ticketUrl = buildAbsoluteTicketUrl((int) $ticket['id'], $adminPage);
    $statusColor = getStatusColor((string) ($ticket['status'] ?? ''));
    $assignee = ($ticket['assigned_email'] ?? '') !== '' ? (string) $ticket['assigned_email'] : $t('email.not_assigned');
    $intro = $t($introKey, ...$introArgs);

    // Plain-text fallback
    $lines = [
        $intro,
        '',
        'Ticket: #' . $ticket['id'] . ' - ' . $ticket['title'],
        $t('email.field_category') . ': ' . $ticket['category'],
        $t('email.field_requester') . ': ' . $ticket['user_email'],
        $t('email.field_assigned') . ': ' . $assignee,
        $t('email.field_status') . ': ' . $ticket['status'],
        $t('email.field_updated') . ': ' . formatDateTime((string) ($ticket['updated_at'] ?? $ticket['created_at'] ?? '')),
    ];
    if (trim($messageText) !== '') {
        $lines[] = '';
        $lines[] = $messageText;
    }
    $lines[] = '';
    $lines[] = $t('email.btn_view') . ': ' . $ticketUrl;
    $plain = implode(PHP_EOL, $lines);

    // HTML body
    $msgHtml = '';
    if (trim($messageText) !== '') {
        $msgHtml = '<div style="margin-top:16px;background:#f4f7fb;border-left:4px solid #0b65c2;padding:12px 16px;border-radius:0 8px 8px 0;font-size:14px;color:#10233f;white-space:pre-wrap;">'
            . htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    $html = '<!DOCTYPE html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#10233f;">'
        . '<div style="max-width:600px;margin:32px auto;padding:0 16px;">'
        . '<div style="background:linear-gradient(135deg,#0e2c52,#0b65c2);border-radius:16px 16px 0 0;padding:24px 28px;">'
        . '<p style="margin:0 0 2px;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.7);">Asclepius · ICT Tickets</p>'
        . '<h1 style="margin:0;font-size:20px;color:#fff;">Ticket #' . (int) $ticket['id'] . '</h1>'
        . '</div>'
        . '<div style="background:#fff;border-radius:0 0 16px 16px;padding:24px 28px;box-shadow:0 8px 24px rgba(15,35,63,.08);">'
        . '<p style="margin:0 0 20px;font-size:15px;">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div style="background:#f4f7fb;border-radius:10px;padding:16px 18px;margin-bottom:20px;">'
        . '<p style="margin:0 0 8px;font-size:16px;font-weight:bold;color:#10233f;">' . htmlspecialchars((string) ($ticket['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:13px;color:#5b6b82;">'
        . '<tr><td style="padding:3px 0;width:140px;">' . htmlspecialchars($t('email.field_category'), ENT_QUOTES, 'UTF-8') . '</td><td style="color:#10233f;">' . htmlspecialchars((string) ($ticket['category'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:3px 0;">' . htmlspecialchars($t('email.field_requester'), ENT_QUOTES, 'UTF-8') . '</td><td style="color:#10233f;">' . htmlspecialchars((string) ($ticket['user_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:3px 0;">' . htmlspecialchars($t('email.field_assigned'), ENT_QUOTES, 'UTF-8') . '</td><td style="color:#10233f;">' . htmlspecialchars($assignee, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:3px 0;">' . htmlspecialchars($t('email.field_status'), ENT_QUOTES, 'UTF-8') . '</td><td><span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:' . htmlspecialchars($statusColor, ENT_QUOTES, 'UTF-8') . ';color:#fff;">' . htmlspecialchars((string) ($ticket['status'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></td></tr>'
        . '<tr><td style="padding:3px 0;">' . htmlspecialchars($t('email.field_updated'), ENT_QUOTES, 'UTF-8') . '</td><td style="color:#10233f;">' . htmlspecialchars(formatDateTime((string) ($ticket['updated_at'] ?? $ticket['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '</table>'
        . '</div>'
        . $msgHtml
        . '<div style="margin-top:24px;text-align:center;">'
        . '<a href="' . htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0b65c2;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;">' . htmlspecialchars($t('email.btn_view'), ENT_QUOTES, 'UTF-8') . '</a>'
        . '</div>'
        . '</div>'
        . '<p style="text-align:center;font-size:11px;color:#94a3b8;margin-top:16px;">Asclepius · KVT ICT</p>'
        . '</div></body></html>';

    return "ASCLEPIUS_HTML_MAIL\x00" . $plain . "\x00" . $html;
}

