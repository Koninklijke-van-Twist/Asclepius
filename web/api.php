<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$vendorAutoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TicketStore.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'constants.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'localization.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'TranslationProvider.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'LaraTranslationProvider.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'translation.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'mail.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'changelog.php';

if (!isset($ictUserColors) || !is_array($ictUserColors)) {
    $ictUserColors = [];
}
normalizeIctUsersConfig($ictUsers, $ictUserColors);

function ensureApiSessionStarted(): void
{
    static $done = false;
    if ($done || session_status() === PHP_SESSION_ACTIVE) {
        $done = true;

        return;
    }

    $appSessionConfigPaths = [
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'session_config.php',
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'session_config.php',
    ];
    foreach ($appSessionConfigPaths as $appSessionConfigPath) {
        if (!is_file($appSessionConfigPath)) {
            continue;
        }

        require_once $appSessionConfigPath;
        configure_app_session();
        break;
    }

    $sessionCookieName = session_name();
    if ($sessionCookieName !== '' && !empty($_COOKIE[$sessionCookieName])) {
        session_start(['read_and_close' => true]);
    }

    $done = true;
}

/**
 * Functions
 */
function sendJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getApiKeyFromRequest(): string
{
    $headerKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($headerKey !== '') {
        return $headerKey;
    }

    $requestKey = trim((string) ($_REQUEST['api_key'] ?? ''));
    if ($requestKey !== '') {
        return $requestKey;
    }

    return '';
}

function isValidApiKey(string $providedKey, array $apiKeys): bool
{
    if ($providedKey === '') {
        return false;
    }

    foreach ($apiKeys as $apiKey) {
        if (!is_string($apiKey)) {
            continue;
        }

        if (hash_equals($apiKey, $providedKey)) {
            return true;
        }
    }

    return false;
}

function isTrustedApiRequester(): bool
{
    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $serverAddress = trim((string) ($_SERVER['SERVER_ADDR'] ?? ''));

    if ($remoteAddress !== '' && $remoteAddress === $serverAddress) {
        return true;
    }

    return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
}

function loadApiClientByToken(string $providedKey): ?array
{
    $apiKey = strtolower(trim($providedKey));
    if ($apiKey === '' || preg_match('/^[a-f0-9]{64}$/', $apiKey) !== 1) {
        return null;
    }

    $apiClientFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_clients' . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json';
    if (!is_file($apiClientFile)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($apiClientFile), true);
    if (!is_array($decoded)) {
        return null;
    }

    $storedApiKey = strtolower(trim((string) ($decoded['api_key'] ?? '')));
    if ($storedApiKey === '' || !hash_equals($storedApiKey, $apiKey)) {
        return null;
    }

    return [
        'oid' => strtolower(trim((string) ($decoded['oid'] ?? ''))),
        'api_key' => $storedApiKey,
        'email' => strtolower(trim((string) ($decoded['email'] ?? ''))),
        'is_admin' => !empty($decoded['is_admin']),
    ];
}

function buildRotatingApiKeyForDate(string $oid, string $dateKey): string
{
    $normalizedOid = strtolower(trim($oid));
    $normalizedDateKey = trim($dateKey);
    if ($normalizedOid === '' || $normalizedDateKey === '') {
        return '';
    }

    return hash('sha256', $normalizedOid . '|' . $normalizedDateKey);
}

function getRefreshRequiredUnauthorizedReason(string $providedKey): ?string
{
    $normalizedKey = strtolower(trim($providedKey));
    if ($normalizedKey === '' || preg_match('/^[a-f0-9]{64}$/', $normalizedKey) !== 1) {
        return null;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $sessionCookieName = session_name();
        if ($sessionCookieName === '' || empty($_COOKIE[$sessionCookieName])) {
            return null;
        }

        session_start(['read_and_close' => true]);
    }

    $oid = strtolower(trim((string) ($_SESSION['user']['oid'] ?? '')));
    if ($oid === '' || preg_match('/^[a-z0-9-]{8,128}$/', $oid) !== 1) {
        return null;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    $currentDate = $now->format('d-m-Y');
    $previousDate = $now->modify('-1 day')->format('d-m-Y');
    $currentDateKey = buildRotatingApiKeyForDate($oid, $currentDate);
    $previousDateKey = buildRotatingApiKeyForDate($oid, $previousDate);

    if ($currentDateKey !== '' && !hash_equals($currentDateKey, $normalizedKey) && $previousDateKey !== '' && hash_equals($previousDateKey, $normalizedKey)) {
        return 'session_expired_refresh_required';
    }

    return null;
}

function getRequestBody(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    $payload = [];

    if (str_contains($contentType, 'application/json')) {
        $rawBody = file_get_contents('php://input');
        if (is_string($rawBody) && trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
    }

    if ($payload === [] && $_POST !== []) {
        $payload = $_POST;
    }

    return merge_api_request_payload($payload);
}

function merge_api_request_payload(array $payload): array
{
    $queryParams = [];
    $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    if ($queryString !== '') {
        parse_str($queryString, $queryParams);
    }

    foreach (['action', 'page_name', 'viewer_email', 'user_email'] as $key) {
        $currentValue = trim((string) ($payload[$key] ?? ''));
        $queryValue = trim((string) ($queryParams[$key] ?? ''));
        if ($currentValue === '' && $queryValue !== '') {
            $payload[$key] = $queryValue;
        }
    }

    return $payload;
}

function buildTicketPollApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'index.php'));
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    $isAdminPortal = !empty($payload['is_admin_portal']);
    $canManageTickets = $isAdminPortal && $userIsAdmin;
    $csrfToken = (string) ($payload['csrf_token'] ?? '');
    $openTicketId = max(0, (int) ($payload['open_ticket_id'] ?? 0));
    $view = trim((string) ($payload['view'] ?? 'overview'));
    $currentLanguage = strtolower(trim((string) ($payload['current_language'] ?? 'nl')));
    if (!array_key_exists($currentLanguage, SUPPORTED_LANGUAGES)) {
        $currentLanguage = 'nl';
    }
    $assignedFilter = trim((string) ($payload['assigned_filter'] ?? ''));
    $searchQuery = trim((string) ($payload['search_query'] ?? ''));
    $statusFilters = array_values(array_filter(
        array_map('trim', (array) ($payload['status_filters'] ?? [])),
        static fn(string $status): bool => $status !== ''
    ));
    $categoryFilters = array_values(array_filter(
        array_map('trim', (array) ($payload['category_filters'] ?? [])),
        static fn(string $category): bool => $category !== ''
    ));
    $lastSignature = trim((string) ($payload['last_signature'] ?? ''));

    $tickets = $store->getTickets($canManageTickets, $viewerEmail, $statusFilters, $assignedFilter, $categoryFilters, $searchQuery);
    $signature = buildTicketSnapshotSignature($tickets);
    if ($lastSignature !== '' && hash_equals($lastSignature, $signature)) {
        return [
            'success' => true,
            'signature' => $signature,
            'unchanged' => true,
        ];
    }

    $tickets = array_map(
        fn(array $ticket): array => localizeTicketForViewer($ticket, $store, $currentLanguage, true),
        $tickets
    );
    $pollContext = [
        'currentPage' => $currentPage,
        'canManageTickets' => $canManageTickets,
        'userIsAdmin' => $userIsAdmin,
        'isAdminPortal' => $isAdminPortal,
        'ictUsers' => $GLOBALS['ictUsers'] ?? [],
        'csrfToken' => $csrfToken,
        'openTicketId' => $openTicketId,
        'view' => $view,
    ];

    return [
        'success' => true,
        'signature' => $signature,
        'tickets' => buildTicketPollItemsFromTickets($store, $tickets, $pollContext, $currentLanguage),
        'is_empty' => $tickets === [],
        'empty_html' => '<div class="empty-state">' . ($isAdminPortal ? h(__('tickets.empty_admin')) : h(__('tickets.empty_user'))) . '</div>',
    ];
}

