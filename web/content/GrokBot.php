<?php

/**
 * Outbound Grok webhooks.
 *
 * The global webhook from auth.php ($grokBot) keeps firing for every ticket
 * event when it is enabled, with that config's sender_email, default_name
 * and default_title.
 *
 * A personal webhook saved under Voorkeuren fires for the same event as well
 * when the ticket assignee has one configured, and when an acting ICT user is
 * known (AI Advies). The same person is called once. An identical URL and send
 * key to the global webhook is not called a second time. The personal callback
 * uses that user's email and display name, with the title "Assistent". The bot
 * may still override name and title on add_ticket_message.
 */
class GrokBot
{
    public const EVENT_NEW_TICKET = 'new-ticket';
    public const EVENT_TICKET_SOLVED = 'ticket-solved';
    public const EVENT_USER_REPLY = 'user-reply';
    public const EVENT_TICKET_REOPENED = 'ticket-reopened';
    public const EVENT_RE_EVALUATE_AND_ADVISE = 're-evaluate-ticket-and-advise';

    public const USER_WEBHOOK_PREF_KEY = 'grok_webhook';
    public const USER_DEFAULT_TITLE = 'Assistent';

    private const DEFAULT_SENDER = 'grok-bot@kvt.nl';
    private const WEBHOOK_TIMEOUT_SECONDS = 5;
    private const EPHEMERAL_KEY_TTL_SECONDS = 3600;
    private const USER_WEBHOOK_URL_MAX_LENGTH = 2000;
    private const USER_WEBHOOK_KEY_MAX_LENGTH = 500;

    /** @var (callable(string, array<string, mixed>, string): void)|null */
    private static $deliveryOverride = null;

    /**
     * @param array<string, mixed>|null $config
     * @param array<string, mixed> $context assigned_email and optional actor_email
     */
    public static function notifyTicketCreated(TicketStore $store, int $ticketId, ?array $config = null, array $context = []): void
    {
        $context['store'] = $store;
        self::notifyTicketEvent($ticketId, self::EVENT_NEW_TICKET, $config, [], $context);
    }

    /**
     * @param array<string, mixed>|null $config
     * @param array<string, mixed> $context assigned_email and optional actor_email
     */
    public static function notifyTicketSolved(TicketStore $store, int $ticketId, ?array $config = null, array $context = []): void
    {
        $context['store'] = $store;
        self::notifyTicketEvent($ticketId, self::EVENT_TICKET_SOLVED, $config, [], $context);
    }

    /**
     * User posted a non-ghost message while the ticket was waiting on the user.
     * Status is the value from before this reply (the UI may already have moved
     * the ticket to "in behandeling" by the time the webhook is delivered).
     *
     * @param array<string, mixed>|null $config
     * @param array<string, mixed> $context assigned_email; the replying user is not a webhook recipient
     */
    public static function notifyUserRepliedWhileWaiting(
        TicketStore $store,
        int $ticketId,
        string $senderRole,
        bool $isGhost,
        string $statusBeforeReply,
        string $messageText,
        ?array $config = null,
        array $context = []
    ): void {
        if ($isGhost || $senderRole !== 'user' || trim($messageText) === '') {
            return;
        }
        if (!self::isWaitingOnUserStatus($statusBeforeReply)) {
            return;
        }

        $context['store'] = $store;
        unset($context['actor_email']);
        self::notifyTicketEvent($ticketId, self::EVENT_USER_REPLY, $config, [], $context);
    }

    /**
     * @param array<string, mixed>|null $config
     * @param array<string, mixed> $context assigned_email and optional actor_email
     */
    public static function notifyTicketReopened(TicketStore $store, int $ticketId, ?array $config = null, array $context = []): void
    {
        $context['store'] = $store;
        self::notifyTicketEvent($ticketId, self::EVENT_TICKET_REOPENED, $config, [], $context);
    }

