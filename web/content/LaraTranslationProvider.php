<?php

class LaraTranslationProvider implements TranslationProvider
{
    /**
     * Constants
     */

    private const LOCALE_BY_APP_LANGUAGE = [
        'nl' => 'nl-NL',
        'en' => 'en-US',
        'de' => 'de-DE',
        'fr' => 'fr-FR',
    ];

    private const KEYBOARD_TOKEN_PATTERN = '/(\[[A-Za-z0-9_-]{1,24}\])/';

    /**
     * Variabelen
     */

    private object $client;

    /**
     * Functies
     */

    public function __construct(object $client)
    {
        $this->client = $client;
    }

    public function translateText(string $rawText, string $targetAppLanguage): array
    {
        $targetLocale = self::LOCALE_BY_APP_LANGUAGE[strtolower(trim($targetAppLanguage))]
            ?? self::LOCALE_BY_APP_LANGUAGE['nl'];

        $payload = $this->buildPayload($rawText);
        $result  = $this->client->translate($payload, null, $targetLocale);

        $sourceLocale = method_exists($result, 'getSourceLanguage')
            ? (string) $result->getSourceLanguage()
            : '';

        $translatedText = method_exists($result, 'getTranslation')
            ? $this->normalizeTranslation($result->getTranslation())
            : '';

        return [
            'translated_text' => $translatedText,
            'source_language' => $this->mapLocaleToAppLanguage($sourceLocale),
        ];
    }

    private function buildPayload(string $rawText): string|array
    {
        if (!class_exists('Lara\\TextBlock')) {
            return $rawText;
        }

        $segments = preg_split(self::KEYBOARD_TOKEN_PATTERN, $rawText, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($segments) || $segments === []) {
            return $rawText;
        }

        $blocks = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $isToken  = preg_match('/^\[[A-Za-z0-9_-]{1,24}\]$/', $segment) === 1;
            $blocks[] = new \Lara\TextBlock($segment, !$isToken);
        }

        return $blocks !== [] ? $blocks : $rawText;
    }

    private function normalizeTranslation(mixed $translation): string
    {
        if (is_string($translation)) {
            return $translation;
        }

        if (!is_array($translation)) {
            return '';
        }

        $parts = [];
        foreach ($translation as $part) {
            if (is_string($part)) {
                $parts[] = $part;
                continue;
            }
            if (is_object($part)) {
                if (method_exists($part, 'getText')) {
                    $parts[] = (string) $part->getText();
                    continue;
                }
                if (method_exists($part, '__toString')) {
                    $parts[] = (string) $part;
                }
            }
        }

        return implode('', $parts);
    }

    private function mapLocaleToAppLanguage(string $locale): string
    {
        $normalized = strtolower(trim($locale));
        if ($normalized === '') {
            return '';
        }

        $base = strtolower(trim((string) (preg_split('/[-_]/', $normalized)[0] ?? $normalized)));
        if (array_key_exists($base, SUPPORTED_LANGUAGES)) {
            return $base;
        }

        return 'nl';
    }
}
