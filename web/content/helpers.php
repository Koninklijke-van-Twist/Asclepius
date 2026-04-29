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

function buildTicketSnapshotSignature(array $tickets): string
{
    $snapshot = array_map(static function (array $ticket): array {
        return [
            'id' => (int) ($ticket['id'] ?? 0),
            'updated_at' => (string) ($ticket['updated_at'] ?? ''),
            'status' => (string) ($ticket['status'] ?? ''),
            'assigned_email' => strtolower((string) ($ticket['assigned_email'] ?? '')),
            'message_count' => (int) ($ticket['message_count'] ?? 0),
        ];
    }, $tickets);

    return sha1(json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: '[]');
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
    
    $previewableExtensions = [
        // Text & markup
        'txt', 'md', 'markdown', 'mdown', 'mkd', 'rst',
        // Code
        'js', 'json', 'php', 'py', 'rb', 'go', 'rs', 'c', 'cpp', 'h', 'cs', 'java', 'sql', 'html', 'css', 'xml', 'yaml', 'yml', 'toml', 'ini', 'cfg', 'conf', 'sh', 'bash',
        // Data
        'csv', 'tsv',
        // Documents
        'pdf', 'xlsx', 'xls', 'docx', 'doc', 'odt', 'rtf',
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
    
    // Text & markup
    if (in_array($extension, ['txt', 'log', 'text'], true)) return 'text';
    if (in_array($extension, ['md', 'markdown', 'mdown', 'mkd', 'rst'], true)) return 'markdown';
    
    // Code
    if (in_array($extension, ['js', 'jsx', 'mjs', 'ts', 'tsx'], true)) return 'javascript';
    if (in_array($extension, ['json', 'jsonld', 'ndjson'], true)) return 'json';
    if (in_array($extension, ['py', 'pyw', 'pyi'], true)) return 'python';
    if (in_array($extension, ['rb', 'erb', 'gemspec'], true)) return 'ruby';
    if (in_array($extension, ['go'], true)) return 'go';
    if (in_array($extension, ['rs'], true)) return 'rust';
    if (in_array($extension, ['c', 'h'], true)) return 'c';
    if (in_array($extension, ['cpp', 'cc', 'cxx', 'hpp', 'h++'], true)) return 'cpp';
    if (in_array($extension, ['cs', 'csx'], true)) return 'csharp';
    if (in_array($extension, ['java'], true)) return 'java';
    if (in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8'], true)) return 'php';
    if (in_array($extension, ['sql'], true)) return 'sql';
    if (in_array($extension, ['html', 'htm'], true)) return 'html';
    if (in_array($extension, ['css', 'scss', 'sass', 'less'], true)) return 'css';
    if (in_array($extension, ['xml', 'svg'], true)) return 'xml';
    if (in_array($extension, ['yaml', 'yml'], true)) return 'yaml';
    if (in_array($extension, ['toml'], true)) return 'toml';
    if (in_array($extension, ['ini', 'cfg', 'conf', 'config'], true)) return 'ini';
    if (in_array($extension, ['sh', 'bash', 'zsh'], true)) return 'bash';
    
    // Data
    if (in_array($extension, ['csv'], true)) return 'csv';
    if (in_array($extension, ['tsv'], true)) return 'tsv';
    
    // Documents
    if (in_array($extension, ['pdf'], true)) return 'pdf';
    if (in_array($extension, ['xlsx', 'xls'], true)) return 'excel';
    if (in_array($extension, ['docx', 'doc'], true)) return 'word';
    
    return null;
}

/**
 * Gets the file size in human-readable format
 */
function formatFileSize(int $bytes): string
{
    $sizes = ['B', 'KB', 'MB', 'GB'];
    if ($bytes <= 0) return '0 B';
    
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

function renderTicketMessageHtml(array $message, string $currentPage): string
{
    ob_start();
    ?>
    <article class="message <?= ($message['sender_role'] ?? '') === 'admin' ? 'admin' : 'user' ?>"
        data-message-id="<?= (int) ($message['id'] ?? 0) ?>">
        <div class="message-meta">
            <strong><?= h((string) ($message['sender_email'] ?? '')) ?></strong>
            <span
                class="message-role"><?= ($message['sender_role'] ?? '') === 'admin' ? h(__('ticket.role_admin')) : h(__('ticket.role_user')) ?></span>
            <span><?= h(formatDateTime((string) ($message['created_at'] ?? ''))) ?></span>
        </div>

        <?php if (trim((string) ($message['message_text'] ?? '')) !== ''): ?>
            <div class="message-text">
                <?= formatTicketMessageText((string) ($message['message_text'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message['attachments'])): ?>
            <ul class="attachment-list">
                <?php foreach ($message['attachments'] as $attachmentIndex => $attachment): ?>
                    <?php
                    $downloadUrl = buildAttachmentDirectUrl($attachment);
                    $isImageAttachment = isImageAttachment($attachment);
                    $thumbLoading = $attachmentIndex < 3 ? 'eager' : 'lazy';
                    $thumbFetchPriority = $attachmentIndex < 3 ? 'high' : 'auto';
                    ?>
                    <li class="attachment-item">
                        <?php if ($isImageAttachment && $downloadUrl !== ''): ?>
                            <button type="button" class="attachment-thumb-button" data-image-preview-trigger
                                data-preview-src="<?= h($downloadUrl) ?>"
                                data-preview-alt="<?= h((string) ($attachment['original_name'] ?? '')) ?>"
                                aria-label="<?= h(__('ticket.preview_image')) ?>">
                                <img class="attachment-thumb" src="<?= h($downloadUrl) ?>"
                                    alt="<?= h((string) ($attachment['original_name'] ?? '')) ?>" loading="<?= h($thumbLoading) ?>"
                                    fetchpriority="<?= h($thumbFetchPriority) ?>" decoding="async">
                            </button>
                        <?php endif; ?>
                        <?php if (canPreviewFile($attachment) && !$isImageAttachment): ?>
                            <button type="button" class="attachment-preview-button" data-file-preview-trigger
                                data-preview-id="<?= (int) ($attachment['id'] ?? 0) ?>"
                                aria-label="<?= h(__('ticket.preview_file')) ?>">
                                <?= h(__('ticket.preview_file')) ?>
                            </button>
                        <?php endif; ?>
                        <a href="<?= h($downloadUrl !== '' ? $downloadUrl : '#') ?>" class="attachment-download-link" <?= $downloadUrl !== '' ? '' : 'aria-disabled="true"' ?>>
                            <?= h((string) ($attachment['original_name'] ?? '')) ?>
                        </a>
                        <span
                            class="attachment-size">(<?= formatFileSize(max(0, (int) ($attachment['file_size'] ?? 0))) ?>)</span>
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
    $ticketColor = getStatusColor((string) ($ticket['status'] ?? ''));
    $shouldOpen = $openTicketId > 0 && (int) ($ticket['id'] ?? 0) === $openTicketId;
    $ticketOpenDuration = getTicketOpenDurationSeconds($ticket);
    $replyFormId = 'reply-form-' . (int) ($ticket['id'] ?? 0);
    $assignedEmail = (string) ($ticket['assigned_email'] ?? '');
    $assignedLabel = $assignedEmail !== '' ? $assignedEmail : __('ticket.unassigned');
    $requesterEmail = strtolower(trim((string) ($ticket['user_email'] ?? '')));
    $assignableIctUsers = array_values(array_filter(
        extractIctUserEmails($ictUsers),
        static fn(string $ictUser): bool => $ictUser !== '' && $ictUser !== $requesterEmail
    ));

    ob_start();
    ?>
    <details class="ticket-card" data-ticket-id="<?= (int) ($ticket['id'] ?? 0) ?>"
        style="--ticket-color: <?= h($ticketColor) ?>;" <?= $shouldOpen ? 'open' : '' ?>>
        <summary>
            <div class="ticket-summary">
                <div>
                    <p class="ticket-main-title"><strong><span
                                data-role="ticket-number">#<?= (int) ($ticket['id'] ?? 0) ?></span> · <span
                                data-role="ticket-title"><?= h((string) ($ticket['title'] ?? '')) ?></span></strong></p>
                    <div class="ticket-subtitle">
                        <span data-role="requester-email"><?= h((string) ($ticket['user_email'] ?? '')) ?></span>
                        <span
                            data-role="ticket-category"><?= h(translateCategory((string) ($ticket['category'] ?? ''))) ?></span>
                        <span
                            data-role="ticket-created"><?= h(formatDateTime((string) ($ticket['created_at'] ?? ''))) ?></span>
                    </div>
                </div>
                <div class="ticket-subtitle">
                    <?php if ($isAdminPortal): ?>
                        <span class="status-pill" data-role="status-pill"
                            style="--ticket-color: <?= h($ticketColor) ?>;"><?= h(translateStatus((string) ($ticket['status'] ?? ''))) ?></span>
                    <?php endif; ?>
                    <?php if ($userIsAdmin && $isAdminPortal): ?>
                        <span class="status-pill" data-role="priority-pill"
                            style="--ticket-color: <?= h(getPriorityColor((int) ($ticket['priority'] ?? 0))) ?>;"><?= h(__('ticket.meta_priority')) ?>
                            <?= (int) ($ticket['priority'] ?? 0) ?> ·
                            <?= h(formatPriorityLabel((int) ($ticket['priority'] ?? 0))) ?></span>
                    <?php endif; ?>
                    <span class="assignee-badge" data-role="assignee-badge"
                        style="--assignee-color: <?= h(emailToHexColor((string) ($assignedEmail !== '' ? $assignedEmail : 'onbekend@kvt.nl'))) ?>;">
                        <?= h($assignedLabel) ?>
                    </span>
                    <span class="count-badge" data-role="message-count-badge"><?= (int) ($ticket['message_count'] ?? 0) ?>
                        <?= h(__('ticket.messages_count')) ?></span>
                    <?php if ($isAdminPortal): ?>
                        <span class="count-badge" data-role="time-open-badge"><?= h(__('ticket.time_open')) ?>
                            <?= h(formatDurationSeconds($ticketOpenDuration)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </summary>

        <div class="ticket-body">
            <div class="meta-grid">
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
                <?php if ($userIsAdmin && $isAdminPortal): ?>
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
                <?php endif; ?>
            </div>

            <div data-role="messages-wrap" <?= $ticketDetail !== null && !empty($ticketDetail['messages']) ? '' : 'hidden' ?>>
                <h3><?= h(__('ticket.messages_heading')) ?></h3>
                <div class="thread" data-role="thread">
                    <?php foreach (($ticketDetail['messages'] ?? []) as $message): ?>
                        <?= renderTicketMessageHtml($message, $currentPage) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="post"
                action="<?= h($currentPage) ?><?= $isAdminPortal && $view === 'settings' ? '?view=settings' : '' ?>"
                enctype="multipart/form-data" class="reply-form" id="<?= h($replyFormId) ?>">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="form_action" value="reply_ticket">
                <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                <input type="hidden" name="ticket_id" value="<?= (int) ($ticket['id'] ?? 0) ?>">

                <?php if ($canManageTickets): ?>
                    <div class="admin-grid">
                        <label>
                            <?= h(__('ticket.status_label')) ?>
                            <select name="status" data-role="status-select">
                                <?php foreach (TICKET_STATUSES as $status): ?>
                                    <option value="<?= h($status) ?>" <?= (string) ($ticket['status'] ?? '') === $status ? 'selected' : '' ?>><?= h(translateStatus($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <?= h(__('ticket.assigned_to')) ?>
                            <select name="assigned_email" data-role="assigned-select">
                                <option value=""><?= h(__('ticket.unassigned')) ?></option>
                                <?php foreach ($assignableIctUsers as $ictUser):
                                    $ictUser = strtolower($ictUser); ?>
                                    <option value="<?= h($ictUser) ?>" <?= strtolower((string) ($ticket['assigned_email'] ?? '')) === $ictUser ? 'selected' : '' ?>>
                                        <?= h($ictUser) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                <?php endif; ?>

                <label>
                    <?= h(__('ticket.new_message')) ?>
                    <textarea name="message" placeholder="<?= h(__('ticket.new_message_placeholder')) ?>"></textarea>
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
                    <input type="file" name="reply_attachments[]" multiple>
                    <span class="hint"><?= h(__('ticket.file_hint')) ?></span>
                </label>

                <div class="button-row">
                    <button
                        type="submit"><?= $canManageTickets ? h(__('ticket.btn_save')) : h(__('ticket.btn_reply')) ?></button>
                </div>
            </form>
        </div>
    </details>
    <?php

    return trim((string) ob_get_clean());
}

function buildTicketPollEntry(array $ticket, ?array $ticketDetail, array $context = []): array
{
    $ticketOpenDuration = getTicketOpenDurationSeconds($ticket);
    $assignedEmail = (string) ($ticket['assigned_email'] ?? '');

    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'title' => (string) ($ticket['title'] ?? ''),
        'user_email' => (string) ($ticket['user_email'] ?? ''),
        'category' => (string) ($ticket['category'] ?? ''),
        'category_label' => translateCategory((string) ($ticket['category'] ?? '')),
        'created_at_label' => formatDateTime((string) ($ticket['created_at'] ?? '')),
        'updated_at_label' => formatDateTime((string) ($ticket['updated_at'] ?? '')),
        'status' => (string) ($ticket['status'] ?? ''),
        'status_label' => translateStatus((string) ($ticket['status'] ?? '')),
        'status_color' => getStatusColor((string) ($ticket['status'] ?? '')),
        'priority' => (int) ($ticket['priority'] ?? 0),
        'priority_label' => formatPriorityLabel((int) ($ticket['priority'] ?? 0)),
        'priority_color' => getPriorityColor((int) ($ticket['priority'] ?? 0)),
        'assigned_email' => $assignedEmail,
        'assigned_label' => $assignedEmail !== '' ? $assignedEmail : __('ticket.unassigned'),
        'assigned_color' => emailToHexColor((string) ($assignedEmail !== '' ? $assignedEmail : 'onbekend@kvt.nl')),
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

function normalizeSavedTicketOverviewFilters(array $prefs): array
{
    $savedFilters = $prefs['ticket_overview_filters'] ?? null;
    if (!is_array($savedFilters)) {
        return [
            'status_filter_active' => false,
            'status_filters' => [],
            'category_filter_active' => false,
            'category_filters' => [],
            'assigned_filter' => '',
        ];
    }

    $statusFilters = array_values(array_filter(
        array_map('trim', (array) ($savedFilters['status_filters'] ?? [])),
        static fn(string $status): bool => in_array($status, TICKET_STATUSES, true)
    ));
    $categoryFilters = array_values(array_filter(
        array_map('trim', (array) ($savedFilters['category_filters'] ?? [])),
        static fn(string $category): bool => in_array($category, TICKET_CATEGORIES, true)
    ));
    $assignedFilter = trim((string) ($savedFilters['assigned_filter'] ?? ''));
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
        'licentie aanvragen' => 'category.licentie_aanvragen',
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
    $normalizedEmail = strtolower(trim($email));
    if ($normalizedEmail === '') {
        return '#64748b';
    }

    $configuredColor = getConfiguredIctUserColor($normalizedEmail);
    if ($configuredColor !== null) {
        return $configuredColor;
    }

    $hash = hash('sha256', $normalizedEmail);
    $hueSeed = hexdec(substr($hash, 0, 2));
    $saturationSeed = hexdec(substr($hash, 2, 2));
    $lightnessSeed = hexdec(substr($hash, 4, 2));

    $hue = (int) (($hueSeed * 137) % 360);
    $hue = (int) ((round($hue / 15) * 15) % 360);
    $saturation = 68 + ($saturationSeed % 18);
    $lightness = 42 + ($lightnessSeed % 12);

    return hslToHex($hue, $saturation, $lightness);
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

