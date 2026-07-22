<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'user_directory.php';

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

/**
 * Ensures $ictUsers is a flat array with emails.
 * Handles both flat arrays (emails) and associative arrays (email => color).
 * Ensures $ictUserColors is a normalized associative array (email => color).
 */
function normalizeIctUsersConfig(array &$ictUsers, array &$ictUserColors = []): void
{
    $normalizedColors = [];
    foreach ($ictUserColors as $email => $color) {
        $normalizedEmail = strtolower(trim((string) $email));
        if ($normalizedEmail === '') {
            continue;
        }

        $normalizedColors[$normalizedEmail] = trim((string) $color);
    }

    $ictUserColors = $normalizedColors;
    $isAssociativeIctUsers = array_keys($ictUsers) !== range(0, count($ictUsers) - 1);

    if ($isAssociativeIctUsers) {
        foreach ($ictUsers as $email => $color) {
            $normalizedEmail = strtolower(trim((string) $email));
            if ($normalizedEmail === '') {
                continue;
            }

            $ictUserColors[$normalizedEmail] = trim((string) $color);
        }

        $ictUsers = array_keys($ictUserColors);
        return;
    }

    $ictUsers = array_values(array_filter(array_map(
        static fn(mixed $value): string => strtolower(trim((string) $value)),
        $ictUsers
    ), static fn(string $email): bool => $email !== ''));
}

/**
 * Helper: Extract email addresses from $ictUsers regardless of whether it's flat or associative.
 * Returns a normalized lowercase array of email addresses.
 */
function extractIctUserEmails(array $ictUsers): array
{
    $isAssociative = array_keys($ictUsers) !== range(0, count($ictUsers) - 1);
    $emails = $isAssociative ? array_keys($ictUsers) : array_values($ictUsers);
    return array_map('strtolower', $emails);
}

function parseEmailListInput(string $input): array
{
    $parts = preg_split('/[,;\n\r]+/', $input) ?: [];
    $emails = [];

    foreach ($parts as $part) {
        $email = strtolower(trim((string) $part));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $emails[$email] = $email;
    }

    return array_values($emails);
}

function findInvalidEmailListTokens(string $input): array
{
    $parts = preg_split('/[,;\n\r]+/', $input) ?: [];
    $invalid = [];

    foreach ($parts as $part) {
        $token = trim((string) $part);
        if ($token === '') {
            continue;
        }

        if (!filter_var($token, FILTER_VALIDATE_EMAIL)) {
            $invalid[] = $token;
        }
    }

    return array_values(array_unique($invalid));
}

function buildRequesterSummary(array $participantEmails, string $fallbackEmail): array
{
    $normalizedParticipants = [];
    foreach ($participantEmails as $participantEmail) {
        $email = strtolower(trim((string) $participantEmail));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $normalizedParticipants[$email] = $email;
    }

    $fallbackEmail = strtolower(trim($fallbackEmail));
    $participants = array_values($normalizedParticipants);
    if ($participants === []) {
        $participants = [$fallbackEmail !== '' ? $fallbackEmail : ''];
    }

    $firstEmail = (string) ($participants[0] ?? '');
    $extraCount = max(0, count($participants) - 1);
    $firstDisplay = formatUserDisplayName($firstEmail);
    $label = $firstDisplay . ($extraCount > 0 ? ' +' . $extraCount : '');
    $displayNames = array_map(
        static fn(string $email): string => formatUserDisplayName($email),
        array_filter($participants, static fn(string $email): bool => $email !== '')
    );
    $tooltip = $extraCount > 0
        ? implode("\n", $displayNames)
        : ($firstDisplay !== $firstEmail && $firstEmail !== '' ? $firstEmail : '');

    return [
        'label' => $label,
        'tooltip' => $tooltip,
        'participants' => $participants,
        'extra_count' => $extraCount,
    ];
}

function buildNavigationQuery(array $statusFilters, array $categoryFilters, string $assignedFilter, string $searchQuery, string $view, bool $isAdminPortal, bool $statusFilterRequestActive = false, bool $categoryFilterRequestActive = false, int $openTicketId = 0, int $page = 1): array
{
    // Filter state lives in user prefs; keep URLs limited to location params.
    unset($statusFilters, $categoryFilters, $assignedFilter, $searchQuery, $statusFilterRequestActive, $categoryFilterRequestActive);

    return buildTicketListLocationQuery($view, $isAdminPortal, $openTicketId, $page);
}

function buildTicketListLocationQuery(string $view, bool $isAdminPortal, int $openTicketId = 0, int $page = 1): array
{
    $query = [];

    if ($isAdminPortal && $view !== 'overview') {
        $query['view'] = $view;
    }

    if (!$isAdminPortal && $view === 'all_tickets') {
        $query['view'] = 'all_tickets';
    }

    if ($openTicketId > 0) {
        $query['open'] = $openTicketId;
    }

    if ($page > 1) {
        $query['page'] = $page;
    }

    return $query;
}

function hasExplicitTicketFilterQueryParams(): bool
{
    return isset($_GET['status_filter_mode'])
        || isset($_GET['status'])
        || isset($_GET['category_filter_mode'])
        || isset($_GET['category'])
        || array_key_exists('assigned', $_GET)
        || array_key_exists('search', $_GET)
        || isset($_GET['reset_filters']);
}

function hasActiveTicketOverviewFilters(bool $statusFilterRequestActive, bool $categoryFilterRequestActive, string $assignedFilter, string $searchQuery): bool
{
    return $statusFilterRequestActive
        || $categoryFilterRequestActive
        || $assignedFilter !== ''
        || $searchQuery !== '';
}

function isTicketOverviewListRequest(): bool
{
    return !isset($_GET['_partial'])
        && !isset($_GET['_tickets_poll'])
        && !isset($_GET['_browser_notifications_poll'])
        && !isset($_GET['_webpush_subscription'])
        && !isset($_GET['_bigscreen_poll'])
        && !isset($_GET['_bigscreen_version']);
}

function buildTicketOverviewNavigationQuery(
    array $statusFilters,
    array $categoryFilters,
    string $assignedFilter,
    string $searchQuery,
    string $view,
    bool $isAdminPortal,
    bool $statusFilterRequestActive,
    bool $categoryFilterRequestActive,
    int $openTicketId = 0,
    int $page = 1
): array {
    return buildNavigationQuery(
        $statusFilters,
        $categoryFilters,
        $assignedFilter,
        $searchQuery,
        $view,
        $isAdminPortal,
        $statusFilterRequestActive,
        $categoryFilterRequestActive,
        $openTicketId,
        $page
    );
}

function buildTicketOverviewNavigationQueryFromSaved(array $savedOverviewFilters, string $view, bool $isAdminPortal, int $openTicketId = 0, int $page = 1): array
{
    unset($savedOverviewFilters);

    return buildTicketListLocationQuery($view, $isAdminPortal, $openTicketId, $page);
}

/**
 * @return list<int|null>
 */
function buildTicketPaginationSequence(int $currentPage, int $totalPages, int $radius = 2): array
{
    if ($totalPages <= 1) {
        return [];
    }

    $pageNumbers = [];
    $addPage = static function (int $page) use (&$pageNumbers, $totalPages): void {
        if ($page >= 1 && $page <= $totalPages) {
            $pageNumbers[$page] = $page;
        }
    };

    $addPage(1);
    for ($page = $currentPage - $radius; $page <= $currentPage + $radius; $page++) {
        $addPage($page);
    }
    $addPage($totalPages);

    $sortedPages = array_values($pageNumbers);
    sort($sortedPages);

    $sequence = [];
    $previousPage = 0;
    foreach ($sortedPages as $page) {
        if ($previousPage > 0 && $page - $previousPage > 1) {
            $sequence[] = null;
        }

        $sequence[] = $page;
        $previousPage = $page;
    }

    return $sequence;
}

function renderTicketPaginationHtml(string $currentPage, int $ticketPage, int $ticketTotalPages, array $baseNavigationQuery): string
{
    if ($ticketTotalPages <= 1) {
        return '';
    }

    $sequence = buildTicketPaginationSequence($ticketPage, $ticketTotalPages);
    if ($sequence === []) {
        return '';
    }

    ob_start();
    ?>
    <nav class="ticket-pagination" aria-label="<?= h(__('pagination.tickets_label')) ?>">
        <?php if ($ticketPage > 1): ?>
            <a class="ticket-pagination-button ticket-pagination-prev"
                href="<?= h(buildPageUrl($currentPage, buildTicketPaginationLinkQuery($baseNavigationQuery, $ticketPage - 1))) ?>"
                aria-label="<?= h(__('pagination.previous')) ?>">‹</a>
        <?php else: ?>
            <span class="ticket-pagination-button ticket-pagination-prev is-disabled" aria-hidden="true">‹</span>
        <?php endif; ?>

        <?php foreach ($sequence as $pageNumber): ?>
            <?php if ($pageNumber === null): ?>
                <span class="ticket-pagination-ellipsis" aria-hidden="true">…</span>
            <?php elseif ($pageNumber === $ticketPage): ?>
                <span class="ticket-pagination-button is-current" aria-current="page"><?= (int) $pageNumber ?></span>
            <?php else: ?>
                <a class="ticket-pagination-button"
                    href="<?= h(buildPageUrl($currentPage, buildTicketPaginationLinkQuery($baseNavigationQuery, (int) $pageNumber))) ?>"><?= (int) $pageNumber ?></a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($ticketPage < $ticketTotalPages): ?>
            <a class="ticket-pagination-button ticket-pagination-next"
                href="<?= h(buildPageUrl($currentPage, buildTicketPaginationLinkQuery($baseNavigationQuery, $ticketPage + 1))) ?>"
                aria-label="<?= h(__('pagination.next')) ?>">›</a>
        <?php else: ?>
            <span class="ticket-pagination-button ticket-pagination-next is-disabled" aria-hidden="true">›</span>
        <?php endif; ?>
    </nav>
    <?php

    return (string) ob_get_clean();
}

function buildPageUrl(string $page, array $query): string
{
    $normalizedQuery = [];
    foreach ($query as $key => $value) {
        if ($value === null || $value === '' || $value === []) {
            continue;
        }

        $normalizedQuery[$key] = $value;
    }

    $queryString = http_build_query($normalizedQuery, '', '&', PHP_QUERY_RFC3986);

    return $page . ($queryString !== '' ? '?' . $queryString : '');
}

function buildTicketPaginationLinkQuery(array $baseQuery, int $page): array
{
    $query = $baseQuery;
    if ($page > 1) {
        $query['page'] = $page;
    } else {
        unset($query['page']);
    }

    return $query;
}

function buildTicketPollPaginationHtml(
    string $currentPage,
    int $ticketPage,
    int $ticketTotalPages,
    array $statusFilters,
    array $categoryFilters,
    string $assignedFilter,
    string $searchQuery,
    string $view,
    bool $isAdminPortal,
    bool $statusFilterRequestActive,
    bool $categoryFilterRequestActive,
    int $openTicketId = 0
): string {
    unset(
        $statusFilters,
        $categoryFilters,
        $assignedFilter,
        $searchQuery,
        $statusFilterRequestActive,
        $categoryFilterRequestActive
    );

    $overviewListView = $view === 'all_tickets' ? 'all_tickets' : 'overview';
    $baseNavigationQuery = buildTicketListLocationQuery($overviewListView, $isAdminPortal, $openTicketId, 1);

    return renderTicketPaginationHtml($currentPage, $ticketPage, $ticketTotalPages, $baseNavigationQuery);
}

