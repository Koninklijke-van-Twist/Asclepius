<?php

class CopilotTranslationProvider implements TranslationProvider
{
    /**
     * Constants
     */

    /** Seconden. De flow antwoordt synchroon en kan tientallen seconden duren. */
    private const DEFAULT_TIMEOUT_SECONDS = 25.0;

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

        $translations = $this->translationsFor($rawText);

        return [
            'translated_text' => (string) ($translations[$targetLanguage] ?? ''),
            'source_language' => $this->sourceLanguageFromTranslations($rawText, $targetLanguage, $translations),
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
        $payload = [
            'id' => $id,
            'text' => $masked['text'],
            'languages' => CPT_DEFAULT_LANGUAGES,
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

        $parsed = cpt_parse_translations($body, CPT_DEFAULT_LANGUAGES);
        if ($parsed === null) {
            throw new RuntimeException(
                'HTTP ' . $status . ' onleesbaar antwoord: ' . substr($body, 0, 200),
                $status
            );
        }

        $translations = [];
        foreach ($parsed['translations'] as $language => $translated) {
            $translations[(string) $language] = $this->unmaskKeyboardTokens((string) $translated, $masked['tokens']);
        }

        return $translations;
    }

    /**
     * De flow levert ook `source`, maar die is soms fout. Die waarde wordt niet gelezen.
     * Alleen een vertaling die (op witruimte na) gelijk is aan de oorspronkelijke tekst
     * telt als brontaal, zodat de bestaande weergaveregels het origineel kunnen houden.
     *
     * @param array<string, string> $translations
     */
    private function sourceLanguageFromTranslations(string $rawText, string $targetLanguage, array $translations): string
    {
        $normalizedRaw = $this->comparableText($rawText);
        $targetText = (string) ($translations[$targetLanguage] ?? '');
        if ($normalizedRaw !== '' && $this->comparableText($targetText) === $normalizedRaw) {
            return $targetLanguage;
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
            if ($normalizedRaw !== '' && $this->comparableText($translated) === $normalizedRaw) {
                $matches[] = $language;
            }
        }

        $matches = array_values(array_unique($matches));
        if (count($matches) === 1) {
            return $matches[0];
        }

        if ($normalizedRaw === '' || $targetText === '') {
            return '';
        }

        // Geen exacte treffer. De taal die het meest op het origineel lijkt (ook de doeltaal)
        // houdt de bestaande regel in stand: het origineel als de tekst al in de leestaal
        // staat, anders de vertaling met "Toon origineel".
        $bestLanguage = '';
        $bestScore = 0.0;
        foreach ($translations as $language => $translated) {
            if (!is_string($translated)) {
                continue;
            }
            $language = strtolower(trim((string) $language));
            if ($language === '' || !array_key_exists($language, SUPPORTED_LANGUAGES)) {
                continue;
            }
            $score = $this->tokenOverlap($normalizedRaw, $this->comparableText($translated));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLanguage = $language;
            }
        }

        return $bestLanguage;
    }

    private function tokenOverlap(string $left, string $right): float
    {
        $leftTokens = $this->wordTokens($left);
        $rightTokens = $this->wordTokens($right);
        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $intersection = 0;
        $rightCounts = array_count_values($rightTokens);
        foreach (array_count_values($leftTokens) as $token => $count) {
            $intersection += min($count, (int) ($rightCounts[$token] ?? 0));
        }
        $union = count($leftTokens) + count($rightTokens) - $intersection;

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * @return list<string>
     */
    private function wordTokens(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text));
        if (!is_array($parts)) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $part) {
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens;
    }

    private function comparableText(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($text));

        return is_string($collapsed) ? $collapsed : trim($text);
    }

    /**
     * @return array{text: string, tokens: list<string>}
     */
    private function maskKeyboardTokens(string $rawText): array
    {
        $tokens = [];
        $masked = preg_replace_callback(
            self::KEYBOARD_TOKEN_PATTERN,
            static function (array $match) use (&$tokens): string {
                $placeholder = '⟦kb' . count($tokens) . '⟧';
                $tokens[] = $match[0];

                return $placeholder;
            },
            $rawText
        );

        return [
            'text' => is_string($masked) ? $masked : $rawText,
            'tokens' => $tokens,
        ];
    }

    /**
     * @param list<string> $tokens
     */
    private function unmaskKeyboardTokens(string $text, array $tokens): string
    {
        foreach ($tokens as $index => $token) {
            $text = str_replace('⟦kb' . $index . '⟧', $token, $text);
        }

        return $text;
    }

    private function requestTimeoutSeconds(): float
    {
        $timeout = $this->timeoutSeconds;
        $maxExecution = (int) ini_get('max_execution_time');
        if ($maxExecution > 5) {
            $timeout = min($timeout, (float) ($maxExecution - 5));
        }

        return max(1.0, $timeout);
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
