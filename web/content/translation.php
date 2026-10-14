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

    $copilotProvider = createCopilotTranslationProvider();
    if ($copilotProvider !== null) {
        $__translationProviderCache['provider'] = $copilotProvider;

        return $copilotProvider;
    }

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

function createCopilotTranslationProvider(): ?CopilotTranslationProvider
{
    global $copilotTranslate;
    if (!isset($copilotTranslate) || !is_array($copilotTranslate)) {
        return null;
    }

    $flowUrl = trim((string) ($copilotTranslate['flowUrl'] ?? ''));
    if (preg_match('~^https://~i', $flowUrl) !== 1) {
        return null;
    }

    $timeoutSeconds = null;
    if (isset($copilotTranslate['timeout']) && is_numeric($copilotTranslate['timeout'])) {
        $timeoutSeconds = (float) $copilotTranslate['timeout'];
    }

    return new CopilotTranslationProvider($flowUrl, $timeoutSeconds);
}

function translateTicketTextForViewer(
    TicketStore $store,
    string $entityType,
    int $entityId,
    int $ticketId,
    string $rawText,
    string $targetAppLanguage,
    bool $cacheOnly = false,
    bool $waitForInFlight = false,
    ?float $inFlightWaitSeconds = null
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

    if (function_exists('ticketMessageIsStatusNoteOnly') && ticketMessageIsStatusNoteOnly($rawText)) {
        $text = ticketTranslationDisplayText($rawText, '', $targetLanguage);

        return [
            'text' => $text,
            'is_translated' => !ticketTranslationTextsMatch($text, $rawText),
            'source_language' => '',
            'target_language' => $targetLanguage,
            'translation_pending' => false,
            'translation_error' => '',
        ];
    }

    $sourceHash = hash('sha256', $rawText);
    $cached = $store->getTextTranslation($entityType, $entityId, $targetLanguage, $sourceHash);
    if (is_array($cached)) {
        return ticketTranslationFromCacheRow($store, $entityType, $entityId, $sourceHash, $rawText, $targetLanguage, $cached);
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

    if (!$store->translationSourceMatches($entityType, $entityId, $rawText)) {
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

    $claimToken = $store->tryClaimTextTranslation($entityType, $entityId, $sourceHash, ticketTranslationClaimTtlSeconds());
    if ($claimToken === null && $waitForInFlight) {
        $claimToken = waitOrTakeOverTextTranslationClaim(
            $store,
            $entityType,
            $entityId,
            $sourceHash,
            $rawText,
            $targetLanguage,
            $inFlightWaitSeconds
        );
        if (is_array($claimToken)) {
            return $claimToken;
        }
    }
    if ($claimToken === null) {
        return [
            'text' => $rawText,
            'is_translated' => false,
            'source_language' => '',
            'target_language' => $targetLanguage,
            'translation_pending' => true,
            'translation_error' => '',
        ];
    }

    try {
        $cachedAfterClaim = $store->getTextTranslation($entityType, $entityId, $targetLanguage, $sourceHash);
        if (is_array($cachedAfterClaim)) {
            return ticketTranslationFromCacheRow($store, $entityType, $entityId, $sourceHash, $rawText, $targetLanguage, $cachedAfterClaim);
        }

        $stored = $provider instanceof CopilotTranslationProvider
            ? storeCopilotTicketTranslations($store, $provider, $entityType, $entityId, $ticketId, $rawText, $sourceHash, $claimToken, $targetLanguage)
            : storeSingleTicketTranslation($store, $provider, $entityType, $entityId, $ticketId, $rawText, $sourceHash, $claimToken, $targetLanguage);
        if ($stored === null) {
            return [
                'text' => $rawText,
                'is_translated' => false,
                'source_language' => '',
                'target_language' => $targetLanguage,
                'translation_pending' => true,
                'translation_error' => '',
            ];
        }

        return $stored;
    } catch (Throwable $e) {
        if ($e instanceof RuntimeException && $e->getMessage() === CopilotTranslationProvider::BUDGET_EXHAUSTED) {
            return [
                'text' => $rawText,
                'is_translated' => false,
                'source_language' => '',
                'target_language' => $targetLanguage,
                'translation_pending' => true,
                'translation_error' => '',
            ];
        }

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
    } finally {
        $store->releaseTextTranslationClaim($entityType, $entityId, $claimToken);
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

function localizeTicketDetailForViewer(array $ticketDetail, TicketStore $store, string $viewerLanguage, bool $cacheOnly = false, bool $waitForInFlight = false): array
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
        $cacheOnly,
        $waitForInFlight
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
            $cacheOnly,
            $waitForInFlight
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

/**
 * Talen die in één Copilot-verzoek meegaan en die Lara per stuk afwerkt.
 *
 * @return list<string>
 */
function ticketTranslationLanguages(): array
{
    return cpt_request_languages();
}

function ticketTranslationFlowTimeoutSeconds(): float
{
    global $copilotTranslate;
    if (isset($copilotTranslate['timeout']) && is_numeric($copilotTranslate['timeout']) && (float) $copilotTranslate['timeout'] > 0) {
        return (float) $copilotTranslate['timeout'];
    }

    return 120.0;
}

function ticketTranslationTimeLimitSeconds(): int
{
    return (int) ceil(ticketTranslationFlowTimeoutSeconds()) + 30;
}

/** Verse claim blijft zo lang gelden; daarna mag een volgende aanvraag het overnemen. */
function ticketTranslationClaimTtlSeconds(): int
{
    return (int) ceil(ticketTranslationFlowTimeoutSeconds()) + 60;
}

/**
 * De lijst keert meteen terug als een andere aanvraag de tekst heeft.
 * Het geopende ticket wacht kort op die aanvraag en neemt een stilgevallen claim over.
 */
function ticketTranslationInteractiveClaimStaleSeconds(): int
{
    return 12;
}

function ticketTranslationInFlightWaitSeconds(): float
{
    return 8.0;
}

/**
 * @return array{text: string, is_translated: bool, source_language: string, target_language: string, translation_pending: bool, translation_error: string, translation_error_detail: string}|string|null
 *         string = claim-token om zelf te vertalen, array = cache die tijdens het wachten binnenkwam, null = nog bezet
 */
function waitOrTakeOverTextTranslationClaim(
    TicketStore $store,
    string $entityType,
    int $entityId,
    string $sourceHash,
    string $rawText,
    string $targetLanguage,
    ?float $waitSeconds = null
): array|string|null {
    $existing = $store->getTextTranslationClaim($entityType, $entityId);
    $existingHash = is_array($existing) ? (string) ($existing['source_hash'] ?? '') : '';
    $sameText = $existingHash !== ''
        && strlen($existingHash) === strlen($sourceHash)
        && hash_equals($existingHash, $sourceHash);
    $age = $sameText ? time() - (int) ($existing['claimed_at'] ?? time()) : 0;
    $staleSeconds = ticketTranslationInteractiveClaimStaleSeconds();
    // tryClaim neemt over bij claimed_at < now - stale, dus één seconde korter zodat 12 seconden al geldt.
    $takeoverAfter = max(0, $staleSeconds - 1);
    if ($sameText && $age >= $staleSeconds) {
        return $store->tryClaimTextTranslation($entityType, $entityId, $sourceHash, $takeoverAfter);
    }

    $deadline = microtime(true) + max(0.0, $waitSeconds ?? ticketTranslationInFlightWaitSeconds());
    while (microtime(true) < $deadline) {
        $cached = $store->getTextTranslation($entityType, $entityId, $targetLanguage, $sourceHash);
        if (is_array($cached)) {
            return ticketTranslationFromCacheRow($store, $entityType, $entityId, $sourceHash, $rawText, $targetLanguage, $cached);
        }

        $slice = $deadline - microtime(true);
        if ($slice <= 0) {
            break;
        }
        usleep((int) (min(0.25, $slice) * 1000000));
    }

    return $store->tryClaimTextTranslation($entityType, $entityId, $sourceHash, $takeoverAfter);
}

function ticketTranslationsCoverAllLanguages(TicketStore $store, string $entityType, int $entityId, string $sourceHash): bool
{
    $languages = ticketTranslationLanguages();
    if ($languages === []) {
        return false;
    }

    foreach ($languages as $language) {
        if ($store->getTextTranslation($entityType, $entityId, $language, $sourceHash) === null) {
            return false;
        }
    }

    return true;
}

/**
 * @return array{text: string, is_translated: bool, source_language: string, target_language: string, translation_pending: bool, translation_error: string, translation_error_detail: string}
 */
function ticketTranslationTextsMatch(string $left, string $right): bool
{
    $normalize = static function (string $text): string {
        $collapsed = preg_replace('/\s+/u', ' ', trim($text));

        return is_string($collapsed) ? $collapsed : trim($text);
    };
    $normalizedLeft = $normalize($left);

    return $normalizedLeft !== '' && $normalizedLeft === $normalize($right);
}

function ticketTranslationIsTranslated(string $sourceLanguage, string $targetLanguage, string $storedText, string $rawText): bool
{
    if ($sourceLanguage === '' || $sourceLanguage === $targetLanguage) {
        return false;
    }

    return !ticketTranslationTextsMatch($storedText, $rawText);
}

function ticketTranslationStoredResult(string $rawText, string $targetLanguage, string $storedText, string $sourceLanguage): array
{
    $sourceLanguage = strtolower(trim($sourceLanguage));
    $readingSource = $sourceLanguage !== '' && $sourceLanguage === $targetLanguage;
    $sameText = ticketTranslationTextsMatch($storedText, $rawText);
    $text = ($readingSource || $sameText || $storedText === '') ? $rawText : $storedText;
    $isTranslated = ticketTranslationIsTranslated($sourceLanguage, $targetLanguage, $text, $rawText);

    return [
        'text' => $text,
        'is_translated' => $isTranslated,
        'source_language' => ($isTranslated || $readingSource) ? $sourceLanguage : '',
        'target_language' => $targetLanguage,
        'translation_pending' => false,
        'translation_error' => '',
        'translation_error_detail' => '',
    ];
}

/**
 * Cache-treffer. De brontaal wordt opnieuw bepaald uit de opgeslagen teksten:
 * precies één taal die gelijk is aan het origineel. Een stempel 'nl' van een
 * prefetch telt niet mee. De lezer in die brontaal krijgt het origineel.
 *
 * @param array<string, mixed> $cached
 * @return array{text: string, is_translated: bool, source_language: string, target_language: string, translation_pending: bool, translation_error: string, translation_error_detail: string}
 */
function ticketTranslationFromCacheRow(
    TicketStore $store,
    string $entityType,
    int $entityId,
    string $sourceHash,
    string $rawText,
    string $targetLanguage,
    array $cached
): array {
    $storedText = (string) ($cached['translated_text'] ?? '');
    $storedSource = (string) ($cached['source_language'] ?? '');
    $sourceLanguage = ticketTranslationSourceLanguage($store, $entityType, $entityId, $rawText, $storedSource);

    return ticketTranslationApplyStatusNotes(
        ticketTranslationStoredResult($rawText, $targetLanguage, $storedText, $sourceLanguage),
        $rawText,
        $targetLanguage
    );
}

function ticketTranslationApplyStatusNotes(array $result, string $rawText, string $targetLanguage): array
{
    if (!function_exists('ticketStatusNotesInText') || ticketStatusNotesInText($rawText) === []) {
        return $result;
    }

    $text = ticketTranslationDisplayText($rawText, (string) ($result['text'] ?? ''), $targetLanguage);
    $result['text'] = $text;
    if (!ticketTranslationTextsMatch($text, $rawText)) {
        $result['is_translated'] = true;
    }

    return $result;
}

function ticketTranslationProviderText(string $rawText): string
{
    if (!function_exists('ticketStatusNotesInText') || ticketStatusNotesInText($rawText) === []) {
        return $rawText;
    }

    $body = ticketTextWithoutStatusNotes($rawText);

    return trim($body) === '' ? $rawText : $body;
}

/**
 * Brontaal van dit bericht: de enige taal waarvan de opgeslagen tekst het origineel is.
 * Wijkt de opgeslagen stempel daarvan af, dan wordt die stempel gecorrigeerd.
 */
function ticketTranslationSourceLanguage(
    TicketStore $store,
    string $entityType,
    int $entityId,
    string $rawText,
    string $fallback = ''
): string {
    $sourceHash = hash('sha256', $rawText);
    $rows = $store->listTextTranslations($entityType, $entityId, $sourceHash);
    $detected = ticketTranslationSourceFromStoredRows($rows, $rawText);
    if ($detected === '') {
        if ($fallback !== '') {
            return strtolower(trim($fallback));
        }

        return $store->getTextTranslationSourceLanguage($entityType, $entityId, $sourceHash);
    }

    foreach ($rows as $row) {
        if (strtolower(trim((string) ($row['source_language'] ?? ''))) !== $detected) {
            $store->setTextTranslationSourceLanguage($entityType, $entityId, $sourceHash, $detected);
            break;
        }
    }

    return $detected;
}

/**
 * @param list<array<string, mixed>> $rows
 */
function ticketTranslationSourceFromStoredRows(array $rows, string $rawText): string
{
    $matches = [];
    foreach ($rows as $row) {
        $language = strtolower(trim((string) ($row['target_language'] ?? '')));
        if ($language === '' || !array_key_exists($language, SUPPORTED_LANGUAGES)) {
            continue;
        }
        if (ticketTranslationTextsMatch((string) ($row['translated_text'] ?? ''), $rawText)) {
            $matches[$language] = true;
        }
    }

    $languages = array_keys($matches);

    return count($languages) === 1 ? (string) $languages[0] : '';
}

function normalizedStoredTranslation(string $rawText, string $language, string $translatedText, string $sourceLanguage): string
{
    if ($sourceLanguage !== '' && $sourceLanguage === $language) {
        return $rawText;
    }
    if ($translatedText === '') {
        return $rawText;
    }

    return $translatedText;
}

/**
 * Copilot levert alle talen in één antwoord. Bewaar ze allemaal, niet alleen de leestaal.
 *
 * @return array{text: string, is_translated: bool, source_language: string, target_language: string, translation_pending: bool, translation_error: string, translation_error_detail: string}|null
 */
function storeCopilotTicketTranslations(
    TicketStore $store,
    CopilotTranslationProvider $provider,
    string $entityType,
    int $entityId,
    int $ticketId,
    string $rawText,
    string $sourceHash,
    string $claimToken,
    string $targetLanguage
): ?array {
    $providerText = ticketTranslationProviderText($rawText);
    $bundle = $provider->translateBundle($providerText, $targetLanguage);
    $sourceLanguage = (string) ($bundle['source_language'] ?? '');
    $translations = is_array($bundle['translations'] ?? null) ? $bundle['translations'] : [];
    $toStore = [];
    foreach ($translations as $language => $translatedText) {
        $language = strtolower(trim((string) $language));
        if ($language === '' || !array_key_exists($language, SUPPORTED_LANGUAGES) || !is_string($translatedText)) {
            continue;
        }
        $storedBody = normalizedStoredTranslation($providerText, $language, $translatedText, $sourceLanguage);
        $toStore[$language] = ticketTranslationApplyStatusNotes(
            ['text' => $storedBody],
            $rawText,
            $language
        )['text'];
    }
    // Een leeg of uitgefilterd antwoord mag niet als enige een nl-rij worden.
    // De prefetch vraagt altijd nl; die ene rij liet en/fr/de voor altijd pending.
    if ($toStore === []) {
        return null;
    }
    if (!array_key_exists($targetLanguage, $toStore)) {
        $storedBody = normalizedStoredTranslation($providerText, $targetLanguage, '', $sourceLanguage);
        $toStore[$targetLanguage] = ticketTranslationApplyStatusNotes(
            ['text' => $storedBody],
            $rawText,
            $targetLanguage
        )['text'];
    }

    if (!$store->saveTextTranslationsIfClaimed($entityType, $entityId, $ticketId, $sourceHash, $claimToken, $sourceLanguage, $toStore)) {
        return null;
    }

    releaseQueuedUserUpdateMailsAfterTranslation($store, $entityType, $entityId);

    return ticketTranslationApplyStatusNotes(
        ticketTranslationStoredResult($rawText, $targetLanguage, $toStore[$targetLanguage], $sourceLanguage),
        $rawText,
        $targetLanguage
    );
}

/**
 * Lara (en elke andere provider) levert één taal per aanroep.
 *
 * @return array{text: string, is_translated: bool, source_language: string, target_language: string, translation_pending: bool, translation_error: string, translation_error_detail: string}|null
 */
function storeSingleTicketTranslation(
    TicketStore $store,
    TranslationProvider $provider,
    string $entityType,
    int $entityId,
    int $ticketId,
    string $rawText,
    string $sourceHash,
    string $claimToken,
    string $targetLanguage
): ?array {
    $providerText = ticketTranslationProviderText($rawText);
    $result = $provider->translateText($providerText, $targetLanguage);
    $sourceLanguage = (string) ($result['source_language'] ?? '');
    $storedText = normalizedStoredTranslation(
        $providerText,
        $targetLanguage,
        (string) ($result['translated_text'] ?? ''),
        $sourceLanguage
    );
    $storedText = ticketTranslationApplyStatusNotes(['text' => $storedText], $rawText, $targetLanguage)['text'];
    if (!$store->saveTextTranslationsIfClaimed(
        $entityType,
        $entityId,
        $ticketId,
        $sourceHash,
        $claimToken,
        $sourceLanguage,
        [$targetLanguage => $storedText]
    )) {
        return null;
    }

    releaseQueuedUserUpdateMailsAfterTranslation($store, $entityType, $entityId);

    return ticketTranslationApplyStatusNotes(
        ticketTranslationStoredResult($rawText, $targetLanguage, $storedText, $sourceLanguage),
        $rawText,
        $targetLanguage
    );
}

/**
 * Zet een vertaling in de wachtrij. Het HTTP-antwoord gaat eerst weg; daarna pas de flow.
 * Bestaande berichten komen hier niet in: die blijven via translate_ticket (vangnet).
 */
function releaseQueuedUserUpdateMailsAfterTranslation(TicketStore $store, string $entityType, int $entityId): void
{
    if ($entityType !== 'ticket_message' || $entityId <= 0 || !function_exists('dispatchQueuedUserUpdateMails')) {
        return;
    }

    try {
        dispatchQueuedUserUpdateMails($store, $entityId);
    } catch (Throwable $exception) {
        error_log('[asclepius] updatemail na vertaling ' . $entityId . ': ' . $exception->getMessage());
    }
}

function queueTicketTextTranslation(TicketStore $store, string $entityType, int $entityId, int $ticketId, string $rawText): void
{
    if (trim($rawText) === '' || $entityId <= 0 || $ticketId <= 0 || trim($entityType) === '') {
        return;
    }
    if (function_exists('ticketMessageIsStatusNoteOnly') && ticketMessageIsStatusNoteOnly($rawText)) {
        return;
    }
    if (createTranslationProvider() === null) {
        return;
    }

    prepareTicketTranslationBackgroundWork();

    if (!isset($GLOBALS['asclepiusTranslationQueue']) || !is_array($GLOBALS['asclepiusTranslationQueue'])) {
        $GLOBALS['asclepiusTranslationQueue'] = [];
    }
    if (empty($GLOBALS['asclepiusTranslationShutdown'])) {
        $GLOBALS['asclepiusTranslationShutdown'] = true;
        register_shutdown_function('runQueuedTicketTranslations');
    }

    $GLOBALS['asclepiusTranslationQueueStore'] = $store;
    $GLOBALS['asclepiusTranslationQueue'][$entityType . ':' . $entityId] = [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'ticket_id' => $ticketId,
        'raw_text' => $rawText,
    ];
}

/** @return list<string> */
function queuedTicketTranslationKeys(): array
{
    $queue = $GLOBALS['asclepiusTranslationQueue'] ?? [];

    return is_array($queue) ? array_keys($queue) : [];
}

function discardQueuedTicketTranslations(): void
{
    $GLOBALS['asclepiusTranslationQueue'] = [];
}

/** Tests en CLI: de wachtrij meteen afwerken. Een webverzoek doet dat pas na het antwoord. */
function flushQueuedTicketTranslations(): void
{
    $previous = !empty($GLOBALS['asclepiusTranslationQueueForce']);
    $GLOBALS['asclepiusTranslationQueueForce'] = true;
    try {
        runQueuedTicketTranslations();
    } finally {
        $GLOBALS['asclepiusTranslationQueueForce'] = $previous;
    }
}

function translationRuntimeIsCli(): bool
{
    if (isset($GLOBALS['asclepiusTranslationSapi'])) {
        $sapi = (string) $GLOBALS['asclepiusTranslationSapi'];

        return $sapi === 'cli' || $sapi === 'phpdbg';
    }

    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function translationHeadersAlreadySent(): bool
{
    if (array_key_exists('asclepiusTranslationHeadersSent', $GLOBALS)) {
        return !empty($GLOBALS['asclepiusTranslationHeadersSent']);
    }

    return headers_sent();
}

/**
 * Mod_php breekt het verzoek af zodra de browser de redirect volgt, tenzij dit
 * al tijdens het plaatsen is gezet. In de shutdown zelf is dat te laat.
 */
function prepareTicketTranslationBackgroundWork(): void
{
    ignore_user_abort(true);
    $GLOBALS['asclepiusTranslationIgnoreAbort'] = true;
}

function runQueuedTicketTranslations(): void
{
    $force = !empty($GLOBALS['asclepiusTranslationQueueForce']);
    $isCli = translationRuntimeIsCli();
    if ($isCli && !$force) {
        return;
    }

    prepareTicketTranslationBackgroundWork();

    $queued = $GLOBALS['asclepiusTranslationQueue'] ?? [];
    if (!is_array($queued) || $queued === []) {
        $idleStore = $GLOBALS['asclepiusTranslationQueueStore'] ?? null;
        if ($idleStore instanceof TicketStore && function_exists('dispatchQueuedUserUpdateMails')) {
            try {
                dispatchQueuedUserUpdateMails($idleStore);
            } catch (Throwable $exception) {
                error_log('[asclepius] updatemail: ' . $exception->getMessage());
            }
        }

        return;
    }

    $store = $GLOBALS['asclepiusTranslationQueueStore'] ?? null;
    if (!$store instanceof TicketStore) {
        $GLOBALS['asclepiusTranslationQueue'] = [];

        return;
    }

    if (!$isCli) {
        if (method_exists($store, 'commitOpenTransaction')) {
            try {
                $store->commitOpenTransaction();
            } catch (Throwable $exception) {
                error_log('[asclepius] vertaling transactie: ' . $exception->getMessage());
            }
        }
        finishHttpResponseForBackgroundWork();
    }

    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $previousStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
    $previousLimit = ini_get('max_execution_time');
    try {
        while (($items = $GLOBALS['asclepiusTranslationQueue'] ?? []) !== []) {
            $GLOBALS['asclepiusTranslationQueue'] = [];
            if (!is_array($items)) {
                break;
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                // Na het antwoord telt de al verstreken requesttijd niet meer mee voor de flow-timeout.
                $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
                @set_time_limit(ticketTranslationTimeLimitSeconds());
                try {
                    prefetchTicketTextTranslations(
                        $store,
                        (string) ($item['entity_type'] ?? ''),
                        (int) ($item['entity_id'] ?? 0),
                        (int) ($item['ticket_id'] ?? 0),
                        (string) ($item['raw_text'] ?? '')
                    );
                } catch (Throwable $exception) {
                    error_log(
                        '[asclepius] vertaling '
                        . (string) ($item['entity_type'] ?? '')
                        . ' '
                        . (string) ($item['entity_id'] ?? '')
                        . ': '
                        . $exception->getMessage()
                    );
                }
            }
        }
    } finally {
        if ($previousStart === null) {
            unset($_SERVER['REQUEST_TIME_FLOAT']);
        } else {
            $_SERVER['REQUEST_TIME_FLOAT'] = $previousStart;
        }
        if ($previousLimit !== false) {
            @ini_set('max_execution_time', (string) $previousLimit);
        }
        if (function_exists('dispatchQueuedUserUpdateMails')) {
            try {
                dispatchQueuedUserUpdateMails($store);
            } catch (Throwable $exception) {
                error_log('[asclepius] updatemail: ' . $exception->getMessage());
            }
        }
    }
}

/**
 * Stuur het antwoord nu naar de browser, zoals Forum-Magnum na een post.
 * PHP-FPM: fastcgi_finish_request. Zonder FPM: Content-Length en de buffers legen.
 */
function finishHttpResponseForBackgroundWork(): bool
{
    if (translationRuntimeIsCli()) {
        return false;
    }

    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        return (bool) @fastcgi_finish_request();
    }

    // Apache mod_php heeft geen fastcgi_finish_request. Headers zijn dan meestal
    // al weg (redirect of de buffer uit bootstrap). Toch de buffers legen en
    // doorgaan; anders stopt het proces zodra de browser de redirect volgt.
    @ini_set('zlib.output_compression', '0');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    $output = '';
    while (ob_get_level() > 0) {
        $chunk = ob_get_clean();
        $output = (is_string($chunk) ? $chunk : '') . $output;
    }
    if (!translationHeadersAlreadySent()) {
        header('Connection: close');
        header('Content-Encoding: none');
        header('Content-Length: ' . strlen($output));
    }
    if (empty($GLOBALS['asclepiusTranslationSuppressFinishOutput'])) {
        echo $output;
        @flush();
    }

    return true;
}

/**
 * Alle talen voor één nieuw of gewijzigd bericht. Een lopende claim voor dezelfde tekst
 * (deze functie, of translate_ticket) blokkeert een tweede flow-aanroep.
 */
function prefetchTicketTextTranslations(
    TicketStore $store,
    string $entityType,
    int $entityId,
    int $ticketId,
    string $rawText
): void {
    if (trim($rawText) === '' || $entityId <= 0 || $ticketId <= 0) {
        return;
    }
    if (!$store->translationSourceMatches($entityType, $entityId, $rawText)) {
        return;
    }

    $sourceHash = hash('sha256', $rawText);
    if (ticketTranslationsCoverAllLanguages($store, $entityType, $entityId, $sourceHash)) {
        return;
    }

    $provider = createTranslationProvider();
    if ($provider === null) {
        return;
    }

    $claimToken = $store->tryClaimTextTranslation($entityType, $entityId, $sourceHash, ticketTranslationClaimTtlSeconds());
    if ($claimToken === null) {
        return;
    }

    try {
        if (ticketTranslationsCoverAllLanguages($store, $entityType, $entityId, $sourceHash)) {
            return;
        }

        if ($provider instanceof CopilotTranslationProvider) {
            // De taal hier is alleen welke tekst het antwoord teruggeeft. De prefetch
            // gebruikt dat antwoord niet. De brontaal komt uit de flow-teksten zelf.
            storeCopilotTicketTranslations(
                $store,
                $provider,
                $entityType,
                $entityId,
                $ticketId,
                $rawText,
                $sourceHash,
                $claimToken,
                'nl'
            );

            return;
        }

        foreach (ticketTranslationLanguages() as $language) {
            if ($store->getTextTranslation($entityType, $entityId, $language, $sourceHash) !== null) {
                continue;
            }
            $stored = storeSingleTicketTranslation(
                $store,
                $provider,
                $entityType,
                $entityId,
                $ticketId,
                $rawText,
                $sourceHash,
                $claimToken,
                $language
            );
            if ($stored === null) {
                return;
            }
        }
    } catch (Throwable $exception) {
        if ($exception instanceof RuntimeException && $exception->getMessage() === CopilotTranslationProvider::BUDGET_EXHAUSTED) {
            return;
        }
        error_log('[asclepius] vertaling ' . $entityType . ' ' . $entityId . ': ' . $exception->getMessage());
    } finally {
        $store->releaseTextTranslationClaim($entityType, $entityId, $claimToken);
    }
}
