<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'message_markdown.php';

class CopilotTranslationProvider implements TranslationProvider
{
    /**
     * Constants
     */

    /** Seconden. Langer wachten is in orde; de flow kan een paar minuten doen over één tekst. */
    private const DEFAULT_TIMEOUT_SECONDS = 120.0;

    /** Ruimte die overblijft om het HTTP-antwoord nog weg te schrijven. */
    private const TIMEOUT_HEADROOM_SECONDS = 5.0;

    /** Korter dan dit starten we geen call meer: PHP zou het verzoek afbreken. */
    private const MIN_CALL_TIMEOUT_SECONDS = 1.0;

    /** translateTicketTextForViewer laat deze tekst pending, zonder opslag. */
    public const BUDGET_EXHAUSTED = 'copilot_budget_exhausted';

    /** Zelfde patroon als Lara: toetsnamen zoals [Enter] niet laten vertalen. */
    private const KEYBOARD_TOKEN_PATTERN = '/(\[[A-Za-z0-9_-]{1,24}\])/';

    /**
     * Variabelen
     */

    private string $flowUrl;

    private float $timeoutSeconds;

    /** @var callable(string, string, float): array{status:int, body:string, error:string} */
    private $transport;

    /**
     * Eén flow-run per tekst (sha256), ook als een tweede doeltaal dezelfde tekst vraagt.
     *
     * @var array<string, array{ok: bool, translations?: array<string, string>, message?: string, code?: int}>
     */
    private array $memo = [];

    /** @var callable|null */
    private static $transportOverride = null;

    /**
     * Functies
     */

    public function __construct(string $flowUrl, ?float $timeoutSeconds = null, ?callable $transport = null)
    {
        $this->flowUrl = $flowUrl;
        if ($timeoutSeconds !== null && $timeoutSeconds > 0) {
            $this->timeoutSeconds = $timeoutSeconds;
        } else {
            $this->timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS;
        }

        if ($transport !== null) {
            $this->transport = $transport;
        } elseif (self::$transportOverride !== null) {
            $this->transport = self::$transportOverride;
        } else {
            $this->transport = function (string $url, string $jsonBody, float $timeout): array {
                return $this->postJson($url, $jsonBody, $timeout);
            };
        }
    }

    public static function useTransportForTests(?callable $transport): void
    {
        self::$transportOverride = $transport;
    }

    public function translateText(string $rawText, string $targetAppLanguage): array
    {
        $targetLanguage = strtolower(trim($targetAppLanguage));
        if (!array_key_exists($targetLanguage, SUPPORTED_LANGUAGES)) {
            $targetLanguage = 'nl';
        }

        $bundle = $this->translateBundle($rawText, $targetLanguage);

        return [
            'translated_text' => (string) ($bundle['translations'][$targetLanguage] ?? ''),
            'source_language' => $bundle['source_language'],
        ];
    }

