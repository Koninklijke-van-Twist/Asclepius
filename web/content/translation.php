<?php

/**
 * Variables
 */

/** @var array{initialized: bool, provider: TranslationProvider|null}|null $__translationProviderCache */
$__translationProviderCache = null;

/**
 * Functies
 */

function createTranslationProvider(): ?TranslationProvider
{
    global $__translationProviderCache;
    if (is_array($__translationProviderCache)) {
        return $__translationProviderCache['provider'] ?? null;
    }

    $__translationProviderCache = ['initialized' => true, 'provider' => null];

    if (!class_exists('Lara\\Translator') || !class_exists('Lara\\LaraCredentials')) {
        return null;
    }

    global $laraTranslate;
    $accessKeyId    = trim((string) ($laraTranslate['ID'] ?? ''));
    $accessKeySecret = trim((string) ($laraTranslate['Secret'] ?? ''));
    if ($accessKeyId === '' || $accessKeySecret === '') {
        return null;
    }

    try {
        $credentials = new \Lara\LaraCredentials($accessKeyId, $accessKeySecret);
        $client      = new \Lara\Translator($credentials);
        $provider    = new LaraTranslationProvider($client);
        $__translationProviderCache['provider'] = $provider;
        return $provider;
    } catch (Throwable) {
        return null;
    }
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
        $cachedSourceLanguage = (string) ($cached['source_language'] ?? '');
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

    $provider = createTranslationProvider();
    if ($provider === null) {
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
        $result = $provider->translateText($rawText, $targetLanguage);

        $detectedAppLanguage = (string) ($result['source_language'] ?? '');
        $translatedText      = (string) ($result['translated_text'] ?? '');

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
            'translation_error_detail' => '',
        ];
    } catch (Throwable $e) {
        $detail = get_class($e) . '[' . $e->getCode() . ']: ' . $e->getMessage()
            . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
        return [
            'text' => $rawText,
            'is_translated' => false,
            'source_language' => '',
            'target_language' => $targetLanguage,
            'translation_pending' => false,
            'translation_error' => 'provider_error',
            'translation_error_detail' => $detail,
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
    $ticketDetail['title_translation_error_detail'] = (string) ($titleTranslation['translation_error_detail'] ?? '');

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
        $message['translation_error_detail'] = (string) ($messageTranslation['translation_error_detail'] ?? '');
    }
    unset($message);

    $ticketDetail['messages'] = $messages;

    return $ticketDetail;
}