    /**
     * ICT requested a fresh AI review of an existing ticket.
     * Payload field advice_prompt: optional free-text note from the AI Advies modal (may be empty).
     *
     * @param array<string, mixed>|null $config
     * @param array<string, mixed> $context assigned_email and actor_email (the ICT user who asked)
     */
    public static function notifyReEvaluateAndAdvise(
        TicketStore $store,
        int $ticketId,
        string $advicePrompt = '',
        ?array $config = null,
        array $context = []
    ): bool {
        $context['store'] = $store;

        return self::notifyTicketEvent(
            $ticketId,
            self::EVENT_RE_EVALUATE_AND_ADVISE,
            $config,
            [
                // Optional ICT note from the modal; empty string is valid and always sent.
                'advice_prompt' => $advicePrompt,
            ],
            $context
        );
    }

    /**
     * Whether a message sender is the configured Grok / AI assistant identity.
     *
     * @param array<string, mixed>|null $config
     */
    public static function isAiAssistantSender(string $senderEmail, ?array $config = null): bool
    {
        if ($config === null) {
            global $grokBot;
            $config = is_array($grokBot ?? null) ? $grokBot : [];
        }

        $configuredSender = strtolower(trim((string) ($config['sender_email'] ?? self::DEFAULT_SENDER)));
        if ($configuredSender === '') {
            $configuredSender = self::DEFAULT_SENDER;
        }

        $senderEmail = strtolower(trim($senderEmail));
        return $senderEmail !== '' && $senderEmail === $configuredSender;
    }

    /**
     * Treat Grok-bot pipeline messages (typically ghost posts as the configured sender) as AI replies.
     *
     * @param array<string, mixed> $message
     * @param array<string, mixed>|null $config
     */
    public static function isAiAssistantMessage(array $message, ?array $config = null): bool
    {
        if ((int) ($message['is_ai_assistant'] ?? 0) === 1) {
            return true;
        }

        return self::isAiAssistantSender((string) ($message['sender_email'] ?? ''), $config);
    }

    /**
     * Voorkeuren view of the logged-in user's webhook. The send key is never included.
     *
     * @return array{configured: bool, webhook_url: string, has_send_key: bool}
     */
    public static function publicUserWebhook(string $email): array
    {
        $stored = self::readUserWebhook($email);
        if ($stored === null) {
            return [
                'configured' => false,
                'webhook_url' => '',
                'has_send_key' => false,
            ];
        }

        return [
            'configured' => true,
            'webhook_url' => $stored['webhook_url'],
            'has_send_key' => $stored['send_key'] !== '',
        ];
    }

    /**
     * Store a personal webhook in the existing user-prefs JSON (web/data, not in git).
     * An empty send key keeps the key already stored for this user.
     *
     * @return array{ok: bool, error_code: string, webhook: array{configured: bool, webhook_url: string, has_send_key: bool}}
     */
    public static function saveUserWebhook(string $email, string $webhookUrl, string $sendKey, ?string $displayName = null): array
    {
        $email = strtolower(trim($email));
        $empty = [
            'configured' => false,
            'webhook_url' => '',
            'has_send_key' => false,
        ];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !function_exists('saveUserPref')) {
            return [
                'ok' => false,
                'error_code' => 'invalid_user',
                'webhook' => self::publicUserWebhook($email),
            ];
        }

        $webhookUrl = trim($webhookUrl);
        $sendKey = trim($sendKey);
        if (!self::isAllowedWebhookUrl($webhookUrl)) {
            return [
                'ok' => false,
                'error_code' => 'invalid_webhook_url',
                'webhook' => self::publicUserWebhook($email),
            ];
        }

        $existing = self::readUserWebhook($email);
        if ($sendKey === '' && $existing !== null) {
            $sendKey = $existing['send_key'];
        }
        if ($sendKey === '') {
            return [
                'ok' => false,
                'error_code' => 'send_key_required',
                'webhook' => $existing === null ? $empty : self::publicUserWebhook($email),
            ];
        }
        if (strlen($sendKey) > self::USER_WEBHOOK_KEY_MAX_LENGTH) {
            return [
                'ok' => false,
                'error_code' => 'send_key_required',
                'webhook' => self::publicUserWebhook($email),
            ];
        }

        $name = trim((string) $displayName);
        if ($name === '') {
            $name = self::captureUserDisplayName($email, $existing['display_name'] ?? '');
        }