    /**
     * Eén flow-antwoord bevat alle gevraagde talen. De aanroeper bewaart ze allemaal.
     *
     * @return array{translations: array<string, string>, source_language: string, target_language: string}
     */
    public function translateBundle(string $rawText, string $targetAppLanguage): array
    {
        $targetLanguage = strtolower(trim($targetAppLanguage));
        if (!array_key_exists($targetLanguage, SUPPORTED_LANGUAGES)) {
            $targetLanguage = 'nl';
        }

        $translations = $this->translationsFor($rawText);

        return [
            'translations' => $translations,
            'source_language' => $this->sourceLanguageFromTranslations($rawText, $translations),
            'target_language' => $targetLanguage,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function translationsFor(string $rawText): array
    {
        $hash = hash('sha256', $rawText);
        if (isset($this->memo[$hash])) {
            return $this->cachedTranslations($this->memo[$hash]);
        }

        try {
            $translations = $this->requestTranslations($rawText, $hash);
        } catch (RuntimeException $exception) {
            $this->memo[$hash] = [
                'ok' => false,
                'message' => $exception->getMessage(),
                'code' => (int) $exception->getCode(),
            ];
            throw $exception;
        }

        $this->memo[$hash] = [
            'ok' => true,
            'translations' => $translations,
        ];

        return $translations;
    }

    /**
     * @param array{ok: bool, translations?: array<string, string>, message?: string, code?: int} $cached
     * @return array<string, string>
     */
    private function cachedTranslations(array $cached): array
    {
        if (empty($cached['ok'])) {
            throw new RuntimeException((string) ($cached['message'] ?? 'provider_error'), (int) ($cached['code'] ?? 0));
        }

        return is_array($cached['translations'] ?? null) ? $cached['translations'] : [];
    }

    /**
     * @return array<string, string>
     */
    private function requestTranslations(string $rawText, string $id): array
    {
        $masked = $this->maskKeyboardTokens($rawText);
        $languages = cpt_request_languages();
        $payload = [
            'id' => $id,
            'text' => $masked['text'],
            'languages' => $languages,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('payload niet te coderen', 0);
        }

        $response = ($this->transport)($this->flowUrl, $json, $this->requestTimeoutSeconds());
        if (!is_array($response)) {
            throw new RuntimeException('cURL: ongeldig transport', 0);
        }

        $status = (int) ($response['status'] ?? 0);
        $body = is_string($response['body'] ?? null) ? $response['body'] : '';
        $curlError = is_string($response['error'] ?? null) ? $response['error'] : '';
        if ($status < 200 || $status >= 300) {
            $message = $status === 0
                ? 'cURL: ' . ($curlError !== '' ? $curlError : 'timeout')
                : 'HTTP ' . $status . ': ' . substr($body, 0, 200);
            throw new RuntimeException($message, $status);
        }

        $parsed = cpt_parse_translations($body, $languages);
        if ($parsed === null) {
            throw new RuntimeException(
                'HTTP ' . $status . ' onleesbaar antwoord: ' . substr($body, 0, 200),
                $status
            );
        }

        $translations = [];
        $attachmentTokens = is_array($masked['attachment_tokens'] ?? null) ? $masked['attachment_tokens'] : [];
        foreach ($parsed['translations'] as $language => $translated) {
            $unmasked = $this->unmaskKeyboardTokens((string) $translated, $masked['tokens'], $attachmentTokens);
            if (function_exists('restoreMissingAttachmentMarkers')) {
                $unmasked = restoreMissingAttachmentMarkers($unmasked, $rawText);
            }
            $translations[(string) $language] = $unmasked;
        }

        return $translations;
    }

    /**
     * De flow levert ook `source`, maar die is soms fout. Die waarde wordt niet gelezen.
     * Alleen als precies één taal exact gelijk is aan de brontekst, is dat de brontaal.
     * De gevraagde leestaal speelt geen rol: een prefetch naar nl mag de brontaal niet worden.
     *
     * @param array<string, string> $translations
     */
    private function sourceLanguageFromTranslations(string $rawText, array $translations): string
    {
        $normalizedRaw = $this->comparableText($rawText);
        if ($normalizedRaw === '') {
            return '';
        }

        $matches = [];
        foreach ($translations as $language => $translated) {
            if (!is_string($translated)) {
                continue;
            }
            $language = strtolower(trim((string) $language));
            if ($language === '' || !array_key_exists($language, SUPPORTED_LANGUAGES)) {
                continue;
            }
            if ($this->comparableText($translated) === $normalizedRaw) {
                $matches[] = $language;
            }
        }

        $matches = array_values(array_unique($matches));
        if (count($matches) === 1) {
            return $matches[0];
        }

        return '';
    }

    private function comparableText(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($text));

        return is_string($collapsed) ? $collapsed : trim($text);
    }

    /**
     * @return array{text: string, tokens: list<string>}
     */
    /**
     * @return array{text: string, tokens: list<string>, attachment_tokens: list<string>}
     */
    private function maskKeyboardTokens(string $rawText): array
    {
        $attachments = function_exists('maskMessageAttachmentMarkers')
            ? maskMessageAttachmentMarkers($rawText)
            : ['text' => $rawText, 'tokens' => []];
        $tokens = [];
        $masked = preg_replace_callback(
            self::KEYBOARD_TOKEN_PATTERN,
            static function (array $match) use (&$tokens): string {
                $placeholder = '⟦kb' . count($tokens) . '⟧';
                $tokens[] = $match[0];

                return $placeholder;
            },
            (string) ($attachments['text'] ?? $rawText)
        );

        return [
            'text' => is_string($masked) ? $masked : (string) ($attachments['text'] ?? $rawText),
            'tokens' => $tokens,
            'attachment_tokens' => is_array($attachments['tokens'] ?? null) ? $attachments['tokens'] : [],
        ];
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $attachmentTokens
     */
    private function unmaskKeyboardTokens(string $text, array $tokens, array $attachmentTokens = []): string
    {
        foreach ($tokens as $index => $token) {
            $text = str_replace('⟦kb' . $index . '⟧', $token, $text);
        }
        if ($attachmentTokens !== [] && function_exists('unmaskMessageAttachmentMarkers')) {
            $text = unmaskMessageAttachmentMarkers($text, $attachmentTokens);
        }

        return $text;
    }

    private function requestTimeoutSeconds(): float
    {
        $timeout = $this->timeoutSeconds;
        $maxExecution = (int) ini_get('max_execution_time');
        if ($maxExecution > 0) {
            $startedAt = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
            $elapsed = is_numeric($startedAt) ? max(0.0, microtime(true) - (float) $startedAt) : 0.0;
            $remaining = (float) $maxExecution - $elapsed - self::TIMEOUT_HEADROOM_SECONDS;
            $timeout = min($timeout, $remaining);
        }

        if ($timeout < self::MIN_CALL_TIMEOUT_SECONDS) {
            throw new RuntimeException(self::BUDGET_EXHAUSTED, 0);
        }

        return $timeout;
    }

    /**
     * @return array{status:int, body:string, error:string}
     */
    private function postJson(string $url, string $jsonBody, float $timeoutSeconds): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'error' => 'curl ontbreekt'];
        }

        $timeoutMs = (int) max(200, $timeoutSeconds * 1000);
        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            return ['status' => 0, 'body' => '', 'error' => 'curl_init mislukt'];
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => (int) min(30000, $timeoutMs),
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_NOSIGNAL => true,
        ]);
        $body = curl_exec($curlHandle);
        $status = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curlHandle);
        curl_close($curlHandle);

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => $curlError,
        ];
    }
}