function buildBrowserNotificationsApiPayload(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    $targetPage = $userIsAdmin ? 'admin.php' : 'index.php';
    $notificationItems = $store->pullBrowserNotifications($viewerEmail, 25);

    return [
        'success' => true,
        'notifications' => array_map(
            static fn(array $notification): array => [
                'id' => (int) ($notification['id'] ?? 0),
                'ticket_id' => (int) ($notification['ticket_id'] ?? 0),
                'title' => (string) ($notification['title'] ?? ''),
                'body' => (string) ($notification['body'] ?? ''),
                'open_url' => $targetPage . '?open=' . (int) ($notification['ticket_id'] ?? 0),
                'created_at' => (string) ($notification['created_at'] ?? ''),
            ],
            $notificationItems
        ),
    ];
}

function handleWebPushSubscriptionApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    if ($viewerEmail === '') {
        return [
            'success' => false,
            'error' => 'viewer_missing',
        ];
    }

    $action = trim((string) ($payload['subscription_action'] ?? 'subscribe'));
    $subscription = is_array($payload['subscription'] ?? null) ? $payload['subscription'] : [];
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));

    if ($action === 'unsubscribe') {
        if ($endpoint !== '') {
            $store->removeWebPushSubscription($viewerEmail, $endpoint);
        }

        return ['success' => true];
    }

    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
    $p256dhKey = trim((string) ($keys['p256dh'] ?? ''));
    $authKey = trim((string) ($keys['auth'] ?? ''));

    $store->saveWebPushSubscription(
        $viewerEmail,
        $endpoint,
        $p256dhKey,
        $authKey,
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
    );

    return ['success' => true];
}

function handleManageTicketParticipantsApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $ticket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $participantInput = trim((string) ($payload['participant_emails'] ?? ''));
    $removeParticipantEmailsRaw = $payload['remove_participant_emails'] ?? [];

    if ($operation === 'add') {
        $removeParticipantEmailsRaw = [];
    } elseif ($operation === 'remove') {
        $singleEmail = strtolower(trim((string) ($payload['participant_email'] ?? '')));
        $removeParticipantEmailsRaw = $singleEmail !== '' ? [$singleEmail] : [];
        $participantInput = '';
    } elseif ($operation !== 'apply') {
        return [
            'success' => false,
            'error' => __('flash.unknown_action'),
        ];
    }

    $invalidTokens = findInvalidEmailListTokens($participantInput);
    if ($invalidTokens !== []) {
        return [
            'success' => false,
            'error' => __('flash.invalid_email_list', implode(', ', $invalidTokens)),
        ];
    }

    $participantEmailsToAdd = parseEmailListInput($participantInput);

    $removeParticipantEmails = [];
    if (is_string($removeParticipantEmailsRaw)) {
        $removeParticipantEmails[] = strtolower(trim($removeParticipantEmailsRaw));
    } elseif (is_array($removeParticipantEmailsRaw)) {
        foreach ($removeParticipantEmailsRaw as $emailRaw) {
            $removeParticipantEmails[] = strtolower(trim((string) $emailRaw));
        }
    }
    $removeParticipantEmails = array_values(array_unique(array_filter(
        $removeParticipantEmails,
        static fn(string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
    )));

    if ($participantEmailsToAdd === [] && $removeParticipantEmails === []) {
        return [
            'success' => false,
            'error' => __('flash.ticket_participant_add_none'),
        ];
    }

    $participantsBefore = is_array($ticket['participant_emails'] ?? null) ? $ticket['participant_emails'] : [];
    $actorEmail = $viewerEmail;
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = strtolower(trim((string) ($ticket['assigned_email'] ?? $ticket['user_email'] ?? '')));
    }
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = 'ict@kvt.nl';
    }
    $participantsBeforeLookup = array_fill_keys(array_map('strtolower', $participantsBefore), true);

    $addedCount = 0;
    if ($participantEmailsToAdd !== []) {
        $addedCount = $store->addTicketParticipants($ticketId, $participantEmailsToAdd, $viewerEmail);
    }

    $removedCount = 0;
    foreach ($removeParticipantEmails as $removeEmail) {
        if ($store->removeTicketParticipant($ticketId, $removeEmail)) {
            $removedCount++;
        }
    }

    $updatedTicket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $participantsAfter = is_array($updatedTicket['participant_emails'] ?? null) ? $updatedTicket['participant_emails'] : [];
    if ($participantsAfter === []) {
        return [
            'success' => false,
            'error' => __('flash.ticket_participant_minimum'),
        ];
    }

    $newParticipants = array_values(array_filter(
        array_map('strtolower', $participantsAfter),
        static fn(string $email): bool => $email !== '' && !isset($participantsBeforeLookup[$email])
    ));
    $participantsAfterLookup = array_fill_keys(array_map('strtolower', $participantsAfter), true);
    $removedParticipants = array_values(array_filter(
        array_map('strtolower', $participantsBefore),
        static fn(string $email): bool => $email !== '' && !isset($participantsAfterLookup[$email])
    ));

    $participantChangeNotifiedViaUpdate = false;
    $participantChangeNote = buildParticipantChangeNote($newParticipants, $removedParticipants);
    if ($participantChangeNote !== '') {
        $store->addMessage($ticketId, $actorEmail, 'admin', $participantChangeNote);
        $updatedTicket = $store->getTicket($ticketId, true, $actorEmail) ?? $updatedTicket;
        $participantsAfter = is_array($updatedTicket['participant_emails'] ?? null) ? $updatedTicket['participant_emails'] : $participantsAfter;

        $requesterRecipients = is_array($updatedTicket['participant_emails'] ?? null)
            ? $updatedTicket['participant_emails']
            : [(string) ($updatedTicket['user_email'] ?? '')];
        $reqLang = getUserMailLang((string) ($updatedTicket['user_email'] ?? ''));
        sendTicketNotification(
            $store,
            $GLOBALS['ictUsers'] ?? [],
            $requesterRecipients,
            __mail('email.subject_update', $reqLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_update', $participantChangeNote, false, $reqLang, __mail('email.intro_update_no_status', $reqLang)),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            null,
            $actorEmail
        );
        $participantChangeNotifiedViaUpdate = true;
    }

    foreach ($newParticipants as $newParticipantEmail) {
        if ($participantChangeNotifiedViaUpdate) {
            continue;
        }

        $participantLang = getUserMailLang($newParticipantEmail);
        sendTicketNotification(
            $store,
            $GLOBALS['ictUsers'] ?? [],
            [$newParticipantEmail],
            __mail('email.subject_participant_added', $participantLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_participant_added', '', false, $participantLang),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            null,
            $actorEmail
        );
    }

    if ($addedCount <= 0 && $removedCount <= 0) {
        return [
            'success' => false,
            'error' => __('flash.ticket_participant_add_none'),
        ];
    }

    $requesterSummary = buildRequesterSummary($participantsAfter, (string) ($updatedTicket['user_email'] ?? ''));
    return [
        'success' => true,
        'message' => __('flash.ticket_participants_saved'),
        'participant_emails' => array_values($participantsAfter),
        'requester_label' => (string) ($requesterSummary['label'] ?? ''),
        'requester_tooltip' => (string) ($requesterSummary['tooltip'] ?? ''),
        'requester_extra_count' => (int) ($requesterSummary['extra_count'] ?? 0),
        'creator_email' => strtolower(trim((string) ($updatedTicket['user_email'] ?? ''))),
    ];
}

function handleChangeTicketCategoryApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $category = trim((string) ($payload['category'] ?? ''));
    $reassign = !empty($payload['reassign']);
    $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'admin.php'));

    if (!in_array($category, TICKET_CATEGORIES, true)) {
        return [
            'success' => false,
            'error' => __('flash.invalid_category'),
        ];
    }

    $ticket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    try {
        $changeResult = $store->changeTicketCategory($ticketId, $category, $reassign);
    } catch (Throwable $exception) {
        return [
            'success' => false,
            'error' => $exception->getMessage(),
        ];
    }

    if (empty($changeResult['changed'])) {
        return [
            'success' => false,
            'error' => __('flash.ticket_category_unchanged'),
        ];
    }

    $actorEmail = $viewerEmail;
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = strtolower(trim((string) ($ticket['assigned_email'] ?? $ticket['user_email'] ?? '')));
    }
    if (!filter_var($actorEmail, FILTER_VALIDATE_EMAIL)) {
        $actorEmail = 'ict@kvt.nl';
    }

    $categoryChangeNote = buildCategoryChangeNote(
        (string) ($changeResult['old_category'] ?? ''),
        (string) ($changeResult['new_category'] ?? ''),
        !empty($changeResult['assignee_changed']),
        (string) ($changeResult['assigned_email'] ?? '')
    );
    $messageId = $store->addMessage($ticketId, $actorEmail, 'admin', $categoryChangeNote);

    $updatedTicket = $store->getTicket($ticketId, true, $actorEmail);
    if ($updatedTicket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $requesterRecipients = is_array($updatedTicket['participant_emails'] ?? null)
        ? $updatedTicket['participant_emails']
        : [(string) ($updatedTicket['user_email'] ?? '')];
    $reqLang = getUserMailLang((string) ($updatedTicket['user_email'] ?? ''));
    sendTicketNotification(
        $store,
        $GLOBALS['ictUsers'] ?? [],
        $requesterRecipients,
        __mail('email.subject_update', $reqLang, $ticketId),
        buildNotificationBody($updatedTicket, 'email.intro_update', $categoryChangeNote, false, $reqLang, __mail('email.intro_update_no_status', $reqLang)),
        $actorEmail,
        (string) ($updatedTicket['category'] ?? ''),
        $ticketId,
        null,
        $actorEmail
    );

    if (!empty($changeResult['assignee_changed']) && trim((string) ($changeResult['assigned_email'] ?? '')) !== '') {
        $assigneeEmail = strtolower(trim((string) $changeResult['assigned_email']));
        $assigneeLang = getUserMailLang($assigneeEmail);
        sendTicketNotification(
            $store,
            $GLOBALS['ictUsers'] ?? [],
            [$assigneeEmail],
            __mail('email.subject_assigned', $assigneeLang, $ticketId),
            buildNotificationBody($updatedTicket, 'email.intro_assigned', $categoryChangeNote, true, $assigneeLang),
            $actorEmail,
            (string) ($updatedTicket['category'] ?? ''),
            $ticketId,
            'assigned',
            $actorEmail
        );
    }

    $assignedEmail = strtolower(trim((string) ($updatedTicket['assigned_email'] ?? '')));
    $messageForRender = [
        'id' => $messageId,
        'sender_email' => $actorEmail,
        'sender_role' => 'admin',
        'message_text' => $categoryChangeNote,
        'message_text_raw' => $categoryChangeNote,
        'attachments' => [],
    ];

    return [
        'success' => true,
        'message' => __('flash.ticket_category_changed'),
        'ticket_id' => $ticketId,
        'category' => (string) ($updatedTicket['category'] ?? ''),
        'category_label' => translateCategory((string) ($updatedTicket['category'] ?? '')),
        'assigned_email' => $assignedEmail,
        'assigned_label' => $assignedEmail !== '' ? formatUserDisplayName($assignedEmail) : __('ticket.unassigned'),
        'assigned_color' => emailToHexColor($assignedEmail !== '' ? $assignedEmail : 'onbekend@kvt.nl'),
        'message_id' => $messageId,
        'message_html' => renderTicketMessageHtml($messageForRender, $currentPage),
    ];
}

function handleChangeTicketTitleApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
    $title = trim((string) ($payload['title'] ?? ''));

    if ($title === '') {
        return [
            'success' => false,
            'error' => __('flash.ticket_title_required'),
        ];
    }

    $ticket = $store->getTicket($ticketId, true, $viewerEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => __('flash.ticket_not_found'),
        ];
    }

    $currentTitle = trim((string) ($ticket['title'] ?? ''));
    if ($title === $currentTitle) {
        return [
            'success' => false,
            'error' => __('flash.ticket_title_unchanged'),
        ];
    }

    if (!$store->updateTicketTitle($ticketId, $title)) {
        return [
            'success' => false,
            'error' => __('flash.db_error_prefix'),
        ];
    }

    $store->deleteTextTranslationsForEntity('ticket_title', $ticketId);

    return [
        'success' => true,
        'message' => __('flash.ticket_title_changed'),
        'ticket_id' => $ticketId,
        'title' => $title,
    ];
}

function buildBigscreenPollApiPayload(TicketStore $store): array
{
    global $ictUsers;

    $allTicketsForPoll = $store->getTickets(true, '');
    warmUserDirectoryForBigscreenPoll($allTicketsForPoll, is_array($ictUsers ?? null) ? $ictUsers : []);

    $pollMaxId = 0;
    $pollLatest = null;
    $pollSnapshot = [];
    $pollOpenTickets = [];

    foreach ($allTicketsForPoll as $ticket) {
        $ticketId = (int) ($ticket['id'] ?? 0);
        if ($ticketId > $pollMaxId) {
            $pollMaxId = $ticketId;
            $pollLatest = $ticket;
        }

        $pollSnapshot[] = [
            'id' => $ticketId,
            'updated_at' => (string) ($ticket['updated_at'] ?? ''),
            'status' => (string) ($ticket['status'] ?? ''),
            'assigned_email' => (string) ($ticket['assigned_email'] ?? ''),
            'message_count' => (int) ($ticket['message_count'] ?? 0),
        ];

        if ((string) ($ticket['status'] ?? '') !== 'afgehandeld') {
            $pollOpenTickets[] = mapBigscreenOpenTicketRow($ticket);
        }
    }

    $pollOverallStats = $store->getOverallStats();
    $pollIctStats = $store->getIctUserStats();
    $pollRequesterStats = $store->getRequesterStats();
    $pollAvailability = $store->getIctUserAvailability();

    $pollIctStatsMapped = array_map(
        static fn(array $row): array => mapBigscreenIctStatRow($row, $pollAvailability),
        $pollIctStats
    );

    $pollRequesterStatsMapped = array_map(
        static fn(array $row): array => mapBigscreenRequesterStatRow($row),
        $pollRequesterStats
    );

    return [
        'success' => true,
        'max_id' => $pollMaxId,
        'snapshot' => $pollSnapshot,
        'overall_stats' => $pollOverallStats,
        'ict_stats' => $pollIctStatsMapped,
        'requester_stats' => $pollRequesterStatsMapped,
        'open_tickets' => $pollOpenTickets,
        'latest' => $pollLatest !== null ? [
            'id' => $pollMaxId,
            'title' => (string) ($pollLatest['title'] ?? ''),
            'user_email' => (string) ($pollLatest['user_email'] ?? ''),
            'user_label' => formatUserDisplayName((string) ($pollLatest['user_email'] ?? '')),
            'assigned_email' => (string) ($pollLatest['assigned_email'] ?? ''),
            'assigned_color' => emailToHexColor((string) ($pollLatest['assigned_email'] ?? '')),
            'priority' => (int) ($pollLatest['priority'] ?? 0),
        ] : null,
    ];
}

