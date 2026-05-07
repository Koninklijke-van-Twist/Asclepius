<?php

/**
 * Constants
 */

const LARA_LOCALE_BY_APP_LANGUAGE = [
    'nl' => 'nl-NL',
    'en' => 'en-US',
    'de' => 'de-DE',
    'fr' => 'fr-FR',
];

const KEYBOARD_SHORTCUT_TOKEN_PATTERN = '/(\[[A-Za-z0-9_-]{1,24}\])/';

/**
 * Variables
 */

/** @var array<string, mixed>|null $__laraTranslatorCache */
$__laraTranslatorCache = null;

/**
 * Functions
 */

function mapAppLanguageToLaraLocale(string $appLanguage): string
{
    $normalized = strtolower(trim($appLanguage));
    return LARA_LOCALE_BY_APP_LANGUAGE[$normalized] ?? LARA_LOCALE_BY_APP_LANGUAGE['nl'];
}

function mapLaraLanguageToAppLanguage(string $languageCode): string
{
    $normalized = strtolower(trim($languageCode));
    if ($normalized === '') {
        return '';
    }

    $base = preg_split('/[-_]/', $normalized)[0] ?? $normalized;
    $base = strtolower(trim((string) $base));

    if (array_key_exists($base, SUPPORTED_LANGUAGES)) {
        return $base;
    }

    return 'nl';
}

function buildKeyboardAwareTranslationPayload(string $rawText): string|array
{
    if (!class_exists('Lara\\TextBlock')) {
        return $rawText;
    }

    $segments = preg_split(KEYBOARD_SHORTCUT_TOKEN_PATTERN, $rawText, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($segments) || $segments === []) {
        return $rawText;
    }

    $blocks = [];
    foreach ($segments as $segment) {
        if ($segment === '') {
            continue;
        }

        $isToken = preg_match('/^\[[A-Za-z0-9_-]{1,24}\]$/', $segment) === 1;
        $blocks[] = new \Lara\TextBlock($segment, !$isToken);
    }

    return $blocks !== [] ? $blocks : $rawText;
}

function createLaraTranslatorClient(): ?object
{
    global $__laraTranslatorCache;
    if (is_array($__laraTranslatorCache)) {
        return $__laraTranslatorCache['client'] ?? null;
    }

    $__laraTranslatorCache = ['client' => null];

    if (!class_exists('Lara\\Translator') || !class_exists('Lara\\LaraCredentials')) {
        return null;
    }

    global $laraTranslate;
    $accessKeyId = trim((string) ($laraTranslate['ID'] ?? ''));
    $accessKeySecret = trim((string) ($laraTranslate['Secret'] ?? ''));
    if ($accessKeyId === '' || $accessKeySecret === '') {
        return null;
    }

    try {
        $credentials = new \Lara\LaraCredentials($accessKeyId, $accessKeySecret);
        $client = new \Lara\Translator($credentials);
        $__laraTranslatorCache['client'] = $client;
        return $client;
    } catch (Throwable) {
        return null;
    }
}

function normalizeTranslatedText(mixed $translation): string
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