function buildCurrentPageUrl(string $page, array $overrides = [], array $removeKeys = []): string
{
    $query = $_GET;

    foreach ($removeKeys as $key) {
        unset($query[$key]);
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '' || $value === []) {
            unset($query[$key]);
            continue;
        }

        $query[$key] = $value;
    }

    $queryString = http_build_query($query);
    return $page . ($queryString !== '' ? '?' . $queryString : '');
}

function resolveTicketBrowseMode(bool $canManageTickets, bool $isAllTicketsView): string
{
    if ($canManageTickets) {
        return 'default';
    }

    return $isAllTicketsView ? 'all_completed_public' : 'default';
}

function normalizeTicketsPerPage(int $value): int
{
    return in_array($value, TICKETS_PER_PAGE_OPTIONS, true) ? $value : DEFAULT_TICKETS_PER_PAGE;
}

function resolveTicketsPerPage(array $userPrefs): int
{
    return normalizeTicketsPerPage((int) ($userPrefs['tickets_per_page'] ?? DEFAULT_TICKETS_PER_PAGE));
}

function renderTicketsPerPageSelectHtml(int $selectedPerPage): string
{
    $selectedPerPage = normalizeTicketsPerPage($selectedPerPage);

    ob_start();
    ?>
    <label class="tickets-per-page-control">
        <?= h(__('filter.per_page_label')) ?>
        <select name="per_page" onchange="this.form.submit()">
            <?php foreach (TICKETS_PER_PAGE_OPTIONS as $option): ?>
                <option value="<?= (int) $option ?>" <?= $option === $selectedPerPage ? 'selected' : '' ?>><?= (int) $option ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php

    return (string) ob_get_clean();
}

function buildTicketSnapshotSignature(array $tickets): string
{
    $snapshot = array_map(static function (array $ticket): array {
        return [
            'id' => (int) ($ticket['id'] ?? 0),
            'updated_at' => (string) ($ticket['updated_at'] ?? ''),
            'status' => (string) ($ticket['status'] ?? ''),
            'assigned_email' => strtolower((string) ($ticket['assigned_email'] ?? '')),
            'message_count' => (int) ($ticket['message_count'] ?? 0),
            'is_private' => (int) ($ticket['is_private'] ?? 0),
        ];
    }, $tickets);

    return sha1(json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: '[]');
}

function buildTicketDetailArray(array $ticket, ?array $participantEmails, array $messages = []): array
{
    $requesterEmail = strtolower(trim((string) ($ticket['user_email'] ?? '')));
    $participants = is_array($participantEmails) && $participantEmails !== []
        ? array_values($participantEmails)
        : ($requesterEmail !== '' ? [$requesterEmail] : []);

    return array_merge($ticket, [
        'participant_emails' => $participants,
        'messages' => $messages,
    ]);
}

function buildTicketCardRenderContext(array $baseContext, array $ticket, int $openTicketId): array
{
    $ticketId = (int) ($ticket['id'] ?? 0);
    $includeMessages = $openTicketId > 0 && $ticketId === $openTicketId;

    return array_merge($baseContext, [
        'includeMessages' => $includeMessages,
        'lazyMessages' => !$includeMessages,
    ]);
}

function buildTicketPollItemsFromTickets(TicketStore $store, array $tickets, array $context, string $viewerLanguage): array
{
    if ($tickets === []) {
        return [];
    }

    $ticketIds = array_values(array_filter(array_map(
        static fn(array $ticket): int => (int) ($ticket['id'] ?? 0),
        $tickets
    ), static fn(int $ticketId): bool => $ticketId > 0));

    $participantsByTicketId = $store->getTicketParticipantsBatch($ticketIds);
    $openTicketId = (int) ($context['openTicketId'] ?? 0);
    $messageTicketIds = $openTicketId > 0 ? [$openTicketId] : [];
    $messagesByTicketId = $messageTicketIds !== []
        ? $store->getTicketMessagesBatch($messageTicketIds)
        : [];
    warmUserDirectoryForContext(collectEmailsForUserDirectoryWarmup(
        $tickets,
        $participantsByTicketId,
        is_array($context['ictUsers'] ?? null) ? $context['ictUsers'] : [],
        $messagesByTicketId
    ));
    $pollItems = [];

    foreach ($tickets as $ticket) {
        $ticketId = (int) ($ticket['id'] ?? 0);
        if ($ticketId <= 0) {
            continue;
        }

        $requesterEmail = strtolower(trim((string) ($ticket['user_email'] ?? '')));
        $participantEmails = $participantsByTicketId[$ticketId] ?? ($requesterEmail !== '' ? [$requesterEmail] : []);
        $ticketDetail = buildTicketDetailArray(
            $ticket,
            $participantEmails,
            $messagesByTicketId[$ticketId] ?? []
        );
        $ticketDetail = localizeTicketDetailForViewer($ticketDetail, $store, $viewerLanguage, true);

        $pollItems[] = buildTicketPollEntry(
            $ticket,
            $ticketDetail,
            buildTicketCardRenderContext($context, $ticket, $openTicketId)
        );
    }

    return $pollItems;
}

function isImageAttachment(array $attachment): bool
{
    $mimeType = strtolower(trim((string) ($attachment['mime_type'] ?? '')));
    if ($mimeType !== '' && str_starts_with($mimeType, 'image/')) {
        return true;
    }

    $originalName = (string) ($attachment['original_name'] ?? '');
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    return in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'], true);
}

/**
 * Determines if a file can be previewed
 */
function canPreviewFile(array $attachment): bool
{
    $originalName = strtolower((string) ($attachment['original_name'] ?? ''));
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $mimeType = strtolower(trim((string) ($attachment['mime_type'] ?? '')));

    if ($mimeType !== '' && str_starts_with($mimeType, 'video/')) {
        return true;
    }

    $previewableExtensions = [
        // Text & markup
        'txt',
        'md',
        'markdown',
        'mdown',
        'mkd',
        'rst',
        // Code
        'js',
        'json',
        'php',
        'py',
        'rb',
        'go',
        'rs',
        'c',
        'cpp',
        'h',
        'cs',
        'java',
        'sql',
        'html',
        'css',
        'xml',
        'yaml',
        'yml',
        'toml',
        'ini',
        'cfg',
        'conf',
        'sh',
        'bash',
        // Data
        'csv',
        'tsv',
        // Documents
        'pdf',
        'xlsx',
        'xls',
        'docx',
        'doc',
        'odt',
        'rtf',
        // Video
        'mp4',
        'webm',
        'ogg',
        'mov',
        'm4v',
    ];

    return in_array($extension, $previewableExtensions, true);
}

/**
 * Gets the preview format/type for a file
 */