function handleSaveAdminEmailPreferencesApiAction(array $payload, ?array $apiClient): array
{
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    ensureApiSessionStarted();
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    $notificationType = trim((string) ($payload['notification_type'] ?? ''));
    if (!in_array($notificationType, ADMIN_EMAIL_NOTIFICATION_TYPES, true)) {
        return [
            'success' => false,
            'error' => 'invalid_notification_type',
        ];
    }

    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($_SESSION['user']['email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    saveAdminEmailPreference($userEmail, $notificationType, !empty($payload['enabled']));

    return [
        'success' => true,
        'preferences' => loadAdminEmailPreferences($userEmail),
    ];
}

function resolveChangelogApiActor(array $payload, ?array $apiClient): array
{
    $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
    if (!$userIsAdmin) {
        return [
            'success' => false,
            'error' => __('flash.settings_admin_only'),
        ];
    }

    ensureApiSessionStarted();
    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($_SESSION['user']['email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    return [
        'success' => true,
        'email' => $userEmail,
    ];
}

function verifyChangelogApiCsrf(array $payload): ?array
{
    ensureApiSessionStarted();

    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        return [
            'success' => false,
            'error' => 'csrf',
        ];
    }

    return null;
}

function handleMarkChangelogReadApiAction(array $payload, ?array $apiClient): array
{
    $actor = resolveChangelogApiActor($payload, $apiClient);
    if (empty($actor['success'])) {
        return $actor;
    }

    $csrfError = verifyChangelogApiCsrf($payload);
    if ($csrfError !== null) {
        return $csrfError;
    }

    $userEmail = (string) ($actor['email'] ?? '');
    $entryId = trim((string) ($payload['entry_id'] ?? ''));
    if ($entryId === '') {
        return [
            'success' => false,
            'error' => 'invalid_entry_id',
        ];
    }

    return [
        'success' => true,
        'read_ids' => markChangelogEntryRead($userEmail, $entryId),
    ];
}

function handleMarkAllChangelogsReadApiAction(array $payload, ?array $apiClient): array
{
    $actor = resolveChangelogApiActor($payload, $apiClient);
    if (empty($actor['success'])) {
        return $actor;
    }

    $csrfError = verifyChangelogApiCsrf($payload);
    if ($csrfError !== null) {
        return $csrfError;
    }

    $userEmail = (string) ($actor['email'] ?? '');
    $entryIds = $payload['entry_ids'] ?? [];
    if (!is_array($entryIds)) {
        $entryIds = [];
    }

    return [
        'success' => true,
        'read_ids' => markAllChangelogEntriesRead($userEmail, $entryIds),
    ];
}

function buildBigscreenVersionApiPayload(): array
{
    $versionFiles = [
        __DIR__ . DIRECTORY_SEPARATOR . 'index.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'data.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'bigscreen_js.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'page_js.php',
    ];

    $versionSource = [];
    foreach ($versionFiles as $versionFile) {
        if (is_file($versionFile)) {
            $versionSource[] = basename($versionFile) . ':' . (string) filemtime($versionFile);
        }
    }

    return [
        'success' => true,
        'version' => sha1(implode('|', $versionSource)),
    ];
}

function buildPageAccessTicketTitle(string $pageName): string
{
    return 'Aanvraag toegang tot ' . $pageName;
}

function buildPageAccessTicketDescription(string $pageName): string
{
    return 'Ik wil graag toegang krijgen tot de pagina ' . $pageName . ' op sleutels.kvt.nl.';
}

function buildAsclepiusWebBasePath(): string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === 'sleutels.kvt.nl') {
        return '/asclepius';
    }

    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $webDir = realpath(__DIR__);
    if (
        is_string($docRoot) && $docRoot !== '' &&
        is_string($webDir) && $webDir !== '' &&
        str_starts_with(str_replace('\\', '/', $webDir), str_replace('\\', '/', $docRoot))
    ) {
        $relative = substr(str_replace('\\', '/', $webDir), strlen(str_replace('\\', '/', $docRoot)));

        return '/' . trim($relative, '/');
    }

    return '/asclepius';
}

function buildAsclepiusTicketUrl(int $ticketId): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'sleutels.kvt.nl'));
    if ($host === '') {
        $host = 'sleutels.kvt.nl';
    }

    return $scheme . '://' . $host . buildAsclepiusWebBasePath() . '/index.php?open=' . $ticketId;
}

function buildPageAccessTicketApiResponse(array $ticket, bool $created): array
{
    $ticketId = (int) ($ticket['id'] ?? 0);
    $messages = [];
    foreach ($ticket['messages'] ?? [] as $message) {
        if (!is_array($message)) {
            continue;
        }

        $senderEmail = (string) ($message['sender_email'] ?? '');
        $messages[] = [
            'id' => (int) ($message['id'] ?? 0),
            'sender_email' => $senderEmail,
            'sender_label' => formatUserDisplayName($senderEmail),
            'sender_role' => (string) ($message['sender_role'] ?? 'user'),
            'message_text' => (string) ($message['message_text'] ?? ''),
            'created_at' => formatDateTime((string) ($message['created_at'] ?? '')),
        ];
    }

    return [
        'success' => true,
        'created' => $created,
        'message' => $created
            ? 'Uw aanvraag is ingediend. U ontvangt ook een bevestiging per e-mail.'
            : 'Er is al een open ticket voor deze toegangsaanvraag.',
        'ticket' => [
            'id' => $ticketId,
            'title' => (string) ($ticket['title'] ?? ''),
            'status' => (string) ($ticket['status'] ?? ''),
            'status_label' => translateStatus((string) ($ticket['status'] ?? '')),
            'category' => (string) ($ticket['category'] ?? ''),
            'category_label' => translateCategory((string) ($ticket['category'] ?? '')),
            'created_at' => formatDateTime((string) ($ticket['created_at'] ?? '')),
            'updated_at' => formatDateTime((string) ($ticket['updated_at'] ?? '')),
        ],
        'messages' => $messages,
        'ticket_url' => buildAsclepiusTicketUrl($ticketId),
    ];
}