function translateTicketTextForViewer(
    TicketStore $store,
    string $entityType,
    int $entityId,
    int $ticketId,
    string $rawText,
    string $targetAppLanguage,
    bool $cacheOnly = false
): array {
    $targetLanguage = strtolower(trim($targetAppLanguage));
    if (!array_key_exists($targetLanguage, SUPPORTED_LANGUAGES)) {
        $targetLanguage = 'nl';
    }

    if (trim($rawText) === '' || $entityId <= 0 || $ticketId <= 0) {
        return [
            'text' => $rawText,
            'is_translated' => false,
            'source_language' => '',
            'target_language' => $targetLanguage,
            'translation_pending' => false,
            'translation_error' => '',
        ];
    }

    $sourceHash = hash('sha256', $rawText);
    $cached = $store->getTextTranslation($entityType, $entityId, $targetLanguage, $sourceHash);
    if (is_array($cached)) {
        $cachedSourceLanguage = mapLaraLanguageToAppLanguage((string) ($cached['source_language'] ?? ''));
        $cachedText = (string) ($cached['translated_text'] ?? $rawText);
        return [
            'text' => $cachedText,
            'is_translated' => $cachedSourceLanguage !== '' && $cachedSourceLanguage !== $targetLanguage,
            'source_language' => $cachedSourceLanguage,
            'target_language' => $targetLanguage,
            'translation_pending' => false,
            'translation_error' => '',
        ];
    }

    if ($cacheOnly) {
        return [
            'text' => $rawText,
            'is_translated' => false,
            'source_language' => '',
            'target_language' => $targetLanguage,
            'translation_pending' => true,
            'translation_error' => '',
        ];
    }

    $translator = createLaraTranslatorClient();
    if ($translator === null) {
        return [
            'text' => $rawText,
            'is_translated' => false,
            'source_language' => '',
            'target_language' => $targetLanguage,
            'translation_pending' => false,
            'translation_error' => 'translator_unavailable',
        ];
    }

    try {
        $payload = buildKeyboardAwareTranslationPayload($rawText);
        $result = $translator->translate(
            $payload,
            null,
            mapAppLanguageToLaraLocale($targetLanguage)
        );

        $detectedAppLanguage = method_exists($result, 'getSourceLanguage')
            ? mapLaraLanguageToAppLanguage((string) $result->getSourceLanguage())
            : '';

        $translatedText = method_exists($result, 'getTranslation')
            ? normalizeTranslatedText($result->getTranslation())
            : '';

        if ($detectedAppLanguage !== '' && $detectedAppLanguage === $targetLanguage) {
            $translatedText = $rawText;
        }

        if ($translatedText === '') {
            $translatedText = $rawText;
        }

        $store->upsertTextTranslation(
            $entityType,
            $entityId,
            $ticketId,
            $targetLanguage,
            $detectedAppLanguage,
            $sourceHash,
            $translatedText
        );

        return [
            'text' => $translatedText,
            'is_translated' => $detectedAppLanguage !== '' && $detectedAppLanguage !== $targetLanguage,
            'source_language' => $detectedAppLanguage,
            'target_language' => $targetLanguage,
            'translation_pending' => false,
            'translation_error' => '',
        ];
    } catch (Throwable $e) {
        return [
            'text' => $rawText,
            'is_translated' => false,
            'source_language' => '',
            'target_language' => $targetLanguage,
            'translation_pending' => false,
            'translation_error' => 'provider_error',
        ];
     }
 }

function localizeTicketForViewer(array $ticket, TicketStore $store, string $viewerLanguage, bool $cacheOnly = false): array
{
    $ticketId = (int) ($ticket['id'] ?? 0);
    $rawTitle = (string) ($ticket['title'] ?? '');
    $translation = translateTicketTextForViewer(
        $store,
        'ticket_title',
        $ticketId,
        $ticketId,
        $rawTitle,
        $viewerLanguage,
        $cacheOnly
    );

    $ticket['title_raw'] = $rawTitle;
    $ticket['title'] = (string) ($translation['text'] ?? $rawTitle);
    $ticket['title_is_translated'] = !empty($translation['is_translated']);
    $ticket['title_translation_pending'] = !empty($translation['translation_pending']);
    $ticket['title_translation_error'] = (string) ($translation['translation_error'] ?? '');

    return $ticket;
}

function localizeTicketDetailForViewer(array $ticketDetail, TicketStore $store, string $viewerLanguage, bool $cacheOnly = false): array
{
    $ticketId = (int) ($ticketDetail['id'] ?? 0);
    $rawTitle = (string) ($ticketDetail['title'] ?? '');
    $titleTranslation = translateTicketTextForViewer(
        $store,
        'ticket_title',
        $ticketId,
        $ticketId,
        $rawTitle,
        $viewerLanguage,
        $cacheOnly
    );

    $ticketDetail['title_raw'] = $rawTitle;
    $ticketDetail['title'] = (string) ($titleTranslation['text'] ?? $rawTitle);
    $ticketDetail['title_is_translated'] = !empty($titleTranslation['is_translated']);
    $ticketDetail['title_translation_pending'] = !empty($titleTranslation['translation_pending']);
    $ticketDetail['title_translation_error'] = (string) ($titleTranslation['translation_error'] ?? '');

    $messages = is_array($ticketDetail['messages'] ?? null) ? $ticketDetail['messages'] : [];
    foreach ($messages as &$message) {
        $messageId = (int) ($message['id'] ?? 0);
        $rawMessageText = (string) ($message['message_text'] ?? '');
        $messageTranslation = translateTicketTextForViewer(
            $store,
            'ticket_message',
            $messageId,
            $ticketId,
            $rawMessageText,
            $viewerLanguage,
            $cacheOnly
        );

        $message['message_text_raw'] = $rawMessageText;
        $message['message_text'] = (string) ($messageTranslation['text'] ?? $rawMessageText);
        $message['message_is_translated'] = !empty($messageTranslation['is_translated']);
        $message['translation_pending'] = !empty($messageTranslation['translation_pending']);
        $message['translation_error'] = (string) ($messageTranslation['translation_error'] ?? '');
    }
    unset($message);

    $ticketDetail['messages'] = $messages;

    return $ticketDetail;
}