function getPreviewFormat(array $attachment): ?string
{
    $originalName = strtolower((string) ($attachment['original_name'] ?? ''));
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $mimeType = strtolower(trim((string) ($attachment['mime_type'] ?? '')));

    if ($mimeType !== '' && str_starts_with($mimeType, 'video/'))
        return 'video';

    // Text & markup
    if (in_array($extension, ['txt', 'log', 'text'], true))
        return 'text';
    if (in_array($extension, ['md', 'markdown', 'mdown', 'mkd', 'rst'], true))
        return 'markdown';

    // Code
    if (in_array($extension, ['js', 'jsx', 'mjs', 'ts', 'tsx'], true))
        return 'javascript';
    if (in_array($extension, ['json', 'jsonld', 'ndjson'], true))
        return 'json';
    if (in_array($extension, ['py', 'pyw', 'pyi'], true))
        return 'python';
    if (in_array($extension, ['rb', 'erb', 'gemspec'], true))
        return 'ruby';
    if (in_array($extension, ['go'], true))
        return 'go';
    if (in_array($extension, ['rs'], true))
        return 'rust';
    if (in_array($extension, ['c', 'h'], true))
        return 'c';
    if (in_array($extension, ['cpp', 'cc', 'cxx', 'hpp', 'h++'], true))
        return 'cpp';
    if (in_array($extension, ['cs', 'csx'], true))
        return 'csharp';
    if (in_array($extension, ['java'], true))
        return 'java';
    if (in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8'], true))
        return 'php';
    if (in_array($extension, ['sql'], true))
        return 'sql';
    if (in_array($extension, ['html', 'htm'], true))
        return 'html';
    if (in_array($extension, ['css', 'scss', 'sass', 'less'], true))
        return 'css';
    if (in_array($extension, ['xml', 'svg'], true))
        return 'xml';
    if (in_array($extension, ['yaml', 'yml'], true))
        return 'yaml';
    if (in_array($extension, ['toml'], true))
        return 'toml';
    if (in_array($extension, ['ini', 'cfg', 'conf', 'config'], true))
        return 'ini';
    if (in_array($extension, ['sh', 'bash', 'zsh'], true))
        return 'bash';

    // Data
    if (in_array($extension, ['csv'], true))
        return 'csv';
    if (in_array($extension, ['tsv'], true))
        return 'tsv';

    // Documents
    if (in_array($extension, ['pdf'], true))
        return 'pdf';
    if (in_array($extension, ['xlsx', 'xls'], true))
        return 'excel';
    if (in_array($extension, ['docx', 'doc'], true))
        return 'word';

    // Video
    if (in_array($extension, ['mp4', 'webm', 'ogg', 'mov', 'm4v'], true))
        return 'video';

    return null;
}

/**
 * Gets the file size in human-readable format
 */
function formatFileSize(int $bytes): string
{
    $sizes = ['B', 'KB', 'MB', 'GB'];
    if ($bytes <= 0)
        return '0 B';

    $i = (int) floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}

function buildAttachmentDirectUrl(array $attachment): string
{
    $ticketId = max(0, (int) ($attachment['ticket_id'] ?? 0));
    $storedName = trim((string) ($attachment['stored_name'] ?? ''));

    if ($ticketId > 0 && $storedName !== '') {
        return 'data/ticket_uploads/' . rawurlencode((string) $ticketId) . '/' . rawurlencode($storedName);
    }

    $storedPath = trim((string) ($attachment['stored_path'] ?? ''));
    if ($storedPath !== '') {
        $normalizedStoredPath = str_replace('\\', '/', $storedPath);
        $marker = '/data/ticket_uploads/';
        $markerPosition = strpos($normalizedStoredPath, $marker);
        if ($markerPosition !== false) {
            $relativePath = substr($normalizedStoredPath, $markerPosition + 1);
            $segments = array_values(array_filter(explode('/', (string) $relativePath), static fn(string $segment): bool => $segment !== ''));
            if ($segments !== []) {
                return implode('/', array_map('rawurlencode', $segments));
            }
        }
    }

    return '';
}

function buildAttachmentMessageMarker(string $filename): string
{
    return '[[attachment:' . str_replace(['[', ']'], '', $filename) . ']]';
}

function extractReferencedAttachmentNames(string $messageText): array
{
    $names = [];
    $normalized = str_replace(["\r\n", "\r"], "\n", $messageText);
    foreach (explode("\n", $normalized) as $line) {
        if (preg_match('/^\[\[attachment:(.+)\]\]$/', trim($line), $match) !== 1) {
            continue;
        }

        $name = trim((string) ($match[1] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return $names;
}

function findAttachmentByOriginalName(array $attachments, string $name): ?array
{
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        if ((string) ($attachment['original_name'] ?? '') === $name) {
            return $attachment;
        }
    }

    return null;
}

function renderMessageInlineAttachmentHtml(array $attachment): string
{
    $downloadUrl = buildAttachmentDirectUrl($attachment);
    $originalName = (string) ($attachment['original_name'] ?? '');
    $attachmentId = (int) ($attachment['id'] ?? 0);
    $isImageAttachment = isImageAttachment($attachment);
    $fileThumbUrl = 'preview.php?id=' . $attachmentId . '&thumbnail=1';
    $fileCheckUrl = 'preview.php?id=' . $attachmentId . '&check=1';

    ob_start();
    ?>
    <div class="message-inline-attachment">
        <?php if ($isImageAttachment && $downloadUrl !== ''): ?>
            <div class="message-inline-attachment-preview">
                <button type="button" class="attachment-thumb-button" data-image-preview-trigger
                    data-preview-src="<?= h($downloadUrl) ?>"
                    data-preview-alt="<?= h($originalName) ?>"
                    aria-label="<?= h(__('ticket.preview_image')) ?>">
                    <img class="attachment-inline-image" data-thumb-src="<?= h($downloadUrl) ?>" src=""
                        alt="<?= h($originalName) ?>" loading="lazy" decoding="async">
                </button>
            </div>
        <?php elseif (canPreviewFile($attachment)): ?>
            <div class="message-inline-attachment-preview">
                <button type="button" class="attachment-file-thumb-button" data-file-thumb-open
                    data-preview-id="<?= $attachmentId ?>" data-file-thumb-check-url="<?= h($fileCheckUrl) ?>"
                    data-file-thumb-src="<?= h($fileThumbUrl) ?>" aria-label="<?= h(__('ticket.preview_file')) ?>" hidden>
                    <iframe class="attachment-file-thumb-frame" title="<?= h(__('ticket.preview_file')) ?>" loading="lazy"
                        sandbox="allow-scripts allow-same-origin" scrolling="no"></iframe>
                </button>
            </div>
        <?php endif; ?>
        <a href="<?= h($downloadUrl !== '' ? $downloadUrl : '#') ?>" class="attachment-download-link message-inline-attachment-link"
            <?= $downloadUrl !== '' ? 'download target="_blank" rel="noopener noreferrer"' : '' ?>>
            <?= h($originalName) ?>
        </a>
    </div>
    <?php
    return (string) ob_get_clean();
}

function renderTicketMessageHtml(array $message, string $currentPage): string
{
    $rawMessageText = (string) ($message['message_text_raw'] ?? ($message['message_text'] ?? ''));
    $displayMessageText = (string) ($message['message_text'] ?? '');
    $messageAttachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
    $referencedAttachmentNames = extractReferencedAttachmentNames($rawMessageText);
    $referencedAttachmentLookup = array_fill_keys($referencedAttachmentNames, true);
    $listAttachments = array_values(array_filter(
        $messageAttachments,
        static fn(array $attachment): bool => !isset($referencedAttachmentLookup[(string) ($attachment['original_name'] ?? '')])
    ));
    $messageIsTranslated = !empty($message['message_is_translated']) && $rawMessageText !== '' && $displayMessageText !== '' && $rawMessageText !== $displayMessageText;
    $translationPending = !empty($message['translation_pending']);

    ob_start();
    ?>
    <article class="message <?= ($message['sender_role'] ?? '') === 'admin' ? 'admin' : 'user' ?>"
        data-message-id="<?= (int) ($message['id'] ?? 0) ?>"
        data-message-text="<?= h((string) json_encode($rawMessageText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
        data-translation-status="<?= $translationPending ? 'pending' : 'loaded' ?>">
        <div class="message-meta">
            <?php $senderEmail = (string) ($message['sender_email'] ?? ''); ?>
            <strong title="<?= h(formatUserDisplayName($senderEmail) !== strtolower(trim($senderEmail)) ? $senderEmail : '') ?>"><?= h(formatUserDisplayName($senderEmail)) ?></strong>
            <span
                class="message-role"><?= ($message['sender_role'] ?? '') === 'admin' ? h(__('ticket.role_admin')) : h(__('ticket.role_user')) ?></span>
            <span><?= h(formatDateTime((string) ($message['created_at'] ?? ''))) ?></span>
            <?php if ($messageIsTranslated): ?>
                <button type="button" class="translation-toggle-button" data-role="message-translation-toggle"
                    data-label-original="<?= h(__('ticket.show_original')) ?>"
                    data-label-translated="<?= h(__('ticket.show_translation')) ?>"
                    data-showing="translated"><?= h(__('ticket.show_original')) ?></button>
            <?php endif; ?>
            <?php if ($translationPending): ?>
                <div class="translation-status-indicator" data-role="translation-status" data-status="pending"
                    title="<?= h(__('translation.loading_tooltip')) ?>">
                    <span class="translation-flag-ghost"
                        aria-hidden="true"><?= h(SUPPORTED_LANGUAGES[getCurrentLanguage()]['flag']) ?></span>
                    <span class="translation-spinner-ring" aria-hidden="true"></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (trim($displayMessageText) !== ''): ?>
            <div class="message-text" data-role="message-text-content"
                data-translated-text="<?= h((string) json_encode($displayMessageText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                data-original-text="<?= h((string) json_encode($rawMessageText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                data-showing="translated">
                <?= formatTicketMessageText($displayMessageText, (int) ($message['id'] ?? 0), $messageAttachments) ?>
            </div>
        <?php endif; ?>

        <?php if ($listAttachments !== []): ?>
            <ul class="attachment-list">
                <?php foreach ($listAttachments as $attachmentIndex => $attachment): ?>
                    <?php
                    $downloadUrl = buildAttachmentDirectUrl($attachment);
                    $isImageAttachment = isImageAttachment($attachment);
                    $thumbLoading = $attachmentIndex < 3 ? 'eager' : 'lazy';
                    $thumbFetchPriority = $attachmentIndex < 3 ? 'high' : 'auto';
                    $attachmentId = (int) ($attachment['id'] ?? 0);
                    $fileThumbUrl = 'preview.php?id=' . $attachmentId . '&thumbnail=1';
                    $fileCheckUrl = 'preview.php?id=' . $attachmentId . '&check=1';
                    ?>
                    <li class="attachment-item">
                        <?php if ($isImageAttachment && $downloadUrl !== ''): ?>
                            <button type="button" class="attachment-thumb-button" data-image-preview-trigger
                                data-preview-src="<?= h($downloadUrl) ?>"
                                data-preview-alt="<?= h((string) ($attachment['original_name'] ?? '')) ?>"
                                aria-label="<?= h(__('ticket.preview_image')) ?>">
                                <img class="attachment-thumb" data-thumb-src="<?= h($downloadUrl) ?>" src=""
                                    alt="<?= h((string) ($attachment['original_name'] ?? '')) ?>" loading="<?= h($thumbLoading) ?>"
                                    fetchpriority="<?= h($thumbFetchPriority) ?>" decoding="async">
                            </button>
                        <?php endif; ?>
                        <?php if (canPreviewFile($attachment) && !$isImageAttachment): ?>
                            <button type="button" class="attachment-file-thumb-button" data-file-thumb-open
                                data-preview-id="<?= $attachmentId ?>" data-file-thumb-check-url="<?= h($fileCheckUrl) ?>"
                                data-file-thumb-src="<?= h($fileThumbUrl) ?>" aria-label="<?= h(__('ticket.preview_file')) ?>" hidden>
                                <iframe class="attachment-file-thumb-frame" title="<?= h(__('ticket.preview_file')) ?>" loading="lazy"
                                    sandbox="allow-scripts allow-same-origin" scrolling="no"></iframe>
                            </button>
                        <?php endif; ?>
                        <a href="<?= h($downloadUrl !== '' ? $downloadUrl : '#') ?>" class="attachment-download-link"
                            <?= $downloadUrl !== '' ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"' ?>>
                            <?= h((string) ($attachment['original_name'] ?? '')) ?>
                        </a>
                        <span class="attachment-size">(<?= formatFileSize(max(0, (int) ($attachment['file_size'] ?? 0))) ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function renderTicketCardHtml(array $ticket, ?array $ticketDetail, array $context = []): string
{
    $currentPage = (string) ($context['currentPage'] ?? 'index.php');
    $canManageTickets = !empty($context['canManageTickets']);
    $userIsAdmin = !empty($context['userIsAdmin']);
    $isAdminPortal = !empty($context['isAdminPortal']);
    $ictUsers = is_array($context['ictUsers'] ?? null) ? $context['ictUsers'] : [];
    $csrfToken = (string) ($context['csrfToken'] ?? '');
    $openTicketId = (int) ($context['openTicketId'] ?? 0);
    $view = (string) ($context['view'] ?? 'overview');
    $includeMessages = !array_key_exists('includeMessages', $context) || !empty($context['includeMessages']);
    $lazyMessages = !empty($context['lazyMessages']);
    $isReadOnlyTicket = !empty($context['isReadOnlyTicket']);
    $ticketColor = getStatusColor((string) ($ticket['status'] ?? ''));
    $shouldOpen = $openTicketId > 0 && (int) ($ticket['id'] ?? 0) === $openTicketId;
    $ticketOpenDuration = getTicketOpenDurationSeconds($ticket);
    $replyFormId = 'reply-form-' . (int) ($ticket['id'] ?? 0);
    $assignedEmail = (string) ($ticket['assigned_email'] ?? '');
    $assignedLabel = $assignedEmail !== '' ? formatUserDisplayName($assignedEmail) : __('ticket.unassigned');
    $requesterEmail = strtolower(trim((string) ($ticket['user_email'] ?? '')));
    $participantEmails = is_array($ticketDetail['participant_emails'] ?? null) ? $ticketDetail['participant_emails'] : [$requesterEmail];
    $requesterSummary = buildRequesterSummary($participantEmails, $requesterEmail);
    $requesterLabel = (string) ($requesterSummary['label'] ?? $requesterEmail);
    $requesterTooltip = (string) ($requesterSummary['tooltip'] ?? $requesterEmail);
    $requesterParticipants = is_array($requesterSummary['participants'] ?? null) ? $requesterSummary['participants'] : [$requesterEmail];
    $requesterExtraCount = (int) ($requesterSummary['extra_count'] ?? 0);
    $rawTitle = (string) ($ticket['title_raw'] ?? ($ticket['title'] ?? ''));
    $displayTitle = (string) ($ticket['title'] ?? '');
    $titleIsTranslated = !empty($ticket['title_is_translated']) && $rawTitle !== '' && $displayTitle !== '' && $rawTitle !== $displayTitle;
    $hasDueDate = trim((string) ($ticket['due_date'] ?? '')) !== '';
    $titleNeedsTrans = !empty($ticket['title_translation_pending']);
    $messagesNeedTrans = false;
    if ($includeMessages) {
        foreach (($ticketDetail['messages'] ?? []) as $msg) {
            if (!empty($msg['translation_pending'])) {
                $messagesNeedTrans = true;
                break;
            }
        }
    }
    $needsTranslation = $titleNeedsTrans || $messagesNeedTrans;
    $canAssignToRequester = isTemplateTicketCategory((string) ($ticket['category'] ?? ''));
    $viewerEmail = strtolower(trim((string) ($context['viewerEmail'] ?? '')));
    $activeCustomStatuses = is_array($context['activeCustomStatuses'] ?? null) ? $context['activeCustomStatuses'] : [];
    $activeCustomStatusLabels = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['display_label'] ?? '')),
        $activeCustomStatuses
    ), static fn(string $label): bool => $label !== ''));
    $recentCustomStatuses = is_array($context['recentCustomStatuses'] ?? null)
        ? array_values(array_filter(array_map('trim', $context['recentCustomStatuses']), static fn(string $s): bool => $s !== ''))
        : [];
    $statusSelectOptions = array_values(array_unique(array_merge(TICKET_STATUSES, $activeCustomStatusLabels)));
    $currentTicketStatus = (string) ($ticket['status'] ?? '');
    if ($currentTicketStatus !== '' && !in_array($currentTicketStatus, $statusSelectOptions, true)) {
        $statusSelectOptions[] = $currentTicketStatus;
    }
    $assignableIctUsers = array_values(array_unique(array_filter(
        extractIctUserEmails($ictUsers),
        static function (string $ictUser) use ($canAssignToRequester, $requesterEmail, $viewerEmail): bool {
            if ($ictUser === '') {
                return false;
            }

            if ($viewerEmail !== '' && $ictUser === $viewerEmail) {
                return true;
            }

            return $canAssignToRequester || $ictUser !== $requesterEmail;
        }
    )));
    $showTicketShareLink = ($canManageTickets && $isAdminPortal)
        || $isReadOnlyTicket
        || (!$isAdminPortal && $view === 'overview');
    $ticketShareUrl = $showTicketShareLink
        ? buildTicketShareUrl((int) ($ticket['id'] ?? 0))
        : '';

    ob_start();
    ?>
    <details class="ticket-card" data-ticket-id="<?= (int) ($ticket['id'] ?? 0) ?>"
        data-needs-translation="<?= $needsTranslation ? '1' : '0' ?>" style="--ticket-color: <?= h($ticketColor) ?>;"
        <?= $shouldOpen ? 'open' : '' ?>>
        <summary>
            <div class="ticket-summary">
                <div>
                    <p class="ticket-main-title"><strong><?php if ($showTicketShareLink && $ticketShareUrl !== ''): ?>
                            <button type="button" class="ticket-share-link" data-role="ticket-share-link"
                                data-share-url="<?= h($ticketShareUrl) ?>"
                                aria-label="<?= h(__('ticket.share_link_label')) ?>"
                                title="<?= h(__('ticket.share_link_label')) ?>">🔗</button>
                        <?php endif; ?><span
                                data-role="ticket-number">#<?= (int) ($ticket['id'] ?? 0) ?></span> · <span
                                data-role="ticket-title"
                                data-translated-text="<?= h((string) json_encode($displayTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                data-original-text="<?= h((string) json_encode($rawTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                data-showing="translated"><?= h($displayTitle) ?></span></strong>
                        <?php if ($titleIsTranslated): ?>
                            <button type="button" class="translation-toggle-button" data-role="title-translation-toggle"
                                data-label-original="<?= h(__('ticket.show_original')) ?>"
                                data-label-translated="<?= h(__('ticket.show_translation')) ?>"
                                data-showing="translated"><?= h(__('ticket.show_original')) ?></button>
                        <?php endif; ?>
                    </p>
                    <div class="ticket-subtitle">
                        <span data-role="requester-email" class="<?= $requesterExtraCount > 0 ? 'requester-multi' : '' ?>"
                            title="<?= h($requesterExtraCount > 0 ? $requesterTooltip : ($requesterLabel !== $requesterEmail && $requesterEmail !== '' ? $requesterEmail : '')) ?>"
                            data-ticket-users-trigger="<?= $requesterExtraCount > 0 ? '1' : '0' ?>"
                            data-user-emails="<?= h((string) json_encode($requesterParticipants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                            <?= h($requesterLabel) ?>
                        </span>
                        <span
                            data-role="ticket-category"><?= h(translateCategory((string) ($ticket['category'] ?? ''))) ?></span>
                        <span
                            data-role="ticket-created"><?= h(formatDateTime((string) ($ticket['created_at'] ?? ''))) ?></span>
                    </div>
                </div>
                <div class="ticket-subtitle">
                    <?php if ($isAdminPortal || $isReadOnlyTicket): ?>
                        <span class="status-pill" data-role="status-pill"
                            style="--ticket-color: <?= h($ticketColor) ?>;"><?= h(translateStatus((string) ($ticket['status'] ?? ''))) ?></span>
                    <?php endif; ?>
                    <span class="assignee-badge" data-role="assignee-badge"
                        style="--assignee-color: <?= h(emailToHexColor((string) ($assignedEmail !== '' ? $assignedEmail : 'onbekend@kvt.nl'))) ?>;"
                        title="<?= h($assignedEmail !== '' && $assignedLabel !== $assignedEmail ? $assignedEmail : '') ?>">
                        <?= h($assignedLabel) ?>
                    </span>
                    <?php if ($userIsAdmin && $isAdminPortal): ?>
                        <span class="status-pill" data-role="priority-pill"
                            style="--ticket-color: <?= h(getPriorityColor((int) ($ticket['priority'] ?? 0))) ?>;"><?= h(__('ticket.meta_priority')) ?>
                            <?= (int) ($ticket['priority'] ?? 0) ?> ·
                            <?= h(formatPriorityLabel((int) ($ticket['priority'] ?? 0))) ?></span>
                    <?php endif; ?>
                    <?php if ($isAdminPortal || $isReadOnlyTicket): ?>
                        <span class="count-badge" data-role="time-open-badge"><?= h(__('ticket.time_open')) ?>:
                            <?= h(formatDurationSeconds($ticketOpenDuration)) ?></span>
                    <?php endif; ?>
                    <?php if ($canManageTickets): ?>
                        <label class="private-ticket-toggle<?= !empty($ticket['is_private']) ? ' is-active' : '' ?>"
                            title="<?= h(__('ticket.private_label')) ?>">
                            <input type="checkbox" data-role="ticket-private-toggle" value="1"
                                <?= !empty($ticket['is_private']) ? 'checked' : '' ?>>
                            <span class="status-pill private-ticket-pill" data-role="private-ticket-pill"
                                data-label-private="<?= h(__('ticket.private_label')) ?>"
                                data-label-public="<?= h(__('ticket.public_label')) ?>"><?= h(!empty($ticket['is_private']) ? __('ticket.private_label') : __('ticket.public_label')) ?></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </summary>

        <div class="ticket-body">
            <div class="meta-grid">
                <?php if ($userIsAdmin && $isAdminPortal): ?>
                    <div class="meta-item">
                        <span class="meta-label"><?= h(__('ticket.title_label')) ?></span>
                        <button type="button" class="secondary-button" data-role="change-title-open"
                            data-current-title="<?= h($rawTitle) ?>">
                            <?= h(__('ticket.change_title_button')) ?>
                        </button>
                    </div>
                <?php endif; ?>
                <div class="meta-item">
                    <span class="meta-label"><?= h(__('ticket.meta_created')) ?></span>
                    <span data-role="meta-created-value"><?= h(formatDateTime((string) ($ticket['created_at'] ?? ''))) ?> ·
                        <?= h(formatDurationSeconds($ticketOpenDuration)) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label"><?= h(__('ticket.meta_updated')) ?></span>
                    <span
                        data-role="meta-updated-value"><?= h(formatDateTime((string) ($ticket['updated_at'] ?? ''))) ?></span>
                </div>
                <?php if ($hasDueDate): ?>
                    <div class="meta-item">
                        <span class="meta-label"><?= h(__('ticket.meta_due_date')) ?></span>
                        <span
                            data-role="meta-due-date-value"><?= h(formatDueDateLabel((string) ($ticket['due_date'] ?? ''))) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($userIsAdmin && $isAdminPortal && !$hasDueDate): ?>
                    <div class="meta-item">
                        <span class="meta-label"><?= h(__('ticket.meta_priority')) ?></span>
                        <select name="priority" form="<?= h($replyFormId) ?>" data-role="priority-select">
                            <option value="0" <?= (int) ($ticket['priority'] ?? 0) === 0 ? 'selected' : '' ?>>
                                <?= h(__('ticket.priority_0')) ?>
                            </option>
                            <option value="1" <?= (int) ($ticket['priority'] ?? 0) === 1 ? 'selected' : '' ?>>
                                <?= h(__('ticket.priority_1')) ?>
                            </option>
                            <option value="2" <?= (int) ($ticket['priority'] ?? 0) === 2 ? 'selected' : '' ?>>
                                <?= h(__('ticket.priority_2')) ?>
                            </option>
                        </select>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?= h(__('ticket.participants_admin_heading')) ?></span>
                        <button type="button" class="secondary-button" data-role="manage-participants-open">
                            <?= h(__('ticket.manage_participants_button')) ?>
                        </button>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?= h(__('ticket.meta_category')) ?></span>
                        <button type="button" class="secondary-button" data-role="change-category-open"
                            data-current-category="<?= h((string) ($ticket['category'] ?? '')) ?>">
                            <?= h(__('ticket.change_category_button')) ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            $messagesWrapHidden = $includeMessages
                ? (($ticketDetail['messages'] ?? []) === [])
                : !$lazyMessages;
            ?>
            <div data-role="messages-wrap"<?= $messagesWrapHidden ? ' hidden' : '' ?><?= $lazyMessages ? ' data-lazy-messages="1"' : '' ?>>
                <h3><?= h(__('ticket.messages_heading')) ?></h3>
                <div class="thread" data-role="thread">
                    <?php if ($includeMessages): ?>
                        <?php foreach (($ticketDetail['messages'] ?? []) as $message): ?>
                            <?= renderTicketMessageHtml($message, $currentPage) ?>
                        <?php endforeach; ?>
                    <?php elseif ($lazyMessages): ?>
                        <p class="hint" data-role="thread-loading-hint" hidden><?= h(__('ticket.thread_loading')) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ticket-users-popover" data-role="ticket-users-popover" hidden>
                <p class="ticket-users-popover-title"><?= h(__('ticket.participants_title')) ?></p>
                <ul class="ticket-users-popover-list" data-role="ticket-users-popover-list">
                    <?php foreach ($requesterParticipants as $participantEmail): ?>
                        <li><?= renderUserDisplayLabel($participantEmail) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($canManageTickets): ?>
                <div class="ticket-participants-modal" data-role="ticket-participants-modal" hidden>
                    <div class="ticket-participants-modal-card">
                        <div class="ticket-participants-modal-head">
                            <h3><?= h(__('ticket.participants_admin_heading')) ?></h3>
                            <button type="button" class="participant-modal-close" data-role="manage-participants-close"
                                aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
                        </div>

                        <p class="hint" data-role="manage-participants-feedback"></p>

                        <div class="participant-chip-list" data-role="participant-chip-list"
                            data-creator-email="<?= h($requesterEmail) ?>">
                            <?php foreach ($requesterParticipants as $participantEmail): ?>
                                <button type="button" class="participant-chip-form" data-role="participant-remove-toggle"
                                    data-participant-email="<?= h($participantEmail) ?>">
                                    <span
                                        class="participant-chip<?= $participantEmail === $requesterEmail ? ' is-requester' : '' ?>"
                                        title="<?= h(formatUserDisplayName($participantEmail) !== $participantEmail ? $participantEmail : '') ?>">
                                        <span class="participant-chip-label"><?= h(formatUserDisplayName($participantEmail)) ?></span>
                                        <span class="participant-chip-remove-text"><?= h(__('ticket.participant_remove')) ?></span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <form method="post"
                            action="<?= h($currentPage) ?><?= $isAdminPortal && $view === 'settings' ? '?view=settings' : '' ?>"
                            class="participant-add-form" data-role="participant-add-form">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="add_ticket_participants">
                            <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                            <input type="hidden" name="ticket_id" value="<?= (int) ($ticket['id'] ?? 0) ?>">

                            <label>
                                <?= h(__('ticket.participants_add_label')) ?>
                                <input type="text" name="participant_emails"
                                    placeholder="<?= h(__('ticket.participants_add_placeholder')) ?>" data-email-chip-input="1">
                            </label>
                            <div class="button-row">
                                <button type="submit"
                                    data-role="participants-apply-button"><?= h(__('ticket.participants_save_button')) ?></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="ticket-participants-modal" data-role="ticket-title-modal" hidden>
                    <div class="ticket-participants-modal-card">
                        <div class="ticket-participants-modal-head">
                            <h3><?= h(__('ticket.change_title_heading')) ?></h3>
                            <button type="button" class="participant-modal-close" data-role="change-title-close"
                                aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
                        </div>

                        <p class="hint" data-role="change-title-feedback"></p>

                        <label>
                            <?= h(__('ticket.change_title_label')) ?>
                            <input type="text" maxlength="150" data-role="change-title-input"
                                value="<?= h($rawTitle) ?>">
                        </label>

                        <div class="button-row">
                            <button type="button" class="secondary-button" data-role="change-title-cancel">
                                <?= h(__('ticket.change_title_cancel')) ?>
                            </button>
                            <button type="button" data-role="change-title-save">
                                <?= h(__('ticket.change_title_save')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ticket-participants-modal" data-role="ticket-category-modal" hidden>
                    <div class="ticket-participants-modal-card">
                        <div class="ticket-participants-modal-head">
                            <h3><?= h(__('ticket.change_category_heading')) ?></h3>
                            <button type="button" class="participant-modal-close" data-role="change-category-close"
                                aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
                        </div>

                        <p class="hint" data-role="change-category-feedback"></p>

                        <label>
                            <?= h(__('ticket.change_category_label')) ?>
                            <select data-role="change-category-select">
                                <?php foreach (TICKET_CATEGORIES as $categoryOption): ?>
                                    <option value="<?= h($categoryOption) ?>" <?= (string) ($ticket['category'] ?? '') === $categoryOption ? 'selected' : '' ?>>
                                        <?= h(translateCategory($categoryOption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="checkbox-line">
                            <input type="checkbox" data-role="change-category-reassign" value="1">
                            <span><?= h(__('ticket.change_category_reassign')) ?></span>
                        </label>

                        <div class="button-row">
                            <button type="button" class="secondary-button" data-role="change-category-cancel">
                                <?= h(__('ticket.change_category_cancel')) ?>
                            </button>
                            <button type="button" data-role="change-category-save">
                                <?= h(__('ticket.change_category_save')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ticket-participants-modal" data-role="ticket-custom-status-modal" hidden>
                    <div class="ticket-participants-modal-card">
                        <div class="ticket-participants-modal-head">
                            <h3><?= h(__('ticket.custom_status_heading')) ?></h3>
                            <button type="button" class="participant-modal-close" data-role="custom-status-close"
                                aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
                        </div>

                        <p class="hint" data-role="custom-status-feedback"></p>

                        <label>
                            <?= h(__('ticket.custom_status_label')) ?>
                            <input type="text" data-role="custom-status-input"
                                maxlength="<?= (int) CUSTOM_TICKET_STATUS_MAX_LENGTH ?>"
                                placeholder="<?= h(__('ticket.custom_status_placeholder')) ?>"
                                autocomplete="off">
                        </label>

                        <?php if ($recentCustomStatuses !== []): ?>
                            <div class="custom-status-recent" data-role="custom-status-recent">
                                <p class="hint"><?= h(__('ticket.custom_status_recent')) ?></p>
                                <div class="custom-status-recent-chips">
                                    <?php foreach ($recentCustomStatuses as $recentStatus): ?>
                                        <button type="button" class="secondary-button custom-status-recent-chip"
                                            data-role="custom-status-recent-pick"
                                            data-status="<?= h($recentStatus) ?>">
                                            <?= h($recentStatus) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="button-row">
                            <button type="button" class="secondary-button" data-role="custom-status-cancel">
                                <?= h(__('ticket.custom_status_cancel')) ?>
                            </button>
                            <button type="button" data-role="custom-status-save">
                                <?= h(__('ticket.custom_status_apply')) ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$isReadOnlyTicket): ?>
            <form method="post"
                action="<?= h($currentPage) ?><?= $isAdminPortal && $view === 'settings' ? '?view=settings' : '' ?>"
                enctype="multipart/form-data" class="reply-form" id="<?= h($replyFormId) ?>">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="form_action" value="reply_ticket">
                <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                <input type="hidden" name="ticket_id" value="<?= (int) ($ticket['id'] ?? 0) ?>">

                <?php if ($canManageTickets): ?>
                    <div class="admin-grid">
                        <label class="status-select-field">
                            <?= h(__('ticket.status_label')) ?>
                            <div class="status-select-row">
                                <select name="status" data-role="status-select">
                                    <?php foreach ($statusSelectOptions as $status): ?>
                                        <option value="<?= h($status) ?>" <?= $currentTicketStatus === $status ? 'selected' : '' ?>><?= h(translateStatus($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="secondary-button" data-role="custom-status-open">
                                    <?= h(__('ticket.custom_status_other')) ?>
                                </button>
                            </div>
                        </label>
                        <label>
                            <?= h(__('ticket.assigned_to')) ?>
                            <select name="assigned_email" data-role="assigned-select">
                                <option value=""><?= h(__('ticket.unassigned')) ?></option>
                                <?php foreach ($assignableIctUsers as $ictUser):
                                    $ictUser = strtolower($ictUser); ?>
                                    <option value="<?= h($ictUser) ?>" <?= strtolower((string) ($ticket['assigned_email'] ?? '')) === $ictUser ? 'selected' : '' ?>>
                                        <?= h(formatUserDisplayName($ictUser)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php if ($hasDueDate): ?>
                            <label>
                                <?= h(__('ticket.meta_due_date')) ?>
                                <input type="date" name="due_date" value="<?= h((string) ($ticket['due_date'] ?? '')) ?>"
                                    data-role="due-date-input">
                            </label>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <label>
                    <?= h(__('ticket.new_message')) ?>
                    <div class="textarea-wrapper">
                        <textarea name="message" placeholder="<?= h(__('ticket.new_message_placeholder')) ?>"></textarea>
                        <button type="button" class="key-picker-toggle" title="<?= h(__('ticket.key_picker_tooltip')) ?>"
                            aria-label="<?= h(__('ticket.key_picker_tooltip')) ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="2" y="6" width="20" height="12" rx="2" />
                                <path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M6 14h.01M18 14h.01M10 14h4" />
                            </svg>
                        </button>
                        <div class="key-picker-popup" hidden aria-label="<?= h(__('ticket.key_picker_tooltip')) ?>"></div>
                    </div>
                </label>

                <?php if (!$canManageTickets && (string) ($ticket['status'] ?? '') === 'afgehandeld'): ?>
                    <label class="checkbox-line" data-role="reopen-wrap" data-user-reopen-enabled="1">
                        <input type="checkbox" name="reopen_ticket" value="1">
                        <span><?= h(__('ticket.reopen')) ?></span>
                    </label>
                <?php else: ?>
                    <label class="checkbox-line" data-role="reopen-wrap"
                        data-user-reopen-enabled="<?= $canManageTickets ? '0' : '1' ?>" hidden>
                        <input type="checkbox" name="reopen_ticket" value="1">
                        <span><?= h(__('ticket.reopen')) ?></span>
                    </label>
                <?php endif; ?>

                <label>
                    <?= h(__('ticket.add_attachments')) ?>
                    <input type="file" name="reply_attachments[]" multiple data-accumulate-files="1">
                    <ul class="draft-attachments-list" data-draft-attachments-list hidden></ul>
                    <span class="hint"><?= h(__('ticket.file_hint')) ?></span>
                </label>

                <div class="button-row">
                    <button
                        type="submit"><?= $canManageTickets ? h(__('ticket.btn_save')) : h(__('ticket.btn_reply')) ?></button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </details>
    <?php

    return trim((string) ob_get_clean());
}

function buildTicketPollEntry(array $ticket, ?array $ticketDetail, array $context = []): array
{
    $ticketOpenDuration = getTicketOpenDurationSeconds($ticket);
    $assignedEmail = (string) ($ticket['assigned_email'] ?? '');
    $participantEmails = is_array($ticketDetail['participant_emails'] ?? null) ? $ticketDetail['participant_emails'] : [(string) ($ticket['user_email'] ?? '')];
    $requesterSummary = buildRequesterSummary($participantEmails, (string) ($ticket['user_email'] ?? ''));

    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'title' => (string) ($ticket['title'] ?? ''),
        'user_email' => (string) ($ticket['user_email'] ?? ''),
        'requester_label' => (string) ($requesterSummary['label'] ?? ''),
        'requester_tooltip' => (string) ($requesterSummary['tooltip'] ?? ''),
        'participant_emails' => array_values($requesterSummary['participants'] ?? []),
        'category' => (string) ($ticket['category'] ?? ''),
        'category_label' => translateCategory((string) ($ticket['category'] ?? '')),
        'created_at_label' => formatDateTime((string) ($ticket['created_at'] ?? '')),
        'updated_at_label' => formatDateTime((string) ($ticket['updated_at'] ?? '')),
        'due_date' => (string) ($ticket['due_date'] ?? ''),
        'due_date_label' => formatDueDateLabel((string) ($ticket['due_date'] ?? '')),
        'status' => (string) ($ticket['status'] ?? ''),
        'status_label' => translateStatus((string) ($ticket['status'] ?? '')),
        'status_color' => getStatusColor((string) ($ticket['status'] ?? '')),
        'priority' => (int) ($ticket['priority'] ?? 0),
        'priority_label' => formatPriorityLabel((int) ($ticket['priority'] ?? 0)),
        'priority_color' => getPriorityColor((int) ($ticket['priority'] ?? 0)),
        'assigned_email' => $assignedEmail,
        'assigned_label' => $assignedEmail !== '' ? formatUserDisplayName($assignedEmail) : __('ticket.unassigned'),
        'assigned_color' => emailToHexColor((string) ($assignedEmail !== '' ? $assignedEmail : 'onbekend@kvt.nl')),
        'is_private' => !empty($ticket['is_private']),
        'message_count' => (int) ($ticket['message_count'] ?? 0),
        'time_open_label' => formatDurationSeconds($ticketOpenDuration),
        'meta_created_value' => formatDateTime((string) ($ticket['created_at'] ?? '')) . ' · ' . formatDurationSeconds($ticketOpenDuration),
        'meta_updated_value' => formatDateTime((string) ($ticket['updated_at'] ?? '')),
        'card_html' => renderTicketCardHtml($ticket, $ticketDetail, $context),
        'messages' => array_map(
            static fn(array $message): array => [
                'id' => (int) ($message['id'] ?? 0),
                'html' => renderTicketMessageHtml($message, (string) ($context['currentPage'] ?? 'index.php')),
            ],
            $ticketDetail['messages'] ?? []
        ),
    ];
}

function normalizeSavedTicketOverviewFilters(array $prefs, array $activeCustomStatusLabels = []): array
{
    $savedFilters = $prefs['ticket_overview_filters'] ?? null;
    if (!is_array($savedFilters)) {
        return [
            'status_filter_active' => false,
            'status_filters' => [],
            'category_filter_active' => false,
            'category_filters' => [],
            'assigned_filter' => '',
            'search_query' => '',
        ];
    }

    $statusFilters = array_values(array_filter(
        array_map('trim', (array) ($savedFilters['status_filters'] ?? [])),
        static fn(string $status): bool => isAllowedTicketStatusValue($status, $activeCustomStatusLabels)
    ));
    $categoryFilters = array_values(array_filter(
        array_map('trim', (array) ($savedFilters['category_filters'] ?? [])),
        static fn(string $category): bool => in_array($category, TICKET_CATEGORIES, true)
    ));
    $assignedFilter = trim((string) ($savedFilters['assigned_filter'] ?? ''));
    $searchQuery = trim((string) ($savedFilters['search_query'] ?? ''));
    $validAssignedValues = array_merge(['', '__unassigned__'], array_map('strtolower', $GLOBALS['ictUsers'] ?? []));

    if (!in_array($assignedFilter, $validAssignedValues, true)) {
        $assignedFilter = '';
    }

    return [
        'status_filter_active' => !empty($savedFilters['status_filter_active']),
        'status_filters' => $statusFilters,
        'category_filter_active' => !empty($savedFilters['category_filter_active']),
        'category_filters' => $categoryFilters,
        'assigned_filter' => $assignedFilter,
        'search_query' => $searchQuery,
    ];
}

function isStatusFilterSelected(string $status, array $statusFilters, bool $statusFilterRequestActive): bool
{
    return !$statusFilterRequestActive || in_array($status, $statusFilters, true);
}

function isCategoryFilterSelected(string $category, array $categoryFilters, bool $categoryFilterRequestActive): bool
{
    return !$categoryFilterRequestActive || in_array($category, $categoryFilters, true);
}

function getSelectableTicketCategories(): array
{
    return array_values(array_filter(
        TICKET_CATEGORIES,
        static fn(string $category): bool => !isTemplateTicketCategory($category)
    ));
}

function getTemplateTicketCategories(): array
{
    return TEMPLATE_TICKET_CATEGORIES;
}

function isTemplateTicketCategory(string $category): bool
{
    return in_array($category, TEMPLATE_TICKET_CATEGORIES, true);
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

function normalizeDueDateInput(string $input): ?string
{
    $value = trim($input);
    if ($value === '') {
        return null;
    }

    $datePart = substr($value, 0, 10);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart) !== 1) {
        return null;
    }

    return $datePart;
}

function isDueDateTodayOrFuture(string $dueDate): bool
{
    $normalized = normalizeDueDateInput($dueDate);
    if ($normalized === null) {
        return false;
    }

    try {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $today = new DateTimeImmutable('today', $timezone);
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized, $timezone);
        return $due instanceof DateTimeImmutable && $due >= $today;
    } catch (Throwable) {
        return false;
    }
}

function countBusinessDaysUntilDueDate(string $dueDate): int
{
    $normalized = normalizeDueDateInput($dueDate);
    if ($normalized === null) {
        return 0;
    }

    try {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $today = new DateTimeImmutable('today', $timezone);
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized, $timezone);
        if (!$due instanceof DateTimeImmutable || $due < $today) {
            return 0;
        }

        $businessDays = 0;
        $cursor = $today;
        while ($cursor <= $due) {
            $weekday = (int) $cursor->format('N');
            if ($weekday <= 5) {
                $businessDays++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $businessDays;
    } catch (Throwable) {
        return 0;
    }
}

function getPriorityFromDueDate(string $dueDate): int
{
    $normalized = normalizeDueDateInput($dueDate);
    if ($normalized === null) {
        return 0;
    }

    try {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $today = new DateTimeImmutable('today', $timezone);
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized, $timezone);
        if (!$due instanceof DateTimeImmutable) {
            return 0;
        }

        $calendarDaysRemaining = (int) $today->diff($due)->format('%r%a');
        if ($calendarDaysRemaining < 7) {
            return countBusinessDaysUntilDueDate($normalized) < 3 ? 2 : 1;
        }
    } catch (Throwable) {
        return 0;
    }

    return 0;
}

function formatDueDateLabel(?string $dueDate): string
{
    $normalized = normalizeDueDateInput((string) $dueDate);
    if ($normalized === null) {
        return '';
    }

    try {
        $date = new DateTimeImmutable($normalized);
        return $date->format('d-m-Y');
    } catch (Throwable) {
        return $normalized;
    }
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

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = __('flash.upload_request_too_large');
            continue;
        }

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

function parsePhpIniSize(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}

function detectOversizedUploadRequest(): bool
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength <= 0) {
        return false;
    }

    $postMaxBytes = parsePhpIniSize((string) ini_get('post_max_size'));
    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        return true;
    }

    return $_SERVER['REQUEST_METHOD'] === 'POST'
        && $contentLength > 0
        && ($_POST === [] || !isset($_POST['csrf_token']))
        && empty($_FILES);
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
    if (isset(STATUS_COLORS[$status])) {
        return STATUS_COLORS[$status];
    }

    $normalized = trim($status);
    if ($normalized === '') {
        return '#475569';
    }

    foreach (STATUS_COLORS as $builtInStatus => $color) {
        if (mb_strtolower($builtInStatus) === mb_strtolower($normalized)) {
            return $color;
        }
    }

    return stringToHexColor($normalized);
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
        'afwachtende op derde partij' => 'status.afwachtende_op_derde_partij',
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
        'licentie aanvragen' => 'category.licentie_aanvragen',
        'Business Central' => 'category.business_central',
        'Hardwareproblemen' => 'category.hardwareproblemen',
        'Softwareproblemen' => 'category.softwareproblemen',
        'sleutels.kvt.nl web-applicatieproblemen' => 'category.web_app_problemen',
        'Anders' => 'category.anders',
        TEMPLATE_TICKET_CATEGORY => 'category.laptop_klaarmaken',
        'Telefoon Klaarmaken' => 'category.telefoon_klaarmaken',
    ];

    return isset($map[$dbCategory]) ? __($map[$dbCategory]) : $dbCategory;
}

function emailToHexColor(string $email): string
{
    $normalizedEmail = strtolower(trim($email));
    if ($normalizedEmail === '') {
        return '#64748b';
    }

    $configuredColor = getConfiguredIctUserColor($normalizedEmail);
    if ($configuredColor !== null) {
        return $configuredColor;
    }

    return stringToHexColor($normalizedEmail);
}

function stringToHexColor(string $value): string
{
    $normalized = trim($value);
    if ($normalized === '') {
        return '#64748b';
    }

    $hash = hash('sha256', mb_strtolower($normalized));
    $hueSeed = hexdec(substr($hash, 0, 2));
    $saturationSeed = hexdec(substr($hash, 2, 2));
    $lightnessSeed = hexdec(substr($hash, 4, 2));

    $hue = (int) (($hueSeed * 137) % 360);
    $hue = (int) ((round($hue / 15) * 15) % 360);
    $saturation = 68 + ($saturationSeed % 18);
    $lightness = 42 + ($lightnessSeed % 12);

    return hslToHex($hue, $saturation, $lightness);
}

function sanitizeCustomTicketStatusInput(string $raw): ?string
{
    $value = trim($raw);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/[^\p{L}\p{N}\s\'\-]/u', '', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    if ($value === '') {
        return null;
    }

    if (mb_strlen($value) > CUSTOM_TICKET_STATUS_MAX_LENGTH) {
        $value = rtrim(mb_substr($value, 0, CUSTOM_TICKET_STATUS_MAX_LENGTH));
    }

    return $value !== '' ? $value : null;
}

function matchBuiltInTicketStatus(string $value): ?string
{
    $needle = mb_strtolower(trim($value));
    if ($needle === '') {
        return null;
    }

    foreach (TICKET_STATUSES as $status) {
        if (mb_strtolower($status) === $needle) {
            return $status;
        }
    }

    return null;
}

function isAllowedTicketStatusValue(string $status, array $activeCustomLabels = []): bool
{
    if (in_array($status, TICKET_STATUSES, true)) {
        return true;
    }

    return in_array($status, $activeCustomLabels, true);
}

/**
 * Resolve a posted/selected status to a canonical value (built-in or registered custom).
 */
function resolveTicketStatusValue(string $raw, ?TicketStore $store, string $actorEmail): ?string
{
    $sanitized = sanitizeCustomTicketStatusInput($raw);
    if ($sanitized === null) {
        return null;
    }

    $builtIn = matchBuiltInTicketStatus($sanitized);
    if ($builtIn !== null) {
        return $builtIn;
    }

    if ($store instanceof TicketStore) {
        return $store->resolveAndRegisterCustomStatus($sanitized, $actorEmail);
    }

    return $sanitized;
}

/**
 * @return list<string>
 */
function getRecentCustomStatusesForUser(string $email): array
{
    $prefs = loadUserPrefs($email);
    $recent = array_values(array_filter(
        array_map('trim', (array) ($prefs['recent_custom_statuses'] ?? [])),
        static fn(string $status): bool => $status !== ''
    ));

    return array_slice($recent, 0, 5);
}

function pushRecentCustomStatusForUser(string $email, string $status): void
{
    $status = trim($status);
    if ($status === '' || matchBuiltInTicketStatus($status) !== null) {
        return;
    }

    $recent = getRecentCustomStatusesForUser($email);
    $recent = array_values(array_filter(
        $recent,
        static fn(string $entry): bool => mb_strtolower($entry) !== mb_strtolower($status)
    ));
    array_unshift($recent, $status);
    saveUserPref($email, 'recent_custom_statuses', array_slice($recent, 0, 5));
}

/**
 * First time a custom status created by this user appears, include it in their active status filters.
 * Once seen (and optionally unchecked by the user), it is not forced back on.
 *
 * @param list<array{display_label?: string, created_by_email?: string}> $activeCustomStatuses
 * @param list<string> $statusFilters
 * @return list<string>
 */
function applyDefaultEnabledOwnCustomStatusFilters(
    string $userEmail,
    array $activeCustomStatuses,
    bool $statusFilterActive,
    array $statusFilters
): array {
    $userEmail = strtolower(trim($userEmail));
    if ($userEmail === '' || $activeCustomStatuses === []) {
        return $statusFilters;
    }

    $prefs = loadUserPrefs($userEmail);
    $known = array_values(array_filter(
        array_map('trim', (array) ($prefs['known_own_custom_status_filters'] ?? [])),
        static fn(string $status): bool => $status !== ''
    ));
    $knownLower = array_map(static fn(string $status): string => mb_strtolower($status), $known);
    $filtersLower = array_map(static fn(string $status): string => mb_strtolower($status), $statusFilters);

    $changedKnown = false;
    $changedFilters = false;

    foreach ($activeCustomStatuses as $row) {
        $label = trim((string) ($row['display_label'] ?? ''));
        $creator = strtolower(trim((string) ($row['created_by_email'] ?? '')));
        if ($label === '' || $creator === '' || $creator !== $userEmail) {
            continue;
        }

        $labelLower = mb_strtolower($label);
        if (!in_array($labelLower, $knownLower, true)) {
            $known[] = $label;
            $knownLower[] = $labelLower;
            $changedKnown = true;

            if ($statusFilterActive && !in_array($labelLower, $filtersLower, true)) {
                $statusFilters[] = $label;
                $filtersLower[] = $labelLower;
                $changedFilters = true;
            }
        }
    }

    if ($changedKnown) {
        saveUserPref($userEmail, 'known_own_custom_status_filters', array_values(array_unique($known)));
    }

    if ($changedFilters) {
        $overview = normalizeSavedTicketOverviewFilters($prefs, array_column($activeCustomStatuses, 'display_label'));
        $overview['status_filter_active'] = true;
        $overview['status_filters'] = array_values(array_unique($statusFilters));
        saveUserPref($userEmail, 'ticket_overview_filters', $overview);
    }

    return array_values(array_unique($statusFilters));
}

function getConfiguredIctUserColor(string $normalizedEmail): ?string
{
    if (!isset($GLOBALS['ictUserColors']) || !is_array($GLOBALS['ictUserColors'])) {
        return null;
    }

    $rawColor = trim((string) ($GLOBALS['ictUserColors'][$normalizedEmail] ?? ''));
    if ($rawColor === '') {
        return null;
    }

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $rawColor) === 1) {
        return strtolower($rawColor);
    }

    if (preg_match('/^#[0-9a-fA-F]{3}$/', $rawColor) === 1) {
        $expanded = strtolower($rawColor);
        return '#' . $expanded[1] . $expanded[1] . $expanded[2] . $expanded[2] . $expanded[3] . $expanded[3];
    }

    return null;
}

function hslToHex(int $hue, int $saturation, int $lightness): string
{
    $h = (($hue % 360) + 360) % 360;
    $s = max(0, min(100, $saturation)) / 100;
    $l = max(0, min(100, $lightness)) / 100;

    if ($s == 0.0) {
        $gray = (int) round($l * 255);
        return sprintf('#%02x%02x%02x', $gray, $gray, $gray);
    }

    $chroma = (1 - abs((2 * $l) - 1)) * $s;
    $x = $chroma * (1 - abs(fmod($h / 60, 2) - 1));
    $match = $l - ($chroma / 2);

    $r1 = 0.0;
    $g1 = 0.0;
    $b1 = 0.0;

    if ($h < 60) {
        $r1 = $chroma;
        $g1 = $x;
    } elseif ($h < 120) {
        $r1 = $x;
        $g1 = $chroma;
    } elseif ($h < 180) {
        $g1 = $chroma;
        $b1 = $x;
    } elseif ($h < 240) {
        $g1 = $x;
        $b1 = $chroma;
    } elseif ($h < 300) {
        $r1 = $x;
        $b1 = $chroma;
    } else {
        $r1 = $chroma;
        $b1 = $x;
    }

    $r = (int) round(($r1 + $match) * 255);
    $g = (int) round(($g1 + $match) * 255);
    $b = (int) round(($b1 + $match) * 255);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function buildCategoryChangeNote(string $oldCategory, string $newCategory, bool $assigneeChanged, string $assignedEmail): string
{
    $lines = [
        __('ticket.category_note_changed', translateCategory($oldCategory), translateCategory($newCategory)),
    ];

    if ($assigneeChanged) {
        $lines[] = $assignedEmail !== ''
            ? __('ticket.category_note_reassigned', $assignedEmail)
            : __('ticket.category_note_unassigned');
    }

    return implode(PHP_EOL, $lines);
}

function buildStatusChangeNote(string $status, string $changedByEmail): string
{
    return __('flash.status_changed_to', translateStatus($status));
}

function buildParticipantChangeNote(array $addedParticipants, array $removedParticipants): string
{
    $normalize = static function (array $emails): array {
        $normalized = [];
        foreach ($emails as $emailRaw) {
            $email = strtolower(trim((string) $emailRaw));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $normalized[$email] = $email;
        }

        return array_values($normalized);
    };

    $added = $normalize($addedParticipants);
    $removed = $normalize($removedParticipants);
    $lines = [];

    if ($added !== []) {
        $lines[] = __('ticket.participant_note_added', implode(', ', $added));
    }

    if ($removed !== []) {
        $lines[] = __('ticket.participant_note_removed', implode(', ', $removed));
    }

    return implode(PHP_EOL, $lines);
}

function getShortcutKeyDefinitions(): array
{
    static $definitions = null;
    if ($definitions !== null) {
        return $definitions;
    }

    $definitions = [
        ['label' => 'Ctrl', 'icon' => null, 'aliases' => ['ctrl', 'control', 'ctl', 'strg']],
        ['label' => 'Alt', 'icon' => null, 'aliases' => ['alt', 'option']],
        ['label' => 'Alt Gr', 'icon' => null, 'aliases' => ['altgr', 'altgraph']],
        ['label' => 'Shift', 'icon' => null, 'aliases' => ['shift']],
        ['label' => '', 'icon' => 'windows', 'aliases' => ['win', 'windows', 'meta', 'super', 'cmd', 'command']],
        ['label' => 'Fn', 'icon' => null, 'aliases' => ['fn', 'function']],
        ['label' => 'Delete', 'icon' => null, 'aliases' => ['delete', 'del']],
        ['label' => 'Backspace', 'icon' => null, 'aliases' => ['backspace', 'bksp']],
        ['label' => 'Enter', 'icon' => null, 'aliases' => ['enter', 'return']],
        ['label' => 'Esc', 'icon' => null, 'aliases' => ['esc', 'escape']],
        ['label' => 'Tab', 'icon' => null, 'aliases' => ['tab']],
        ['label' => 'Space', 'icon' => null, 'aliases' => ['space', 'spacebar']],
        ['label' => 'Caps Lock', 'icon' => null, 'aliases' => ['caps', 'capslock']],
        ['label' => 'Insert', 'icon' => null, 'aliases' => ['ins', 'insert']],
        ['label' => 'Home', 'icon' => null, 'aliases' => ['home']],
        ['label' => 'End', 'icon' => null, 'aliases' => ['end']],
        ['label' => 'Pg Up', 'icon' => null, 'aliases' => ['pageup', 'pgup']],
        ['label' => 'Pg Down', 'icon' => null, 'aliases' => ['pagedown', 'pgdn']],
        ['label' => 'PrtSc', 'icon' => null, 'aliases' => ['printscreen', 'prtsc', 'printscr', 'snapshot']],
        ['label' => 'Scr Lk', 'icon' => null, 'aliases' => ['scrolllock', 'scrlk']],
        ['label' => 'Pause', 'icon' => null, 'aliases' => ['pause', 'break']],
        ['label' => 'Menu', 'icon' => null, 'aliases' => ['menu', 'contextmenu', 'apps']],
        ['label' => '', 'icon' => 'arrow-up', 'aliases' => ['up', 'arrowup', 'uparrow']],
        ['label' => '', 'icon' => 'arrow-down', 'aliases' => ['down', 'arrowdown', 'downarrow']],
        ['label' => '', 'icon' => 'arrow-left', 'aliases' => ['left', 'arrowleft', 'leftarrow']],
        ['label' => '', 'icon' => 'arrow-right', 'aliases' => ['right', 'arrowright', 'rightarrow']],
        ['label' => 'Num Lk', 'icon' => null, 'aliases' => ['numlock']],
        ['label' => 'Num /', 'icon' => null, 'aliases' => ['numdivide', 'numpaddivide']],
        ['label' => 'Num *', 'icon' => null, 'aliases' => ['nummultiply', 'numpadmultiply']],
        ['label' => 'Num -', 'icon' => null, 'aliases' => ['numminus', 'numpadminus']],
        ['label' => 'Num +', 'icon' => null, 'aliases' => ['numplus', 'numpadplus']],
        ['label' => 'Num Enter', 'icon' => null, 'aliases' => ['numenter', 'numpadenter']],
        ['label' => 'Num .', 'icon' => null, 'aliases' => ['numdecimal', 'numpaddecimal', 'numdot']],
        ['label' => '-', 'icon' => null, 'aliases' => ['minus', 'hyphen', 'dash', '-']],
        ['label' => '=', 'icon' => null, 'aliases' => ['equals', 'equal', '=']],
        ['label' => ',', 'icon' => null, 'aliases' => ['comma', ',']],
        ['label' => '.', 'icon' => null, 'aliases' => ['period', 'dot', '.']],
        ['label' => '/', 'icon' => null, 'aliases' => ['slash', 'forwardslash', '/']],
        ['label' => '\\', 'icon' => null, 'aliases' => ['backslash', '\\']],
        ['label' => ';', 'icon' => null, 'aliases' => ['semicolon', ';']],
        ['label' => "'", 'icon' => null, 'aliases' => ['quote', 'apostrophe', "'"]],
        ['label' => '`', 'icon' => null, 'aliases' => ['backtick', 'grave', '`']],
        ['label' => '[', 'icon' => null, 'aliases' => ['lbracket', 'leftbracket', 'openbracket']],
        ['label' => ']', 'icon' => null, 'aliases' => ['rbracket', 'rightbracket', 'closebracket']],
        ['label' => '@', 'icon' => null, 'aliases' => ['at', 'atsign', '@']],
        ['label' => '*', 'icon' => null, 'aliases' => ['asterisk', 'star', '*']],
        ['label' => '#', 'icon' => null, 'aliases' => ['hash', 'pound', 'hashtag', '#']],
        ['label' => '!', 'icon' => null, 'aliases' => ['exclamation', 'bang', '!']],
        ['label' => '?', 'icon' => null, 'aliases' => ['question', '?']],
        ['label' => '&', 'icon' => null, 'aliases' => ['ampersand', 'and', '&']],
        ['label' => '%', 'icon' => null, 'aliases' => ['percent', '%']],
        ['label' => '^', 'icon' => null, 'aliases' => ['caret', '^']],
        ['label' => '+', 'icon' => null, 'aliases' => ['plus', '+']],
        ['label' => '<', 'icon' => null, 'aliases' => ['lessthan', 'lt', '<']],
        ['label' => '>', 'icon' => null, 'aliases' => ['greaterthan', 'gt', '>']],
        ['label' => '~', 'icon' => null, 'aliases' => ['tilde', '~']],
        ['label' => '|', 'icon' => null, 'aliases' => ['pipe', 'bar', '|']],
        ['label' => '_', 'icon' => null, 'aliases' => ['underscore', '_']],
        ['label' => '"', 'icon' => null, 'aliases' => ['doublequote', 'dquote', '"']],
        ['label' => 'Vol +', 'icon' => null, 'aliases' => ['volumeup', 'volup']],
        ['label' => 'Vol -', 'icon' => null, 'aliases' => ['volumedown', 'voldown']],
        ['label' => 'Mute', 'icon' => null, 'aliases' => ['volumemute', 'mute']],
        ['label' => 'Play/Pause', 'icon' => null, 'aliases' => ['mediaplaypause', 'playpause']],
        ['label' => 'Next', 'icon' => null, 'aliases' => ['medianexttrack', 'nexttrack']],
        ['label' => 'Prev', 'icon' => null, 'aliases' => ['mediaprevtrack', 'previoustrack']],
    ];

    foreach (range(1, 12) as $functionNumber) {
        $fLabel = 'F' . $functionNumber;
        $definitions[] = ['label' => $fLabel, 'icon' => null, 'aliases' => [strtolower($fLabel)]];
    }

    foreach (range(0, 9) as $numPadDigit) {
        $numLabel = 'Num ' . $numPadDigit;
        $definitions[] = ['label' => $numLabel, 'icon' => null, 'aliases' => ['num' . $numPadDigit, 'numpad' . $numPadDigit]];
    }

    return $definitions;
}

function getShortcutKeyDefinition(string $keyToken): ?array
{
    $normalizeAlias = static function (string $value): string {
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    };

    static $aliasMap = null;
    static $rawMap = null;
    if ($aliasMap === null) {
        $aliasMap = [];
        $rawMap = [];

        $register = static function (array $definition) use (&$aliasMap, &$rawMap, $normalizeAlias): void {
            $aliases = is_array($definition['aliases'] ?? null) ? $definition['aliases'] : [];
            foreach ($aliases as $alias) {
                $raw = strtolower(trim((string) $alias));
                if ($raw !== '') {
                    $rawMap[$raw] = $definition;
                }

                $normalizedAlias = $normalizeAlias((string) $alias);
                if ($normalizedAlias === '') {
                    continue;
                }

                $aliasMap[$normalizedAlias] = $definition;
            }
        };

        foreach (getShortcutKeyDefinitions() as $definition) {
            $register($definition);
        }
    }

    $rawToken = strtolower(trim($keyToken));
    if (isset($rawMap[$rawToken])) {
        return $rawMap[$rawToken];
    }

    $normalizedToken = $normalizeAlias($keyToken);
    if ($normalizedToken === '') {
        return null;
    }

    if (isset($aliasMap[$normalizedToken])) {
        return $aliasMap[$normalizedToken];
    }

    if (preg_match('/^[a-z0-9]$/', $normalizedToken) === 1) {
        return ['label' => strtoupper($normalizedToken), 'icon' => null, 'aliases' => [$normalizedToken]];
    }

    if (preg_match('/^f([1-9]|1[0-2])$/', $normalizedToken) === 1) {
        return ['label' => strtoupper($normalizedToken), 'icon' => null, 'aliases' => [$normalizedToken]];
    }

    return null;
}

function renderShortcutKeyHtml(string $keyToken, bool $forEmail = false): ?string
{
    $definition = getShortcutKeyDefinition($keyToken);
    if ($definition === null) {
        return null;
    }

    $labelValue = trim((string) ($definition['label'] ?? ''));
    $label = $labelValue !== '' ? h($labelValue) : '';
    $iconType = (string) ($definition['icon'] ?? '');

    if ($label === '' && $iconType === '') {
        return null;
    }

    $iconHtml = '';
    if ($iconType === 'windows') {
        $iconAttributes = $forEmail
            ? 'style="width:12px;height:12px;display:block;flex:0 0 auto;"'
            : 'class="shortcut-key-icon"';
        $iconHtml = '<svg ' . $iconAttributes . ' viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
            . '<path d="M2 3.5L11 2v9H2v-7.5zm11 7.5V2l11-1.5V11H13zM2 13h9v9L2 20.5V13zm11 0h11v10.5L13 22v-9z"/>'
            . '</svg>';
    } elseif ($iconType === 'arrow-up' || $iconType === 'arrow-down' || $iconType === 'arrow-left' || $iconType === 'arrow-right') {
        $iconAttributes = $forEmail
            ? 'style="width:12px;height:12px;display:block;flex:0 0 auto;"'
            : 'class="shortcut-key-icon"';
        $arrowPath = 'M12 4l6 6h-4v10h-4V10H6l6-6z';
        if ($iconType === 'arrow-down') {
            $arrowPath = 'M12 20l-6-6h4V4h4v10h4l-6 6z';
        } elseif ($iconType === 'arrow-left') {
            $arrowPath = 'M4 12l6-6v4h10v4H10v4l-6-6z';
        } elseif ($iconType === 'arrow-right') {
            $arrowPath = 'M20 12l-6 6v-4H4v-4h10V6l6 6z';
        }

        $iconHtml = '<svg ' . $iconAttributes . ' viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
            . '<path d="' . $arrowPath . '"/>'
            . '</svg>';
    }

    $labelHtml = $label !== '' ? '<span class="shortcut-key-label">' . $label . '</span>' : '';

    if ($forEmail) {
        return '<span class="shortcut-key" style="display:inline-flex;align-items:center;justify-content:center;gap:4px;box-sizing:border-box;height:22px;padding:0 7px;border:1px solid #b7c1d0;border-bottom-width:2px;border-radius:6px;background:#ffffff;color:#132238;font-size:12px;font-weight:600;line-height:1;vertical-align:middle;white-space:nowrap;">'
            . $iconHtml
            . $labelHtml
            . '</span>';
    }

    return '<span class="shortcut-key">'
        . $iconHtml
        . $labelHtml
        . '</span>';
}

function renderShortcutMarkup(string $escapedText, bool $forEmail = false): string
{
    $rendered = preg_replace_callback(
        '/\[([^\[\]\r\n]{1,24})\]/',
        static function (array $matches) use ($forEmail): string {
            $keyToken = html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES, 'UTF-8');
            $replacement = renderShortcutKeyHtml($keyToken, $forEmail);
            return $replacement ?? (string) ($matches[0] ?? '');
        },
        $escapedText
    ) ?? $escapedText;

    $plusSeparator = $forEmail
        ? '<span class="shortcut-plus" style="display:inline-block;padding:0 4px;color:#5b6b82;font-weight:700;">+</span>'
        : '<span class="shortcut-plus">+</span>';

    return preg_replace(
        '/(<span class="shortcut-key"(?:\s[^>]*)?>.*?<\/span>)\s*\+\s*(?=<span class="shortcut-key"(?:\s[^>]*)?>)/s',
        '$1' . $plusSeparator,
        $rendered
    ) ?? $rendered;
}

function makeTextInteractive(string $text, bool $forEmail = false): string
{
    $escapedText = h($text);
    $escapedText = renderShortcutMarkup($escapedText, $forEmail);

    $escapedText = preg_replace_callback(
        '~(?:(https?://|www\.)[^\s<]+)~i',
        static function (array $matches) use ($forEmail): string {
            $displayValue = $matches[0];
            $href = str_starts_with(strtolower($displayValue), 'www.') ? 'https://' . $displayValue : $displayValue;
            $safeHref = h($href);
            $safeLabel = h($displayValue);

            if ($forEmail) {
                return '<a href="' . $safeHref . '">' . $safeLabel . '</a>';
            }

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

function formatTicketMessageText(?string $messageText, int $messageId = 0, array $attachments = []): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $messageText));
    if ($normalized === '') {
        return '';
    }

    $formattedLines = [];
    foreach (explode("\n", $normalized) as $lineIndex => $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            $formattedLines[] = '';
            continue;
        }

        if (preg_match('/^\[\[attachment:(.+)\]\]$/', $trimmedLine, $attachmentMatch) === 1) {
            $attachmentName = trim((string) ($attachmentMatch[1] ?? ''));
            $attachment = $attachmentName !== '' ? findAttachmentByOriginalName($attachments, $attachmentName) : null;
            if ($attachment !== null) {
                $formattedLines[] = renderMessageInlineAttachmentHtml($attachment);
            } else {
                $formattedLines[] = '<em>' . h($attachmentName !== '' ? $attachmentName : $trimmedLine) . '</em>';
            }
            continue;
        }

        if (preg_match('/^(\s*)\[( |x|X)\]\s*(.*)$/', $line, $checkboxMatch) === 1) {
            $isChecked = strtolower((string) $checkboxMatch[2]) === 'x';
            $checkboxText = (string) ($checkboxMatch[3] ?? '');
            $checkboxLabel = $checkboxText !== '' ? makeTextInteractive($checkboxText) : '&nbsp;';
            $formattedLines[] = '<label class="message-checkbox-line">'
                . '<input type="checkbox" data-role="message-checkbox" data-message-id="' . (int) $messageId . '" data-line-index="' . (int) $lineIndex . '"'
                . ($isChecked ? ' checked' : '')
                . ($messageId > 0 ? '' : ' disabled')
                . '>'
                . '<span>' . $checkboxLabel . '</span>'
                . '</label>';
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

function formatTicketMessageTextForEmail(?string $messageText): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $messageText));
    if ($normalized === '') {
        return '';
    }

    $formattedLines = [];
    foreach (explode("\n", $normalized) as $line) {
        if (trim($line) === '') {
            $formattedLines[] = '';
            continue;
        }

        $trimmedLine = trim($line);
        if (preg_match('/^\[\[attachment:(.+)\]\]$/', $trimmedLine, $attachmentMatch) === 1) {
            $attachmentName = trim((string) ($attachmentMatch[1] ?? ''));
            $formattedLines[] = '<p style="margin:8px 0;"><em>📎 '
                . htmlspecialchars($attachmentName !== '' ? $attachmentName : $trimmedLine, ENT_QUOTES, 'UTF-8')
                . '</em></p>';
            continue;
        }

        $formattedLines[] = makeTextInteractive($line, true);
    }

    return implode('<br>', $formattedLines);
}

function buildAbsoluteTicketUrl(int $ticketId, bool $adminPage = false): string
{
    return buildTicketShareUrl($ticketId);
}

function buildTicketShareUrl(int $ticketId, string $shareContext = 'universal'): string
{
    if ($ticketId <= 0) {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/index.php')), '/.');
    $baseUrl = $scheme . '://' . $host . ($basePath !== '' ? $basePath : '') . '/';

    return $baseUrl . 'index.php?' . http_build_query([
        'open' => $ticketId,
    ]);
}

function isOpenTicketNavigationRequest(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return false;
    }

    foreach (['_partial', '_tickets_poll', '_bigscreen_poll', '_browser_notifications_poll', '_webpush_subscription', 'download'] as $skipKey) {
        if (isset($_GET[$skipKey])) {
            return false;
        }
    }

    return true;
}

function resolveTicketForOpenLinkRouting(TicketStore $store, int $ticketId, bool $userIsAdmin, string $userEmail): ?array
{
    if ($userIsAdmin) {
        $ticket = $store->getTicket($ticketId, true, $userEmail);
        if ($ticket !== null) {
            return $ticket;
        }
    }

    $ticket = $store->getTicket($ticketId, false, $userEmail);
    if ($ticket !== null) {
        return $ticket;
    }

    return $store->getTicket($ticketId, false, $userEmail, 'all_completed_public');
}

function isTicketRequester(array $ticket, string $userEmail): bool
{
    return strtolower(trim((string) ($ticket['user_email'] ?? ''))) === strtolower(trim($userEmail));
}

function maybeRedirectForOpenTicketLink(
    TicketStore $store,
    bool $userIsAdmin,
    bool $isAdminPortal,
    int $openTicketId,
    string $userEmail,
    string $requestedView
): void {
    if ($openTicketId <= 0 || !isOpenTicketNavigationRequest()) {
        return;
    }

    $ticketForRouting = resolveTicketForOpenLinkRouting($store, $openTicketId, $userIsAdmin, $userEmail);
    if ($ticketForRouting === null) {
        return;
    }

    if (isTicketRequester($ticketForRouting, $userEmail)) {
        if ($isAdminPortal || $requestedView === 'all_tickets') {
            redirectToPage('index.php', ['open' => $openTicketId]);
        }

        return;
    }

    if ($userIsAdmin) {
        if (!$isAdminPortal) {
            redirectToPage('admin.php', ['open' => $openTicketId]);
        }

        return;
    }

    if ($isAdminPortal) {
        redirectToPage('index.php', ['open' => $openTicketId]);
    }

    $publicTicket = $store->getTicket($openTicketId, false, $userEmail, 'all_completed_public');
    if ($publicTicket !== null) {
        if ($requestedView !== 'all_tickets') {
            redirectToPage('index.php', [
                'view' => 'all_tickets',
                'open' => $openTicketId,
            ]);
        }

        return;
    }

    $participantTicket = $store->getTicket($openTicketId, false, $userEmail);
    if ($participantTicket !== null) {
        if ($requestedView === 'all_tickets') {
            redirectToPage('index.php', ['open' => $openTicketId]);
        }

        return;
    }
}

function validateOpenTicketLinkAccess(
    TicketStore $store,
    int $openTicketId,
    bool $userIsAdmin,
    bool $isAdminPortal,
    bool $isAllTicketsView,
    string $userEmail,
    string $ticketBrowseMode
): bool {
    if ($openTicketId <= 0) {
        return true;
    }

    if ($userIsAdmin && $isAdminPortal) {
        return $store->getTicket($openTicketId, true, $userEmail) !== null;
    }

    if ($isAllTicketsView) {
        return $store->getTicket($openTicketId, false, $userEmail, 'all_completed_public') !== null;
    }

    if (!$isAdminPortal) {
        return $store->getTicket($openTicketId, false, $userEmail) !== null;
    }

    return true;
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
        $messageTextHtml = formatTicketMessageTextForEmail($messageText);
        $msgHtml = '<div style="margin-top:16px;background:#f4f7fb;border-left:4px solid #0b65c2;padding:12px 16px;border-radius:0 8px 8px 0;font-size:14px;color:#10233f;white-space:pre-wrap;">'
            . $messageTextHtml
            . '</div>';
    }

    $html = '<!DOCTYPE html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#10233f;">'
        . '<div style="max-width:600px;margin:32px auto;padding:0 16px;">'
        . '<div style="background:linear-gradient(135deg,#0e2c52,#0b65c2);border-radius:16px 16px 0 0;padding:24px 28px;">'
        . '<p style="margin:0 0 2px;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.7);">Asclepius · ICT Tickets</p>'
        . '<h1 style="margin:0;font-size:20px;">Ticket #' . (int) $ticket['id'] . '</h1>'
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