function handleRequestPageAccessApiAction(TicketStore $store, array $payload, ?array $apiClient): array
{
    global $ictUsers;

    $userEmail = strtolower(trim((string) (
        $apiClient['email'] ?? ($payload['viewer_email'] ?? ($payload['user_email'] ?? ''))
    )));
    if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'invalid_user',
        ];
    }

    if ($apiClient === null && !isTrustedApiRequester()) {
        return [
            'success' => false,
            'error' => 'unauthorized',
        ];
    }

    $pageName = trim((string) ($payload['page_name'] ?? ''));
    if ($pageName === '') {
        return [
            'success' => false,
            'error' => 'page_name_required',
        ];
    }

    $category = 'sleutels.kvt.nl web-applicatieproblemen';
    $title = buildPageAccessTicketTitle($pageName);
    $description = buildPageAccessTicketDescription($pageName);

    $existingTicket = null;
    foreach ($store->getTickets(false, $userEmail) as $ticket) {
        if (!is_array($ticket)) {
            continue;
        }

        if (
            trim((string) ($ticket['title'] ?? '')) === $title
            && strtolower(trim((string) ($ticket['status'] ?? ''))) !== 'afgehandeld'
        ) {
            $existingTicket = $ticket;
            break;
        }
    }

    $created = false;
    if ($existingTicket !== null) {
        $ticketId = (int) ($existingTicket['id'] ?? 0);
    } else {
        try {
            $result = $store->createTicket($title, $category, $userEmail, $description);
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'error' => 'create_failed',
                'details' => $exception->getMessage(),
            ];
        }

        $ticketId = (int) ($result['ticket_id'] ?? 0);
        $created = true;

        $createdTicket = $store->getTicket($ticketId, true, $userEmail);
        if ($createdTicket !== null) {
            $assignedEmail = trim((string) ($result['assigned_email'] ?? ''));
            $ictRecipients = $assignedEmail !== ''
                ? [$assignedEmail]
                : extractIctUserEmails(is_array($ictUsers) ? $ictUsers : []);
            $ictLang = $assignedEmail !== '' ? getUserMailLang($assignedEmail) : 'nl';
            sendTicketNotification(
                $store,
                is_array($ictUsers) ? $ictUsers : [],
                $ictRecipients,
                __mail('email.subject_new_ticket', $ictLang, $ticketId),
                buildNotificationBody($createdTicket, 'email.intro_new_ict', $description, true, $ictLang),
                $userEmail,
                (string) ($createdTicket['category'] ?? $category),
                $ticketId,
                'new_ticket',
                $userEmail
            );

            $requesterRecipients = is_array($createdTicket['participant_emails'] ?? null)
                ? $createdTicket['participant_emails']
                : [$userEmail];
            $requesterLang = getUserMailLang($userEmail);
            sendTicketNotification(
                $store,
                is_array($ictUsers) ? $ictUsers : [],
                $requesterRecipients,
                __mail('email.subject_created', $requesterLang, $ticketId),
                buildNotificationBody($createdTicket, 'email.intro_created_self', $description, false, $requesterLang),
                null,
                (string) ($createdTicket['category'] ?? $category),
                $ticketId,
                null,
                $userEmail
            );
        }
    }

    $ticket = $store->getTicket($ticketId, false, $userEmail);
    if ($ticket === null) {
        return [
            'success' => false,
            'error' => 'ticket_not_found',
        ];
    }

    return buildPageAccessTicketApiResponse($ticket, $created);
}

/**
 * Page load
 */
if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
$providedApiKey = getApiKeyFromRequest();
$apiClient = loadApiClientByToken($providedApiKey);
$hasValidServiceApiKey = isValidApiKey($providedApiKey, $apiKeys ?? []);
if (!isTrustedApiRequester() && $apiClient === null && !$hasValidServiceApiKey) {
    $unauthorizedReason = getRefreshRequiredUnauthorizedReason($providedApiKey);
    sendJson(401, [
        'success' => false,
        'error' => 'Ongeldige API-key.',
        'reason' => $unauthorizedReason,
    ]);
}

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers ?? [], TICKET_CATEGORIES);
} catch (Throwable $exception) {
    sendJson(500, [
        'success' => false,
        'error' => 'Database kon niet worden geopend.',
        'details' => $exception->getMessage(),
    ]);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $ticketId = max(0, (int) ($_GET['id'] ?? 0));

    if ($ticketId > 0) {
        $ticket = $store->getTicket($ticketId, true, '');
        if ($ticket === null) {
            sendJson(404, [
                'success' => false,
                'error' => 'Ticket niet gevonden.',
            ]);
        }

        sendJson(200, [
            'success' => true,
            'ticket' => $ticket,
        ]);
    }

    $tickets = $store->getTickets(true, '', [], null, []);
    sendJson(200, [
        'success' => true,
        'count' => count($tickets),
        'tickets' => $tickets,
    ]);
}

