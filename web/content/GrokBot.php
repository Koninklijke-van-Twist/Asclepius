<?php

class GrokBot
{
    public const EVENT_NEW_TICKET = 'new-ticket';
    public const EVENT_TICKET_SOLVED = 'ticket-solved';

    private const DEFAULT_SENDER = 'grok-bot@kvt.nl';
    private const WEBHOOK_TIMEOUT_SECONDS = 5;
    private const EPHEMERAL_KEY_TTL_SECONDS = 3600;

    /**
     * @param array<string, mixed>|null $config
     */
    public static function notifyTicketCreated(TicketStore $store, int $ticketId, ?array $config = null): void
    {
        self::notifyTicketEvent($ticketId, self::EVENT_NEW_TICKET, $config);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public static function notifyTicketSolved(TicketStore $store, int $ticketId, ?array $config = null): void
    {
        self::notifyTicketEvent($ticketId, self::EVENT_TICKET_SOLVED, $config);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private static function notifyTicketEvent(int $ticketId, string $type, ?array $config = null): void {
        if ($ticketId <= 0) {
            return;
        }

        if ($config === null) {
            global $grokBot;
            $config = is_array($grokBot ?? null) ? $grokBot : [];
        }

        if (empty($config['enabled'])) {
            return;
        }

        $webhookUrl = trim((string) ($config['webhook_url'] ?? ''));
        if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return;
        }

        $issued = self::issueEphemeralApiKey($config, $ticketId);
        if ($issued === null) {
            return;
        }

        $sendKey = trim((string) ($config['send_key'] ?? ($config['webhook_secret'] ?? '')));
        self::postWebhook($webhookUrl, [
            'type' => $type,
            'ticket_id' => $ticketId,
            'api_key' => $issued['api_key'],
        ], $sendKey);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function resolveDefaultIdentity(string $senderEmail, array $config = []): array
    {
        if ($config === []) {
            global $grokBot;
            $config = is_array($grokBot ?? null) ? $grokBot : [];
        }

        $configuredSender = strtolower(trim((string) ($config['sender_email'] ?? self::DEFAULT_SENDER)));
        $senderEmail = strtolower(trim($senderEmail));
        if ($configuredSender === '' || $senderEmail === '' || $senderEmail !== $configuredSender) {
            return [
                'name' => '',
                'title' => '',
            ];
        }

        return [
            'name' => trim((string) ($config['default_name'] ?? '')),
            'title' => trim((string) ($config['default_title'] ?? '')),
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