        saveUserPref($email, self::USER_WEBHOOK_PREF_KEY, [
            'webhook_url' => $webhookUrl,
            'send_key' => $sendKey,
            'display_name' => $name,
            'updated_at' => date('c'),
        ]);
        self::restrictUserPrefsFile($email);

        return [
            'ok' => true,
            'error_code' => '',
            'webhook' => self::publicUserWebhook($email),
        ];
    }

    public static function clearUserWebhook(string $email): void
    {
        if (!function_exists('deleteUserPref')) {
            return;
        }

        deleteUserPref($email, self::USER_WEBHOOK_PREF_KEY);
        self::restrictUserPrefsFile($email);
    }

    /**
     * @param (callable(string, array<string, mixed>, string): void)|null $override
     */
    public static function setDeliveryOverride(?callable $override): void
    {
        self::$deliveryOverride = $override;
    }

    private static function isWaitingOnUserStatus(string $status): bool
    {
        $canonical = defined('TICKET_STATUS_WAITING_ON_USER')
            ? (string) TICKET_STATUS_WAITING_ON_USER
            : 'afwachtende op gebruiker';

        return strtolower(trim($status)) === $canonical;
    }

    /**
     * Global webhook first, then personal webhooks for the assignee and acting user.
     *
     * @param array<string, mixed>|null $config Global $grokBot config, or a test override.
     * @param array<string, mixed> $extra Extra JSON body fields merged after type/ticket_id/api_key.
     * @param array<string, mixed> $context store, assigned_email, actor_email
     */
    private static function notifyTicketEvent(int $ticketId, string $type, ?array $config = null, array $extra = [], array $context = []): bool
    {
        if ($ticketId <= 0) {
            return false;
        }

        if ($config === null) {
            global $grokBot;
            $config = is_array($grokBot ?? null) ? $grokBot : [];
        }

        $sent = self::deliverWebhook($ticketId, $type, $config, $extra);
        if (!empty($context['skip_personal'])) {
            return $sent;
        }

        foreach (self::personalWebhookConfigs($ticketId, $context) as $personalConfig) {
            if (self::sameWebhookDestination($config, $personalConfig)) {
                continue;
            }
            if (self::deliverWebhook($ticketId, $type, $personalConfig, $extra)) {
                $sent = true;
            }
        }

        return $sent;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $extra
     */
    private static function deliverWebhook(int $ticketId, string $type, array $config, array $extra = []): bool
    {
        if ($ticketId <= 0 || empty($config['enabled'])) {
            return false;
        }

        $webhookUrl = trim((string) ($config['webhook_url'] ?? ''));
        if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $issued = self::issueEphemeralApiKey($config, $ticketId);
        if ($issued === null) {
            return false;
        }

        $sendKey = trim((string) ($config['send_key'] ?? ($config['webhook_secret'] ?? '')));
        $payload = array_merge([
            'type' => $type,
            'ticket_id' => $ticketId,
            'api_key' => $issued['api_key'],
        ], $extra);
        self::postWebhook($webhookUrl, $payload, $sendKey);

        return true;
    }

    /**
     * Personal webhooks alongside the global one: ticket assignee, plus the acting
     * ICT user when the caller knows who that is (AI Advies).
     *
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private static function personalWebhookConfigs(int $ticketId, array $context): array
    {
        $emails = [];
        $assigned = strtolower(trim((string) ($context['assigned_email'] ?? '')));
        $store = $context['store'] ?? null;
        if ($assigned === '' && $ticketId > 0 && $store instanceof TicketStore && method_exists($store, 'getTicketAssigneeEmail')) {
            $assigned = strtolower(trim($store->getTicketAssigneeEmail($ticketId)));
        }
        if ($assigned !== '' && filter_var($assigned, FILTER_VALIDATE_EMAIL)) {
            $emails[$assigned] = $assigned;
        }

        $actor = strtolower(trim((string) ($context['actor_email'] ?? '')));
        if ($actor !== '' && filter_var($actor, FILTER_VALIDATE_EMAIL)) {
            $emails[$actor] = $actor;
        }

        $configs = [];
        foreach ($emails as $email) {
            $personal = self::userWebhookDeliveryConfig($email);
            if ($personal !== null) {
                $configs[] = $personal;
            }
        }

        return $configs;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function userWebhookDeliveryConfig(string $email): ?array
    {
        $stored = self::readUserWebhook($email);
        if ($stored === null) {
            return null;
        }

        return [
            'enabled' => true,
            'webhook_url' => $stored['webhook_url'],
            'send_key' => $stored['send_key'],
            'sender_email' => $email,
            'default_name' => self::captureUserDisplayName($email, $stored['display_name']),
            'default_title' => self::USER_DEFAULT_TITLE,
        ];
    }

    /**
     * Skip a second POST when the personal webhook is the same bot as auth.php.
     *
     * @param array<string, mixed> $global
     * @param array<string, mixed> $personal
     */
    private static function sameWebhookDestination(array $global, array $personal): bool
    {
        if (empty($global['enabled'])) {
            return false;
        }

        $globalUrl = trim((string) ($global['webhook_url'] ?? ''));
        $personalUrl = trim((string) ($personal['webhook_url'] ?? ''));
        $globalKey = trim((string) ($global['send_key'] ?? ($global['webhook_secret'] ?? '')));
        $personalKey = trim((string) ($personal['send_key'] ?? ($personal['webhook_secret'] ?? '')));

        return $globalUrl !== '' && $globalUrl === $personalUrl && $globalKey === $personalKey;
    }

    /**
     * @return array{webhook_url: string, send_key: string, display_name: string}|null
     */
    private static function readUserWebhook(string $email): ?array
    {
        if (!function_exists('loadUserPrefs')) {
            return null;
        }

        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $prefs = loadUserPrefs($email);
        $stored = $prefs[self::USER_WEBHOOK_PREF_KEY] ?? null;
        if (!is_array($stored)) {
            return null;
        }

        $url = trim((string) ($stored['webhook_url'] ?? ''));
        $sendKey = trim((string) ($stored['send_key'] ?? ''));
        if (!self::isAllowedWebhookUrl($url) || $sendKey === '') {
            return null;
        }

        return [
            'webhook_url' => $url,
            'send_key' => $sendKey,
            'display_name' => trim((string) ($stored['display_name'] ?? '')),
        ];
    }

    private static function isAllowedWebhookUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > self::USER_WEBHOOK_URL_MAX_LENGTH) {
            return false;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = trim((string) parse_url($url, PHP_URL_HOST));

        return ($scheme === 'http' || $scheme === 'https') && $host !== '';
    }

    private static function captureUserDisplayName(string $email, string $stored = ''): string
    {
        $email = strtolower(trim($email));
        $stored = trim($stored);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
            if ($sessionEmail === $email) {
                foreach (['name', 'display_name', 'naam', 'full_name'] as $sessionKey) {
                    $sessionName = trim((string) ($_SESSION['user'][$sessionKey] ?? ''));
                    if ($sessionName !== '' && strtolower($sessionName) !== $email) {
                        return $sessionName;
                    }
                }
            }
        }

        if (function_exists('formatUserDisplayName')) {
            $live = trim(formatUserDisplayName($email));
            if ($live !== '' && strtolower($live) !== $email) {
                return $live;
            }
        }

        if ($stored !== '') {
            return $stored;
        }

        return $email;
    }

    private static function restrictUserPrefsFile(string $email): void
    {
        if (!function_exists('getUserPrefsPath')) {
            return;
        }

        $path = getUserPrefsPath($email);
        if ($path !== null && is_file($path)) {
            @chmod($path, 0640);
        }
    }

    /**
     * Global auth.php identity when the sender is that bot. Personal webhook
     * callbacks carry default_name / default_title on the ephemeral API client
     * so a normal message from the same user does not pick up the bot title.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $apiClient
     * @return array{name: string, title: string}
     */
    public static function resolveDefaultIdentity(string $senderEmail, array $config = [], array $apiClient = []): array
    {
        if ($config === []) {
            global $grokBot;
            $config = is_array($grokBot ?? null) ? $grokBot : [];
        }

        $configuredSender = strtolower(trim((string) ($config['sender_email'] ?? self::DEFAULT_SENDER)));
        $senderEmail = strtolower(trim($senderEmail));
        if ($configuredSender !== '' && $senderEmail !== '' && $senderEmail === $configuredSender) {
            return [
                'name' => trim((string) ($config['default_name'] ?? '')),
                'title' => trim((string) ($config['default_title'] ?? '')),
            ];
        }

        $clientEmail = strtolower(trim((string) ($apiClient['email'] ?? '')));
        $kind = (string) ($apiClient['kind'] ?? '');
        $oid = (string) ($apiClient['oid'] ?? '');
        $isGrokClient = $kind === 'grok_bot_ephemeral' || $oid === 'grok-bot';
        if ($isGrokClient && $clientEmail !== '' && $clientEmail === $senderEmail) {
            $name = trim((string) ($apiClient['default_name'] ?? ''));
            $title = trim((string) ($apiClient['default_title'] ?? ''));
            if ($name !== '' && $title === '') {
                $title = self::USER_DEFAULT_TITLE;
            }
            if ($name !== '' || $title !== '') {
                return [
                    'name' => $name,
                    'title' => $title,
                ];
            }
        }

        return [
            'name' => '',
            'title' => '',
        ];
    }

    public static function apiClientsDirectory(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_clients';
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function isApiClientExpired(array $decoded, ?string $clientFile = null): bool
    {
        $expiresAt = trim((string) ($decoded['expires_at'] ?? ''));
        if ($expiresAt === '') {
            return false;
        }

        $expiresTs = strtotime($expiresAt);
        if ($expiresTs !== false && $expiresTs >= time()) {
            return false;
        }

        if ($clientFile !== null && is_file($clientFile)) {
            @unlink($clientFile);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{api_key: string, expires_at: string}|null
     */
    private static function issueEphemeralApiKey(array $config, int $ticketId): ?array
    {
        $directory = self::apiClientsDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0750, true)) {
            return null;
        }
        if (!is_writable($directory)) {
            return null;
        }

        self::pruneExpiredEphemeralApiKeys($directory);

        $apiKey = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+' . self::EPHEMERAL_KEY_TTL_SECONDS . ' seconds'))->format('c');
        $senderEmail = strtolower(trim((string) ($config['sender_email'] ?? self::DEFAULT_SENDER)));
        if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $senderEmail = self::DEFAULT_SENDER;
        }

        $blob = [
            'oid' => 'grok-bot',
            'api_key' => $apiKey,
            'email' => $senderEmail,
            'is_admin' => true,
            'kind' => 'grok_bot_ephemeral',
            'default_name' => trim((string) ($config['default_name'] ?? '')),
            'default_title' => trim((string) ($config['default_title'] ?? '')),
            'ticket_id' => $ticketId,
            'expires_at' => $expiresAt,
            'created_at' => date('c'),
        ];

        $path = $directory . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json';
        $written = @file_put_contents(
            $path,
            json_encode($blob, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        if ($written === false) {
            return null;
        }

        return [
            'api_key' => $apiKey,
            'expires_at' => $expiresAt,
        ];
    }

    private static function pruneExpiredEphemeralApiKeys(string $directory): void
    {
        foreach ((array) glob($directory . DIRECTORY_SEPARATOR . '*.json') as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($file), true);
            if (!is_array($decoded)) {
                continue;
            }
            if (($decoded['kind'] ?? '') !== 'grok_bot_ephemeral') {
                continue;
            }

            self::isApiClientExpired($decoded, $file);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function postWebhook(string $url, array $payload, string $sendKey = ''): void
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($jsonPayload) || $jsonPayload === '') {
            return;
        }

        if (self::$deliveryOverride !== null) {
            (self::$deliveryOverride)($url, $payload, $sendKey);
            return;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Asclepius-Webhook/1.0',
        ];
        if ($sendKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $sendKey;
        }

        if (function_exists('curl_init')) {
            $curlHandle = curl_init($url);
            if ($curlHandle === false) {
                return;
            }

            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, self::WEBHOOK_TIMEOUT_SECONDS);
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, self::WEBHOOK_TIMEOUT_SECONDS);
            curl_exec($curlHandle);
            curl_close($curlHandle);

            return;
        }

        @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $jsonPayload,
                'timeout' => self::WEBHOOK_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]));
    }
}