if ($method === 'POST') {
    $payload = getRequestBody();
    $action = trim((string) ($payload['action'] ?? ''));

    if ($action === 'ticket_poll') {
        sendJson(200, buildTicketPollApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'ticket_thread') {
        $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
        $currentPage = normalizeReturnPage((string) ($payload['current_page'] ?? 'index.php'));
        $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
        $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
        $isAdminPortal = !empty($payload['is_admin_portal']);
        $canManageTickets = $isAdminPortal && $userIsAdmin;
        $currentLanguage = strtolower(trim((string) ($payload['current_language'] ?? 'nl')));
        if (!array_key_exists($currentLanguage, SUPPORTED_LANGUAGES)) {
            $currentLanguage = 'nl';
        }

        $ticketDetail = $store->getTicket($ticketId, $canManageTickets, $viewerEmail);
        if (!is_array($ticketDetail)) {
            sendJson(404, ['success' => false, 'error' => 'ticket_not_found']);
        }

        $ticketDetail = localizeTicketDetailForViewer($ticketDetail, $store, $currentLanguage, true);
        $messages = array_map(
            static fn(array $message): array => [
                'id' => (int) ($message['id'] ?? 0),
                'html' => renderTicketMessageHtml($message, $currentPage),
            ],
            $ticketDetail['messages'] ?? []
        );

        sendJson(200, [
            'success' => true,
            'ticket_id' => $ticketId,
            'messages' => $messages,
        ]);
    }

    if ($action === 'browser_notifications_poll') {
        sendJson(200, buildBrowserNotificationsApiPayload($store, $payload, $apiClient));
    }

    if ($action === 'webpush_subscription') {
        sendJson(200, handleWebPushSubscriptionApiAction($store, $payload, $apiClient));
    }

    if ($action === 'manage_ticket_participants') {
        sendJson(200, handleManageTicketParticipantsApiAction($store, $payload, $apiClient));
    }

    if ($action === 'change_ticket_category') {
        sendJson(200, handleChangeTicketCategoryApiAction($store, $payload, $apiClient));
    }

    if ($action === 'change_ticket_title') {
        sendJson(200, handleChangeTicketTitleApiAction($store, $payload, $apiClient));
    }

    if ($action === 'save_admin_email_preferences') {
        sendJson(200, handleSaveAdminEmailPreferencesApiAction($payload, $apiClient));
    }

    if ($action === 'mark_changelog_read') {
        sendJson(200, handleMarkChangelogReadApiAction($payload, $apiClient));
    }

    if ($action === 'mark_all_changelogs_read') {
        sendJson(200, handleMarkAllChangelogsReadApiAction($payload, $apiClient));
    }

    if ($action === 'manage_ticket_template') {
        $userIsAdmin = !empty($apiClient['is_admin']);
        if (!$userIsAdmin && !isTrustedApiRequester()) {
            sendJson(403, ['success' => false, 'error' => __('flash.settings_admin_only')]);
        }

        $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
        $sessionToken = '';
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionCookieName = session_name();
            if ($sessionCookieName !== '' && !empty($_COOKIE[$sessionCookieName])) {
                session_start(['read_and_close' => true]);
            }
        }
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
            sendJson(403, ['success' => false, 'error' => 'csrf']);
        }

        $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
        $authorEmail = strtolower(trim((string) ($apiClient['email'] ?? '')));

        if ($operation === 'create') {
            $name = trim((string) ($payload['name'] ?? ''));
            $body = trim((string) ($payload['body'] ?? ''));
            if ($name === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_name_required')]);
            }
            if ($body === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_body_required')]);
            }
            $store->createTicketTemplate($name, $body, $authorEmail);
        } elseif ($operation === 'update') {
            $id = max(1, (int) ($payload['id'] ?? 0));
            $name = trim((string) ($payload['name'] ?? ''));
            $body = trim((string) ($payload['body'] ?? ''));
            if ($name === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_name_required')]);
            }
            if ($body === '') {
                sendJson(422, ['success' => false, 'error' => __('flash.template_body_required')]);
            }
            if (!$store->updateTicketTemplate($id, $name, $body, $authorEmail)) {
                sendJson(404, ['success' => false, 'error' => __('flash.template_not_found')]);
            }
        } elseif ($operation === 'delete') {
            $id = max(1, (int) ($payload['id'] ?? 0));
            if (!$store->deleteTicketTemplate($id)) {
                sendJson(404, ['success' => false, 'error' => __('flash.template_not_found')]);
            }
        } elseif ($operation === 'reorder') {
            $orderedIds = array_map('intval', (array) ($payload['ordered_ids'] ?? []));
            $store->reorderTicketTemplates($orderedIds);
        } elseif ($operation !== 'list') {
            sendJson(422, ['success' => false, 'error' => 'unknown_operation']);
        }

        sendJson(200, ['success' => true, 'templates' => $store->getTicketTemplates()]);
    }

    if ($action === 'update_ticket_message_checkbox') {
        $userIsAdmin = !empty($apiClient['is_admin']);
        if (!$userIsAdmin && !isTrustedApiRequester()) {
            sendJson(403, ['success' => false, 'error' => __('flash.settings_admin_only')]);
        }

        $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionCookieName = session_name();
            if ($sessionCookieName !== '' && !empty($_COOKIE[$sessionCookieName])) {
                session_start(['read_and_close' => true]);
            }
        }
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
            sendJson(403, ['success' => false, 'error' => 'csrf']);
        }

        $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
        $messageId = max(1, (int) ($payload['message_id'] ?? 0));
        $lineIndex = max(0, (int) ($payload['line_index'] ?? 0));
        $checked = !empty($payload['checked']);
        $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));

        $updatedMessageText = $store->updateTicketMessageCheckboxState($ticketId, $messageId, $lineIndex, $checked, true, $viewerEmail);
        if ($updatedMessageText === null) {
            sendJson(422, ['success' => false, 'error' => 'update_failed']);
        }

        sendJson(200, [
            'success' => true,
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'message_text' => $updatedMessageText,
        ]);
    }

    if ($action === 'bigscreen_poll') {
        if (!($apiClient['is_admin'] ?? false) && !isTrustedApiRequester()) {
            sendJson(403, [
                'success' => false,
                'error' => 'forbidden',
            ]);
        }
        sendJson(200, buildBigscreenPollApiPayload($store));
    }

    if ($action === 'bigscreen_version') {
        if (!($apiClient['is_admin'] ?? false) && !isTrustedApiRequester()) {
            sendJson(403, [
                'success' => false,
                'error' => 'forbidden',
            ]);
        }
        sendJson(200, buildBigscreenVersionApiPayload());
    }

    if ($action === 'request_page_access') {
        $effectiveApiClient = $apiClient;
        if ($effectiveApiClient === null && $hasValidServiceApiKey) {
            $viewerEmail = strtolower(trim((string) ($payload['viewer_email'] ?? ($payload['user_email'] ?? ''))));
            $effectiveApiClient = [
                'email' => $viewerEmail,
                'is_admin' => false,
                'oid' => '',
                'api_key' => $providedApiKey,
            ];
        }

        sendJson(200, handleRequestPageAccessApiAction($store, $payload, $effectiveApiClient));
    }

    if ($action === 'translate_ticket') {
        $ticketId = max(1, (int) ($payload['ticket_id'] ?? 0));
        $language = strtolower(trim((string) ($payload['language'] ?? 'nl')));
        if (!array_key_exists($language, SUPPORTED_LANGUAGES)) {
            $language = 'nl';
        }
        $viewerEmail = strtolower(trim((string) ($apiClient['email'] ?? ($payload['viewer_email'] ?? ''))));
        $userIsAdmin = !empty($apiClient['is_admin']) || !empty($payload['user_is_admin']);
        $isAdminPortal = !empty($payload['is_admin_portal']);
        $canManageTickets = $isAdminPortal && $userIsAdmin;

        $ticketDetail = $store->getTicket($ticketId, $canManageTickets, $viewerEmail);
        if (!is_array($ticketDetail)) {
            sendJson(404, ['success' => false, 'error' => 'ticket_not_found']);
        }

        $ticketDetail = localizeTicketDetailForViewer($ticketDetail, $store, $language, false);

        $messages = [];
        foreach (($ticketDetail['messages'] ?? []) as $message) {
            $rawText = (string) ($message['message_text_raw'] ?? ($message['message_text'] ?? ''));
            $displayText = (string) ($message['message_text'] ?? '');
            $messages[] = [
                'id' => (int) ($message['id'] ?? 0),
                'message_text' => $displayText,
                'message_text_raw' => $rawText,
                'message_is_translated' => !empty($message['message_is_translated']),
                'translation_error' => (string) ($message['translation_error'] ?? ''),
                'translation_error_detail' => (string) ($message['translation_error_detail'] ?? ''),
            ];
        }

        $rawTitle = (string) ($ticketDetail['title_raw'] ?? ($ticketDetail['title'] ?? ''));
        sendJson(200, [
            'success' => true,
            'ticket_id' => $ticketId,
            'title' => (string) ($ticketDetail['title'] ?? ''),
            'title_raw' => $rawTitle,
            'title_is_translated' => !empty($ticketDetail['title_is_translated']),
            'title_translation_error' => (string) ($ticketDetail['title_translation_error'] ?? ''),
            'title_translation_error_detail' => (string) ($ticketDetail['title_translation_error_detail'] ?? ''),
            'messages' => $messages,
        ]);
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $category = trim((string) ($payload['category'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $userEmail = strtolower(trim((string) ($payload['user_email'] ?? '')));
    $priority = max(0, min(2, (int) ($payload['priority'] ?? 0)));
    $participantEmailsRaw = $payload['participant_emails'] ?? [];
    $participantEmails = [];

    if (is_string($participantEmailsRaw)) {
        $participantEmails = parseEmailListInput($participantEmailsRaw);
    } elseif (is_array($participantEmailsRaw)) {
        $participantEmails = parseEmailListInput(implode(',', array_map(static fn($value): string => (string) $value, $participantEmailsRaw)));
    }

    if ($userEmail === '') {
        $userEmail = strtolower(trim((string) ($payload['requester_email'] ?? '')));
    }

    $errors = [];
    if ($title === '') {
        $errors[] = 'Titel is verplicht.';
    }
    if (!in_array($category, TICKET_CATEGORIES, true)) {
        $errors[] = 'Categorie is ongeldig.';
    }
    if ($description === '') {
        $errors[] = 'Beschrijving is verplicht.';
    }
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'user_email moet een geldig e-mailadres zijn.';
    }

    if ($errors !== []) {
        sendJson(422, [
            'success' => false,
            'errors' => $errors,
        ]);
    }

    try {
        $result = $store->createTicket($title, $category, $userEmail, $description, [], $priority, $participantEmails);
        $ticketId = (int) ($result['ticket_id'] ?? 0);
        $ticket = $store->getTicket($ticketId, true, '');

        sendJson(201, [
            'success' => true,
            'ticket_id' => $ticketId,
            'assigned_email' => $result['assigned_email'] ?? null,
            'ticket' => $ticket,
        ]);
    } catch (Throwable $exception) {
        sendJson(500, [
            'success' => false,
            'error' => 'Ticket kon niet worden aangemaakt.',
            'details' => $exception->getMessage(),
        ]);
    }
}

sendJson(405, [
    'success' => false,
    'error' => 'Method niet toegestaan.',
]);
}
